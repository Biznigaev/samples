<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?=LANG_CHARSET?>">
		<script type="text/javascript" src="/bitrix/templates/general/js/jquery-1.8.0.min.js"></script>
	</head>
	<body<?if ($USER->IsAuthorized()){?> id="user_is_authorized"<?}?> class="visible">
		<center>
			<img src="/applications/terminal/img/en_logo.gif" />
			<br />
			<h2>Treasury Special Rate Observation</h2>
			<div style="border:1px;width:600px;height:600px;overflow:auto;" id="data-div">
				<table border="2" cellpadding="4" cellspacing="2" id="currList">
					<thead>
						<tr>
							<td bgcolor="#6a1686" style="color:#ffffff; padding: 4px;" width="100" align="center"><b>Currency</b></td>
							<td bgcolor="#6a1686" style="color:#ffffff; padding: 4px;" width="100" align="center"><b>Buy</b></td>
							<td bgcolor="#6a1686" style="color:#ffffff; padding: 4px;" width="100" align="center"><b>Sell</b></td>
							<td bgcolor="#6a1686" style="color:#ffffff; padding: 4px;" width="100" align="center"><b>Rate</b></td>
							<td bgcolor="#6a1686" style="color:#ffffff; padding: 4px;" width="100" align="center"><b>Buy spread</b></td>
							<td bgcolor="#6a1686" style="color:#ffffff; padding: 4px;" width="100" align="center"><b>Sell spread</b></td>
						</tr>
					</thead>
					<tbody>
					<?foreach ($arResult['REPORT_4'] as $sName => &$arItem):?>
						<tr id="<?=$sName?>">
						    <td bgcolor="#e1dae3"><b><?=$sName?></b></td>
						    <td class="BUY" align="right"><span><b><?=$arItem["BUY"];?></b></span></td>
						    <td class="SELL" align="right"><span><b><?=$arItem["SELL"];?></b></span></td>
						    <td class="RATE" align="right"><span><b><?=$arItem["RATE"];?></b></span></td>
						    <td class="BUY_SPREAD" align="right"><span><b><?=$arItem["BUY_SPREAD"];?></b></span></td>
						    <td class="SELL_SPREAD" align="right"><span><b><?=$arItem["SELL_SPREAD"];?></b></span></td>
						</tr>
					<?endforeach?>
					</tbody>
				</table>
			</div>
		</center><?
if ($arParams['NEED_AUTH'] == 'Y')
{?>
		<iframe id="auth" name="auth" src="/auth.php"></iframe><?
}?>
		<script type="text/javascript" src="<?=$this->__folder?>/script.js"></script>
	</body>
</html>