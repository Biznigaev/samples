<?php
namespace Company\Main\Helpers;
use Bitrix\Main\Localization\Loc;

class PricesHelper
{
	const INNER_PRICE = 'supplier_price';

	public static function getTypes()
	{
		return [
			'supplier_price' => Loc::getMessage('SUPPLIER_PRICE_TEXT'),
			'commerce_price' => Loc::getMessage('COMMERCE_PRICE_TEXT'),
			'retail_price' => Loc::getMessage('RETAIL_PRICE_TEXT'),
			'minimal_price' => Loc::getMessage('MINIMAL_PRICE_TEXT'),
		];
	}
}