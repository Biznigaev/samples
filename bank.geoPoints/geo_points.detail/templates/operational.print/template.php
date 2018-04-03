<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();?>
<h1 class="H1"><?=$arResult['NAME']?></h1>
<p></p>
<div class="main_text">
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
		<p style="margin: 3px 0pt 5px;"><img src="//mkb.ru/images/for_map/metro/line-<?=$arLine['CODE']?>.gif" />&nbsp;<?=$stationName?></p>
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
			<p class="main_text">
				<input type="button" value="Печать" onclick="window.print()" />
			</p>
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
?>
<?
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
if (count($arResult['NEAREST_POINTS']['DISTANCE']))
{
?>
<div>
	<b>Другие точки продаж в этом районе</b>
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
						<span style="font-size:12px;color:#000;"><?=$arPointList[$id]['NAME']?></span>
						<br />
						<span style="font-size:12px;padding-right:10px;color:#616161;"><?=$arPointList[$id]['ADDRESS']?></span>
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
?>
	</div>
</div>