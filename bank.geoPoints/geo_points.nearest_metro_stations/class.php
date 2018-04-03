<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)die();
/**
 * @todo: настройка кэширования
 */
use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Application; 

if (!\Bitrix\Main\Loader::includeModule('iblock'))
{
	return;
}
Loc::loadMessages(__FILE__);
// подключение библиотеки геолокации
if (!function_exists('GetDistance'))
{
	include_once $_SERVER['DOCUMENT_ROOT'].'/local/php_interface/include/geocode.php';
}

class CGeoPointsNearestMetroStations extends CBitrixComponent
{
	private $longitude,
			$latitude,
			$iblockID;
	public function __construct($lat, $lon)
	{
		$this->longitude = $lon;
		$this->latitude = $lat;
		$this->iblockID = $this->getIblockID();
	}
	protected function getIblockID()
	{
		// получение ID справочника станций метро		
		$arIblockMetro = CIBlock::getList(array(
			'ID'=>'ASC'
		),array(
			'TYPE' => 'other',
			'CODE' => 'metro'
		))->Fetch();
		return $arIblockMetro['ID'];
	}
	protected function getMetroStations()
	{
		// получение станций метро
		$db_res = CIBlockElement::getList(false,array(
			'IBLOCK_ID' => $this->iblockId,
			// станции метро, у которых проставлены координаты
			'!=PROPERTY_COORDS' => false
		), false, false, array(
			'ID','PROPERTY_COORDS'
		));
		$arStations = array();
		while ($station = $db_res->Fetch())
		{
			$arStations[$station['ID']] = array(
				'lat' => 0,
				'lon' => 0
			);
			list(
				$arStations[$station['ID']]['lat'],
				$arStations[$station['ID']]['lon']
			) = explode(',', $station['PROPERTY_COORDS_VALUE']);
		}
		return $arStations;
	}
	public function getStationsList($maxDistance, $maxStations)
	{
		$arLinkedStations = array();
		foreach (self::getMetroStations() as $stationId => $coords)
		{
			$distance = GetDistance(
				array(
					'lat' => $this->latitude,
					'lon' => $this->longitude
				), $coords
			);
			if ($distance <= $maxDistance)
			{
				$arLinkedStations[$stationId] = $distance;
			}
		}
		if (count($arLinkedStations))
		{
			// если найдено слишком много ст.м.
			if (count($arLinkedStations >= $maxStations))
			{
			// забирать только самые приближенные
			asort($arLinkedStations);
				$arLinkedStations = array_keys(
					array_slice($arLinkedStations, 0, $maxStations, true)
				);
			}
		}
		return $arLinkedStations;
	}
	public function executeComponent()
	{

	}
}