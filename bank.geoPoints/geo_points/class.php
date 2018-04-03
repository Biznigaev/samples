<?php
use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

Loc::loadMessages(__FILE__);

class CGeoPoints extends CBitrixComponent
{
	private $defaultViewMode = 'gmap';
	private $defaultPointType = 'office';
	private $defaultPage = 'list';

	protected $defaultUrlTemplates404 = array(
		'list' => '?type=#POINT_TYPE#',
		'detail' => 'detail.php?id=#POINT_ID#'
	);
	protected $variableAliaces = array();
	protected $urlTemplates = array();
	protected $variables = array('POINT_ID','POINT_TYPE');
	protected $page = false;

	public function onPrepareComponentParams($params)
	{
		if (empty($params['CACHE_TIME']))
		{
			$params['CACHE_TIME'] = 86400;
		}
		if (empty($params['FILE_404']))
		{
			$params['FILE_404'] = false;
		}

		return $params;
	}
	protected function initPageTemplate(&$arVariables)
	{
		$componentPage = CComponentEngine::ParseComponentPath(
			$this->arParams['SEF_FOLDER'],
			$this->urlTemplates,
			$arVariables
		);
		$b404 = false;
		if (!$componentPage)
		{
			$b404 = true;
		}
		elseif ($componentPage == 'list')
		{
			// опреление типа точки на карте (банкомат/офис/терминал/касса)
			$userType = $this->request->getQuery('type');
			if (is_null($userType))
			{
				$userType = $this->defaultPointType;
			}
			//    не удалось определелить тип точки на карте
			$b404 = (empty($userType)
					 || !in_array($userType, $this->arParams['POINT_TYPES']));
			if (!$b404)
			{
				$arVariables['TYPE'] = $userType;
				$arVariables['TITLE'] = $this->arParams['LIST_TITLES'][$userType];
				$arVariables['FILTER'] = $this->arParams['POINTS_FILTER'][$userType];
			}
		}
		elseif (in_array($componentPage, array('detail', 'nearest')))
		{
			if (!class_exists('CIBlockElement'))
			{
				Loader::includeModule('iblock');
			}
			$dbRes = CIBlockElement::GetList(false, array(
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
				'ID' => $this->request->getQuery('id'),
				'ACTIVE' => 'Y'
			), false, false, array('ID','PROPERTY_TYPE'));
			// отделение найдено
			if ($dbRes->SelectedRowsCount())
			{
				$arRes = $dbRes->Fetch();
				// опреление типа точки на карте (банкомат /офис /терминал /касса)
				$arRes = CIBlockPropertyEnum::GetByID($arRes['PROPERTY_TYPE_ENUM_ID']);
				$arVariables['TYPE'] = $arRes['XML_ID'];
			}
			else
			{
				$b404 = true;
			}
		}
		elseif ($componentPage == 'search')
		{
			$userView = $this->request->getQuery('view');
			$b404 = (empty($userView) || !in_array($userView, $this->arParams['VIEW_MODES']));
		}
		if (!$b404)
		{
			$this->page = $componentPage;
		}
	}
	public function executeComponent()
	{
		$arVariables = array();
		if ($this->arParams['SEF_MODE'] == 'Y')
		{
			$this->urlTemplates = CComponentEngine::MakeComponentUrlTemplates(
				$this->defaultUrlTemplates404,
				$this->arParams['SEF_URL_TEMPLATES']
			);
			$this->variableAliaces = CComponentEngine::MakeComponentVariableAliases(
				$this->defaultUrlTemplates404,
				$this->arParams['VARIABLE_ALIASES']
			);
			$this->initPageTemplate($arVariables);
			if (!$this->page 
				&& Loader::includeModule('iblock'))
			{
				\Bitrix\Iblock\Component\Tools::process404("",
					($this->arParams["SET_STATUS_404"] === "Y"),
					($this->arParams["SET_STATUS_404"] === "Y"),
					($this->arParams["SHOW_404"] === "Y"),
					$this->arParams["FILE_404"]
				);
				return;
			}
			CComponentEngine::InitComponentVariables(
				$this->page,
				$this->variables,
				$this->variableAliaces,
				$arVariables
			);
		}
		$this->arResult = array(
			'FOLDER' => $this->arParams['SEF_FOLDER'],
			'URL_TEMPLATES' => $this->urlTemplates,
			'VARIABLES' => $arVariables,
			'ALIACES' => $this->variableAliaces
		);
		$this->IncludeComponentTemplate($this->page);
	}
}