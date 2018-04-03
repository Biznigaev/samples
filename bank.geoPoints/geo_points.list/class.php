<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

Loc::loadMessages(__FILE__);

class CGeoPointsSearch extends CBitrixComponent
{
	public function executeComponent()
	{






		$this->IncludeComponentTemplate();
	}
}