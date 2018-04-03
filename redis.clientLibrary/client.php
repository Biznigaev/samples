<?php
namespace Company\Main\Redis;
// чтобы не путаться в наименовании текущего класса, для обращения к API нативной библиотеки успользуется след. alias:
use \Redis as NativeLibRedis;

use Bitrix\Main\Config,
	Bitrix\Main\Diag\Debug,
	Bitrix\Main\SystemException,
	Bitrix\Main\Localization\Loc;

use Company\Main\Models\LmProductsTable,
	Company\Main\Helpers\RedisHelper;

Loc::loadMessages(__FILE__);
/**
 * @todo: Создать метод touch для продления записи (поле modified)
 */
// надстройка для работы с Redis-ом
// адаптирован под работу с b_lm_products и конфигом битрикса .settings_extra.php
class Client
{
	const KEY_DELIMITER = ':';
	const CONFIG_KEY = 'redis';
	private static $connection,
				   $instance = null;
	protected static $mapping = [
		0 => 'title', 
		1 => 'quantity', 
		2 => 'weight', 
		3 => 'multiplication_factor', 
		4 => 'code_company',
		5 => 'modified',
		6 => 'commerce_price', 
		// розничная цена
		7 => 'retail_price',
		// корп. цена
		8 => 'minimal_price',
		9 => 'supplier_price',
		10=> 'ttl',
		11=> 'goods_company'
	];

	private function __construct()
	{
		if (is_null(Config\Configuration::getValue(self::CONFIG_KEY)))
		{
			throw SystemException(Loc::getMessage('ERR_CLIENT_CONFIG_UNDEFINED'));
		}
		$config = Config\Configuration::getValue(self::CONFIG_KEY);
		self::$connection = new NativeLibRedis();
		if (!empty($config['socket']))
		{
			self::$connection->pconnect($config['socket']);
		}
		else
		{
			self::$connection->pconnect($config['host'][0], $config['host'][1]);
		}
		if (!self::$connection)
		{
			throw new SystemException(
				Loc::getMessage('ERR_CLIENT_CONNECTION_ERR', [
						'#CONFIG#' => $config['host'][0].':'.$config['host'][1]
					]
				)
			);
		}
		if (!self::$connection->auth($config['auth']))
		{
			throw new SystemException(
				Loc::getMessage('ERR_CLIENT_AUTH_ERR', [
						'#CONFIG#' => $config['host'][0].':'.$config['host'][1]
					]
				)
			);
		}
		// не подключать серилизатор для проверки строковых значений
		self::$connection->setOption(NativeLibRedis::OPT_SERIALIZER, NativeLibRedis::SERIALIZER_NONE);
		// не возвращать пустые значения при поиске
		self::$connection->setOption(NativeLibRedis::OPT_SCAN, NativeLibRedis::SCAN_RETRY);
	}

	// ограничивает клонирование объекта
	protected function __clone() {}

	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function bindKeys(&$fields)
	{
		$tmp = [];
		foreach ($fields as $i => &$val)
		{
			$tmp[self::$mapping[$i]] = $val;
			unset($fields[$i]);
		}
		return $tmp;
	}
	
	/**
	 * Метод проверяет просроченность записи
	 * и если просрочена, то удаляет её
	 * @param expireUTC int время протухания сессии
	 * @param key string ключ Бренд+Артикул
	 * @param hashKey int внешний ключ поставщика
	 * @return bool признак устарела ли запись
	 */
	protected static function isExpired($expireUTC, $key, $hashKey)
	{
		$expired = $expireUTC < time();
		if ($expired)
		{
			self::$connection->hdel($key, $hashKey);
		}
		return $expired;
	}

