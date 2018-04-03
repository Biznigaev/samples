<?php
namespace Company\Main\Helpers\Dicts;

use \CIBlockElement,
	Bitrix\Main\Loader,
	Bitrix\Main\Data\Cache;
use Company\Main\Helpers\Dicts\Margin,
	Company\Main\Helpers\Dicts\Discount;

class Factory
{
	private static $cacheTime = 3600;
	private static $cacheDir = '/price/dicts/';
	/**
	 * Получение справочника из инфоблоков. Параметры передаются в @param where
	 * @param where array фильтр
	 * @param select array поля выборки
	 * @param order array | false сортировка. (По умолч. ID => ASC)
	 */
	protected static function createDictionary($select, $where, $order=false)
	{
		if (!$order)
		{
			$order = ['ID' => 'ASC'];
		}
		$cacheId = md5(
			json_encode(
				array_merge($select, $where, $order)
			)
		);
		$result = [];
		$cache = Cache::createInstance();
		if ($cache->initCache(self::$cacheTime, $cacheId, self::$cacheDir))
		{
			$result =  $cache->getVars();
		}
		elseif ($cache->startDataCache())
		{
			if (!Loader::includeModule('iblock'))
			{
				$cache->abortDataCache();
			}
			else
			{
				$dbRes = CIBlockElement::getList($order, $where, false, false, $select);
				while ($row = $dbRes->fetch())
				{
					$result[] = $row;
				}
				if (!count($result))
				{
					$cache->abortDataCache();
				}
				$cache->endDataCache($result);
			}
		}
		return $result;
	}

	public static function getDictionary($dictName)
	{
		switch ($dictName)
		{
			case 'margin':
			{
				return Margin::getInstance();
				break;
			}
			case 'discount':
			{
				return Discount::getInstance();
				break;
			}
		}
	}
}