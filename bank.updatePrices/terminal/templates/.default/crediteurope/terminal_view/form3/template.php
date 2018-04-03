<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?=LANG_CHARSET?>" />
	<link type="text/css" rel="stylesheet" href="<?=$this->__folder?>/style.css" />
	<script type="text/javascript" src="/bitrix/templates/general/js/jquery-1.8.0.min.js"></script>
</head>
<body<?if ($USER->IsAuthorized()){?> id="user_is_authorized"<?}?> class="visible">
	<div>
		<img src="/applications/terminal/img/en_logo.gif" /><br />
		<h2>Treasury Reports</h2>
		<h3>Report#1</h3>
		<div id="data-div1">
			<table border="2" cellpadding="4" cellspacing="2" id="currList" align="center">
				<thead>
					<tr>
						<th>CURRENCY TYPE</th>
						<th>CURRENCY TOTAL AMOUNT</th>
						<th>AVERAGE RATE</th>
						<th>RUB TOTAL AMOUNT</th>
					</tr>
				</thead>
				<tbody><?
foreach ($arResult["REPORT_1"] as $sCurrency => &$arElement)
{?>
					<tr id="<?=$sCurrency?>" class="nowrap">
						<td><span><?=$sCurrency?></span></td>
						<td class="CUR"><span><?=$arElement["CUR"]?></span></td>
						<td class="AVG"><span><?=$arElement["AVG"]?></span></td>
						<td class="RUB"><span><?=$arElement["RUB"]?></span></td>
        			</tr><?
}?>
				</tbody>
			</table>
		</div>

		<h3>Report#2</h3>

	    <table<?if (!count($arResult['REPORT_3'])):?> style="display:none;"<?endif?> border="2" cellpadding="4" cellspacing="2" id="currList3" align="center">
			<thead>
				<th>OPERATION TIME</th>
				<th>BUY CURRENCY</th>
				<th>BUY AMOUNT</th>
				<th>SELL CURRENCY</th>
				<th>SELL AMOUNT</th>
				<th>MICEX RATE</th>
				<th>SWAP POINT</th>
				<th>FINAL RATE</th>
				<th>VALUE DATE</th>
		   </thead>
			<tbody><?
foreach ($arResult['REPORT_3'] as &$arItem)
{?>
				<tr>
					<td align="left"><?=$arItem["OPERATIONTIME"]?></td>
					<td><span><?=$arItem["BUYCURRENCY"]?></span></td>
					<td><span><?=$arItem["BUYAMOUNT"]?></span></td>
					<td><span><?=$arItem["SELLCURRENCY"]?></span></td>
					<td><span><?=$arItem["SELLAMOUNT"]?></span></td>
					<td><span><?=$arItem["MICEXRATE"]?></span></td>
					<td><span><?=$arItem["SWAPPOINT"]?></span></td>
					<td><span><?=$arItem["RATE"]?></span></td>
					<td><span><?=$arItem["VALUEDATE"]?></span></td>
				</tr><?
}?>
	   		</tbody>
	   	</table>
		<div id="data-div2">
			<table border="2" cellpadding="4" cellspacing="2" id="operList" align="center">
				<thead>
					<tr>
						<th>entry time</th>
						<th>reference number</th>
						<th>buy currency code</th>
						<th>buy amount</th>
						<th>sell currency code</th>
						<th>sell amount</th>
						<th>treasury rate</th>
						<th>operation user</th>
						<th>approve user</th>
						<th>client rate</th>
						<th>comments</th>
					</tr>
				</thead>
				<tbody><?
foreach ($arResult["REPORT_2"] as $REFERENCE_NUMBER => &$arElement)
{?>
					<tr id="<?=$REFERENCE_NUMBER?>">
					    <td class="ENTRY_TIME"><?=date("H:i:s",strtotime($arElement["ENTRY_TIME"]))?></td>
					    <td class="REFERENCE_NUMBER"><?=$REFERENCE_NUMBER?></td>
					    <td class="BUY_CURRENCY_CODE"><?=$arElement["BUY_CURRENCY_CODE"]?></td>
					    <td class="BUY_AMOUNT"><?=$arElement["BUY_AMOUNT"]?></td>
					    <td class="SELL_CURRENCY_CODE"><?=$arElement["SELL_CURRENCY_CODE"]?></td>
					    <td class="SELL_AMOUNT"><?=$arElement["SELL_AMOUNT"]?></td>
					    <td class="OPERATION_RATE"><?=$arElement["OPERATION_RATE"]?></td>
					    <td class="OPERATION_USER"><?=$arElement["OPERATION_USER"]?></td>
						<td class="APPROVE_USER"><?=$arElement["APPROVE_USER"]?></td>
					    <td class="RATE"><?=$arElement["RATE"]?></td>
					    <td class="COMMENTS"><?=$arElement["COMMENTS"]?></td>
					</tr><?
}?>
				</tbody>
			</table>
		</div>
	</div><?
if ($arParams['NEED_AUTH'] == 'Y')
{?>
	<iframe id="auth" name="auth" src="/auth.php"></iframe><?
}?>
	<script type="text/javascript" src="<?=$this->__folder?>/script.js"></script>
</body>
</html>