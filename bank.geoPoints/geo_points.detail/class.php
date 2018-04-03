<?
use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Application; 

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)die();

if (!\Bitrix\Main\Loader::includeModule('iblock'))
{
	return;
}
Loc::loadMessages(__FILE__);
/**
 * @todo: Отдельный шаблон для печати (?PRINT=Y)
 */

class CGeoPointsDetail extends CBitrixComponent
{
	private $metroIblockId = 60;
	private $arSelect = array(
		'ID',
		'NAME',
		'PROPERTY_ADRESS',
		'PROPERTY_STATIONS',
		'PROPERTY_GPS',
		'PROPERTY_PROEZD',
		'PROPERTY_SPEC_WORK_FROM','PROPERTY_SPEC_WORK_TO','PROPERTY_SPEC_WORK_WHY',
		'PROPERTY_SERVICES_FIZ','PROPERTY_SERVICES_UR',
	);
	public function onPrepareComponentParams(&$arParams)
	{
		if (!isset($arParams['CACHE_TIME']))
		{
			$arParams["CACHE_TIME"] = 86400;
		}
		return $arParams;
	}
	// инициализация точки на карте
	private function initPoint()
	{
		$arFields = $this->getPointData();
		switch ($this->arParams['POINT_TYPE'])
		{
			// офис продаж
			case 'office':
			{
				$this->prepareOffice($arFields);
				break;
			}
			case 'atm':
			{
				$this->prepareAtm($arFields);
				break;
			}
			case 'terminal':
			{
				$this->prepareTerminal($arFields);
				break;
			}
			case 'operational':
			{
				$this->prepareOperational($arFields);
				break;
			}
		}
		$this->arResult['NAME'] = $arFields['NAME'];
		$this->arResult['ADDRESS'] = $arFields['PROPERTY_ADRESS_VALUE'];	
		// координаты текущей точки на карте
		$this->arResult['COORDS'] = array(
			'lat' => 0,
			'lon' => 0
		);
		list(
			$this->arResult['COORDS']['lon'],
			$this->arResult['COORDS']['lat']
		) = explode(',', 
			$arFields['PROPERTY_GPS_VALUE']
		);
		// приближенность к ст.м.
		if (count($arFields['PROPERTY_STATIONS_VALUE']))
		{
			$this->arResult['METRO'] = $this->getMetroList( $arFields['PROPERTY_STATIONS_VALUE'] );
		}
		// загрузка ближайшик точек на карте
		CBitrixComponent::IncludeComponentClass('mkb:geo_points.detail.nearest');
		$obPoints = new CGeoPointsNearest();
		$obPoints->setState(
			$this->arParams['POINT_ID'],
			$this->arParams['IBLOCK_ID'],
			$this->arParams['DETAIL_URL_TEMPLATE']
		);
		$this->arResult['NEAREST_POINTS'] = &$obPoints->executeComponent(false);
		// описание проезда (как добраться?)
		$this->arResult['PROEZD'] = $arFields['PROPERTY_PROEZD_VALUE']['TEXT'];
		// спец. режим работы
		if (!empty($arFields['PROPERTY_SPEC_WORK_FROM_VALUE'])
			&& !empty($arFields['PROPERTY_SPEC_WORK_TO_VALUE'])
			&& !empty($arFields['PROPERTY_SPEC_WORK_WHY_VALUE']))
		{
			if (strtotime($arFields['PROPERTY_SPEC_WORK_FROM_VALUE']) >= time()
				&& strtotime($arFields['PROPERTY_SPEC_WORK_TO_VALUE']) <= time())
			{
				$this->arResult['SPECIAL_WORK_MODE'] = $arFields['PROPERTY_SPEC_WORK_WHY_VALUE']['TEXT'];
			}
		}
		// доступные услуги для физ.лиц
		if (count($arFields['PROPERTY_SERVICES_FIZ_VALUE']))
		{
			$this->arResult['SERVICES']['FIZ'] = array_values($arFields['PROPERTY_SERVICES_FIZ_VALUE']);
		}
		// доступные услуги для юр.лиц
		if (count($arFields['PROPERTY_SERVICES_UR_VALUE']))
		{
			$this->arResult['SERVICES']['UR'] = array_values($arFields['PROPERTY_SERVICES_UR_VALUE']);
		}
	}
	// обработка полученных полей для точки на карте типа - Офис
	private function prepareOffice(&$arFields)
	{
		// добавление фотографии отделения
		if (count($arFields['PROPERTY_OTD_PHOTO_VALUE']))
		{
			$this->arResult['PHOTO'] = array();
			foreach ($arFields['PROPERTY_OTD_PHOTO_VALUE'] as $fileID)
			{
				$arFile = CFile::GetByID($fileID)->Fetch();
				$this->arResult['PHOTO'][] = array(
					'WIDTH' => $arFile['WIDTH'],
					'HEIGHT' => $arFile['HEIGHT'],
					'SRC' => CFile::GetPath($fileID)
				);
				unset($arFile);
			}
		}
		// режим работы
		$this->arResult['WORK_MODE'] = $arFields['PROPERTY_MODE_WORK_VALUE'];
		// телефоны
		$this->arResult['PHONE'] = $arFields['PROPERTY_PHONE_VALUE'];
		// услуги для физ./юр. лиц
		$this->arResult['SERVICES'] = array();
		// наличие сейфового хранилища в офисе
		if (!empty($arFields['PROPERTY_IS_SAFE_ENUM_ID']))
		{
			// режим работы сейфовой ячейки
			if (!empty($arFields['PROPERTY_WORK_MODE_DEPOSIT_VALUE']['TEXT']))
			{
				$this->arResult['SAFE_WORK_MODE'] = $arFields['PROPERTY_WORK_MODE_DEPOSIT_VALUE']['TEXT'];
			}
		}
		// высление загруженности отделения
		CBitrixComponent::IncludeComponentClass('mkb:geo_points.detail.office_load');
		$obLoad = new CGeoPointsLoad();
		$obLoad->setState(
			$this->arParams['ID'],
			$this->arParams['IBLOCK_ID']
		);
		$this->arResult['LOAD'] = $obLoad->executeComponent();
	}
	// обработка полученных полей для точки на карте типа - Терминал
	private function prepareTerminal(&$arFields)
	{
		$this->arResult['WORK_MODE'] = $arFields['PROPERTY_WORK_PERIOD_TERM_VALUE'];
		// Терминал с приемом карт
		$this->arResult['CARD_IN_TERM'] = !empty($arFields['PROPERTY_CARD_IN_TERM_ENUM_ID']);
		// Платежи без комиссий в терминале
		$this->arResult['COMISSION'] = !empty($arFields['PROPERTY_COMISSION_ENUM_ID']);
	}
	// обработка полученных полей для точки на карте типа - Оперкасса
	private function prepareOperational(&$arFields)
	{
		// режим работы
		$this->arResult['WORK_MODE'] = $arFields['PROPERTY_MODE_WORK_VALUE'];
		// телефоны
		$this->arResult['PHONE'] = $arFields['PROPERTY_PHONE_VALUE'];
	}
	// обработка полученных полей для точки на карте типа - Банкомат
	private function prepareAtm(&$arFields)
	{
		$this->arResult['WORK_MODE'] = $arFields['PROPERTY_MODE_WORK_BANKOMAT_VALUE'];
		// валюта выдачи
		if (is_array($arFields['PROPERTY_CASH_MACHINE_CUR_VALUE'])
			&& count($arFields['PROPERTY_CASH_MACHINE_CUR_VALUE']))
		{
			$this->arResult['CASH_OUT'] = array_values($arFields['PROPERTY_CASH_MACHINE_CUR_VALUE']);
		}
		// валюта приёма
		if (is_array($arFields['PROPERTY_CASH_MACHINE_CUR_OUT_VALUE'])
			&& count($arFields['PROPERTY_CASH_MACHINE_CUR_OUT_VALUE']))
		{
			$this->arResult['CASH_IN'] = array_values($arFields['PROPERTY_CASH_MACHINE_CUR_OUT_VALUE']);
		}
	}
	// получить список станций/линий метро по списку станций текущей точки
	private function getMetroList(&$metroList)
	{
		$arMetroList = array();

		$dbRes = CIBlockElement::GetList(false, array(
			'IBLOCK_TYPE' => 'other',
			'IBLOCK_CODE' => 'metro',
			'ID' => $metroList
		),false, false, array(
			'ID','NAME','IBLOCK_SECTION_ID'
		));
		while ($arStation = $dbRes->Fetch())
		{
			$arMetroList[$arStation['IBLOCK_SECTION_ID']]['STATIONS'][$arStation['ID']] = $arStation['NAME'];
		}
		$dbRes = CIBlockSection::GetList(array(
			'ID' => 'ASC'
		), array(
			'IBLOCK_TYPE' => 'other',
			'IBLOCK_ID' => $this->metroIblockId,
			'ID' => array_keys($arMetroList)
		), false, array(
			'ID', 'NAME', 'UF_LINE_CODE'
		));
		while ($arLine = $dbRes->Fetch())
		{
			$arMetroList[$arLine['ID']]['CODE'] = $arLine['UF_LINE_CODE'];
			$arMetroList[$arLine['ID']]['NAME'] = $arLine['NAME'];
		}
		return $arMetroList;
	}
	// получить точку на карте из справочника БД
	private function getPointData()
	{
		return CIBlockElement::GetList(array(
			'ID' => 'ASC'
		), array(
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'ID' => $this->arParams['POINT_ID']
		), false, false, $this->prepareSelectFields())->Fetch();
	}
	protected function extractDataFromCache()
	{
		if ($this->arParams['CACHE_TYPE'] == 'N')
		{
			return false;
		}
		return !($this->StartResultCache(false, ($this->arParams['TYPE'].$this->arParams['ID'])));
	}
	// формирование списка полей выборки точки продаж
	protected function prepareSelectFields()
	{
		$arLocalFields = array();
		switch ($this->arParams['POINT_TYPE'])
		{
			// офис продаж
			case 'office':
			{
				$arLocalFields = array(
					'PROPERTY_OTD_PHOTO',
					'PROPERTY_PHONE',
					'PROPERTY_MODE_WORK',
					'PROPERTY_IS_SAFE',
					'PROPERTY_WORK_MODE_DEPOSIT'
				);
				break;
			}
			// банкомат
			case 'atm':
			{
				$arLocalFields = array(
					'PROPERTY_MODE_WORK_BANKOMAT',
					'PROPERTY_CASH_MACHINE_CUR',
					'PROPERTY_CASH_MACHINE_CUR_OUT'
				);
				break;
			}
			// терминал
			case 'terminal':
			{
				$arLocalFields = array(
					'PROPERTY_WORK_PERIOD_TERM',
					'PROPERTY_CARD_IN_TERM',
					'PROPERTY_COMISSION'
				);
				break;
			}
			// оперкасса
			case 'operational':
			{
				$arLocalFields = array(
					'PROPERTY_MODE_WORK',
					'PROPERTY_PHONE'
				);
				break;
			}
		}
		return array_merge($this->arSelect, $arLocalFields);
	}
	public function executeComponent()
	{
		// инициализация точки на карте
		if (!$this->extractDataFromCache())
		{
			$this->initPoint();
			$this->setResultCacheKeys(array_keys($this->arResult));
			$this->includeComponentTemplate();
			$this->endResultCache();
		}
	}
}