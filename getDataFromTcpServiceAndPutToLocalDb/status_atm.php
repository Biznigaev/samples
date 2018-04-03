<?php
//    private класс коннекта по сокету
class CTcpTransport
{
	private $ip,$port,$conn;

	protected function __construct( $ip,$port )
	{
		$this->ip = $ip;
		$this->port = $port;
	}
	protected function __destruct()
	{
		fclose($this->conn);
	}
	public function getIP()
	{
		return $this->ip;
	}
	public function getPort()
	{
		return $this->port;
	}
	protected function Connect()
	{
		$socket = fsockopen($this->ip, $this->port, $errno, $errstr, 30);
		if (!$socket)
		{
			throw new Exception($errstr);
		}
		$this->conn = $socket;
	}
	protected function Send($message)
	{
		if (fputs($this->conn,$message) === FALSE)
		{
			throw new Exception('Can\'t send message');
		}
	}
	protected function Read()
	{
		$response = "";
		while ($line = fread($this->conn, 4096))
		{
		    $response .= $line;
		}
		return $response;
	}
	protected function Close()
	{
		fclose($this->conn);
	}
}
/**
 *    интерфейс на случай если из данной системы понадобятся не только статусы банкоматов
 */
interface IAtmStatus
{
	public static function GetList($ip,$port,$arCities);
	public static function GetByID($ip,$port,$cityID);
}
/**
 *    @todo исключения в отдельный файл через унаследованный класс
 */
