<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

global $APPLICATION;

$APPLICATION->SetTitle('Банкомат: '.$arResult['NAME'].' - '.$arResult['ADDRESS'].' - МОСКОВСКИЙ КРЕДИТНЫЙ БАНК');
$APPLICATION->SetPageProperty("description",'МОСКОВСКИЙ КРЕДИТНЫЙ БАНК - '.'Банкомат: '.$arResult['NAME'].' - '.$arResult['ADDRESS']);