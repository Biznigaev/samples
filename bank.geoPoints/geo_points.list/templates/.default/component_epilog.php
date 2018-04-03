<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();

CJSCore::Init();
$APPLICATION->AddHeadScript('//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false');
$APPLICATION->AddHeadScript('/about_bank/address/poi_data/js/markerclusterer.js');