<?php
use \Bitrix\Main;
use Bitrix\Main\Loader;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class CGeoPointsXls extends CBitrixComponent
{
	private $iblockId;
	private $typeEnum;
	private $xls;
	public function __construct()
	{
		Loader::includeModule('iblock');
		include_once ($_SERVER["DOCUMENT_ROOT"].'/tools/phpExcel/PHPExcel.php');
		// $objPHPExcel
		$this->xls = PHPExcel_IOFactory::load(dirname(__FILE__).'/template_offices.xls');
		$this->getTypeEnum();
	}
	// выборка инфоблока
	private function getIblockId()
	{
		if (!$this->iblockId)
		{
			$arIblock = CIBlock::getList(array('id'=>'asc'), array(
				'TYPE' => 'structure_bank',
				'CODE' => 'OFFICE_BANK'
			))->Fetch();

			$this->iblockId = $arIblock['ID'];
		}
		return $this->iblockId;
	}
	// выборка типов точек на карте
	private function getTypeEnum()
	{
		if (!$this->typeEnum)
		{
			$dbType = CIBlockPropertyEnum::getList(array(
				'sort' => 'asc',
				'id' => 'asc'
			),array(
				'IBLOCK_ID' => $this->getIblockId(),
				'PROPERTY_ID' => 'TYPE'
			));
			$this->typeEnum = array();
			while ($arType = $dbType->Fetch())
			{
				$this->typeEnum[$arType['EXTERNAL_ID']] = array(
					'ID' => $arType['ID'],
					'NAME' => $arType['VALUE']
				);
			}
		}
		return $this->typeEnum;
	}
	public function executeComponent()
	{
		// работа с xls-библиотекой
		$strDate = 'дата формирования '.date("d.m.Y");
		$arCities = $arCities2 = $arStations = array();

		// выборка офисов/отделений
		$dbElement = CIBlockElement::getList(array(
			'ID' => 'ASC'
		),array(
			'IBLOCK_ID' => $this->getIblockId(),
			'ACTIVE' => 'Y',
			'PROPERTY_TYPE' => $this->typeEnum['office']['ID']
		), false, false, array(
			'ID','NAME',
			'PROPERTY_TYPE',
			'PROPERTY_CITY',
			'PROPERTY_ADRESS',
			'PROPERTY_PHONE',
			'PROPERTY_MODE_WORK',
			'PROPERTY_TYPE_REGION',
			'PROPERTY_STATIONS'
		));

		$this->xls->setActiveSheetIndex(0);
		$aSheet = $this->xls->getActiveSheet();
		$aSheet->setCellValue('A2', $strDate);
		$i = 4;

		while ($arElement = $dbElement->GetNext())
		{
			$bElement = array(
				'NAME' => $arElement['NAME'],
				'ADRESS' => $arElement['PROPERTY_ADRESS_VALUE'],
				'TYPE_REGION' => $arElement['PROPERTY_TYPE_REGION_VALUE'],
				'PHONE' => implode(", ", $arElement['PROPERTY_PHONE_VALUE']),
				'MODE_WORK' => $arElement['PROPERTY_MODE_WORK_VALUE']
			);
			if (!empty($arElement['PROPERTY_CITY_VALUE']))
			{
				if (!array_key_exists($arElement['PROPERTY_CITY_VALUE'], $arCities))
				{
					$dbCity = CIBlockElement::getList(array(
						'ID' => 'ASC'
					),array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'mos_obl',
						'ID' => $arElement['PROPERTY_CITY_VALUE']
					), false, false, array(
						'ID','NAME'
					));
					if ($dbCity->SelectedRowsCount())
					{
						$arCity = $dbCity->Fetch();
						$arCities[$arCity['ID']] = $arCity['NAME'];
						unset($arCity);
					}
					unset($dbCity);
				}
				$bElement['CITY'] = $arCities[$arElement['PROPERTY_CITY_VALUE']];
			}
			if (count($arElement['PROPERTY_STATIONS_VALUE']))
			{
				$bMetro = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					if (!array_key_exists($stationId, $arStations))
					{
						$bMetro[] = $stationId;
					}
				}
				if (count($bMetro))
				{
					$dbStation = CIBlockElement::getList(array(
						'ID' => 'ASC'
					), array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'metro',
						'ID' => $bMetro
					), false, false, array(
						'ID','NAME'
					));
					if ($dbStation->SelectedRowsCount())
					{
						while ($arStation = $dbStation->Fetch())
						{
							$arStations[$arStation['ID']] = $arStation['NAME'];
						}
					}
					unset($dbStation,$bMetro);
				}
				$rsMetroStations = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					$rsMetroStations[] = $arStations[$stationId];
				}
				$bElement['STATIONS'] = implode('/',$rsMetroStations);
				unset($rsMetroStations);
			}

			$aSheet->setCellValueByColumnAndRow(0, $i, str_replace("&quot;", "", iconv('windows-1251', 'utf-8', $bElement['NAME'])));
			$aSheet->setCellValueByColumnAndRow(1, $i, str_replace("&quot;", "'", iconv('windows-1251', 'utf-8', $bElement['ADRESS'])));
			$aSheet->setCellValueByColumnAndRow(2, $i, iconv('windows-1251', 'utf-8', $bElement['TYPE_REGION']));
			$aSheet->setCellValueByColumnAndRow(3, $i, iconv('windows-1251', 'utf-8', $bElement['STATIONS']));
			$aSheet->setCellValueByColumnAndRow(4, $i, iconv('windows-1251', 'utf-8', $bElement['CITY']));
			$aSheet->setCellValueByColumnAndRow(5, $i, iconv('windows-1251', 'utf-8', $bElement['PHONE']));
			$aSheet->setCellValueByColumnAndRow(6, $i, iconv('windows-1251', 'utf-8', $bElement['MODE_WORK']));
			++$i;
			unset($bElement);
		}
		// выборка опер.касс
		$dbElement = CIBlockElement::getList(array(
			'ID' => 'ASC'
		),array(
			'IBLOCK_ID' => $this->getIblockId(),
			'ACTIVE' => 'Y',
			'PROPERTY_TYPE' => $this->typeEnum['operational']['ID']
		), false, false, array(
			'ID','NAME',
			'PROPERTY_TYPE',
			'PROPERTY_CITY',
			'PROPERTY_ADRESS',
			'PROPERTY_PHONE',
			'PROPERTY_MODE_WORK',
			'PROPERTY_TYPE_REGION',
			'PROPERTY_STATIONS'
		));

		$this->xls->setActiveSheetIndex(1);
		$aSheet = $this->xls->getActiveSheet();
		$aSheet->setCellValue('A2', $strDate);
		$i = 4;
		while ($arElement = $dbElement->GetNext())
		{
			$bElement = array(
				'NAME' => $arElement['NAME'],
				'ADRESS' => $arElement['PROPERTY_ADRESS_VALUE'],
				'TYPE_REGION' => $arElement['PROPERTY_TYPE_REGION_VALUE'],
				'PHONE' => implode(", ", $arElement['PROPERTY_PHONE_VALUE']),
				'MODE_WORK' => $arElement['PROPERTY_MODE_WORK_VALUE']
			);
			if (!empty($arElement['PROPERTY_CITY_VALUE']))
			{
				if (!array_key_exists($arElement['PROPERTY_CITY_VALUE'], $arCities))
				{
					$dbCity = CIBlockElement::getList(array(
						'ID' => 'ASC'
					),array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'mos_obl',
						'ID' => $arElement['PROPERTY_CITY_VALUE']
					), false, false, array(
						'ID','NAME'
					));
					if ($dbCity->SelectedRowsCount())
					{
						$arCity = $dbCity->Fetch();
						$arCities[$arCity['ID']] = $arCity['NAME'];
						unset($arCity);
					}
					unset($dbCity);
				}
				$bElement['CITY'] = $arCities[$arElement['PROPERTY_CITY_VALUE']];
			}
			if (count($arElement['PROPERTY_STATIONS_VALUE']))
			{
				$bMetro = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					if (!array_key_exists($stationId, $arStations))
					{
						$bMetro[] = $stationId;
					}
				}
				if (count($bMetro))
				{
					$dbStation = CIBlockElement::getList(array(
						'ID' => 'ASC'
					), array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'metro',
						'ID' => $bMetro
					), false, false, array(
						'ID','NAME'
					));
					if ($dbStation->SelectedRowsCount())
					{
						while ($arStation = $dbStation->Fetch())
						{
							$arStations[$arStation['ID']] = $arStation['NAME'];
						}
					}
					unset($dbStation,$bMetro);
				}
				$rsMetroStations = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					$rsMetroStations[] = $arStations[$stationId];
				}
				$bElement['STATIONS'] = implode('/',$rsMetroStations);
				unset($rsMetroStations);
			}
			$aSheet->setCellValueByColumnAndRow(0, $i, str_replace("&quot;", "", iconv('windows-1251', 'utf-8', $bElement['NAME'])));
			$aSheet->setCellValueByColumnAndRow(1, $i, str_replace("&quot;", "'", iconv('windows-1251', 'utf-8',$bElement['ADRESS'])));
			$aSheet->setCellValueByColumnAndRow(2, $i, iconv('windows-1251', 'utf-8', $bElement['TYPE_REGION']));
			$aSheet->setCellValueByColumnAndRow(3, $i, iconv('windows-1251', 'utf-8', $bElement['STATIONS']));
			$aSheet->setCellValueByColumnAndRow(4, $i, iconv('windows-1251', 'utf-8', $bElement['CITY']));
			$aSheet->setCellValueByColumnAndRow(5, $i, iconv('windows-1251', 'utf-8', $bElement['PHONE']));
			$aSheet->setCellValueByColumnAndRow(6, $i, iconv('windows-1251', 'utf-8', $bElement['MODE_WORK']));
			++$i;
			unset($bElement);
		}
		// выборка банкоматов
		$dbElement = CIBlockElement::getList(array(
			'ID' => 'ASC'
		),array(
			'IBLOCK_ID' => $this->getIblockId(),
			'ACTIVE' => 'Y',
			'PROPERTY_TYPE' => $this->typeEnum['atm']['ID']
		), false, false, array(
			'ID','NAME',
			'PROPERTY_TYPE',
			'PROPERTY_CITY',
			'PROPERTY_ADRESS',
			'PROPERTY_PHONE',
			'PROPERTY_MODE_WORK',
			'PROPERTY_TYPE_REGION',
			'PROPERTY_STATIONS',

			'PROPERTY_CITY2',
			'PROPERTY_IS_LIMITED',
			'PROPERTY_IS_CASH_IN',
			'PROPERTY_CASH_MACHINE_CUR',
			'PROPERTY_CASH_MACHINE_CUR_OUT',
			'PROPERTY_MODE_WORK_BANKOMAT',
			'PROPERTY_WORK_MODE',
			'PROPERTY_MODEL'
		));
		$this->xls->setActiveSheetIndex(2);
		$aSheet = $this->xls->getActiveSheet();
		$aSheet->setCellValue('A2', $strDate);
		$i = 4;
		while ($arElement = $dbElement->GetNext())
		{
			$bElement = array(
				'TYPE' => $arElement["PROPERTY_TYPE_VALUE"],
				'NAME' => $arElement['NAME'],
				'ADRESS' => $arElement['PROPERTY_ADRESS_VALUE'],
				'TYPE_REGION' => $arElement['PROPERTY_TYPE_REGION_VALUE'],
				'PHONE' => implode(", ", $arElement['PROPERTY_PHONE_VALUE']),
				'MODE_WORK' => $arElement['PROPERTY_MODE_WORK_VALUE'],
				'WORK_MODE' => $arElement['PROPERTY_WORK_MODE_VALUE'],
				'MODE_WORK_BANKOMAT' => $arElement['PROPERTY_MODE_WORK_BANKOMAT_VALUE'],
				'MODEL' => str_replace('АТМ', '',$arElement['PROPERTY_MODEL_VALUE']),
				'IS_LIMITED' => $arElement['PROPERTY_IS_LIMITED_VALUE'],
				'IS_CASH_IN' => $arElement['PROPERTY_IS_CASH_IN_VALUE'],
				'CASH_MACHINE_CUR' => implode('/',$arElement['PROPERTY_CASH_MACHINE_CUR_VALUE']),
				'CASH_MACHINE_CUR_OUT' => implode('/',$arElement['PROPERTY_CASH_MACHINE_CUR_OUT_VALUE'])
			);
			if (!empty($arElement['PROPERTY_CITY_VALUE']))
			{
				if (!array_key_exists($arElement['PROPERTY_CITY_VALUE'], $arCities))
				{
					$dbCity = CIBlockElement::getList(array(
						'ID' => 'ASC'
					),array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'mos_obl',
						'ID' => $arElement['PROPERTY_CITY_VALUE']
					), false, false, array(
						'ID','NAME'
					));
					if ($dbCity->SelectedRowsCount())
					{
						$arCity = $dbCity->Fetch();
						$arCities[$arCity['ID']] = $arCity['NAME'];
						unset($arCity);
					}
					unset($dbCity);
				}
				$bElement['CITY'] = $arCities[$arElement['PROPERTY_CITY_VALUE']];
			}
			if (!empty($arElement['PROPERTY_CITY2_VALUE']))
			{
				if (!array_key_exists($arElement['PROPERTY_CITY2_VALUE'], $arCities2))
				{
					$dbCity = CIBlockElement::getList(array(
						'ID' => 'ASC'
					),array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'ts_reg',
						'ID' => $arElement['PROPERTY_CITY2_VALUE']
					), false, false, array(
						'ID','NAME'
					));
					if ($dbCity->SelectedRowsCount())
					{
						$arCity = $dbCity->Fetch();
						$arCities2[$arCity['ID']] = $arCity['NAME'];
						unset($arCity);
					}
					unset($dbCity);
				}
				$bElement['CITY2'] = $arCities2[$arElement['PROPERTY_CITY2_VALUE']];
			}
			if (count($arElement['PROPERTY_STATIONS_VALUE']))
			{
				$bMetro = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					if (!array_key_exists($stationId, $arStations))
					{
						$bMetro[] = $stationId;
					}
				}
				if (count($bMetro))
				{
					$dbStation = CIBlockElement::getList(array(
						'ID' => 'ASC'
					), array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'metro',
						'ID' => $bMetro
					), false, false, array(
						'ID','NAME'
					));
					if ($dbStation->SelectedRowsCount())
					{
						while ($arStation = $dbStation->Fetch())
						{
							$arStations[$arStation['ID']] = $arStation['NAME'];
						}
					}
					unset($dbStation,$bMetro);
				}
				$rsMetroStations = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					$rsMetroStations[] = $arStations[$stationId];
				}
				$bElement['STATIONS'] = implode('/',$rsMetroStations);
				unset($rsMetroStations);
			}

			$aSheet->setCellValueByColumnAndRow(0, $i,  str_replace("&quot;", "'", iconv('windows-1251', 'utf-8', $bElement['NAME'])));
			$aSheet->setCellValueByColumnAndRow(1, $i, iconv('windows-1251', 'utf-8', $bElement['IS_LIMITED']));
			$aSheet->setCellValueByColumnAndRow(2, $i, iconv('windows-1251', 'utf-8', $bElement['IS_CASH_IN']));
			$aSheet->setCellValueByColumnAndRow(3, $i, iconv('windows-1251', 'utf-8', $bElement['CASH_MACHINE_CUR']));
			$aSheet->setCellValueByColumnAndRow(4, $i, iconv('windows-1251', 'utf-8', $bElement['CASH_MACHINE_CUR_OUT']));
			$aSheet->setCellValueByColumnAndRow(5, $i, str_replace("&quot;", "'", iconv('windows-1251', 'utf-8', $bElement['ADRESS'])));
			$aSheet->setCellValueByColumnAndRow(6, $i, iconv('windows-1251', 'utf-8', $bElement['TYPE_REGION']));
			$aSheet->setCellValueByColumnAndRow(7, $i, iconv('windows-1251', 'utf-8', $bElement['STATIONS']));
			$aSheet->setCellValueByColumnAndRow(8, $i, iconv('windows-1251', 'utf-8', $bElement['CITY']));
			$aSheet->setCellValueByColumnAndRow(9, $i, iconv('windows-1251', 'utf-8', $bElement['CITY2']));
			$aSheet->setCellValueByColumnAndRow(10, $i, iconv('windows-1251', 'utf-8', $bElement['MODE_WORK_BANKOMAT']));
			$aSheet->setCellValueByColumnAndRow(11, $i, iconv('windows-1251', 'utf-8', $bElement['WORK_MODE']));
			$aSheet->setCellValueByColumnAndRow(12, $i, iconv('windows-1251', 'utf-8', $bElement['MODEL']));
			
			if ($bElement['IS_LIMITED'])
			{
				for ($j=0;$j<12;$j++)
				{
					$aSheet->getStyleByColumnAndRow($j,$i)->getFont()->getColor()->setARGB('00BFBFBF');
				}
			}
			++$i;
			unset($bElement);
		}
		// терминал
		$dbElement = CIBlockElement::getList(array(
			'ID' => 'ASC'
		),array(
			'IBLOCK_ID' => $this->getIblockId(),
			'ACTIVE' => 'Y',
			'PROPERTY_TYPE' => $this->typeEnum['terminal']['ID']
		), false, false, array(
			'ID','NAME',
			'PROPERTY_TYPE',
			'PROPERTY_CITY',
			'PROPERTY_ADRESS',
			'PROPERTY_PHONE',
			'PROPERTY_MODE_WORK',
			'PROPERTY_TYPE_REGION',
			'PROPERTY_STATIONS',
			'PROPERTY_IS_LIMITED',
			'PROPERTY_CARD_IN_TERM',
			'PROPERTY_CITY2',
			'PROPERTY_WORK_PERIOD_TERM',
			'PROPERTY_WORK_MODE_TERM',
			'PROPERTY_MODEL_TERM'
		));
		$this->xls->setActiveSheetIndex(3);
		$aSheet = $this->xls->getActiveSheet();
		$aSheet->setCellValue('A2', $strDate);
		$i = 4;
		while ($arElement = $dbElement->GetNext())
		{
			$bElement = array(
				'TYPE' => $arElement["PROPERTY_TYPE_VALUE"],
				'NAME' => $arElement['NAME'],
				'ADRESS' => $arElement['PROPERTY_ADRESS_VALUE'],
				'TYPE_REGION' => $arElement['PROPERTY_TYPE_REGION_VALUE'],
				'PHONE' => implode(", ", $arElement['PROPERTY_PHONE_VALUE']),
				'MODE_WORK' => $arElement['PROPERTY_MODE_WORK_VALUE'],
				'IS_LIMITED' => $arElement['PROPERTY_IS_LIMITED_VALUE'],
				'CARD_IN_TERM' => $arElement['PROPERTY_CARD_IN_TERM_VALUE'],
				'WORK_PERIOD_TERM' => $arElement['PROPERTY_WORK_PERIOD_TERM_VALUE'],
				'WORK_MODE_TERM' => $arElement['PROPERTY_MWORK_MODE_TERM_VALUE'],
				'MODEL_TERM' => $arElement['PROPERTY_MODEL_TERM_VALUE']
			);
			if (!empty($arElement['PROPERTY_CITY_VALUE']))
			{
				if (!array_key_exists($arElement['PROPERTY_CITY_VALUE'], $arCities))
				{
					$dbCity = CIBlockElement::getList(array(
						'ID' => 'ASC'
					),array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'mos_obl',
						'ID' => $arElement['PROPERTY_CITY_VALUE']
					), false, false, array(
						'ID','NAME'
					));
					if ($dbCity->SelectedRowsCount())
					{
						$arCity = $dbCity->Fetch();
						$arCities[$arCity['ID']] = $arCity['NAME'];
						unset($arCity);
					}
					unset($dbCity);
				}
				$bElement['CITY'] = $arCities[$arElement['PROPERTY_CITY_VALUE']];
			}
			if (!empty($arElement['PROPERTY_CITY2_VALUE']))
			{
				if (!array_key_exists($arElement['PROPERTY_CITY2_VALUE'], $arCities2))
				{
					$dbCity = CIBlockElement::getList(array(
						'ID' => 'ASC'
					),array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'ts_reg',
						'ID' => $arElement['PROPERTY_CITY2_VALUE']
					), false, false, array(
						'ID','NAME'
					));
					if ($dbCity->SelectedRowsCount())
					{
						$arCity = $dbCity->Fetch();
						$arCities2[$arCity['ID']] = $arCity['NAME'];
						unset($arCity);
					}
					unset($dbCity);
				}
				$bElement['CITY2'] = $arCities2[$arElement['PROPERTY_CITY2_VALUE']];
			}
			if (count($arElement['PROPERTY_STATIONS_VALUE']))
			{
				$bMetro = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					if (!array_key_exists($stationId, $arStations))
					{
						$bMetro[] = $stationId;
					}
				}
				if (count($bMetro))
				{
					$dbStation = CIBlockElement::getList(array(
						'ID' => 'ASC'
					), array(
						'IBLOCK_TYPE' => 'other',
						'IBLOCK_CODE' => 'metro',
						'ID' => $bMetro
					), false, false, array(
						'ID','NAME'
					));
					if ($dbStation->SelectedRowsCount())
					{
						while ($arStation = $dbStation->Fetch())
						{
							$arStations[$arStation['ID']] = $arStation['NAME'];
						}
					}
					unset($dbStation,$bMetro);
				}
				$rsMetroStations = array();
				foreach ($arElement['PROPERTY_STATIONS_VALUE'] as &$stationId)
				{
					$rsMetroStations[] = $arStations[$stationId];
				}
				$bElement['STATIONS'] = implode('/',$rsMetroStations);
				unset($rsMetroStations);
			}

			$aSheet->setCellValueByColumnAndRow(0, $i, str_replace("&quot;", "'", iconv('windows-1251', 'utf-8', $bElement['NAME'])));
			$aSheet->setCellValueByColumnAndRow(1, $i, iconv('windows-1251', 'utf-8', $bElement['IS_LIMITED']));
			$aSheet->setCellValueByColumnAndRow(2, $i, iconv('windows-1251', 'utf-8', $bElement['CARD_IN_TERM']));
			$aSheet->setCellValueByColumnAndRow(3, $i, str_replace("&quot;", "'", iconv('windows-1251', 'utf-8', $bElement['ADRESS'])));
			$aSheet->setCellValueByColumnAndRow(4, $i, iconv('windows-1251', 'utf-8', $bElement['TYPE_REGION']));
			$aSheet->setCellValueByColumnAndRow(5, $i, iconv('windows-1251', 'utf-8', $bElement['STATIONS']));
			$aSheet->setCellValueByColumnAndRow(6, $i, iconv('windows-1251', 'utf-8', $bElement['CITY']));
			$aSheet->setCellValueByColumnAndRow(7, $i, iconv('windows-1251', 'utf-8', $bElement['CITY2']));
			$aSheet->setCellValueByColumnAndRow(8, $i, iconv('windows-1251', 'utf-8', $bElement['WORK_PERIOD_TERM']));
			$aSheet->setCellValueByColumnAndRow(9, $i, iconv('windows-1251', 'utf-8', $bElement['WORK_MODE_TERM']));
			$aSheet->setCellValueByColumnAndRow(10, $i, iconv('windows-1251', 'utf-8', $bElement['MODEL_TERM']));
			
			if ($bElement['IS_LIMITED'])
			{
				for ($j=0;$j<12;$j++)
				{
					$aSheet->getStyleByColumnAndRow($j,$i)->getFont()->getColor()->setARGB('00BFBFBF');
				}
			}
			++$i;
			unset($bElement);
		}
		
		unset($arCities,$arCities2,$arStations);
		if (file_exists($_SERVER['DOCUMENT_ROOT'].'/about_bank/address/all_offices.xls'))
		{
			unlink($_SERVER['DOCUMENT_ROOT'].'/about_bank/address/all_offices.xls');
		}
		// сохранение файла
		$objWriter = new PHPExcel_Writer_Excel5($this->xls);
		$objWriter->save($_SERVER['DOCUMENT_ROOT'].'/about_bank/address/all_offices.xls');
	}
}