class CAtmStatException extends Exception
{
	public function __construct($message, $code, Exception $previous = null)
	{

		parent::__construct($message, $code, $previous);
	}
	public function __toString()
	{
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
class CAtmStatus extends CTcpTransport implements IAtmStatus
{
	private $socket,$message;

	public function __construct($ip,$port)
	{
		$this->socket = new CTcpTransport($ip,$port);
	}
	public function SetMessage($cityID)
	{
		$strMess  = '<EXT V="1.0">';
			$strMess .= '<SN>INTRANET</SN>';
			$strMess .= '<FCID>001</FCID>';
			$strMess .= '<TCID>008</TCID>';
			$strMess .= '<CTID>'.(string)mktime().(string)mt_rand(100000,999999).'</CTID>';#    default CTID: 6910001500407292
			$strMess .= '<UID>INTRANET</UID>';

			$strMess .= '<PAR>';
				$strMess .= '<I N="CITY">' .$cityID. '</I>';
				$strMess .= '<I N="DH_LANGUAGE">RU</I>';
				$strMess .= '<I N="SERVICENAME">CC_BS_ATMSTATUSOBSERVATION</I>';
			$strMess .= '</PAR>';

		$strMess .= '</EXT>';

		$this->message = $strMess;
	}
	public function __destruct()
	{
		unset($this->message);
		$this->message = null;
	}
	protected function SendMessage()
	{
		$this->socket->Send($this->message);
		$response = $this->socket->Read();
		return $response;
	}
	/**
	 *    Получает через экземпляр CTcpTrancport результат в виде (stirng)
	 *    @param $ip        (string)  ip-адрес сервера
	 *    @param $port      (integer) числовой идентификатор порта
	 *    @param $arCities  (array)   список иденитификаторов городов
	 *    @return           (array)   пропарсенный ответ сервиса
	 */
	public static function GetList($ip,$port,$arCities)
	{
		$len = count($arCities);
		$arResult = array();
		$ob = new CAtmStatus($ip,$port);

		foreach ($arCities as $i => &$cityID)
		{
			$ob->socket->Connect();
			$ob->SetMessage($cityID);
			$ob->SetMsgType();

			$bResult = array();
			$bResult = self::ParseResponse( $ob->SendMessage(),$cityID );
			if ($bResult == -1)
			{
				continue;
			}
			foreach ($bResult as $key => &$arItem)
			{
				$arResult[$key] = $arItem;
				unset($bResult[$key]);
			}
			unset($bResult);
			$bResult = NULL;
			/**
			 *    @todo очистка памяти если израсходавоно > 10Mb
			 */
			if ($i % 9 == 0)
			{
				gc_enable();
				if (gc_enabled())
				{
					gc_collect_cycles();
					gc_disable();
				}
			}
			if ($len > 1)
			{
				usleep(500000);#    0.5 sec
			}
			$ob->socket->Close();
		}
		gc_enable();
		if (gc_enabled())
		{
			gc_collect_cycles();
			gc_disable();
		}
		return $arResult;
	}
	/**
	 *    Склейка результата из формата string в array
	 *    Если найден атрибут ERR на верхнем уровне документа, то сгенерируется исключение
	 *    @param  (string)  &$response - адрес на начало сообщения
	 *    @return (array)   пропарсенный $response
	 */
	public function ParseResponse(&$response,$cityID)
	{
		$arResult = array();
		//    проверка длинны полученного сообщения
		$arResult['LENGTH'] = substr($response,0,6);
		$xml = simplexml_load_string(substr($response,6));
		#file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/log.xml',$response);die();
		if (!$xml)
		{
			$xml = simplexml_load_string(  Encoding::fixUTF8(substr($response,6))  );
		}
		# Sn - название сервиса
		$arResult['SN'] = (string)$xml->SN;
		# Fcid - канал отправителя
		$arResult['FCID'] = (string)$xml->FCID;
		# Tcid - канал получателя
		$arResult['TCID'] = (string)$xml->TCID;
		# Ctid - Ид клиентской транзакции
		$arResult['CTID'] = (string)$xml->CTID;
		# Ид Юзера
		$arResult['UID'] = (string)$xml->UID;
		$arResult['STID'] = (string)$xml->STID;
		$arResult['RC'] = (int)$xml->RC;
		//    в случае ошибки, пишем её в файл
		if ($xml->ERR)
		{
			$error = array();
			foreach ($xml->PAR->I as $k => $v)
			{
				$v = (array)$v;
				$error[] = $v[0];
			}
			ignore_user_abort(true);
			if ($fp = fopen(str_replace('.php','',__FILE__).'.log',"ab+"))//    LIFO
			{
				if (flock($fp, LOCK_EX))
				{
					@fwrite($fp, $_SERVER["HTTP_HOST"]."|".date("Y-m-d H:i:s")."|".$xml->ERR."|".$error[0]."|".$error[1]."|".$cityID."\n");
					@fflush($fp);
					@flock($fp, LOCK_UN);
					@fclose($fp);
				}
			}
			ignore_user_abort(false);
			// throw new Exception($error[0] .': '. $error[1]);
			return -1;
		}
		$len = count($xml->PAR->A[0]->V);
		for ($i=0; $i<$len; ++$i)
		{
			//    вычисление статуса по таблице
			$arResult['ITEMS'][(string)$xml->PAR->A[0]->V[$i]]['STATUS'] = self::GetDetail((string)$xml->PAR->A[2]->V[$i],(string)$xml->PAR->A[3]->V[$i]);
			$arResult['ITEMS'][(string)$xml->PAR->A[0]->V[$i]]['ADDRESS'] = (string)$xml->PAR->A[1]->V[$i];
			$arResult['ITEMS'][(string)$xml->PAR->A[0]->V[$i]]['REGION'] = $cityID;
			//    вычисление доступности валюты
			$arResult['ITEMS'][(string)$xml->PAR->A[0]->V[$i]]['STATUS']['RUR'] = CAtmStatusTools::CheckCurrency('RUR',$xml->PAR->A[2]->V[$i].$xml->PAR->A[3]->V[$i]);
			$arResult['ITEMS'][(string)$xml->PAR->A[0]->V[$i]]['STATUS']['USD'] = CAtmStatusTools::CheckCurrency('USD',$xml->PAR->A[4]->V[$i].$xml->PAR->A[5]->V[$i]);
		}
		return $arResult['ITEMS'];
	}
	/**
	 *    Получение банкоматов по конкретному населённому пункту
	 *    @param $ip      (string)  ip-адрес сервера
	 *    @param $port    (integer) числовой идентификатор порта
	 *    @param $cityID  (integer) числовой идентификатор города
	 *    @return         (array)   пропарсенный ответ сервиса
	 */
	public static function GetByID($ip,$port,$cityID)
	{
		return self::GetList($ip,$port,array($cityID));
	}
	/**
	 *    Установка типа сообщения из рассчета его длины
	 */
	private function SetMsgType()
	{
		$len = mb_strlen($this->message, '8bit');
		$msgType = 'C';#    0 .. 999999
		if ($len < 999999)
		{
		    if ($len >= 100000)
		    {
		        $msgType = 'D';#    100000 .. 999999
		    }
		    else
		    {
		        if ($len >= 10000)
		        {
		            $msgType = 'B';#    10000 .. 99999
		        }
		        else
		        {
		            $msgType = 'A';#    0 .. 9999
		        }
		    }
		}
		$this->message = $msgType . str_pad(strVal($len),5,'0',STR_PAD_LEFT) . $this->message;
	}
	/**
	 *    Вычисление доступности банкомата по кодам его статуса
	 *
	 *    @param $strStatus    (string) номер статуса
	 *    @param $strSubstatus (string) подкатегория статуса (для статуса 2, т.к. у него 2 варианта)
	 *
	 *    ! - ошибка, некоторые функции не доступны
	 *    - - пустое значение (null), функция отсутствует
	 *    N - функция банкомата не доступна
	 *    Y - функция банкомата доступна
	 *
	 *    @return (array) список функций и их степень доступности
	 */
	public function GetDetail($strStatus, $strSubstatus)
	{
	    $arResult = array();

	    switch ($strStatus)
	    {
	        case '01':
	        {
	            $arResult['STATE'] = 'N';
	            $arResult['CASHOUT'] = 'N';
	            $arResult['CASHIN'] = 'N';
	            $arResult['OTHER'] = 'N';
	            $arResult['CHEQUE'] = 'N';
	            break;
	        }
	        case '02':
	        {
	            $arResult['STATE'] = '!';
	            if ($strSubstatus == 'A')
	            {
		            $arResult['CASHOUT'] = 'N';
		            $arResult['CASHIN'] = 'Y';
	            }
	            elseif ($strSubstatus == 'B')
	            {
		            $arResult['CASHOUT'] = 'Y';
		            $arResult['CASHIN'] = 'N';

	            }
	            $arResult['OTHER'] = 'Y';
	            $arResult['CHEQUE'] = '-';
	            break;
	        }
	        case '03':
	        {
	            $arResult['STATE'] = 'Y';
	            $arResult['CASHOUT'] = 'Y';
	            $arResult['CASHIN'] = 'Y';
	            $arResult['OTHER'] = 'Y';
	            $arResult['CHEQUE'] = '-';
	            break;
	        }
	        case '04':
	        {
	            $arResult['STATE'] = '!';
	            $arResult['CASHOUT'] = 'N';
	            $arResult['CASHIN'] = 'N';
	            $arResult['OTHER'] = 'Y';
	            $arResult['CHEQUE'] = 'Y';
	            break;
	        }
	    }

	    return $arResult;
	}
}
//    вспомогательный класс для работы с БД
class CAtmStatusTools
{
	/**
	 *    Получить идентификаторы значений для checkbox-ов по выбранным кодам свойств
	 *
	 *    @param $arProps (array) список кодов свойств
	 *    @return (array) результирующий список свойств формата: 
	 *    PROPERTY_ID(id св-ва) | NAME(название св-ва) | CODE(код св-ва для обратной связки) | VALUE_ID(ID значения VALUE) | VALUE (текст значения)
	 */
	public function GetPropertyList($arProps)
	{
		if (count($arProps) < 1)
		{
			return;
		}
		$str = '';
		foreach ($arProps as $key => $strProp)
		{
			if ($key > 0)
			{
				$str .= ',';
			}
			$str .= '\''.$strProp.'\'';
		}
		global $DB;
		$strSQL = " SELECT t1.id AS PROPERTY_ID,t1.NAME,t1.CODE,t2.id AS VALUE_ID,t2.VALUE 
					FROM 
					(
						SELECT id,name,code
    					FROM b_iblock_property
    					WHERE iblock_id = 14 
						  AND code IN ({$str})
					) t1
					INNER JOIN b_iblock_property_enum t2 
						ON t2.property_id=t1.id
					ORDER BY PROPERTY_ID ASC";

		$rsProps = $DB->Query($strSQL, false, $err_mess.__LINE__);
		$arResult = array();
		while ($arProp = $rsProps->Fetch())
		{
			$arResult[$arProp['CODE']] = array(
				'PROPERTY_ID' => $arProp['PROPERTY_ID'],
				'NAME' => $arProp['NAME'],
				'VALUE_ID' => $arProp['VALUE_ID'],
				'VALUE' => $arProp['VALUE'],
			);
		}

		return $arResult;
	}
	/**
	 *    Проверка валюты на доступность по ассоц. таблице:
	 *    +----------------------+------------+
	 *    | валюта | код статуса | значение   |
	 *    +--------+-------------+------------+
	 *    | RUR    | 02B         | доступен   |
	 *    +--------+-------------+------------+
	 *    | RUR    | 03A         | доступен   |
	 *    +--------+-------------+------------+
	 *    | RUR    | 01A         | недоступен |
	 *    +--------+-------------+------------+
	 *    | RUR    | 04A         | недоступен |
	 *    +--------+-------------+------------+
	 *    | USD    | 02B         | доступен   |
	 *    +--------+-------------+------------+
	 *    | USD    | 03A         | доступен   |
	 *    +--------+-------------+------------+
	 *    | USD    | 01A         | недоступен |
	 *    +--------+-------------+------------+
	 *    | USD    | 04A         | недоступен |
	 *    +--------+-------------+------------+
	 *
	 *    @param $strType (string) тип валюты
	 *    @param $strState (string) код состояния (см. метод CAtmStatus::ParseResponse)
	 *    return (string) Y|N статус доступности валюты
	 */
	public function CheckCurrency($strType,$strState)
	{
		//    таблица соответствий
		static $arMap = array(
			'RUR' => array('02B','03A'),
			'USD' => array('02B','03A')
		);
		//    если пришёл неизвестный ключ
		if (!array_key_exists($strType, $arMap))
		{
			return;
		}
		if (in_array($strState, $arMap[$strType]))
		{
			return 'Y';
		}
		else
		{
			return 'N';
		}
	}
	/**
	 *    Получить статусы всех банкоматов
	 *    Если кеш не найден, делает выборку из инфоблока
	 *
	 *    @param $TTL       (integer) время жизни кеша
	 *    @param $IBLOCK_ID (integer) идентификатор инфоблока
	 *    @param $arOrder   (array)   сорировка выборки
	 *    @param $arFields  (array)   поля статусов для выборки
	 *    @return (array) список банкоматов с тегом ID элемента
	 *
	 */
	public function GetList($TTL,$IBLOCK_ID,$arOrder,$arFields)
	{
		if (!CModule::IncludeModule("iblock"))
		{
			$GLOBALS['APPLICATION']->ThrowException("Ошибка обращения к инфоблокам");
			return false;
		}
		$obCache = new CPHPCache;
		$cacheID = md5('/crediteurope/atm.status/');
		$cachePath = '/crediteurope/atm.status/';

		$arResult = array();

		if ($obCache->InitCache($TTL, $cacheID, $cachePath))
		{
			$arCache = $obCache->GetVars();
			$arResult = unserialize($arCache['STATUS_ATMS']);
		}
		elseif ( $obCache->StartDataCache() )
		{
			$arFields[] = 'ID';
			$rsElement = CIBlockElement::GetList(
				$arOrder,
				array(
					"IBLOCK_ID" => $IBLOCK_ID,
					'PROPERTY_TYPE_BRANCH_VALUE' => 'Банкомат'
				),
				false,
				false,
				$arFields
			);
			while ($arItem = $rsElement->Fetch())
			{
				$ID = $arItem['ID'];
				unset(
					$arItem['ID'],
					$arItem['PROPERTY_NUMBER_ATM_VALUE_ID'],
					$arItem['PROPERTY_STATE_VALUE_ID'],
					$arItem['PROPERTY_CASHOUT_ENUM_ID'],
					$arItem['PROPERTY_CASHOUT_VALUE_ID'],
					$arItem['PROPERTY_CASHIN_ENUM_ID'],
					$arItem['PROPERTY_CASHIN_VALUE_ID'],
					$arItem['PROPERTY_OTHER_ENUM_ID'],
					$arItem['PROPERTY_OTHER_VALUE_ID'],
					$arItem['PROPERTY_CHEQUE_ENUM_ID'],
					$arItem['PROPERTY_CHEQUE_VALUE_ID'],
					$arItem['PROPERTY_CURRENCY_ENUM_ID'],
					$arItem['PROPERTY_CURRENCY_VALUE_ID']
				);
				//    предотвращение перезаписи значения множественного свойства "Валюта"
				if (!array_key_exists($ID, $arResult))
				{
					$arResult[$ID] = $arItem;
					$arResult[$ID]['PROPERTY_CURRENCY_VALUE'] = array();
				}
				$arResult[$ID]['PROPERTY_CURRENCY_VALUE'][] = $arItem['PROPERTY_CURRENCY_VALUE'];
			}
			$obCache->EndDataCache(
				array(
					"STATUS_ATMS" => serialize($arResult)
				)
			);
		}
		return $arResult;
	}
	/**
	 *    Метод для проверки статусов по банкоматам. 
	 *    Массив $MESSAGE содержится в комплексном компоненте
	 *    +----------------------------+
	 *    | тег | статус               |
	 *    +-----+----------------------+
	 *    | 00  | банкомат не доступен |
	 *    | 01  | не доступен cashin   |
	 *    | 10  | не доступен cashout  |
	 *    | 11  | всё доступно         |
	 *    +-----+----------------------+
	 *    @param &$arBranches   (array) хэш-лист банкоматов/отделений
	 *    @param &$arStatusList (array) хэш-лист статусов по банкоматам
	 *    @return (void) в хэш-лист arBranches добавляются новые ключи: STATE - содержит текст статуса, NUMBER - содержит номер банкомата
	 *
	 */
	public function CheckList(&$arBranches,&$arStatusList)
	{
		foreach ($arBranches as $key => &$arItem)
		{
			if ($arItem['PROPERTY_TYPE_BRANCH_ENUM_ID'] != 58
				|| (empty($arStatusList[$key]['PROPERTY_NUMBER_ATM_VALUE']) 
					|| empty($arStatusList[$key]['PROPERTY_STATE_VALUE'])))
			{
				continue;
			}
			$arState = &$arStatusList[$key];
			$strState = array();
			switch ($arState['PROPERTY_STATE_VALUE'])
			{
				case "Y":
				{
					$strState['11'] = GetMessage('STATUS_ATM_AVAILIBLE');
					break;
				}
				case "!":
				{
					//    условие для cash-out
					if (empty($arItem['PROPERTY_CASH_IN_VALUE']))
					{
						//     если недоступна функция выдачи наличных
						if ($arState['PROPERTY_CASHOUT_VALUE'] <> 'Y')
						{
							$strState['00'] = GetMessage('STATUS_ATM_NOT_AVAILIBLE');
							// $strState['10'] = GetMessage('STATUS_ATM_CASHOUT_NOT_AVAILIBLE');
						}
						else
						{
							$strState['11'] = GetMessage('STATUS_ATM_AVAILIBLE');
						}
					}
					//    условие для cash-in
					else
					{
						//    если доступна хотя бы одна из функций – прием наличных, выдача наличных
						//    cashout доступна
						if ($arState['PROPERTY_CASHOUT_VALUE'] == 'Y')
						{
							//    ... и cashin доступна
							if ($arState['PROPERTY_CASHIN_VALUE'] == 'Y')
							{
								$strState['11'] = GetMessage('STATUS_ATM_AVAILIBLE');
							}
							else
							{
								$strState['01'] = GetMessage('STATUS_ATM_CASHIN_NOT_AVAILIBLE');
							}
						}
						//    cashout недоступна...
						else
						{
							//    ... но cashin доступна
							/*
							if ($arState['PROPERTY_CASHIN_VALUE'] == 'Y')
							{
								$strState['10'] = GetMessage('STATUS_ATM_CASHOUT_NOT_AVAILIBLE');
							}
							else
							{*/
								$strState['00'] = GetMessage('STATUS_ATM_NOT_AVAILIBLE');
								// $strState['01'] = GetMessage('STATUS_ATM_CASHIN_NOT_AVAILIBLE');
								// $strState['10'] = GetMessage('STATUS_ATM_CASHOUT_NOT_AVAILIBLE');
							/*}*/
						}
						/*if ($arItem['PROPERTY_OTHER_VALUE'] <> 'Y')
						{

						}
						if ($arItem['PROPERTY_CHEQUE_VALUE'] <> 'Y')
						{

						}*/
					}
					break;
				}
				case "N":
				{
					$strState['00'] = GetMessage('STATUS_ATM_NOT_AVAILIBLE');
					//    cashout
					/*
					if (empty($arItem['PROPERTY_CASH_IN_VALUE']))
					{
						$strState['10'] = GetMessage('STATUS_ATM_CASHOUT_NOT_AVAILIBLE');
					}
					//    cashin
					else
					{
						$strState['01'] = GetMessage('STATUS_ATM_CASHIN_NOT_AVAILIBLE');
						$strState['10'] = GetMessage('STATUS_ATM_CASHOUT_NOT_AVAILIBLE');
					}
					*/
					break;
				}
			}
			$arItem['NUMBER'] = $arState['PROPERTY_NUMBER_ATM_VALUE'];
			$arItem['STATUS'] = $strState;
			//    определение доступной валюты
			if (count($arState['PROPERTY_CURRENCY_VALUE']) > 0)
			{
				$strStatus = array();
				foreach ($arState['PROPERTY_CURRENCY_VALUE'] as &$sCurrency)
				{
					$strStatus[] = GetMessage('STATUS_ATM_CURRENCY_AVAILABLE_'.$sCurrency);
				}
				$arItem['CURRENCY_STATE'] = implode(', ',$strStatus);
			}
			else
			{
				$arItem['CURRENCY_STATE'] = GetMessage('STATUS_ATM_CURRENCY_NOT_AVAILABLE');
			}
		}
	}
	/**
	 *    Refresh кеша по статусам банкоматов
	 *    @param $PATH      (string)  путь до папки с кэшем
	 *    @param $TTL       (integer) time to leave для кэша
	 *    @param $IBLOCK_ID (integer) ID инфоблока
	 *    @param $arOrder   (array)   сортировка при выборке
	 *    @param $arFields  (array)   поля, отвечающие за статус
	 *    @return (void) в начале очищается директория PATH, затем создаётся новый путь в PATH
	 */
	public static function UpdateCache($PATH, $TTL, $IBLOCK_ID, $arOrder, $arFields)
	{
	    //    Очистка кеша
	    $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache' .$PATH. '/';
	    shell_exec('cd '.$baseDir.';rm -rf ./*');
		foreach ($arFields as &$arItem)
		{
			$arItem = 'PROPERTY_' . $arItem;
		}
	    //    Создание кеша
	    self::GetList($TTL,$IBLOCK_ID,$arOrder,$arFields);
	}
}

class Encoding
{
	protected static $win1252ToUtf8 = array(
        128 => "\xe2\x82\xac",

        130 => "\xe2\x80\x9a",
        131 => "\xc6\x92",
        132 => "\xe2\x80\x9e",
        133 => "\xe2\x80\xa6",
        134 => "\xe2\x80\xa0",
        135 => "\xe2\x80\xa1",
        136 => "\xcb\x86",
        137 => "\xe2\x80\xb0",
        138 => "\xc5\xa0",
        139 => "\xe2\x80\xb9",
        140 => "\xc5\x92",

        142 => "\xc5\xbd",


        145 => "\xe2\x80\x98",
        146 => "\xe2\x80\x99",
        147 => "\xe2\x80\x9c",
        148 => "\xe2\x80\x9d",
        149 => "\xe2\x80\xa2",
        150 => "\xe2\x80\x93",
        151 => "\xe2\x80\x94",
        152 => "\xcb\x9c",
        153 => "\xe2\x84\xa2",
        154 => "\xc5\xa1",
        155 => "\xe2\x80\xba",
        156 => "\xc5\x93",

        158 => "\xc5\xbe",
        159 => "\xc5\xb8"
	);
  
	protected static $brokenUtf8ToUtf8 = array(
        "\xc2\x80" => "\xe2\x82\xac",
        
        "\xc2\x82" => "\xe2\x80\x9a",
        "\xc2\x83" => "\xc6\x92",
        "\xc2\x84" => "\xe2\x80\x9e",
        "\xc2\x85" => "\xe2\x80\xa6",
        "\xc2\x86" => "\xe2\x80\xa0",
        "\xc2\x87" => "\xe2\x80\xa1",
        "\xc2\x88" => "\xcb\x86",
        "\xc2\x89" => "\xe2\x80\xb0",
        "\xc2\x8a" => "\xc5\xa0",
        "\xc2\x8b" => "\xe2\x80\xb9",
        "\xc2\x8c" => "\xc5\x92",
        
        "\xc2\x8e" => "\xc5\xbd",
        
        
        "\xc2\x91" => "\xe2\x80\x98",
        "\xc2\x92" => "\xe2\x80\x99",
        "\xc2\x93" => "\xe2\x80\x9c",
        "\xc2\x94" => "\xe2\x80\x9d",
        "\xc2\x95" => "\xe2\x80\xa2",
        "\xc2\x96" => "\xe2\x80\x93",
        "\xc2\x97" => "\xe2\x80\x94",
        "\xc2\x98" => "\xcb\x9c",
        "\xc2\x99" => "\xe2\x84\xa2",
        "\xc2\x9a" => "\xc5\xa1",
        "\xc2\x9b" => "\xe2\x80\xba",
        "\xc2\x9c" => "\xc5\x93",
        
        "\xc2\x9e" => "\xc5\xbe",
        "\xc2\x9f" => "\xc5\xb8"
	);
    
	protected static $utf8ToWin1252 = array(
       "\xe2\x82\xac" => "\x80",
       
       "\xe2\x80\x9a" => "\x82",
       "\xc6\x92"     => "\x83",
       "\xe2\x80\x9e" => "\x84",
       "\xe2\x80\xa6" => "\x85",
       "\xe2\x80\xa0" => "\x86",
       "\xe2\x80\xa1" => "\x87",
       "\xcb\x86"     => "\x88",
       "\xe2\x80\xb0" => "\x89",
       "\xc5\xa0"     => "\x8a",
       "\xe2\x80\xb9" => "\x8b",
       "\xc5\x92"     => "\x8c",
       
       "\xc5\xbd"     => "\x8e",
       
       
       "\xe2\x80\x98" => "\x91",
       "\xe2\x80\x99" => "\x92",
       "\xe2\x80\x9c" => "\x93",
       "\xe2\x80\x9d" => "\x94",
       "\xe2\x80\xa2" => "\x95",
       "\xe2\x80\x93" => "\x96",
       "\xe2\x80\x94" => "\x97",
       "\xcb\x9c"     => "\x98",
       "\xe2\x84\xa2" => "\x99",
       "\xc5\xa1"     => "\x9a",
       "\xe2\x80\xba" => "\x9b",
       "\xc5\x93"     => "\x9c",
       
       "\xc5\xbe"     => "\x9e",
       "\xc5\xb8"     => "\x9f"
	);

  	static function toUTF8($text)
  	{

		if(is_array($text))
		{
		  foreach($text as $k => $v)
		  {
		    $text[$k] = self::toUTF8($v);
		  }
		  return $text;
		} elseif(is_string($text)) {

		  $max = strlen($text);
		  $buf = "";
		  for($i = 0; $i < $max; $i++){
		      $c1 = $text{$i};
		      if($c1>="\xc0"){ //Should be converted to UTF8, if it's not UTF8 already
		        $c2 = $i+1 >= $max? "\x00" : $text{$i+1};
		        $c3 = $i+2 >= $max? "\x00" : $text{$i+2};
		        $c4 = $i+3 >= $max? "\x00" : $text{$i+3};
		          if($c1 >= "\xc0" & $c1 <= "\xdf"){ //looks like 2 bytes UTF8
		              if($c2 >= "\x80" && $c2 <= "\xbf"){ //yeah, almost sure it's UTF8 already
		                  $buf .= $c1 . $c2;
		                  $i++;
		              } else { //not valid UTF8.  Convert it.
		                  $cc1 = (chr(ord($c1) / 64) | "\xc0");
		                  $cc2 = ($c1 & "\x3f") | "\x80";
		                  $buf .= $cc1 . $cc2;
		              }
		          } elseif($c1 >= "\xe0" & $c1 <= "\xef"){ //looks like 3 bytes UTF8
		              if($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf"){ //yeah, almost sure it's UTF8 already
		                  $buf .= $c1 . $c2 . $c3;
		                  $i = $i + 2;
		              } else { //not valid UTF8.  Convert it.
		                  $cc1 = (chr(ord($c1) / 64) | "\xc0");
		                  $cc2 = ($c1 & "\x3f") | "\x80";
		                  $buf .= $cc1 . $cc2;
		              }
		          } elseif($c1 >= "\xf0" & $c1 <= "\xf7"){ //looks like 4 bytes UTF8
		              if($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf"){ //yeah, almost sure it's UTF8 already
		                  $buf .= $c1 . $c2 . $c3;
		                  $i = $i + 2;
		              } else { //not valid UTF8.  Convert it.
		                  $cc1 = (chr(ord($c1) / 64) | "\xc0");
		                  $cc2 = ($c1 & "\x3f") | "\x80";
		                  $buf .= $cc1 . $cc2;
		              }
		          } else { //doesn't look like UTF8, but should be converted
		                  $cc1 = (chr(ord($c1) / 64) | "\xc0");
		                  $cc2 = (($c1 & "\x3f") | "\x80");
		                  $buf .= $cc1 . $cc2;
		          }
		      } elseif(($c1 & "\xc0") == "\x80"){ // needs conversion
		            if(isset(self::$win1252ToUtf8[ord($c1)])) { //found in Windows-1252 special cases
		                $buf .= self::$win1252ToUtf8[ord($c1)];
		            } else {
		              $cc1 = (chr(ord($c1) / 64) | "\xc0");
		              $cc2 = (($c1 & "\x3f") | "\x80");
		              $buf .= $cc1 . $cc2;
		            }
		      } else { // it doesn't need convesion
		          $buf .= $c1;
		      }
		  }
		  return $buf;
		} else {
		  return $text;
		}
	}

	static function toWin1252($text) {
    	if(is_array($text)) {
    	  	foreach($text as $k => $v) {
	    	    $text[$k] = self::toWin1252($v);
      	}
      	return $text;
    	}
    	elseif(is_string($text))
    	{
      		return utf8_decode(str_replace(array_keys(self::$utf8ToWin1252), array_values(self::$utf8ToWin1252), self::toUTF8($text)));
    	}
    	else
    	{
      		return $text;
    	}
	}

	static function toISO8859($text) {
		return self::toWin1252($text);
	}

	static function toLatin1($text) {
		return self::toWin1252($text);
	}

  	static function fixUTF8($text){
    	if(is_array($text)) {
    	  	foreach($text as $k => $v) {
	        	$text[$k] = self::fixUTF8($v);
	      	}
	      	return $text;
		}

	    $last = "";
	    while($last <> $text){
	      $last = $text;
	      $text = self::toUTF8(utf8_decode(str_replace(array_keys(self::$utf8ToWin1252), array_values(self::$utf8ToWin1252), $text)));
	    }
	    $text = self::toUTF8(utf8_decode(str_replace(array_keys(self::$utf8ToWin1252), array_values(self::$utf8ToWin1252), $text)));
	    return $text;
	  }
  
	static function UTF8FixWin1252Chars($text)
	{    
		return str_replace(array_keys(self::$brokenUtf8ToUtf8), array_values(self::$brokenUtf8ToUtf8), $text);
	}
  
	static function removeBOM($str="")
	{
		if (substr($str, 0,3) == pack("CCC",0xef,0xbb,0xbf))
		{
			$str=substr($str, 3);
		}
		return $str;
	}
  
	public static function normalizeEncoding($encodingLabel)
	{
		$encoding = strtoupper($encodingLabel);
		$enc = preg_replace('/[^a-zA-Z0-9\s]/', '', $encoding);
		$equivalences = array(
			'ISO88591' => 'ISO-8859-1',
			'ISO8859'  => 'ISO-8859-1',
			'ISO'      => 'ISO-8859-1',
			'LATIN1'   => 'ISO-8859-1',
			'LATIN'    => 'ISO-8859-1',
			'UTF8'     => 'UTF-8',
			'UTF'      => 'UTF-8',
			'WIN1252'  => 'ISO-8859-1',
			'WINDOWS1252' => 'ISO-8859-1'
		);

		if (empty($equivalences[$encoding])){
			return 'UTF-8';
		}

		return $equivalences[$encoding];
	}

	public static function encode($encodingLabel, $text)
	{
		$encodingLabel = self::normalizeEncoding($encodingLabel);
		if ($encodingLabel == 'UTF-8') 
			return Encoding::toUTF8($text);
		if ($encodingLabel == 'ISO-8859-1') 
			return Encoding::toLatin1($text);
	}

}
?>