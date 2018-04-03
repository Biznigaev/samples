<?if(!Defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

class HrDb
{
	private static $instance = NULL;
	private $conn;
	private function __construct()
	{
		$this->conn = mssql_connect("xxx", "xxx", "xxx");
		mssql_select_db('xxx');
	}
	protected function __clone()
	{}
	private function __wakeup()
	{}
	public function getFields()
	{
		global $DB, $USER, $APPLICATION;
		$arResult = array();
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		$ob = self::$instance;
		$dbUser = $DB->Query('SELECT XML_ID FROM b_user WHERE ID='.$USER->GetId());
		if (!$dbUser->SelectedRowsCount())
		{
			return $arResult;
		}
		$arProfile = $dbUser->Fetch();
		$dbResult = mssql_query('SELECT * FROM hrfn_table_info_coglasie('.$arProfile['XML_ID'].')') or die(mssql_get_last_message());
		if (!mssql_num_rows($dbResult))
		{
			return $arResult;
		}
		// если сотрудник идентификирован, то логировать обращение
		self::logUserAccess();
		return self::mapping($APPLICATION->ConvertCharsetArray(
			mssql_fetch_assoc($dbResult), 'Windows-1251', 'UTF-8'
		));
	}
	private function logUserAccess()
	{
		global $DB,
			   $USER;
		$DB->Query('INSERT INTO soglasie_log (USER_ID) VALUES ('.$USER->getId().')');
	}
	private static function mapping($arFields)
	{
		static $arMap = array(
			'sname' => 'LASTNAME',
			'name_i' => 'FIRSTNAME',
			'name_o' => 'HISNAME',
			'name_old' => 'OLD_LASTNAME',
			'date_birth' => 'BIRTHDATE',
			'bAddr_city' => 'BIRTHPLACE',
			'sex' => 'GENDER',
			'graj' => 'CITIZENSHIP',
			'name' => 'DOCUMENT_TYPE',
			'inn' => 'INN',
			'passp_ser' => 'PASSPORT_SER',
			'passp_num' => 'PASSPORT_NUM',
			'passp_grant' => 'PASSPORT_ISSUED',
			'passp_code' => 'PASSPORT_CODE',
			'passp_date' => 'PASSPORT_ISSUING_DATE',
			'rAddr_zip' => 'REG_POSTCODE',
			'pAddr_zip' => 'ACT_POSTCODE',
			'rAddr_region' => 'REG_REGION',
			'pAddr_region' => 'ACT_REGION',
			'rAddr_city' => 'REG_CITY',
			'pAddr_city' => 'ACT_CITY',
			'rAddr_okrug' => 'REG_DISTRICT',
			'pAddr_okrug' => 'ACT_DISTRICT',
			'rAddr_street' => 'REG_STREET',
			'pAddr_street' => 'ACT_STREET',
			'rAddr_house' => 'REG_HOUSE',
			'pAddr_house' => 'ACT_HOUSE',
			'rAddr_flat' => 'REG_FLAT',
			'pAddr_flat' => 'ACT_FLAT',
			'SocNumber' => 'SNILS',
			'EMail' => 'EMAIL',
			'dop_phohe' => 'HOME_PHONE',
			'Phone' => 'MOBILE_PHONE',
			'Full_Name' => 'FULLNAME'
		);
		$arResult = array();
		foreach ($arFields as $key => $val)
		{
			if (in_array($key, array('date_birth','passp_date')))
			{
				$val = date('d.m.Y', strtotime($val));
			}
			elseif ($key == 'birth_place'
				&& !empty($arFields['birth_place'])
				&& empty($arFields['bAddr_city']))
			{
				$val = $arFields['bAddr_city'];
			}
			elseif ($key == 'Phone')
			{
				if (strlen(str_replace(' ','',strval($arFields['Phone_code'].$arFields['Phone']))) == 10)
				{
					$val = implode(' ', array('+7', $arFields['Phone_code'], $arFields['Phone']));
				}
				else
				{
					$val = implode(' ', array($arFields['Phone_code'], $arFields['Phone']));
				}
			}
			elseif ($key == 'dop_phohe')
			{
				if (strlen(str_replace(' ','',$val)) == 11
					&& substr($val,0,1) == '8')
				{
					$val = str_replace(' ','',$val);
					$val = implode(
						' ',
						array(
							'+7',
							substr($val, 1,3),
							substr($val, 4)
						)
					);
				}
			}
			elseif ($key == 'rAddr_block')
			{
				$val = trim($val);
				$rAddr_house = trim($arFields['rAddr_house']);
				if (!empty($val))
				{
					if (!empty($rAddr_house))
					{
						$val = $arFields['rAddr_house'].'/'.$val;
					}
					$arResult['REG_HOUSE'] = $val;
				}
			}
			elseif ($key == 'pAddr_block')
			{
				$val = trim($val);
				$pAddr_house = trim($arFields['pAddr_house']);
				if (!empty($val))
				{
					if (!empty($pAddr_house))
					{
						$val = $arFields['pAddr_house'].'/'.$val;
					}
					$arResult['ACT_HOUSE'] = $val;
				}
			}
			if (array_key_exists($key, $arMap))
			{
				$arResult[$arMap[$key]] = $val;
			}
		}

		return $arResult;
	}
}