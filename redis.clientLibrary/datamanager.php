<?php
namespace Company\Main\Redis;

use Company\Main\Helpers\RedisHelper,
	Company\Main\Helpers\PricesHelper;

class DataManager extends Client
{
	public static function getProduct($filter, $useCalulcation = true)
	{
		$select = [
			'weight',
			'title', 
			'multiplication_factor',
			'code_company',
			'quantity', 
			'supplier_id',
			'goods_company',
			// получение типа цены
			'price' => RedisHelper::getPriceColumn(),
		];
		// соединение с redis-ом
		$redis = parent::getInstance();
		$result = $redis::get([
			'select' => $select,
			'filter' => $filter,
		]);
		if (count($result))
		{
			if ($useCalulcation === true)
			{
				// получение оптимальной цены
				$result = RedisHelper::calculatePrice($result);
				unset($result['goods_company']);
			}
			else
			{
				$result = RedisHelper::getResultList($result);
			}
		}

		return $result;
	}

	// добавление продукта в Redis
	public static function bindProduct($fields)
	{
		$redis = parent::getInstance();
		$result = $redis->set($fields);
		return $result;
	}
	
 	// получение товара от всех поставщиков (без расчета оптимальной стоимости)
	public static function getProductFromAllSuppliers($filter)
	{
		if (isset($filter['article']))
		{
			$filter['article'] = RedisHelper::cleanArticle($filter['article']);
			if (empty($filter['article']))
			{
				return false;
			}
		}
		if (isset($filter['brand']))
		{
			$filter['brand'] = RedisHelper::cleanBrand($filter['brand']);
			if (empty($filter['brand']))
			{
				return false;
			}
		}
		$select = [
			'title',
			'article',
			'brand',
			'quantity', 
			'supplier_id',
			'code_company',
			'goods_company',
			'weight',
			// получение типа цены
			'price' => RedisHelper::getPriceColumn(),
		];
		// соединение с redis-ом
		$redis = parent::getInstance();
		$result = $redis::get([
			'select' => $select,
			'filter' => $filter,
		]);
		if (count($result))
		{
			return RedisHelper::getResultList($result);
		}

		return false;
	}

	// получение закупочной цены товара
	public static function getProductBasePrice($article, $brand, $supplierId)
	{
		$redis = parent::getInstance();
		$result = $redis::get([
			'select' => [PricesHelper::INNER_PRICE],
			'filter' => [
				'article' => RedisHelper::cleanArticle($article),
				'brand' => $brand,
				'supplier_id' => $supplierId
			]
		]);
		if (count($result))
		{
			return reset($result)[PricesHelper::INNER_PRICE];
		}
		return false;
	}

 	// поиск ключа в БД по артикулу (по части ключа)
	public static function getProductsbyArticle($article, $limit=1000)
	{
		$redis = parent::getInstance();
		$article = RedisHelper::cleanArticle($article);
		if (empty($article))
		{
			return [];
		}
		$connection = parent::getConnection();
		$it = null;
		$result = [];
		$select = [
			'title',
			'article',
			'brand',
			'quantity', 
			'supplier_id',
			'code_company',
			'goods_company',
			'modified',
			'weight',
			// получение типа цены
			'price' => RedisHelper::getPriceColumn(),
		];
		do
		{
			// поиск ключей по артикулу
			$arKeys = $connection->scan($it, $article.parent::KEY_DELIMITER.'*', $limit);
		    if ($arKeys !== FALSE)
		    {
				foreach ($arKeys as $arKey)
				{
					$filter = [
						'article' => '', 
						'brand' => ''
					];
					list(
						$filter['article'], 
						$filter['brand']
					) = explode(
						parent::KEY_DELIMITER, 
						$arKey
					);
					$result = array_merge(
						$result, 
						// получение товаров
						$redis::get([
							'select' => $select,
							'filter' => $filter
						])
					);
				}
		    }
		}
		while ($it > 0);
		
		if (count($result))
		{
			$result = RedisHelper::getResultList($result);
		}
		
		return $result;
	}

	/**
	 * Получить агрегированное кол-во по товару, при этом 
	 * калькуляция внутреннего и внешнего кол-ва не смешивается
	 * @param article string артикул товара
	 * @param brand string бренд товара
	 * @return int
	 */
	public static function getAggregatedQuanity($article, $brand)
	{
		$quantity = 0;
		if ($result = self::getProductFromAllSuppliers([
			'article' => $article,
			'brand' => $brand,
		]))
		{
			$quantity = array_sum(
				array_column($result, 'quantity')
			);
		}
		return $quantity;
	}
}