<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main;
use Bitrix\Main\Loader;

Loader::includeModule('iblock');
/**
 * @todo: Добавить кэширование - сейчас он на уровне компонента детальной страницы точки продаж
 */
class CGeoPointsNearest extends CBitrixComponent
{
	private $coords;
	private $distance = 500;

	public function setState($pointFrom, $iblockId, $strTemplate)
	{
		$this->arParams['ID'] = $pointFrom;
		$this->arParams['IBLOCK_ID'] = $iblockId;
		$this->arParams['DETAIL_URL_TEMPLATE'] = $strTemplate;

		// получение координат по текущей точке на карте
		$dbRes = CIBlockElement::GetList(false, array(
			'IBLOCK_ID' => $iblockId,
			'ID' => $pointFrom,
			'ACTIVE' => 'Y',
		), false, false, array('ID','PROPERTY_GPS'))->Fetch();
		$gps = array(
			'lat' => 0,
			'lon' => 0
		);
		list($gps['lon'], $gps['lat']) = explode(',', $dbRes['PROPERTY_GPS_VALUE']);
		$this->coords = $gps;
	}
	protected function getPointTypes()
	{
		// получение XML_ID типов точек на карте
		$dbRes = CIBlockPropertyEnum::GetList(array(
			'sort' => 'asc',
			'id' => 'asc'
		), array(
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'PROPERTY_ID' => 'TYPE'
		));
		$arTypes = array();
		while ($arEnum = $dbRes->Fetch())
		{
			$arTypes[$arEnum['ID']] = $arEnum['XML_ID'];
		}
		return $arTypes;
	}
	// получение списка точек для сравнения
	protected function &detectNearestPoints()
	{
		$dbRes = CIBlockElement::GetList(array(
			'ID' => 'ASC'
		), array(
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'!ID' => $this->arParams['ID'],
			'ACTIVE' => 'Y',
		), false, false, array('ID','PROPERTY_GPS'));
		$arDistance = array();
		while ($arPoint = $dbRes->Fetch())
		{
			$gps = array(
				'lat' => 0,
				'lon' => 0
			);
			list(
				$gps['lon'],
				$gps['lat']
			) = explode(',', $arPoint['PROPERTY_GPS_VALUE']);
			$distance = $this->getDistance($this->coords, $gps);
			if ($distance < $this->distance)
			{
				$arDistance[$arPoint['ID']] = $distance;
			}
		}

		return $arDistance;
	}
	// формирование детальной ссылки из шаблона
	protected function getDetailUrl($pointID)
	{
		return str_replace('#POINT_ID#', $pointID, $this->arParams['DETAIL_URL_TEMPLATE']);
	}
	// вычисление дистанции в метрах
	public function getDistance($point1, $point2)
	{
		$theta = $point1['lon'] - $point2['lon'];
		$dist = sin(deg2rad($point1['lat'])) * sin(deg2rad($point2['lat'])) + cos(deg2rad($point1['lat'])) * cos(deg2rad($point2['lat'])) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;

		return ceil(($miles * 1.609344)*1000);
	}
	/**
	 * Получить ближайшие точки с разбивкой по типам точек на карте
	 * @param $f_includeTemplate bool отрабатьвать ли шабон компонента или просто отдать результирующий массив
	 */
	public function executeComponent($f_includeTemplate=true)
	{
		// получение XML_ID типов точек на карте
		$arTypes = $this->getPointTypes();
		// получение списка точек для сравнения
		$this->arResult['DISTANCE'] = $this->detectNearestPoints();
		// получение подробной инф-ии по найденным ближайшим точкам продаж
		if (count($this->arResult['DISTANCE']))
		{
			$this->arResult['POINTS'] = array();
			$dbRes = CIBlockElement::GetList(array(
				'NAME' => 'ASC',
				'PROPERT_TYPE' => 'ASC'
			), array(
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
				'ID' => array_keys($this->arResult['DISTANCE']),
				'ACTIVE' => 'Y',
			), false, false, array('ID','NAME','PROPERTY_ADRESS','PROPERTY_TYPE'));
			while ($arPoint = $dbRes->Fetch())
			{
				$this->arResult['POINTS'][$arTypes[$arPoint['PROPERTY_TYPE_ENUM_ID']]][$arPoint['ID']] = array(
					'LINK' => $this->getDetailUrl($arPoint['ID']),
					'NAME' => $arPoint['NAME'],
					'ADDRESS' => $arPoint['PROPERTY_ADRESS_VALUE']
				);
			}
		}
		if ($f_includeTemplate)
		{
			$this->includeComponentTemplate();
		}
		else
		{
			asort($this->arResult['DISTANCE']);
			
			return $this->arResult;
		}
	}
}