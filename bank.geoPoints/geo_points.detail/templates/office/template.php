<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();?>
<h1 class="H1"><?=$arResult['NAME']?></h1>
<p></p>
<div class="main_text" style="position:relative;">
<?
if (count($arResult['NEAREST_POINTS']['DISTANCE']))
{
?>
<div class="right_menu n_right_f" style="float:right; width:300px; color:#000; display:block;" >
	<h3>��������� ����� ������</h3>
	<p></p>
	<div class="n_other_ot" id="nearestFilials">
<?
	foreach (array(
		'office' => '���������',
		'operational' => '������������ �����',
		'terminal' => '���������',
		'atm' => '���������'
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
						<span><?=$distance?> �</span>
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
<?=$arResult['ADDRESS']?>
<br />
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
	<script type="text/javascript" src="/js/jquery-1.6.1.js"></script>
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
				<img src="/images/icons/print.png" alt="������" />
			</div>
			<div style="position: absolute;top:0px; left: 20px;">
				<a class="menu_pad_10_L" href="#" onclick="document.getElementById('prnBtn').click()">������</a>
			</div>
			<div class="hidElem">
				<form name="printFrom" method="GET" target="_blank">
					<input type="hidden" name="id" value="<?=$arParams['ID']?>" />
					<input type="hidden" name="print" value="Y" />
					<input type="submit" id="prnBtn" />
				</form>
			</div>
			<div style="position:absolute; top:-5px; left: 120px; width:150px;">
				<img src="/images/icons/star.png" alt="������� ���������"/>
			</div>
			<div style="position:absolute; top:0px; left:160px;">
				<a href="/on_line_service/feedback_form.php" target="_blank">������� ���������</a>
			</div>
		</div>
		<p></p>
<?
if (!empty($arResult['PROEZD'])):
?>
		<div>
			<img src="/images/icons/trackback.png" alt="������" />&nbsp;&nbsp;
			<a style="cursor: pointer; border-bottom: thin dashed #900027; text-decoration: none; position: absolute; margin: 0px;" onfocus="this.blur()" onclick="open_section('proezd_div')">������</a>
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
			<img src="/images/icons/mobile_phone.png" alt="��������� ��������" style="float:left;padding-right:10px;" />
			<b style="position:relative; margin:0 ;width:<?=$arParams['MAP_WIDTH']?>px;"><?=implode(', ', $arResult['PHONE'])?></b>
		</div>
<?
endif
?>
		<p></p>
		<p></p>
		<p></p>
		<div>
			<img src="/images/icons/clock.png" alt="����� ������" style="float:left;padding-right:10px;" />
			<span style="position:relative;;margin:2px 0 0 0;width:350px;"><b>����� ������: </b><?=$arResult['WORK_MODE']?></span>
		</div>
<p></p>
<p>
	<strong>������������� ���������</strong>
</p>
	<div id="single_load_div">
		<div style="height: 30px;">
			<span class="abs_pos" style="font-size: 14px; color: #333; font-familiy: Tahoma; font-weight: bold; margin: 4px 0px 0px 0px; color: #900027;">�������, <?=$arResult['LOAD']['TODAY']['DAY_NAME']?>:</span>
			<span class="abs_pos" style="font-size: 10px; color: #7c7c7c; font-familiy: Tahoma; margin: 25px 0 0 0;">�����:</span>
			<img class="abs_pos" style="margin: 0px 0px 0px 97px" src="/images/poi/busy/bg.png" alt="" />
<?
	$inc = 60;
	foreach ($arResult['LOAD']['WEEK_DAY'][$arResult['LOAD']['TODAY']['LOAD']] as $i => &$arHour)
	{
?>
		<img class="abs_pos" style="margin: 5px 0px 0px <?=(16*$i)+42+$inc?>px;" src="<?=$arHour['SRC']?>" alt="<?=$arHour['alt']?>">
		<span class="abs_pos" style="font-size: 9px;  font-familiy: Tahoma; margin: 25px 0px 0px <?=(16*$i)+44+$inc?>px; <? if ((10+$i)==date('G')): ?>color: #900027; font-weight: bold;<? else: ?>color: #7c7c7c;<? endif ?>"><?=(10+$i)?></span>
<?
	}
?>
	</div>
</div>
<div id="load_all_days">
	<p>
		<a style=">cursor: pointer; border-bottom: thin dashed #900027; text-decoration: none; margin: -0px 0 0 0 " onfocus="this.blur()" onclick="open_section('load_div');open_section('single_load_div');open_section('load_all_days');open_section('load_single_day');">��������� ���</a>
	</p>
</div>
<div id="load_single_day" class="hidElem">
	<p>
		<a style="cursor: pointer; border-bottom: thin dashed #900027; text-decoration: none; margin: -0px 0 0 0 " onfocus="this.blur()" onclick="open_section('load_div');open_section('single_load_div');open_section('load_all_days');open_section('load_single_day');">�������</a>
	</p>
</div>
<div class="hidElem" id="load_div" onclick="this.blur()" style="border: 0px solid #000;">
<?
	foreach ($arResult['LOAD']['WEEK_DAY'] as $i => &$arWeekDay)
	{
		$day = FormatDate('D', strtotime(date('D', strtotime("Sunday + ".($i+1)." days"))));
?>
	<div style="height: 30px;">
		<span class="abs_pos" style="font-size: 14px; color: #333; font-familiy: Tahoma; font-weight: bold; margin: 4px 0px 0px 0px; <? if ($day == FormatDate('D', MakeTimeStamp(date('d.m.Y')))): ?>color: #900027;<? endif ?>"><?=$day?>:</span>
<?
		if (!$i)
		{
?>
			<span class="abs_pos" style="font-size: 10px; color: #7c7c7c; font-familiy: Tahoma; margin: 25px 0px 0px 0px">�����:</span>
<?
		}
?>
		<img class="abs_pos" style="margin: 0px 0px 0px 37px" src="/images/poi/busy/bg.png" />
<?
		foreach ($arWeekDay as $idx => &$arHour)
		{
?>
		<img class="abs_pos" style="margin: 5px 0px 0px <?=(16*$idx)+42?>px;" src="<?=$arHour['SRC']?>" alt="<?=$arHour['ALT']?>">
		<span class="abs_pos" style="font-size: 9px; font-familiy:Tahoma; margin: 25px 0px 0px <?=(16*$idx)+44?>px; <? if (((10+$idx)==date('G')) && ($day == FormatDate('D', MakeTimeStamp(date('d.m.Y'))))): ?>color: #900027; font-weight: bold;<? else: ?>color: #7c7c7c;<? endif ?>"><?=(10+$idx)?></span>
<?
		}
?>
	</div>
	<div style="height: 10px;"></div>
<?
	}
?>
</div>
<?
if (!empty($arResult['SAFE_WORK_MODE']))
{
?>
<p>
	<strong>����� ������ ��������� ���������:</strong>
	<br /><?=$arResult['SAFE_WORK_MODE']?>
</p>
<?
}
if (!empty($arResult['SPECIAL_WORK_MODE']))
{
?>
<div style="width: 400px;">
	<b>
		<span style="color: #900027;">��������! ��������� � ������ ������:</span>
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
?>
<div style="width:<?=$arParams['MAP_WIDTH']?>px;">
	<p>������������� �������� �������� ����� ����������� ���������� �����. </p>
<?
if (array_key_exists('FIZ', $arResult['SERVICES'])
	&& count($arResult['SERVICES']['FIZ']))
{
?>
	<strong>������ ���������� �����</strong>
	<ul>
		<li>� �������� � ���������� �������� �� �������</li>
		<li>� ���������� ��������� � ��������� ���� ������������� ��������� ������ � ���������� �������� �� ���</li>
		<li>� ��������������� ������������ �� ����� ����</li>
		<li>� ����������������</li>
		<li>� ��������� ������������ (������������); ���������� ������� �������������� ������ � ��������� ������ ���</li>
		<li>� ����� �������� ������� � ���� ��������� ��������</li>
		<li>� ����� �������� ������� ��� ������������� ��������� �� ������� Western Union �� ������ � �� �����, � ����� ������� �� ���������</li>
		<li>� ������� � ����� ���������� ������� ����� � ������������� �����������</li>
		<li>� ������������� ��������� ��� �������� �����</li>
		<li>� �������� ������ � ���������� �������� �� ��� � ������ � ����������� ������</li>
		<li>� ����� ������</li>
<?
	if (!empty($arResult['SAFE_WORK_MODE']))
	{
?>
		<li>� ������ �������������� ���������� ������</li>
<?
		if ($arParams['ID'] == 1021977)
		{
?>
		<li>� ������������ �������� �� ������ ���������� ���.�����</li>
<?
		}
?>
		<li>� ����������</li>
		<li>� ������� ������������� ����� � ����������������� ���������� �����</li>
<?
		if ($arParams['ID'] == 1021977)
		{
?>
		<li>� ������ ���������</li>
		<li>� ������ �����������</li>
<?
		}
	}
?>
	</ul>
	<br />
<?
}

if (array_key_exists('UR', $arResult['SERVICES'])
	&& count($arResult['SERVICES']['UR']))
{
?>
	<ul>
		<li>� �������� � ������� ������</li>
		<li>� ������������� �������� �� ��������� ������������ ����</li>
		<li>� �������� ������������ ����������� ���</li>
		<li>� ������������ ������ � �������� �������</li>
	</ul>
<?
}
?>
			<br /><br />
			<a href="<?=$arParams['BACK_LINK']?>">��������� � �����</a>
		</div>
	</div>
</div>