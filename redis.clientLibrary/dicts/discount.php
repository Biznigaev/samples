<?php
/**
 * Справочник наценок. При получении справочника применяется memcached
 */
namespace Company\Main\Helpers\Dicts;

class Discount extends Factory
{
	private static $instance;
	private static $discountRules;
	private static $iblockType = 'linemedia_auto';
	private static $iblockCode = 'lm_auto_discount';
	private static $selectFields = [
		'ID', 
		'PROPERTY_discount', 
		'PROPERTY_user_id', 
		'PROPERTY_user_group', 
		'PROPERTY_supplier_id'
	];

	private function __construct()
	{
		// создание справочника и фильтр полученных значений
		array_walk(
			parent::createDictionary(
				self::$selectFields, [
				'IBLOCK_TYPE' => self::$iblockType,
				'IBLOCK_CODE' => self::$iblockCode,
				'ACTIVE' => 'Y',
			]),
			function (&$v, $k)
			{
				if (!isset(self::$discountRules[$v['ID']]['discount']))
				{
					self::$discountRules[$v['ID']]['discount'] = doubleval($v['PROPERTY_DISCOUNT_VALUE']);
				}
				if (!empty($v['PROPERTY_USER_ID_VALUE'])
					&& !in_array($v['PROPERTY_USER_ID_VALUE'], self::$discountRules[$v['ID']]['user_id']))
				{
					self::$discountRules[$v['ID']]['user_id'][] = $v['PROPERTY_USER_ID_VALUE'];
				}
				if (!empty($v['PROPERTY_USER_GROUP_VALUE'])
					&& !in_array($v['PROPERTY_USER_GROUP_VALUE'], self::$discountRules[$v['ID']]['user_group']))
				{
					self::$discountRules[$v['ID']]['user_group'][] = $v['PROPERTY_USER_GROUP_VALUE'];
				}
				if (!empty($v['PROPERTY_SUPPLIER_ID_VALUE'])
					&& !in_array($v['PROPERTY_SUPPLIER_ID_VALUE'], self::$discountRules[$v['ID']]['supplier_id']))
				{
					self::$discountRules[$v['ID']]['supplier_id'][] = $v['PROPERTY_SUPPLIER_ID_VALUE'];
				}
			},
			ARRAY_FILTER_USE_BOTH
		);
	}
	public function __clone()
	{}

	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Получение величины наценки по id поставщика
	 */
	public static function getBySupplierId($sId)
	{
		global $USER;
		self::getInstance();
		$discount = 0;
		foreach (self::$discountRules as $id => &$conditions)
		{
			if (in_array($sId, $conditions['supplier_id']))
			{
				$checkUserId = (
					isset($conditions['user_id'])
					&& in_array($USER->getId(), $conditions['user_id'])
				);
				$checkInGroup = (
					isset($conditions['user_group'])
					&& \CSite::InGroup($conditions['user_group'])
				);
				if ($checkUserId 
					|| $checkInGroup)
				{
					$discount = $conditions['discount'];
					break;
				}
			}
		}
		return $discount;
	}
}