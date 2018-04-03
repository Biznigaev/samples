<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

class CDBService
{
	private $connect;
	private $login;

	public function __construct($login, $password, $database)
	{
		$conn = oci_pconnect($login, $password, $database);
		if (!$conn)
		{
			$e = oci_error();
			trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
			return false;
		}
		$this->connect = $conn;
	}
	public function setLogin($login)
	{
		$this->login = $login;
	}
	public function __destruct()
	{
		unset($this->connect);
	}
	private function QueryExec($object_name,$aFields=array())
	{
		$strSQL = "BEGIN fhm.pkg_treasure.".$object_name.'(';
		$list = array(':cursor');
		if (count($aFields) > 0)
		{
			foreach ($aFields as $id => $item)
			{
				$list[] = ':param'.$id;
			}
		}
		$strSQL .= implode(',',$list).'); END;';

		$curs = oci_new_cursor($this->connect);
		$stmt = oci_parse($this->connect, $strSQL);

		oci_bind_by_name($stmt,"cursor",$curs,-1,OCI_B_CURSOR);
		if (count($aFields) > 0)
		{
			foreach ($aFields as $id => $item)
			{
				oci_bind_by_name($stmt,'param'.$id,$item,-1);
			}
		}
		if (!oci_execute($stmt))
		{
			echo (oci_error($stmt)['message']);
			exit(1);
		}
		if (!oci_execute($curs))
		{
			echo (oci_error($curs)['message']);
			exit(2);
		}

		$rs = array();
		while ($row = oci_fetch_assoc($curs))
		{
			$rs[] = $row;
		}

		oci_free_statement($stmt);
		oci_free_statement($curs);

		$db = new CDBResult;
		$db->InitFromArray($rs);
		unset($rs);
		return $db;
	}
	/**
	 *    Получение данных в формате form3ajax.php
	 */
	public function GetList1($debug=false)
	{
		$start_time = 0;
		$stop_time = 0;
		if ($debug)
		{
			$start_time = self::GetMicroTime();
		}

		$rs = $this->QueryExec('proc_report1');

		if ($debug)
		{
			$stop_time = self::GetMicroTime();
		}
		$aResult = array();

		while ($row = $rs->Fetch())
		{
			$aResult[$row['REFERENCENUMBER']] = array(
				'ENTRY_TIME'         => trim($row['ENTRYTIME']),
				'BUY_CURRENCY_CODE'  => trim($row['BUYCURRENCYCODE']),
				'BUY_AMOUNT'         => number_format(trim($row['BUYAMOUNT']), 2, localeconv()['decimal_point'], ' '),
				'SELL_CURRENCY_CODE' => trim($row['SELLCURRENCYCODE']),
				'SELL_AMOUNT'        => number_format(trim($row['SELLAMOUNT']), 2, localeconv()['decimal_point'], ' '),
				'OPERATION_RATE'     => trim($row['OPERATIONRATE']),
				'COMMENTS'           => trim($row['COMMENTS']),
				'OPERATION_USER'     => trim($row['OPERATIONUSER']),
				'APPROVE_USER'       => trim($row['APPROVEUSER']),
				'CHANNEL'            => trim($row['CHANNEL']),
				'RATE'               => number_format(trim($row['RATE']), 4, localeconv()['decimal_point'], ' '),
			);
		}
		unset($rs);
		if ($debug)
		{
			self::AddMessage2Log('REPORT1: '.round(($stop_time - $start_time), 4));
		}
		return json_encode($aResult);
	}
	/**
	 *    Получение данных в формате form3ajax_2.php
	 */
	public function GetList2($debug=false)
	{
		$start_time = 0;
		$stop_time = 0;
		if ($debug)
		{
			$start_time = self::GetMicroTime();
		}

		$rs = $this->QueryExec('proc_report2');
		
		if ($debug)
		{
			$stop_time = self::GetMicroTime();
		}
		$aResult = array();

		while ($row = $rs->Fetch())
		{
			$aResult[$row['CURRENCYTYPE']] = array(
				'CUR' => number_format ($row['CURRENCY_TOTAL_AMOUNT'],2,localeconv()['decimal_point'],' '),
				'AVG' => number_format ($row['AVGRATE'],              4,localeconv()['decimal_point'],' '),
				'RUB' => number_format ($row['RUB_TOTAL_AMOUNT'],     2,localeconv()['decimal_point'],' '),
			);
		}

		unset($rs);
		if ($debug)
		{
			self::AddMessage2Log('REPORT2: '.round(($stop_time - $start_time), 4));
		}
		return json_encode($aResult);
	}
	/**
	 *    Получение данных в формате form3ajax (отчёт по продажам)
	 */
	public function GetList3($debug=false)
	{
		$start_time = 0;
		$stop_time = 0;
		if ($debug)
		{
			$start_time = self::GetMicroTime();
		}

		$rs = $this->QueryExec('proc_report3');
		
		if ($debug)
		{
			$stop_time = self::GetMicroTime();
		}
		$aResult = array();

		while ($row = $rs->Fetch())
		{
			$aResult[] = array(
				'OPERATIONTIME' => date('H:i:s',strtotime($row['OPERATIONTIME'].' '.$row['VALUEDATE'])),
				'BUYCURRENCY'   => $row['BUYCURRENCY'],
				'BUYAMOUNT'     => number_format($row['BUYAMOUNT'], 2, localeconv()['decimal_point'], ' '),
				'SELLCURRENCY'  => $row['SELLCURRENCY'],
				'SELLAMOUNT'    => number_format($row['SELLAMOUNT'], 2, localeconv()['decimal_point'], ' '),
				'MICEXRATE'     => $row['MICEXRATE'],
				'SWAPPOINT'     => $row['SWAPPOINT'],
				'RATE'          => $row['RATE'],
				'VALUEDATE'     => date('d.m.Y',strtotime($row['VALUEDATE']))
			);
		}
		unset($rs);
		if ($debug)
		{
			self::AddMessage2Log('REPORT3: '.round(($stop_time - $start_time), 4));
		}
		return json_encode($aResult);
	}
	/**
	 *    Получение данных в формате form4ajax.php
	 */
	public function GetList4($debug=false)
	{
		$start_time = 0;
		$stop_time = 0;
		if ($debug)
		{
			$start_time = self::GetMicroTime();
		}
		/**
		 *    Взять список получателей из Memcached
		 */
		$arrUsers  = CTreasuryReports::GetAjax4Users();
		if (count($arrUsers) == 0)
		{
			return [];
		}
		$intExpire = CTreasuryReports::getExpire();
		
		foreach ($arrUsers as $sLogin => &$LAST_QUERY_TIME)
		{
			//    проверяем, не просрочен ли последний запрос
			if (time() - $LAST_QUERY_TIME > $intExpire)
			{
				unset($arrUsers[$sLogin]);
			}
		}
		//    если все пользователи просрочены
		if (count($arrUsers) == 0)
		{
			return [];
		}
		$dbUsers = CUser::GetList(
			($by="id"), 
			($order="asc"),
			array(
				'LOGIN' => implode(' | ',array_keys($arrUsers))
			),
			array('FIELDS' => array('LOGIN','PASSWORD'))
		);
		$is_filtered = $dbUsers->is_filtered;
		while ($aUser = $dbUsers->Fetch())
		{
			$aResult[strToLower($aUser['LOGIN'])]['PASSWORD_HASH'] = $aUser['PASSWORD'];
		}
		$arUsers = [];
		foreach (array_keys($aResult) as $user)
		{
			$arUsers[] = "'".$user."'";
		}
		$arUsers=implode(',',$arUsers);
		$rs = $this->QueryExec('proc_report4',array($arUsers));
		if ($debug)
		{
			$stop_time = self::GetMicroTime();
		}
		while ($row = $rs->Fetch())
		{
			$aResult[strToLower($row['LOGIN'])]['FIELDS'][$row['NAME']] = array(
				'BUY'         => trim($row['BUY']),
				'SELL'        => trim($row['SELL']),
				'RATE'        => trim($row['RATE']),
				'BUY_SPREAD'  => trim($row['BUY_SPREAD']),
				'SELL_SPREAD' => trim($row['SELL_SPREAD']),
			);
		}
		foreach ($aResult as $sLogin => &$aElement)
		{
			$aResult[strToLower($sLogin)]['FIELDS'] = json_encode($aElement['FIELDS']);
		}
		oci_free_statement($stmt);
		if ($debug)
		{
			self::AddMessage2Log('REPORT4: '.round(($stop_time - $start_time), 4));
		}
		//    example: запись в лог факта получения данных из БД
		#CDaemon::AddMessage2Log(print_r(">CDBService: DATA(".strlen(print_r($aResult,1)).") IS EXTRACTED\n",1));

		return $aResult;
	}
	public function GetMicroTime()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}
	public function AddMessage2Log($sText)
	{
		if ($fp = fopen(dirname(__FILE__).'/logs/cdbservice.txt', "ab+"))
        {
            if (flock($fp, LOCK_EX))
            {
                fwrite($fp, $sText."\n");
                fwrite($fp, "----------\n");
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
	}
}