<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @todo: Проверка на шаблон печати
 */
$mapWidth = 396;
$mapHeight = 230;
$templateName = $arResult['VARIABLES']['TYPE'];

if (isset($_REQUEST['print'])
	&& $_REQUEST['print'] == 'Y')
{
	$templateName .= '.print';
	$mapWidth = 640;
	$mapHeight = 480;
	CJSCore::Init(array("jquery"));
}
$APPLICATION->IncludeComponent('mkb:geo_points.detail', $templateName, array(
	'IBLOCK_ID' => $arParams['IBLOCK_ID'],
	'POINT_TYPE' => $arResult['VARIABLES']['TYPE'],
	'POINT_ID' => $arResult['VARIABLES']['POINT_ID'],
	'MAP_WIDTH' => $mapWidth,
	'MAP_HEIGHT' => $mapHeight,
	'CACHE_TYPE' => 'Y',
	'CACHE_TIME' => 3600,
	'BACK_LINK' => $arResult['FOLDER'].str_replace(array(
		'#POINT_TYPE#',
		'index.php'
	), array(
		$arResult['VARIABLES']['TYPE'],
		''
	), $arResult['URL_TEMPLATES']['list']).'&id='.$arResult['VARIABLES']['POINT_ID']
));