<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$arResult['REPORT_4'] = (array)json_decode(CTreasuryReports::GetFourthReport(print_r($arParams['OCI_CONNECTION'],1)));
$arResult['REPORT_4'] = reset($arResult['REPORT_4']);
foreach ($arResult['REPORT_4'] as $key => &$val)
{
	$val = (array)$val;
}
$LOGIN = $APPLICATION->get_cookie('LOGIN');
if ($USER->IsAuthorized()
	&& empty($LOGIN))
{
	$APPLICATION->set_cookie('LOGIN',$USER->GetLogin(),time()+60*60*24);
}

$APPLICATION->IncludeComponent('crediteurope:terminal_view','form4',
	array(
		'NEED_AUTH' => ($USER->IsAuthorized()) ? 'N' : 'Y',
		'REPORT_4' => $arResult['REPORT_4'],
	),
	$component
);