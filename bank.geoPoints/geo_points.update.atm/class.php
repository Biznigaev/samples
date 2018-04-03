<?php
use \Bitrix\Main;
use Bitrix\Main\Loader;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class CGeoPointsUpdateAtm extends CBitrixComponent
{
	private $serviceHost = '10.0.10.99',
			$serviceUser = 'atmreadonly',
			$servicePass = 'huRd54FdbN';
	// получение точек из сервиса
	private function getPointsList()
	{
		$arPoints = array();
		$con = mssql_connect($this->serviceHost, $this->serviceUser, $this->servicePass);
		$query = mssql_query("
			SELECT 
				atm.number,atm.kontragent, atm.address, atm.dostup, atm.operation_time, atm.currency, atm.model, atm.url_map, 
				CAST(atm.atm_location AS text) AS location,
				reloc.atm, 'relocation' = CASE WHEN reloc.atm > 0 THEN 1 ELSE 0 END
			FROM atm 
			LEFT JOIN (
				SELECT
					atm
				FROM
					atmrelocation
				WHERE
					reldate >= DATEADD(day,-1,GETDATE())
					AND
					type='установка'
			) AS reloc ON
				reloc.atm = atm.number
			WHERE
				atm.status = 'В эксплуатации'
			ORDER BY
				atm.number ASC"
		);
		while ($atm = mssql_fetch_assoc($query))
		{
			$number_atm  = 'АТМ'.str_repeat("0", 3-strlen($atm["number"])).$atm["number"];
		    $arPoints[$number_atm] = array(
	    		// название 
				'name' => $atm['kontragent'],
		    	// адрес
		    	'address' => $atm["address"],
		    	'model' => $atm['model'],
		    	// доступ к банкомату
		    	'is_limited' => $atm['dostup'] == 'закрытый' ? 'да' : NULL,
		    	// круглосуточно ли работает
		    	'is_around' => preg_match('/^Круглосуточно/', $atm["operation_time"], $tmp) ? 'Да' : '',
		    	// время работы
				'work_time' => mb_convert_case($atm['operation_time'], MB_CASE_LOWER, "CP1251"),
				// валюта выдачи
				'currency' => str_replace("RUB", "RUR", $atm['currency']),
				'mass_currency_res' => array(),
				// описание проезда
				'proezd' => trim($atm["location"]),
				// координаты
				'url_map' => $atm['url_map']
		    );
			if (preg_match("/(^\d)|(^пн)|(^вт)|(^ср)|(^чт)|(^пт)|(^сб)|(^вс)|(круглосуточно)/i", $arPoints[$number_atm]['work_time'], $tmp)
				&& !preg_match("/выходн/i", $arPoints[$number_atm]['work_time'], $tmp))
			{
				$arPoints[$number_atm]['work_time'] = "ежедневно, ".$arPoint['work_time'];
			}
			$arPoints[$number_atm]['work_time'] = preg_replace("/(\d{2}:\d{2})-(\d{2}:\d{2})/", 'с $1 до $2', $arPoints[$number_atm]['work_time']);
			$mass_rurrency_value = array(
				'RUR' => '866',
				'USD' => '867',
				'EUR' => '868'
			);
			foreach (explode("/", $arPoints[$number_atm]['currency']) as $key => $cur)
			{
				$arPoints[$number_atm]['mass_currency_res'][] = $mass_rurrency_value[$cur];
			}
		}
		// закрыть соединение с БД
		mssql_close($con);

		return $arPoints;
	}
	private function addPoint(&$arPoint)
	{
		$arAtmModels = array(
			'NCR 6632',
			'WN CINEO C4040 FL-4',
			'WN CINEO C4040 FL-5',
			'NCR  Personas 73е'
		);

	}
	public function executeComponent()
	{
		// получение банкоматов из сервиса
		$arPoints = $this->getPointsList();
		Loader::includeModule('iblock');
		$dbRes = CIBlockElement::GetList(array(
			'ID' => 'ASC'
		), array(
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'PROPERTY_TYPE_VALUE' => 'Банкомат'
		), false, false, array(
			'ID','PROPERTY_MODEL','ACTIVE'
		));
		while ($arItem = $dbRes->Fetch())
		{
			if (!array_key_exists($arItem['PROPERTY_MODEL_VALUE'], $arPoints))
			{
				// деактивация банокмата
				if ($arItem['ACTIVE'] == 'Y')
				{
					$el = new CIBlockElement;
					$res = $el->Update($arFields['ID'], array(
						"ACTIVE" => "N",
						"TIMESTAMP_X" => date('d.m.Y H:i:s')
					));
				}
			}
			else
			{
				unset($arPoints[$arItem['PROPERTY_MODEL_VALUE']]);
			}
		}
		// создание нового банкомата
		if (count($arPoints))
		{
			foreach ($arPoints as $number_atm => &$arPoint)
			{
				$this->addPoint($arPoint);	 
				$el = new CIBlockElement;
				$PRODUCT_ID = $el->Add(array(
					"IBLOCK_ID" => $this->arParams['IBLOCK_ID'],
					"NAME" => $arPoint['kontragent'],
					"ACTIVE" => "Y", 
					"PROPERTY_VALUES" => array(
						"538" => 461,// тип точки
						"107" => $address,// адрес
						"681" => $number_atm,// номер банкомата
						'897' => $is_limited == 'да'? 888 : '',// ограничение доступа
						'793' => $atm["url_map"],
						'550' => in_array($arPoint['model'], $arAtmModels) ? 473:'',// cash-in
						'890' => in_array($arPoint['model'], $arAtmModels) ? array(871,872,873) : '',// валюта приема
						'889' => $arPoint['mass_currency_res'],// валюта выдачи
						'539' => $arPoint['work_time'],// время работы
						'893' => array(877,881),
						'971' => 1017
					)
				));
			}
		}
	}
}