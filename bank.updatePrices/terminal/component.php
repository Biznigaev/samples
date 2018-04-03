<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

include_once ('cdaemon.php');
include_once ('cdbservice.php');
include_once ('ctreasuryreports.php');

//    если зашли через демона
if ($arParams["SEF_MODE"] <> "Y")
{
	//    если пользователи давно не запрашивали данные, 
	//    убиваем процесс обновления данных
	if ((int)time() - CTreasuryReports::GetLastQuery() > (int)$arParams['TIMEOUT'])
	{
		CDaemon::Stop();
		CTreasuryReports::SetLastQuery('');
	}
	else
	{
		//    Подключаемся к БД и обновляем данные в кэше
		$db = new CDBService($arParams['OCI_CONNECTION']['LOGIN'],
							 $arParams['OCI_CONNECTION']['PASSWORD'],
							 $arParams['OCI_CONNECTION']['DATABASE']);

		$key = print_r($arParams['OCI_CONNECTION'],1);

		//    получение отчёта по запросу
		global $argv;
		switch ($argv[1])
		{
			case 'REPORT1':
			{
				//    запись в кэш
				CTreasuryReports::SetReport($key,'ajax1',$db->GetList1());
				break;
			}
			case 'REPORT2':
			{
				//    запись в кэш
				CTreasuryReports::SetReport($key,'ajax2',$db->GetList2());
				break;
			}
			case 'REPORT3':
			{
				//    запись в кэш
				CTreasuryReports::SetReport($key,'ajax3',$db->GetList3());
				break;
			}
			case 'REPORT4':
			{
				//    получаем результат по каждому пользователю
				$arResult = $db->GetList4();
				if (count($arResult) > 0)
				{
					foreach ($arResult as $sLogin => &$aElement)
					{
						CTreasuryReports::SetReport($key.$aElement['PASSWORD_HASH'],strToLower($sLogin),$aElement['FIELDS']);
					}
				}
				break;
			}
		}
	}
}
else
{
	global $USER;
	/**
	 *    проверка прав доступа (только для авторизованного пользователя)
	 */
	if ($USER->IsAuthorized())
	{
		if (!$USER->IsAdmin() 
			&& !in_array($arParams['TREASURY_GROUP_ID'], $USER->GetUserGroupArray()))
		{
			exit();
		}
	}
	$arDefaultUrlTemplates404 = array(
		"interface" => "",
		"ajax"      => "ajax/#REPORT_ID#/",
		"form3"     => "form3.php",
		"form4"     => "form4.php",
	);
	$arDefaultVariableAliases404 = array();
	$arDefaultVariableAliases = array();
	$arComponentVariables = array(
		"REPORT_ID",
	);
	$arVariables = array();
	$arUrlTemplates = CComponentEngine::MakeComponentUrlTemplates($arDefaultUrlTemplates404, $arParams["SEF_URL_TEMPLATES"]);

	$componentPage = CComponentEngine::ParseComponentPath(
		$arParams["SEF_FOLDER"],
		$arUrlTemplates,
		$arVariables
	);

	$b404 = false;
	if (empty($componentPage))
	{
		$componentPage = 'interface';
	}
	else
	{
		if (!$componentPage)
		{
			$componentPage = "404";
			$b404 = true;
		}
	}
	
	//    если зашли браузером на раздел для ajax-а
	if ($componentPage == 'ajax')
	{
		if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
			|| strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
		{
			$componentPage = '404';
			$b404 = true;
		}
	}
	
	if ($b404)
	{
		$folder404 = str_replace("\\", "/", $arParams["SEF_FOLDER"]);
		if ($folder404 != "/")
		{
			$folder404 = "/".trim($folder404, "/ \t\n\r\0\x0B")."/";
		}
		if (substr($folder404, -1) == "/")
		{
			$folder404 .= "index.php";
		}
		if ($folder404 != $APPLICATION->GetCurPage(true))
		{
			CHTTP::SetStatus("404 Not Found");
		}
	}
	CComponentEngine::InitComponentVariables($componentPage, $arComponentVariables, $arVariableAliases, $arVariables);
	$arResult = array(
		"FOLDER" => $arParams["SEF_FOLDER"],
		"URL_TEMPLATES" => $arUrlTemplates,
		"VARIABLES" => $arVariables,
		"ALIASES" => $arVariableAliases,
	);
	//    запускать сервис только если он авторизован
	if ($componentPage <> 'ajax'
		&& $USER->IsAuthorized())
	{
		//    проверяем, есть ли запущенный демон
		if (!CDaemon::IsActive())
		{
			//    передаём интервалы между запросами
			$pid = CDaemon::Start($arParams['INTERVALS']);
			//    если нет, создаём
			if ($pid < 0)
			{
				//    запись в лог неудачной попытки создания процесса
				CDaemon::AddMessage2Log('FAILED TO FORK PROCCESS');
				exit();
			}
		}
	}
	//    если зашли под ajax-ом
	if ($componentPage == 'ajax')
	{
		header("Content-Type: application/json");
		header("Expires: on, 01 Jan 1970 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		define("STOP_STATISTICS", true);
		define("NOT_CHECK_PERMISSIONS", true);
		
		switch (strtoUpper($arResult['VARIABLES']['REPORT_ID']))
		{
			case 'REPORT1':
			{
				echo (CTreasuryReports::GetSecondReport(print_r($arParams['OCI_CONNECTION'],1)));
				break;
			}
			case 'REPORT2':
			{
				$json = (array)json_decode(CTreasuryReports::GetFirstReport(print_r($arParams['OCI_CONNECTION'],1)));
				$key = key($json);
				$val = (array) reset($json);
				if (gettype(reset($json)) == 'boolean')
				{
					$val = '';
				}
				else
				{
					//    сортируем по возрастанию (сначала старые записи)
					asort($val);
				}
				echo json_encode([$key => $val]);
				break;
			}
			case 'REPORT3':
			{
				echo (CTreasuryReports::GetThirdReport(print_r($arParams['OCI_CONNECTION'],1)));
				break;
			}
			case 'REPORT4':
			{
				echo (CTreasuryReports::GetFourthReport(print_r($arParams['OCI_CONNECTION'],1)));
				break;
			}
		}
	}
	//    если зашли под интерфейсом
	elseif ($componentPage == 'form3'
			|| $componentPage == 'form4')
	{
		$this->IncludeComponentTemplate($componentPage);
	}
}