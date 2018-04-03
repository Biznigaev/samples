<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

Loc::loadMessages(__FILE__);

class CGeoPointsSearch extends CBitrixComponent
{
	/**
	 *    @todo: Проинтегрировать кэширование в виде медотов класса
	 */
	public function executeComponent()
	{
		//    маппинг входных параметров
		$arType = array(
			'atm' => 461,
			'terminal' => 462,
			'operating' => 460,
			'branch' => 459
		);
		//    маппинг сервисов
		$arServices = array(
			'branch' => array('fiz','ur','rent-safe'),
			'atm' => array('dayandnight','cashout-rur','cashin'),
			'terminal' => array('dayandnight','accepts_cards','card_troika'),
			'operating' => array()
		);
		//    маппинг представления
		$arView = array(
			'gmap','metro','list'
		);
		if ((!in_array($_REQUEST['view'],$arView) && empty($_REQUEST['id']))
			|| !array_key_exists($_REQUEST['type'],$arType) 
			|| !check_bitrix_sessid())
		{
			die();
		}
		if (array_key_exists('services',$_REQUEST))
		{
			foreach ($_REQUEST['services'] as $strService)
			{
				if (!in_array($strService, $arServices[$_REQUEST['type']]))
				{
					die();
				}
			}
		}
		$f_metro = $_REQUEST['view'] == 'metro';
		//    создание объекта кэша
		$obCache = new CPHPCache;
		$ttl = 86400;
		$cache_id = $_REQUEST['type'].'|'.implode('.',$_REQUEST['services']).'|'.$_REQUEST['view'];
		$cache_path = '/gmaps.point.list/';

		if (!empty($_REQUEST['id']))
		{
			//    валидация ввода id-отделений
			if (!is_numeric($_REQUEST['id']))
			{
				if (!is_array($_REQUEST['id']))
				{
					die();
				}
				else
				{
					foreach ($_REQUEST['id'] as $id)
					{
						if (!is_numeric($id))
						{
							die();
						}
					}
					$cache_id .= '|'.implode('',$_REQUEST['id']);
				}
			}
			else
			{
				$cache_id .= '|'.$_REQUEST['id'];
			}
		}
		//    валидация строки ввода
		$strRequestQuery = '';
		if (!empty($_REQUEST['query']))
		{
			$strRequestQuery = mb_strtolower(strip_tags($_REQUEST['query']));
			$cache_id .= '|'.$strRequestQuery;
			$f_metro = true;
		}
		if ($obCache->InitCache($ttl,$cache_id,$cache_path))
		{
			// получаем закешированные переменные
			$arVars = $obCache->GetVars();
			$arResult = $arVars['CONTENT'];
		}
		else
		{
			// иначе обращаемся к базе
			CModule::includeModule('iblock');
			// CModule::IncludeModule('iblock');
			$db_res = CIBlock::GetList(false,array(
				'TYPE' => 'structure_bank',
				'CODE' => 'OFFICE_BANK'
			))->Fetch();
			$IBLOCK_ID = $db_res['ID'];
			unset($db_res);
			//    получение справочника типов точек
			$db_enum = CIBlockPropertyEnum::GetList(array(
				'sort' => 'asc',
				'value' => 'asc'
			), array(
				'PROPERTY_ID' => 'TYPE',
				'IBLOCK_ID' => $IBLOCK_ID
			));
			$arProps = array();
			while ($item = $db_enum->Fetch())
			{
				$arProps[$item['ID']] = $item['VALUE'];
			}
			unset($db_enum);
			//    получение первичного списка точек для карты
			$arOrder = array(
				'ID' => 'ASC'
			);
			$arFilter = array(
				'IBLOCK_ID' => $IBLOCK_ID,
				'ACTIVE' => 'Y',
				'PROPERTY_TYPE_VALUE' => $arProps[$arType[$_REQUEST['type']]],
				'PROPERTY_IS_LIMITED_VALUE' => false
			);
			$arSelect = array('ID','NAME','PROPERTY_GPS','PROPERTY_ADRESS');
			if ($_REQUEST['type'] == 'branch')
			{
				$arSelect[] = 'PROPERTY_SERVICES_FIZ';
				$arSelect[] = 'PROPERTY_SERVICES_UR';
			}
			elseif ($_REQUEST['type'] == 'atm')
			{
				$arSelect[] = 'PROPERTY_WORK_MODE';/* 24ч */
				$arSelect[] = 'PROPERTY_MODE_WORK_BANKOMAT';
				$arSelect[] = 'PROPERTY_CASH_MACHINE_CUR';
				$arSelect[] = 'PROPERTY_CASH_MACHINE_CUR_OUT';
				$arSelect[] = 'PROPERTY_IS_CASH_IN';
			}
			elseif ($_REQUEST['type'] == 'terminal')
			{
				$arSelect[] = 'PROPERTY_WORK_MODE_TERM';
				$arSelect[] = 'PROPERTY_WORK_PERIOD_TERM';
				$arSelect[] = 'PROPERTY_CARD_IN_TERM';
				$arSelect[] = 'PROPERTY_CARD_TROYKA';
			}
			if (!empty($_REQUEST['id']))
			{
				$arFilter['ID'] = $_REQUEST['id'];
				$arSelect = array(
					'branch' => array('PROPERTY_PROEZD','PROPERTY_MODE_WORK'/*,'PROPERTY_TYPE_REGION'*/),
					'atm' => array('PROPERTY_WORK_MODE','PROPERTY_MODE_WORK_BANKOMAT'),
					'terminal' => array('PROPERTY_WORK_MODE_TERM','PROPERTY_WORK_PERIOD_TERM'),
					'operating' => array('PROPERTY_ADRESS','PROPERTY_MODE_WORK')
				);
				$arSelect = array_merge(
					array('ID','PROPERTY_STATIONS'),
					$arSelect[$_REQUEST['type']]
				);
			}
			if ($f_metro)
			{
				if (!in_array('PROPERTY_STATIONS', $arSelect))
				{
					$arSelect[] = 'PROPERTY_STATIONS';
				}
				if (!in_array('PROPERTY_ADRESS', $arSelect))
				{
					$arSelect[] = 'PROPERTY_ADRESS';
				}
			}
			$arResult = array();
			//    Exec
			$db_res = CIBlockElement::GetList($arOrder,$arFilter,false,false,$arSelect);
			//    если кол-во результатов = 0, то отменить кэширование текущего результата
			if ($db_res->SelectedRowsCount())
			{
				//     первичная загрузка всех точек данного типа
				if (empty($_REQUEST['id']))
				{
					$rent_safe = false;
					if ($_REQUEST['type'] == 'branch')
					{
						//    идентификтор св-ва "Аренда сейфовой ячейки"
						$db_enum = CIBlockPropertyEnum::GetList(array(
							'id' => 'asc'
						), array(
							'IBLOCK_ID' => $IBLOCK_ID,
							'PROPERTY_ID' => 'SERVICES_FIZ',
							'EXTERNAL_ID' => 894
						));
						if ($db_enum->SelectedRowsCount())
						{
							$enum = $db_enum->Fetch();
							$rent_safe = $enum['ID'];
						}
						unset($db_enum);
					}
					$arStation = array();
					//    получение первичного списка терминалов для карты
					while ($item = $db_res->Fetch())
					{
						list($lon,$lat) = explode(',',trim($item['PROPERTY_GPS_VALUE']));
						$f_exists = false;
						//    фильтр по checkbox-ам
						if (is_array($_REQUEST['services']))
						{
							//    отделения
							if ($_REQUEST['type'] == 'branch')
							{
								if (in_array('fiz', $_REQUEST['services'])
									&& !count($item['PROPERTY_SERVICES_FIZ_VALUE']))
								{
									continue;
								}
								if (in_array('ur', $_REQUEST['services'])
									&& !count($item['PROPERTY_SERVICES_UR_VALUE']))
								{
									continue;
								}
								if (in_array('rent-safe', $_REQUEST['services'])
									&& !array_key_exists($rent_safe, $item['PROPERTY_SERVICES_FIZ_VALUE']))
								{
									continue;
								}
							}
							//    банкоматы
							elseif ($_REQUEST['type'] == 'atm')
							{
								if (in_array('dayandnight', $_REQUEST['services'])
									&& empty($item['PROPERTY_WORK_MODE_VALUE']))
								{
									continue;
								}
								if (in_array('cashout-rur', $_REQUEST['services'])
									&& !in_array('RUR',$item['PROPERTY_CASH_MACHINE_CUR_VALUE']))
								{
									continue;
								}
								if (in_array('cashin', $_REQUEST['services'])
									&& empty($item['PROPERTY_IS_CASH_IN_VALUE']))
								{
									continue;
								}
							}
							//    терминалы
							elseif ($_REQUEST['type'] == 'terminal')
							{
								if (in_array('dayandnight', $_REQUEST['services'])
									&& empty($item['PROPERTY_WORK_MODE_TERM_ENUM_ID']))
								{
									continue;
								}
								if (in_array('accepts_cards', $_REQUEST['services'])
									&& empty($item['PROPERTY_CARD_IN_TERM_ENUM_ID']))
								{
									continue;
								}
								if (in_array('card_troika', $_REQUEST['services'])
									&& empty($item['PROPERTY_CARD_TROYKA_ENUM_ID']))
								{
									continue;
								}
							}
						}
						//    поиск по подстроке
						if (!empty($strRequestQuery))
						{
							//    поиск по переданному значению строки поиска
							$f_exists = mb_strpos(mb_strtolower($item['NAME']), $strRequestQuery) !== false;
							if (!$f_exists)
							{
								//    поиск по адресу
								$f_exists = mb_strpos($item['PROPERTY_ADRESS_VALUE'], $strRequestQuery) !== false;
								//    поиск по станциям метро
								if (!$f_exists
									&& count($item['PROPERTY_STATIONS_VALUE']))
								{
									$dbRes = CIBlockElement::GetList(array(
										'NAME' => 'ASC',
										'ID' => 'ASC'
									),array(
										'IBLOCK_TYPE' => 'other',
										'IBLOCK_CODE' => 'metro',
										'ACTIVE' => 'Y',
										'ID' => $item['PROPERTY_STATIONS_VALUE']
									),false,false,array('ID','NAME','IBLOCK_SECTION_ID','IBLOCK_ID'));
									while ($station = $dbRes->Fetch())
									{
										if (mb_strpos(mb_strtolower($station['NAME']), $strRequestQuery) !== false)
										{
											$f_exists = true;
											break;
										}
									}
								}
								if ($_REQUEST['type'] == 'branch')
								{
									//    поиск по услугам физ.лиц
									if (!$f_exists
										&& in_array('PROPERTY_SERVICES_FIZ', $arSelect)
										&& count($item['PROPERTY_SERVICES_FIZ_VALUE']))
									{
										foreach ($item['PROPERTY_SERVICES_FIZ_VALUE'] as $prop_id => $prop_value)
										{
											if (mb_strpos(mb_strtolower($prop_value), $strRequestQuery) !== false)
											{
												$f_exists = true;
												break;
											}
										}
									}
									//    поиск по услугам юр.лиц
									if (!$f_exists
										&& in_array('PROPERTY_SERVICES_UR', $arSelect)
										&& count($item['PROPERTY_SERVICES_UR_VALUE']))
									{
										foreach ($item['PROPERTY_SERVICES_UR_VALUE'] as $prop_id => $prop_value)
										{
											if (mb_strpos(mb_strtolower($prop_value), $strRequestQuery) !== false)
											{
												$f_exists = true;
												break;
											}
										}
									}
								}
							}
						}
						else
						{
							$f_exists = true;
						}
						//    добавление в результирующий список
						if ($f_exists)
						{
							$arItem = array(
								'id' => $item['ID'],
								'coords' => array(
									'lat' => $lat,
									'lon' => $lon
								),
								'name' => mb_convert_encoding($item['NAME'],'UTF-8','windows-1251'),
								'addr' => mb_convert_encoding($item['PROPERTY_ADRESS_VALUE'],'UTF-8','windows-1251')
							);
							if ($f_metro && count($item['PROPERTY_STATIONS_VALUE']))
							{
								foreach ($item['PROPERTY_STATIONS_VALUE'] as &$station_id)
								{
									$arStation[$station_id]['points'][] = $item['ID'];
								}
							}
							$arResult[] = $arItem;
							unset($arItem);
						}
					}
					if (count($arStation))
					{
						$db_res = CIBlockElement::GetList(array(
							'NAME'=>'ASC',
							'ID'=>'ASC'
						),array(
							'IBLOCK_TYPE' => 'other',
							'IBLOCK_CODE' => 'metro',
							'ACTIVE' => 'Y',
							'ID' => array_keys($arStation)
						),false,false,array(
							'ID','NAME',
							'PROPERTY_WIDTH',
							'PROPERTY_HEIGHT',
							'PROPERTY_LEFT',
							'PROPERTY_TOP',
						));
						while ($item = $db_res->Fetch())
						{
							$arStation[$item['ID']]['id'] = $item['ID'];
							$arStation[$item['ID']]['name'] = mb_convert_encoding($item['NAME'],'UTF-8','windows-1251');
							$arStation[$item['ID']]['width'] = (int)$item['PROPERTY_WIDTH_VALUE'];
							$arStation[$item['ID']]['height'] = (int)$item['PROPERTY_HEIGHT_VALUE'];
							$arStation[$item['ID']]['left'] = (int)$item['PROPERTY_LEFT_VALUE'];
							$arStation[$item['ID']]['top'] = (int)$item['PROPERTY_TOP_VALUE'];

							if ($_REQUEST['type'] == 'branch')
							{
								$arStation[$item['ID']]['workingtime'] = '';
							}
							elseif ($_REQUEST['type'] == 'atm')
							{
								$arStation[$item['ID']]['workingtime'] = '';
							}
							elseif ($_REQUEST['type'] == 'terminal')
							{
								$arStation[$item['ID']]['workingtime'] = '';
							}
						}
						$arResult = array(
							'points' => $arResult,
							'metro' => $arStation
						);
					}
				}
				//    загрузка точек по id
				else
				{
					while ($item = $db_res->Fetch())
					{
						if (count($item['PROPERTY_STATIONS_VALUE']))
						{
							$dbRes = CIBlockElement::GetList(array(
								'NAME'=>'ASC',
								'ID'=>'ASC'
							),array(
								'IBLOCK_TYPE' => 'other',
								'IBLOCK_CODE' => 'metro',
								'ACTIVE' => 'Y',
								'ID' => $item['PROPERTY_STATIONS_VALUE']
							),false,false,array('ID','NAME','IBLOCK_SECTION_ID','IBLOCK_ID'));
							$item['PROPERTY_STATIONS_VALUE'] = array();
							$arStation = array();
							$metro_iblock_id = 0;
							while ($station = $dbRes->Fetch())
							{
								$metro_iblock_id = $station['IBLOCK_ID'];
								$arStation[$station['IBLOCK_SECTION_ID']][$station['ID']] = mb_convert_encoding($station['NAME'],'UTF-8','windows-1251');
							}
							$dbRes = CIBlockSection::GetList(array('ID'=>'ASC'),array(
								'IBLOCK_ID' => $metro_iblock_id,
								'ID' => array_keys($arStation)
							),false,array(
								'ID','UF_LINE_CODE'
							));
							while ($line = $dbRes->Fetch())
							{
								foreach ($arStation[$line['ID']] as $id => &$name)
								{
									$item['PROPERTY_STATIONS_VALUE'][] = array(
										'line' => $line['UF_LINE_CODE'],
										'name' => /*mb_convert_encoding(*/$name/*,'UTF-8','windows-1251')*/
									);
								}
							}
						}
						$arItem = array(
							'metro' => $item['PROPERTY_STATIONS_VALUE']
						);
						if ($_REQUEST['type'] == 'branch')
						{
							//    как добраться
							$arItem['how_to_get'] = mb_convert_encoding($item['PROPERTY_PROEZD_VALUE']['TEXT'],'UTF-8','windows-1251');			
							$arItem['workingtime'] = mb_convert_encoding($item['PROPERTY_MODE_WORK_VALUE'],'UTF-8','windows-1251');
						}
						elseif ($_REQUEST['type'] == 'atm')
						{
							$arItem['workingtime'] = mb_convert_encoding($item['PROPERTY_MODE_WORK_BANKOMAT_VALUE'],'UTF-8','windows-1251');
							$arItem['dayandnight'] = !empty($item['PROPERTY_WORK_MODE_VALUE']);
						}
						elseif ($_REQUEST['type'] == 'terminal')
						{
							$arItem['workingtime'] = mb_convert_encoding($item['PROPERTY_WORK_PERIOD_TERM_VALUE'],'UTF-8','windows-1251');
							$arItem['dayandnight'] = !empty($item['PROPERTY_WORK_MODE_TERM_VALUE'])? '1' : '0';
						}
						elseif ($_REQUEST['type'] == 'operating')
						{
							$arItem['workingtime'] = mb_convert_encoding($item['PROPERTY_MODE_WORK_VALUE'],'UTF-8','windows-1251');
						}
						$arItem['id'] = $item['ID'];
						$arResult[] = $arItem;
					}
				}
				// начинаем буферизирование вывода
				$obCache->StartDataCache($ttl,$cache_id,$cache_path);
				$obCache->EndDataCache(array(
					'CONTENT' => $arResult
				));
			}
			unset($db_res);
		}
		//    вывод
		header('Content-type: application/json; charset=windows-1251');
		echo (
			JSON_UNESCAPED_UNICODE(
				json_encode($arResult)
			)
		);
		die();
	}
}