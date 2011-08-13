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
 * @todo Протестировать + подумать над Exception
 * @todo Добавить какую-то функцию/переменную для CbrfOutOfDateException
 * @todo Написать readme!
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
	 * Класс кэша по умоланию, если не используется стандартный компонент в системе
	 * @var string
	 */
	public $cacheClass = 'CFileCache';
	/**
	 * Дата в формате date() при наступлении которой кэш устаревает
	 * @var string
	 */
	public $cacheDateString = 'Ymd';
	/**
	 * Генерировать CbrfOutOfDateException или по возможности брать предыдущие значения
	 * @var unknown_type
	 */
	public $generateCbrfOutOfDateException = false;
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
		
		if (!($this->_currencyArray = $this->_cache->get('cbrf_currency')))
		{
			if(($result = $this->loadDataFromSource()) === true) 
			{
				$this->getCache()->set('cbrf_currency', $this->_currencyArray, 0, new CbrfDateDependency($this->cacheDateString));
				// Если курс валют не поменялся с предыдущего обновления
				if ($this->getCache()->get('cbrf_currency_out_of_date') && $this->getCache()->get('cbrf_currency_out_of_date') === $this->getCache()->get('cbrf_currency'))
				{
					$this->getCache()->delete('cbrf_currency');
				}
				$this->getCache()->set('cbrf_currency_out_of_date', $this->_currencyArray);
			}
			else if ($this->generateCbrfOutOfDateException)
			{
				throw new CbrfOutOfDateException($result);
			}
			else // Извлекаем устаревшие данные 
			{
				$this->_currencyArray = $this->getCache()->get('cbrf_currency_out_of_date');
			}
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
	/**
	 * Загрузить данные от источника
	 * @return mixed true если все успешно, иначе string с сообщением об ошибке
	 */
	protected function loadDataFromSource()
	{
		$xml = @simplexml_load_file($this->sourceUrl);
		if (!$xml) return 'Data from sourceUrl is broken';
		
		foreach ($xml->{'Valute'} as $valute) 
		{
			$value = str_replace(',', '.', $valute->{'Value'}) / $valute->{'Nominal'};
			$this->_currencyArray[current($valute->{'CharCode'})] = $value;	
		}
		
		if (empty($this->_currencyArray)) return 'Data from sourceUrl is broken';
		
		return true;
	}
}

/**
 * Зависиомть кэша от даты в формате date()
 */
class CbrfDateDependency extends CCacheDependency
{
	/**
	 * Дата для организации зависиости
	 * @var string
	 */
	public $dateString;
	/**
	 * Конструктор
	 * @param string $dateString строка в формате date()
	 */
	public function __construct($dateString = 'Ymd')
	{
		$this->dateString = $dateString;
	}
	/**
	 * Генерируем зависмость
	 */
	protected function generateDependentData()
	{
		return date($this->dateString);
	}
}

class CbrfException extends CException {}
class CbrfOutOfDateException extends CbrfException {}