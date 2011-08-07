<?php 
/**
 * Класс для определения официального курса валют относительно рубля
 * 
 * Курсы беруться с официального сайта ЦБ РФ.
 * Поддерживается кэш. Есть возможность использовать как системный кэш, так
 * и внутренний на основе CFileCache или другого класса
 * 
 * @todo Повысить точность операций с плавающей точкой?
 * @todo Где лучше хранить кэш, внутри расширения, или в runtime?
 */
class Cbrf
{
	/**
	 * URL источника
	 * @var string
	 */
	public $sourceUrl = 'http://www.cbr.ru/scripts/XML_daily.asp';
	/**
	 * Разрешить использование глобального кэша?
	 * @var boolean
	 */
	public $globalCache = true;
	/**
	 * Класс кэша по умоланию, если не используется стандартный общесистемный
	 * @var string
	 */
	public $cacheClass = 'CFileCache';
	/**
	 * Время, в секундах, через которое кэш точно устареет
	 * @var int
	 */
	public $cacheTime = 86400;
	/**
	 * Строка в формате date() для определения частоты обновления кэша
	 * @var string
	 */
	public $cacheDate = 'Ymd';
	/**
	 * Системный массив с валютами в формате [currencyCode] => value
	 * @var array
	 */
	private $_currencyArray = array();
	/**
	 * Класс отвечающий за кэш, наследник CCahche
	 * @var ICache $cache
	 */
	private $_cache;
	/**
	 * Получить стоимость валюты относительно 1 рубля
	 * @param string $currency
	 * @return float
	 */
	public function getCurrencyRate($currency)
	{
		if (isset($this->_currencyArray[$currency])) {
			return $this->_currencyArray[$currency];	
		} else {
			throw new Exception('Uknown currency ' . $currency);
		}
	}
	/**
	 * Получить значение в нужной валюте относительно рубля
	 * @param float $value Число в валюте
	 * @param string $currency Буквенный код валюты
	 * @return float
	 */
	public function getCurrencyValue($value, $currency)
	{
		return $value * $this->getCurrencyRate($currency);
	}
	/**
	 * Инициализируем класс
	 */
	public function init()
	{
		// Если есть общесистемный кэш используем его
		if ($this->globalCache && Yii::app()->cache !== null && Yii::app()->cache instanceof ICache)
		{
			$this->setCache(Yii::app()->cache);
		}
		else // Иначе создаем внутренний кэш 
		{
			$this->setCache(new $this->cacheClass);
			$this->getCache()->init();
		}
		
		$last_date = $this->_cache->get('cbrf_date');
		if ($last_date == $this->cacheDate())
		{
			$this->loadDataFromCache();
		}
		else
		{
			$this->loadData();
			$this->prepareCache();
		}
	}
	/**
	 * Установить свой класс кэша
	 * @param $cache
	 */
	public function setCache($cache) 
	{
		if (!$cache instanceof ICache) 
		{
			throw new Exception('Cache must be instance of ICache');
		}
		$this->_cache = $cache;
	}
	/**
	 * Получить кэш
	 */
	public function getCache()
	{
		return $this->_cache;
	}
	protected function loadData()
	{
		// Получаем значение от источника
		$str = file_get_contents($this->sourceUrl);
		
		// Выбираем необходимые значения
		preg_match_all('|<CharCode>(.*)</CharCode>[\W]*<Nominal>(.*)</Nominal>[\W]*<Name>.*</Name>[\W]*<Value>(.*)</Value>|iU', $str, $arr);
		
		// Проверяем загрузились ли корректно значения
		if (is_array($arr[3]) && count($arr[3]) > 0) {
			for ($i = 0; $i < count($arr[0]); $i++) {
				$value = str_replace(',', '.', $arr[3][$i]) / $arr[2][$i];
				$name = $arr[1][$i];
				
				if (empty($value) || empty($name)) {
					throw new Exception('Data from sourceUrl is broken');
				}
				
				$this->_currencyArray[$name] = $value;
			}
		} else {
			throw new Exception('Data from sourceUrl is broken');
		}
	}
	protected function loadDataFromCache()
	{
		$this->_currencyArray = unserialize($this->_cache->get('cbrf_currency'));
	}
	/**
	 * Записать в кэш текущие значения
	 */
	protected function prepareCache()
	{
		$this->_cache->set('cbrf_date', $this->cacheDate(), $this->cacheTime);
		$this->_cache->set('cbrf_currency', serialize($this->_currencyArray), $this->cacheTime);
	}
	/**
	 * Формат данных для проверки устаревания кэша
	 * @return string
	 */
	protected function cacheDate()
	{
		return date('Ymd');
	}
}