<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// для карты
$current_gps = "37.617633, 55.755786";
$map_zoom = 10;

if (isset($_REQUEST['id']) 
	&& is_numeric($_REQUEST['id']))
{
	// Если есть Id определенного объекта, то на карте надо перейти на него с увеличенным масштабом
	$obCache = new CPhpCache;
	if ($obCache->InitCache($arParams['CACHE_TIME'],$arResult['VARIABLES']['TYPE'].$_REQUEST['id']))
	{
		$arVars = $obCache->GetVars();
		$map_zoom = $arVars['map_zoom'];
		$current_gps = $arVars['current_gps'];
	}
	elseif ($obCache->StartDataCache())
	{
		CModule::IncludeModule("iblock");
		//достаю совйства элемента из инфоблока
		$res = CIBlockElement::GetProperty($arParams['IBLOCK_ID'], $_REQUEST['id'], array(
			'sort' => 'asc'
		), array(
			'CODE' => 'GPS'
		));
		if ($res->SelectedRowsCount())
		{
			$list_fields = $res->Fetch();
			$current_gps = $list_fields['VALUE'];
			$map_zoom = 15;
			unset($list_fields);
		}
		unset($res);
		$obCache->EndDataCache(array(
			'map_zoom' => $map_zoom,
			'current_gps' => $current_gps
		));
	}
}
?>
<input type="hidden" name="get_type" id="get_type" value="<?=$arResult['VARIABLES']['TYPE']?>" />
<div id="current_gps" name="current_gps"><?=$current_gps?></div>
<div id="map_zoom" name="map_zoom"><?=$map_zoom?></div>

<table width="100%">
	<tr>
		<td style="padding:0 0 15px 0;">
			<h1 class="h1_new"><?=$arResult['VARIABLES']['TITLE']?></h1>
			<?$APPLICATION->IncludeComponent(
				"bitrix:menu",
				"geo_points",
				Array(
					"COMPONENT_TEMPLATE" => ".default",
					"ROOT_MENU_TYPE" => "center",
					"MENU_CACHE_TYPE" => "N",
					"MENU_CACHE_TIME" => "3600",
					"MENU_CACHE_USE_GROUPS" => "Y",
					"MENU_CACHE_GET_VARS" => array(""),
					"MAX_LEVEL" => "1",
					"CHILD_MENU_TYPE" => "",
					"USE_EXT" => "Y",
					"DELAY" => "N",
					"ALLOW_MULTI_SELECT" => "N"
				)
			);?>
			<?$APPLICATION->IncludeComponent(
				"bitrix:main.include",
				"geo_points",
				Array(
					"COMPONENT_TEMPLATE" => ".default",
					"AREA_FILE_SHOW" => "page",
					"AREA_FILE_SUFFIX" => $arResult['VARIABLES']['TYPE'],
					"EDIT_TEMPLATE" => "",
					"PATH" => ""
				)
			);?>
		</td>
	</tr>	
	<tr>
		<td>
            <div class="main_text">
            	<?$APPLICATION->IncludeComponent('mkb:geo_points.list','',array(
            		'IBLOCK_ID' => $arParams['IBLOCK_ID'],
            		'FILTER' => $arResult['VARIABLES']['FILTER'],
            		'TYPE' => $arResult['VARIABLES']['TYPE'],
            		'CACHE_TYPE' => $arParams['CACHE_TYPE'],
            		'CACHE_TIME' => $arParams['CACHE_TIME']
            	))?>
                <br /><br />
            </div>
        </td>
 
    </tr>
</table>
<div class="main_text">
<p>
	<a id="all-points-list" href="/about_bank/address/all_offices.xls">Список отделений, банкоматов и терминалов (файл Excel)</a>
</p>
<br/>
<p><b>Снять наличные без комиссии</b> можно в&nbsp;банкоматах банков-партнёров: <noindex><a href="http://alfabank.ru/atms/moscow/" rel="nofollow" target="_blank">Альфа-Банк</a></noindex>, <noindex><a href="http://www.raiffeisen.ru/offices/" rel="nofollow" target="_blank">Райффайзенбанк</a></noindex>, <noindex><a href="https://www.unicreditbank.ru/ru/moscow/branch-finder.html" rel="nofollow" target="_blank">ЮниКредит Банк</a></noindex>, <noindex><a href="http://www.rgsbank.ru/about/atms/moscow/?day7=N&hours24=N" rel="nofollow" target="_blank">Росгосстрах&nbsp;Банк</a></noindex>.<br /></p>
<p><b>Внести платеж по&nbsp;кредиту и&nbsp;пополнить счет дебетовой или кредитной карты без комиссии</b> возможно в&nbsp;сети платежных устройств с&nbsp;функцией cash&nbsp;in: <a href="http://mkb.ru/about_bank/address/?type=atm&srv=atmcashin"> банкоматах</a> и&nbsp;<a href="/about_bank/address/?type=terminal&srv=terminalcards"><?/*SEO*/?>банковских<?/*SEO*/?> терминалах</a> МОСКОВСКОГО КРЕДИТНОГО БАНКА, а&nbsp;также в&nbsp;<noindex><a href="http://www.alfabank.ru/atm/moscow/" rel="nofollow" target="_blank">банкоматах ОАО &laquo;Альфа-Банк&raquo;</a></noindex>.</p>
</div>
<p></p>
<br />
<?
$arrFilter = $arParams['NEWSLIST_FILTER'];
$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"mkb_news_poi",
	Array(
		"DISPLAY_DATE" => "Y",
		"DISPLAY_NAME" => "Y",
		"DISPLAY_PICTURE" => "Y",
		"DISPLAY_PREVIEW_TEXT" => "Y",
		"AJAX_MODE" => "N",
		"IBLOCK_TYPE" => "news",
		"IBLOCK_ID" => "1",
		"NEWS_COUNT" => "7",
		"SORT_BY1" => "ACTIVE_FROM",
		"SORT_ORDER1" => "DESC",
		"SORT_BY2" => "SORT",
		"SORT_ORDER2" => "ASC",
		"FILTER_NAME" => "arrFilter",
		"FIELD_CODE" => Array(),
		"PROPERTY_CODE" => Array(),
		"CHECK_DATES" => "Y",
		"DETAIL_URL" => "",
		"PREVIEW_TRUNCATE_LEN" => "",
		"ACTIVE_DATE_FORMAT" => "d.m.Y",
		"DISPLAY_PANEL" => "N",
		"SET_TITLE" => "N",
		"SET_STATUS_404" => "N",
		"INCLUDE_IBLOCK_INTO_CHAIN" => "N",
		"ADD_SECTIONS_CHAIN" => "N",
		"HIDE_LINK_WHEN_NO_DETAIL" => "N",
		"PARENT_SECTION" => "",
		"PARENT_SECTION_CODE" => "",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "36000",
		"CACHE_FILTER" => "N",
		"DISPLAY_TOP_PAGER" => "N",
		"DISPLAY_BOTTOM_PAGER" => "Y",
		"PAGER_TITLE" => "Новости",
		"PAGER_SHOW_ALWAYS" => "Y",
		"PAGER_TEMPLATE" => "",
		"PAGER_DESC_NUMBERING" => "N",
		"PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
		"PAGER_SHOW_ALL" => "Y",
		"AJAX_OPTION_SHADOW" => "Y",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"AJAX_OPTION_HISTORY" => "N"
	)
);?>