	// распаковывает строку из redis
	protected static function unpackValue(&$value)
	{
		return self::bindKeys(
            json_decode($value, true)
		);
	}
	/**
	 * @todo: Поиск только по бренду или только по артикулу очень долго выполняется через scan
	 * 		  Или же делать через keys *, то тогда будет блокирующая выборка
	 */
	protected static function get($params)
	{
		// проверка синтаксиса запроса
		foreach (array_keys($params) as $param)
		{
			if (!in_array($param, ['select', 'filter', 'order']))
			{
				throw new SystemException('Передан неизвестный параметр '.$params[$param]);
			}
		}
		// сборка фильтра по значению
		$valueFilter = false;
		foreach (array_keys($params['filter']) as $fieldName)
		{
			if (in_array($fieldName, self::$mapping))
			{
				$valueFilter[$fieldName] = $params['filter'][$fieldName];
			}
		}
		$article = $brand = $supplierId = false;
		$key = [];
		if (array_key_exists('article', $params['filter']))
		{
			$article = RedisHelper::cleanArticle($params['filter']['article']);
			$key[] = $article;
		}
		if (array_key_exists('brand', $params['filter']))
		{
			$brand = strToUpper($params['filter']['brand']);
		}
		if (array_key_exists('supplier_id', $params['filter']))
		{
			$supplierId = $params['filter']['supplier_id'];
		}
		$key = $article.self::KEY_DELIMITER.$brand;	
		$selectFields = [];
		if (array_key_exists('select', $params))
		{
			foreach ($params['select'] as $i => &$fieldName)
			{
				if (is_string($i))
				{
					if (in_array($i, self::$mapping)
						|| in_array($i, ['supplier_id', 'article', 'brand']))
					{
						throw new SystemException("Выбранный alias {$i} является зарезервированным и присутствует в полях сущности");
					}
				}
				// проверка в области ключей и в области значений
				if (!in_array($fieldName, self::$mapping)
					&& !in_array($fieldName, ['supplier_id', 'article', 'brand']))
				{
					throw new SystemException("Поле выборки {$fieldName} не существует");
				}
			}
			$selectFields = &$params['select'];
		}
		else
		{
			$selectFields = &self::$mapping;
		}
		$result = [];
		// поиск по brand + article
		if (self::$connection->exists($key))
		{
			// если указан supplier_id, то поиск по hash-ключу
			if ($supplierId)
			{
				$row = [];
				$value = self::unpackValue(
					self::$connection->hget($key, $supplierId)
				);
				// проверка ttl
				if (self::isExpired($value['ttl'], $key, $supplierId))
				{
					return [];
				}
				foreach ($selectFields as $i => &$fieldName)
				{
					switch ($fieldName)
					{
						case 'supplier_id':
						case 'article':
						case 'brand':
						{
							$row[$fieldName] = $params['filter'][$fieldName];
							break;
						}
						default: 
						{
							if (is_string($i))
							{
								$row[$i] = $value[$fieldName];
							}
							else
							{
								$row[$fieldName] = $value[$fieldName];
							}
						}
					}
				}
				$result[] = $row;
				unset($row);
			}
			// поиск по всем hash-ключам/поставщикам
			else
			{
				foreach (self::$connection->hgetall($key) as $hashKey => $value)
				{
					$value = self::unpackValue($value);
					// проверка ttl
					if (self::isExpired($value['ttl'], $key, $hashKey))
					{
						continue;
					}
					// фильтр по значениям
					if ($valueFilter)
					{
						foreach ($valueFilter as $fieldName => &$fieldValue)
						{
							if ($value[$fieldName] <> $fieldValue)
							{
								continue 2;
							}
						}
					}
					$row = [];
					foreach ($selectFields as $i => &$fieldName)
					{
						switch ($fieldName)
						{
							case 'supplier_id':
							{
								$row[$fieldName] = $hashKey;
								break;
							}
							case 'article':
							case 'brand':
							{
								$row[$fieldName] = $params['filter'][$fieldName];
								break;
							}
							default: 
							{
								if (is_string($i))
								{
									$row[$i] = $value[$fieldName];
								}
								else
								{
									$row[$fieldName] = $value[$fieldName];
								}
							}
						}
					}
					$result[] = $row;
					unset($row);
				}
			}
		}
		return $result;
	}
	/**
	 * добавление / изменение товара
	 * @todo: добавить валидацию полей перед записью checkFieldsBeforeSave
	 */
	protected static function set(&$fields)
	{
		if (!$fields['original_article'])
		{
			throw new SystemException('[ERROR] Не задано обязательное поле: original_article');
		}
		if (!$fields['brand_title'])
		{
			throw new SystemException('[ERROR] Не задано обязательное поле: brand_title');
		}
		$key = implode(self::KEY_DELIMITER, [
			RedisHelper::cleanArticle($fields['original_article']),
			strToUpper($fields['brand_title'])
		]);
		if (empty($key)
			|| $key == self::KEY_DELIMITER)
		{
			throw new Exception('[ERROR] Ошибка создания ключа записи');
		}
		if (!$fields['supplier_id'])
		{
			throw new SystemException('[ERROR] Не задано обязательное поле: supplier_id');
		}
		$hashKey = intval($fields['supplier_id']);
		if (empty($hashKey))
		{
			throw new SystemException('[ERROR] Ошибка создания хеш-ключа записи');
		}
		unset(
			$fields['original_article'],
			$fields['brand_title'],
			$fields['supplier_id']
		);
		if (!$fields[\Company\Main\Helpers\PricesHelper::INNER_PRICE])
		{
			throw new SystemException('[ERROR] Базовая цена не может иметь нулевое значение');
		}
		if (!is_null($fields['code_company']))
		{
			if (!$fields['commerce_price'])
			{
				unset($fields['commerce_price']);
			}
			if (!$fields['retail_price'])
			{
				unset($fields['retail_price']);
			}
		}
		// ttl считается через отдельный справочник
		$fields['modified'] = time();
		$fields['multiplication_factor'] = doubleval($fields['multiplication_factor']);

		// если значение уже есть
		if ($row = self::$connection->hget($key, $hashKey))
		{
			if (!self::isExpired($row['ttl'], $key, $hashKey))
			{
				if (!isset($fields['commerce_price'])
					&& !empty($row['commerce_price']))
				{
					$fields['commerce_price'] = $row['commerce_price'];
				}
				if (!isset($fields['retail_price'])
					&& !empty($row['retail_price']))
				{
					$fields['retail_price'] = $row['retail_price'];
				}
				$row = self::unpackValue($row);
			}
		}
		// запись
		return !is_null(
			self::$connection->hset($key, $hashKey, self::packValue(array_values($fields)))
		);
	}

	protected function getConnection()
	{
		return self::$connection;
	}

	protected static function packValue($value)
	{
		return gzcompress(
			json_encode(
				$value, 
				JSON_NUMERIC_CHECK
				|JSON_UNESCAPED_UNICODE
				|JSON_UNESCAPED_SLASHES
			), 9
		);
	}
}