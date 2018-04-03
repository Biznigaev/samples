<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if (!count($arParams['FILTER']))
{
	return;
}
$srvMapping = array(
	'office' => array(
		'safe' => 'rent-safe',
		'off' => 'fiz',
		'biz' => 'ur'
	),
	'atm' => array(
		'atmcashin' => 'cashin'
	),
	'terminal' => array(
		'terminalcards' => 'accepts_cards'
	)
);
if (!empty($_REQUEST['srv'])
	&& in_array($_REQUEST['srv'], $srvMapping[$arParams['POINT_TYPE']]))
{
	foreach ($arParams['FILTER'] as $idx => &$arItem)
	{
		$arItem['DEFAULT'] = $arItem['KEY'] == $_REQUEST['srv'];
	}
}
/**
 *    @todo: Выставление признака checked из cookie
 */
$obCache = new CPhpCache;
$cache_time = $arParams['CACHE_TIME'];
$cache_tags = $arParams['POINT_TYPE'].print_r($arParams['FILTER'],1);
if ($obCache->InitCache($cache_time,$cache_tags))
{
	$arVars = $obCache->GetVars();
	$arResult = $arVars['ITEMS'];
}
elseif ($obCache->StartDataCache())
{
	CModule::IncludeModule('iblock');
	$arResult = $arParams['CHECKED'] = array();

	$arProp = CIBlockPropertyEnum::GetList(false,array(
		'IBLOCK_ID' => $arParams['IBLOCK_ID'],
		'PROPERTY_ID' => 'TYPE',
		'EXTERNAL_ID' => $arParams['POINT_TYPE']
	))->Fetch();

	$arFilter = array(
		'IBLOCK_ID' => $arParams['IBLOCK_ID'],
		'ACTIVE' => 'Y', 
		'PROPERTY_TYPE' => $arProp['ID'],
		'PROPERTY_IS_LIMITED' => false
	);
	foreach ($arParams['FILTER'] as $idx => &$arItem)
	{
		$arResult[$idx] = array(
			'ID' => $arItem['KEY'],
			'TITLE' => $arItem['NAME'],
			'COUNT' => intVal(
				CIBlockElement::GetList(array(
					'ID' => 'ASC'
				), array_merge($arFilter,$arItem['FILTER']), array(), false, array(
					'ID'
				))
			),
			'CHECKED' => in_array($arItem['KEY'], $arParams['CHECKED']) || $arItem['DEFAULT']
		);
	}
	$obCache->EndDataCache(array(
		'ITEMS' => $arResult
	));
}
$this->IncludeComponentTemplate();