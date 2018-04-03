<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arResult['REPORT_1'] = (array)json_decode(CTreasuryReports::GetSecondReport(print_r($arParams['OCI_CONNECTION'],1)));
$arResult['REPORT_1'] = reset($arResult['REPORT_1']);
foreach ($arResult['REPORT_1'] as $key => &$val)
{
	$val = (array)$val;
}
TrimArr($arResult['REPORT_1']);

$arResult['REPORT_2'] = (array)json_decode(CTreasuryReports::GetFirstReport(print_r($arParams['OCI_CONNECTION'],1)));
$arResult['REPORT_2'] = reset($arResult['REPORT_2']);
foreach ($arResult['REPORT_2'] as $key => &$val)
{
	$val = (array)$val;
}
TrimArr($arResult['REPORT_2']);

$arResult['REPORT_3'] = (array)json_decode(CTreasuryReports::GetThirdReport(print_r($arParams['OCI_CONNECTION'],1)));
$arResult['REPORT_3'] = reset($arResult['REPORT_3']);
foreach ($arResult['REPORT_3'] as $key => &$val)
{
	$val = (array)$val;
}
TrimArr($arResult['REPORT_3']);

$LOGIN = $APPLICATION->get_cookie('LOGIN');
if ($USER->IsAuthorized()
	&& empty($LOGIN))
{
	$APPLICATION->set_cookie('LOGIN',$USER->GetLogin(),time()+60*60*24);
}

$APPLICATION->IncludeComponent('crediteurope:terminal_view','form3',
	array(
		'NEED_AUTH' => ($USER->IsAuthorized()) ? 'N' : 'Y',
		'REPORT_1'  => $arResult['REPORT_1'],
		'REPORT_2'  => $arResult['REPORT_2'],
		'REPORT_3'  => $arResult['REPORT_3'],
	),
	$component
);