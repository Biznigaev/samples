<?php
/**
 * Справочник наценок. При получении справочника применяется memcached
 */
namespace Company\Main\Helpers\Dicts;

class Margin extends Factory
{
	private static $instance;
	private static $marginRules;
	private static $iblockType = 'linemedia_auto';
	private static $iblockCode = 'lm_auto_suppliers';
	private static $selectFields = [
		'ID', 
		'PROPERTY_supplier_id', 
		'PROPERTY_markup'
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
				if (!empty($v['PROPERTY_MARKUP_VALUE'])
					&& doubleval($v['PROPERTY_MARKUP_VALUE']) > 0)
				{
					self::$marginRules[intval($v['PROPERTY_SUPPLIER_ID_VALUE'])] = doubleval(
						$v['PROPERTY_MARKUP_VALUE']
					);
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
		self::getInstance();
		if (array_key_exists($sId, self::$marginRules))
		{
			return self::$marginRules[$fields['supplier_id']];
		}
	}
}