<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/**
 * @todo: Добавить кэширование
 */
use Bitrix\Main;
use Bitrix\Main\Loader;

Loader::includeModule('iblock');
// получает загруженность отделения
class CGeoPointsLoad extends CBitrixComponent
{
	// статус загруженности
	/*
		green  – менеджеры свободны, 
		yellow – небольшая очередь, 
		orange – очередь более 3 человек, 
		red    – очередь более 5 человек
	*/
	private $arStatus = array(
		0 => array(
			'img' => 'none',
			'alt' => ''
		),
		1 => array(
			'img' => 'green',
			'alt' => 'менеджеры свободны'
		),
		2 => array(
			'img' => 'green',
			'alt' => 'менеджеры свободны'
		),
		3 => array(
			'img' => 'yellow',
			'alt' => 'небольшая очередь'
		),
		4 => array(
			'img' => 'orange',
			'alt' => 'очередь более 3 человек'
		),
		5 => array(
			'img' => 'red',
			'alt' => 'очередь более 5 человек'
		)
	);
	public function setState($pointID, $iblockID)
	{
		$this->arParams['IBLOCK_ID'] = $iblockID;
		$this->arParams['ID'] = $pointID;
	}
	public function executeComponent()
	{
		$arFields = CIBlockElement::GetList(false, array(
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'ID' => $this->arParams['ID'],
		), false, false, array(
			'ID','PROPERTY_LOAD'
		))->Fetch();

		$this->arResult['TODAY'] = array(
			'DAY_NAME' => FormatDate('D', MakeTimeStamp(date('d.m.Y'))),
			'LOAD' => date('N')-1
		);
		$this->arResult['WEEK_DAY'] = array();
		foreach ($arFields['PROPERTY_LOAD_VALUE'] as $i => &$arDayLoad)
		{
			foreach (explode('-', $arDayLoad) as $idx => $load)
			{
				$this->arResult['WEEK_DAY'][$i][] = array(
					'SRC' => '/images/poi/busy/'.$this->arStatus[$load]['img'].'.png',
					'ALT' => utf8win1251($this->arStatus[$load]['alt'])
				);
			}
		}

		return $this->arResult;
	}
}