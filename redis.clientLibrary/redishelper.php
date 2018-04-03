<?php
namespace Company\Main\Helpers;

use Company\Main\Helpers\Dicts,
	Bitrix\Main\Config\Option;

abstract class RedisHelper
{
	const DAY_IN_SECONDS = 86400;

	// очистка артикула
	public static function cleanArticle(&$article)
	{
		return strToUpper(preg_replace(['/[\W_]/u','/\s+/'], '', $article));
	}
	// очистка бренда
	public static function cleanBrand(&$brand)
	{
		$brand = trim($brand);
		return strToUpper($brand);
	}

	// получение типа цены
	public static function getPriceColumn()
	{
		foreach (array_keys(PricesHelper::getTypes()) as $code)
		{
			if (\CSite::InGroup([Option::get('company.main', $code)]))
			{
				return $code;
			}
		}
		return false;
	}
	// является ли поставщиком текущей запчасти Company
	public static function isInnerPart(&$item)
	{
		return boolval($item['goods_company']);
	}
	/**
	 * получение цены
	 */
	public static function calculatePrice(&$items)
	{
		$selected = false;
		foreach ($items as $i => &$item)
		{
			// если есть код company
			if (self::isInnerPart($item))
			{
				$selected = $i;
				break;
			}
			// иначе найти наименьшую цену
			else
			{
				if ($selected === false
					|| $item['price'] < $items[$selected]['price'])
				{
					$selected = $i;
				}
			}
		}
		// скидка
		// self::setDiscount($items[$selected]);

		return $items[$selected];
	}

	/**
	 * скидка
	 */
	public static function setDiscount(&$fields)
	{
		$obDiscount = Dicts\Factory::getDictionary('discount');
		$discount = $obDiscount::getBySupplierId($fields['supplier_id']);
		if ($discount)
		{
			$fields['price'] -= (doubleval($fields['price']) * .01) * $discount;
		}
	}

	/**
	 * наценка
	 */
	public static function setMargin(&$fields)
	{
		$obMargin = Dicts\Factory::getDictionary('margin');
		$margin = $obMargin::getBySupplierId($fields['supplier_id']);
		if ($margin)
		{
			$fields['price'] += (
				(doubleval($fields['price']) * .01) * $margin
			);
		}
	}
	/**
	 * Получает список запчастей @param $result array, и возвращает:
	 * 1. Если найдено наличие на складах Company, то возвращает только внутр. наличие
	 * 2. Если нет внутреннего наличия, то возвращает все запчасти
	 */
	public static function getResultList($result)
	{
		$items = [
			'inner' => [],
			'all' => [],
		];
		foreach ($result as &$item)
		{
			// если поставщиком текущей запчасти явл-ся Company
			if (self::isInnerPart($item))
			{
				// будет выбрана только текущая деталь
				$items['inner'][] = $item;
			}
			$items['all'][] = $item;
		}
		if (!count($items['inner']))
		{
			$result = $items['all'];
		}
		else
		{
			$result = $items['inner'];
		}
		return $result;
	}
}