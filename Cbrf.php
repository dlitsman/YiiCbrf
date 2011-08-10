<?php 
/**
 *  Copyright (c) 2011, Лицман Дмитрий
 *
 * Разрешается повторное распространение и использование как в виде исходного кода, так и в
 * двоичной форме, с изменениями или без, при соблюдении следующих условий:
 *
 *     * При повторном распространении исходного кода должно оставаться указанное выше
 *       уведомление об авторском праве, этот список условий и последующий отказ от гарантий.
 *     * При повторном распространении двоичного кода должна сохраняться указанная выше
 *       информация об авторском праве, этот список условий и последующий отказ от гарантий в
 *       документации и/или в других материалах, поставляемых при распространении. 
 *     * Ни название <Организации>, ни имена ее сотрудников не могут быть использованы в
 *       качестве поддержки или продвижения продуктов, основанных на этом ПО без
 *       предварительного письменного разрешения. 
 *
 * ЭТА ПРОГРАММА ПРЕДОСТАВЛЕНА ВЛАДЕЛЬЦАМИ АВТОРСКИХ ПРАВ И/ИЛИ ДРУГИМИ СТОРОНАМИ
 * "КАК ОНА ЕСТЬ" БЕЗ КАКОГО-ЛИБО ВИДА ГАРАНТИЙ, ВЫРАЖЕННЫХ ЯВНО ИЛИ ПОДРАЗУМЕВАЕМЫХ,
 * ВКЛЮЧАЯ, НО НЕ ОГРАНИЧИВАЯСЬ ИМИ, ПОДРАЗУМЕВАЕМЫЕ ГАРАНТИИ КОММЕРЧЕСКОЙ ЦЕННОСТИ И
 * ПРИГОДНОСТИ ДЛЯ КОНКРЕТНОЙ ЦЕЛИ. НИ В КОЕМ СЛУЧАЕ, ЕСЛИ НЕ ТРЕБУЕТСЯ СООТВЕТСТВУЮЩИМ
 * ЗАКОНОМ, ИЛИ НЕ УСТАНОВЛЕНО В УСТНОЙ ФОРМЕ, НИ ОДИН ВЛАДЕЛЕЦ АВТОРСКИХ ПРАВ И НИ ОДНО
 * ДРУГОЕ ЛИЦО, КОТОРОЕ МОЖЕТ ИЗМЕНЯТЬ И/ИЛИ ПОВТОРНО РАСПРОСТРАНЯТЬ ПРОГРАММУ, КАК БЫЛО
 * СКАЗАНО ВЫШЕ, НЕ НЕСЁТ ОТВЕТСТВЕННОСТИ, ВКЛЮЧАЯ ЛЮБЫЕ ОБЩИЕ, СЛУЧАЙНЫЕ,
 * СПЕЦИАЛЬНЫЕ ИЛИ ПОСЛЕДОВАВШИЕ УБЫТКИ, ВСЛЕДСТВИЕ ИСПОЛЬЗОВАНИЯ ИЛИ НЕВОЗМОЖНОСТИ
 * ИСПОЛЬЗОВАНИЯ ПРОГРАММЫ (ВКЛЮЧАЯ, НО НЕ ОГРАНИЧИВАЯСЬ ПОТЕРЕЙ ДАННЫХ, ИЛИ ДАННЫМИ,
 * СТАВШИМИ НЕПРАВИЛЬНЫМИ, ИЛИ ПОТЕРЯМИ ПРИНЕСЕННЫМИ ИЗ-ЗА ВАС ИЛИ ТРЕТЬИХ ЛИЦ, ИЛИ ОТКАЗОМ
 * ПРОГРАММЫ РАБОТАТЬ СОВМЕСТНО С ДРУГИМИ ПРОГРАММАМИ), ДАЖЕ ЕСЛИ ТАКОЙ ВЛАДЕЛЕЦ ИЛИ
 * ДРУГОЕ ЛИЦО БЫЛИ ИЗВЕЩЕНЫ О ВОЗМОЖНОСТИ ТАКИХ УБЫТКОВ.
 * 
 * 
 * 
 * Определения официального курса валют относительно рубля
 * 
 * Курсы беруться с официального сайта ЦБ РФ.
 * Поддерживается кэш. Есть возможность использовать как системный кэш, так
 * и внутренний на основе CFileCache или другого класса
 * 
 * Использование
 * 
 * Для установки необходимо прописать в списке компонентов в конфигурационном файле
 *	'import'=>array(
 *		'application.models.*',
 *		'application.components.*',
 *		'application.extensions.cbrf.*', // папка с классом
 *	),
 *	
 * Далее добавить как компонент
 *	'components'=>array(
 *		'cbrf' => array(
 *			'class' => 'Cbrf',
 *			'defaultCurrency' => 'EUR',
 *			// дополнительные параметры смотреть в phpdoc формате класса
 *		),
 *		
 * Внутри приложения можно использовать в виде
 * 
 *	Yii::app()->cbrf->getValue(1000, 'USD')
 * вернет стоимость 1000 долларов в рублях
 *	Yii::app()->cbrf->getValue(1000)
 * вернет стоимость 1000 евро в рублях
 *	Yii::app()->cbrf->getRate('USD')
 * вернет курс доллара по отношению к рублю
 *	Yii::app()->cbrf->getRates()
 * вернет с массивом курсов
 * 
 * @todo Добавить небольшую отсрочку кэшу
 */
class Cbrf
{
	/**
	 * Валюта по умолчанию для короткой записи
	 * @var string
	 */
	public $defaultCurrency = 'USD';
	/**
	 * URL источника
	 * @var string
	 */
	public $sourceUrl = 'http://www.cbr.ru/scripts/XML_daily.asp';
	/**
	 * Использовать компонент в системе Yii::app()->{$cacheId}
	 * @var string
	 */
	public $cahceId = 'cache';
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
	 * Класс отвечающий за кэш, реализующий ICahche
	 * @var ICache $cache
	 */
	private $_cache;
	/**
	 * Получить стоимость валюты относительно 1 рубля
	 * @param string $currency
	 * @return float
	 */
	public function getRate($currency)
	{
		if (isset($this->_currencyArray[$currency])) {
			return $this->_currencyArray[$currency];	
		} else {
			throw new CbrfException('Uknown currency ' . $currency);
		}
	}
	/**
	 * Получить значение в нужной валюте относительно рубля
	 * @param float $value Число в валюте
	 * @param string $currency Буквенный код валюты
	 * @return float
	 */
	public function getValue($value, $currency = false)
	{
		if (!$currency) $currency = $this->defaultCurrency;
		return $value * $this->getRate($currency);
	}
	/**
	 * Получить список всех котировок
	 * @return array
	 */
	public function getRates()
	{
		return $this->_currencyArray;
	}
	/**
	 * Инициализируем класс
	 */
	public function init()
	{
		// Если есть общесистемный кэш используем его
		if (Yii::app()->{$this->cahceId})
		{
			$this->setCache(Yii::app()->{$this->cahceId});
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
			throw new CbrfException('Cache must be instance of ICache');
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
					throw new CbrfException('Data from sourceUrl is broken');
				}
				
				$this->_currencyArray[$name] = $value;
			}
		} else {
			throw new CbrfException('Data from sourceUrl is broken');
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
		return date($this->cacheDate);
	}
}

class CbrfException extends CException
{
	
}