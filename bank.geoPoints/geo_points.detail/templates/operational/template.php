<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();?>
<h1 class="H1"><?=$arResult['NAME']?></h1>
<p></p>
<div class="main_text">
<?
if (count($arResult['NEAREST_POINTS']['DISTANCE']))
{
?>
<div class="right_menu n_right_f">
	<h3>Ближайшие точки продаж</h3>
	<p></p>
	<div class="n_other_ot" id="nearestFilials">
<?
	foreach (array(
		'office' => 'Отделения',
		'operational' => 'Операционные кассы',
		'terminal' => 'Терминалы',
		'atm' => 'Банкоматы'
	) as $type => $name)
	{
		if (!array_key_exists($type, $arResult['NEAREST_POINTS']['POINTS']))
		{
			continue;
		}
		$arPointList = &$arResult['NEAREST_POINTS']['POINTS'][$type];
		?>
		<div style="padding-bottom: 50px;">
			<strong><?=$name?></strong><?
		foreach ($arResult['NEAREST_POINTS']['DISTANCE'] as $id => $distance)
		{
			if (array_key_exists($id, $arPointList))
			{
				?>
			<table border="0" style="border-collapse: collapse;margin-bottom:7px;" cellpadding="0" cellspacing="0" >
				<tr>
					<td style="width: 240px;" valign="top" align="left">
						<a style="z-index:10000;" href="<?=$arPointList[$id]['LINK']?>"><?=$arPointList[$id]['NAME']?></a>
						<p style="color: rgb(97,97,97);font-size:12px;padding-right:10px;border-bottom:0px;"><?=$arPointList[$id]['ADDRESS']?></p>
					</td>
					<td valign="top" align="right">
						<span><?=$distance?> м</span>
						<br style="clear: both;">
					</td>
				</tr>
			</table><?
			}
		}
		?>
		</div>
		<?
	}
?>
	</div>
</div>
<?
}
?>
<p><?=$arResult['ADDRESS']?></p>
<?
if (count($arResult['METRO']))
{
?>
	<div style="margin:12px 0;">
<?
	foreach ($arResult['METRO'] as $line_id => &$arLine)
	{
		foreach ($arLine['STATIONS'] as $station_id => &$stationName)
		{
?>
		<p class="m_line_<?=$arLine['CODE']?>" style="margin: 3px 0pt 5px;"><?=$stationName?></p>
<?
		}
	}
?>
	</div>
<?
}
?>
	<div style="width:<?=$arParams['MAP_WIDTH']?>px; height:<?=$arParams['MAP_HEIGHT']?>px; ">
		<div id="mapContainer" style="width:<?=$arParams['MAP_WIDTH']?>px; height:<?=$arParams['MAP_HEIGHT']?>px;">
			<div id="filialMap" data-type="<?=$arParams["POINT_TYPE"]?>" style="max-width:<?=$arParams['MAP_WIDTH']?>px; height:<?=$arParams['MAP_HEIGHT']?>px;" data-lon="<?=$arResult['COORDS']['lon']?>" data-lat="<?=$arResult['COORDS']['lat']?>"></div>
		</div>
	</div>
	<script type="text/javascript" src="//maps.google.com/maps/api/js?sensor=false"></script>
	
	<script type="text/javascript">
		var gMap = null,
			pointType = {
				office: '/images/poi/ico_branch.png',
				operational: '/images/poi/ico_operating.png',
				atm: '/images/poi/ico_atm.png',
				terminal: '/images/poi/ico_terminals.png'
			}
		$(document).ready(function()
		{
			gMap = new google.maps.Map($('#filialMap')[0], {
				zoom: 15,
				center: new google.maps.LatLng($('#filialMap').data('lat'), $('#filialMap').data('lon')),
				navigationControl: true,
				navigationControlOptions: {
					style: google.maps.NavigationControlStyle.SMALL
				},
				streetViewControl: false,
				mapTypeId: google.maps.MapTypeId.ROADMAP
			})
			new google.maps.Marker({
			    position: new google.maps.LatLng($('#filialMap').data('lat'), $('#filialMap').data('lon')),
			    map: gMap,
			    icon: pointType[$('#filialMap').data('type')]
			})
		})
	</script>
	<div class="n_page">
		<p></p>
		<div style="position:relative; height: 40px;">
			<div style="position:absolute; top:0px; left:0px;">
				<img src="/images/icons/print.png" alt="Печать" />
			</div>
			<div style="position: absolute;top:0px; left: 20px;">
				<a class="menu_pad_10_L" href="#" onclick="document.getElementById('prnBtn').click()">Печать</a>
			</div>
			<div class="hidElem">
				<form name="printFrom" method="GET" target="_blank">
					<input type="hidden" name="id" value="<?=$arParams['POINT_ID']?>" />
					<input type="hidden" name="print" value="Y" />
					<input type="submit" id="prnBtn" />
				</form>
			</div>
		</div>
		<p></p>
<?
if (!empty($arResult['PROEZD'])):
?>
		<div>
			<img src="/images/icons/trackback.png" alt="Проезд" />&nbsp;&nbsp;
			<a style="cursor: pointer; border-bottom: thin dashed #900027; text-decoration: none; position: absolute; margin: 0px;" onfocus="this.blur()" onclick="open_section('proezd_div')">Проезд</a>
		</div>
		<div id="proezd_div" class="hidElem"><?=$arResult['PROEZD']?></div>
<?
endif
?><?
if (count($arResult['PHONE'])):
?>
		<p></p>
		<p></p>
		<div>
			<img src="/images/icons/mobile_phone.png" alt="Контактые телефоны" style="float:left;padding-right:10px;" />
			<b style="position:relative; margin:0 ;width:<?=$arParams['MAP_WIDTH']?>px;"><?=implode(', ', $arResult['PHONE'])?></b>
		</div>
<?
endif
?>
		<p></p>
		<p></p>
		<p>
			<div>
				<img src="/images/icons/clock.png" alt="Режим работы" style="float:left;padding-right:10px;" />
				<span style="position:relative;;margin:2px 0 0 0;width:450px;">
					<b>Режим работы: </b>
					<?=$arResult['WORK_MODE']?>
				</span>
			</div>
		</p>
<?
if (!empty($arResult['SPECIAL_WORK_MODE']))
{
?>
<div style="width: 400px;">
	<b>
		<span style="color: #900027;">Внимание! Изменения в режиме работы:</span>
	</b>
	<br /><?=$arResult['SPECIAL_WORK_MODE']?>
</div>
<?
}
?>
<br />
<?
if (count($arResult['PHOTO']))
{
?>
<p>
<?
	foreach ($arResult['PHOTO'] as &$arPhoto)
	{
?>
	<img src="<?=$arPhoto['SRC']?>" width="<?=$arPhoto['WIDTH']?>" height="<?=$arPhoto['HEIGHT']?>" alt="" />
<?
	}
?>
</p>
<?
}

if (array_key_exists('FIZ', $arResult['SERVICES'])
	&& count($arResult['SERVICES']['FIZ']))
{
?>
	<div class="operation-services">
		<strong>Услуги операционной кассы: </strong>
		<br />
		<ul>
			<li>Обмен валюты; принимаемые к обмену виды иностранной валюты: доллар США и Евро</li>
			<li>Операции с пластиковыми картами банка: 
			
			<ul>
				<li>выдача наличных денежных средств по банковским картам международных систем Visa и MasterCard; </li>
				<li>пополнение счетов банковских карт международных систем Visa и MasterCard как с целью погашения овердрафтов, так и с целью обычного использования средств;</li>
			</ul>
			</li>
		</ul>
	</div>
<?
}

if (array_key_exists('UR', $arResult['SERVICES'])
	&& count($arResult['SERVICES']['UR']))
{
?>
	<ul>
		<li>— Открытие и ведение счетов</li>
		<li>— Осуществление расчетов по поручению юридического лица</li>
		<li>— Кассовое обслуживание юридических лиц</li>
		<li>— Кредитование малого и среднего бизнеса</li>
	</ul>
<?
}
?>
			<br /><br />
			<a href="<?=$arParams['BACK_LINK']?>">Вернуться к карте</a>
	</div>
</div>