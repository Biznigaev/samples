<?if(!Defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$filename = dirname(__FILE__).'/Договор.docx';
if (file_exists($filename))
{
	$arResult['DOCUMENT'] = implode(', ', array(
		$arResult['DOCUMENT_TYPE'].' ',
		strval($arResult['PASSPORT_SER']).' '.strval($arResult['PASSPORT_NUM']),
		'Выдан '. $arResult['PASSPORT_ISSUED'], $arResult['PASSPORT_ISSUING_DATE'],
		$arResult['PASSPORT_CODE']
	));
	if ($arResult['GENDER'] == 'М')
	{
		$arResult['GENDER'] = $arResult['SEX'] = 'Мужской';
	}
	elseif ($arResult['GENDER'] == 'Ж')
	{
		$arResult['GENDER'] = $arResult['SEX'] = 'Женский';
	}
	$arAddress = array();
	foreach (array('ACT_POSTCODE', 'ACT_REGION', 'ACT_CITY',
				   'ACT_DISTRICT', 'ACT_STREET', 'ACT_HOUSE',
				   'ACT_FLAT') as $key)
	{
		$val = trim($arResult[$key]);
		if (!empty($val))
		{
			$arAddress[] = $val;
		}
	}
	$arResult['ADDRESS'] = implode(', ', $arAddress);
	$arResult['CONTACT_PHONE'] = '+7 495 7774888';
	if (!empty($arResult['MOBILE_PHONE']))
	{
		$arResult['CONTACT_PHONE'] = $arResult['MOBILE_PHONE'];
	}
	elseif (!empty($arResult['HOME_PHONE']))
	{
		$arResult['CONTACT_PHONE'] = $arResult['HOME_PHONE'];
	}
	$i = explode(' ', microtime());
	$time = str_replace('.','',strval($i[0] + $i[1]));
	// дублирование файла во временную директорию
	$tmpfilename = $_SERVER['DOCUMENT_ROOT'].'/upload/tmp/'.$GLOBALS['USER']->getId().'_'.$time.'.docx';
	if (copy($filename, $tmpfilename))
	{
		$zip = new ZipArchive;
		if ($zip->open($tmpfilename))
		{
			$doc = $zip->getFromName('word/document.xml');
			foreach ($arResult as $key => &$val)
			{
				$doc = str_replace('#'.$key.'#', $val, $doc);
			}
			$zip->deleteName('word/document.xml');
			$zip->addFromString('word/document.xml', $doc);
			$zip->close();
			
			$arResult['FILE'] = array(
				'PATH' => $tmpfilename,
				'NAME' => 'Договор.docx'
			);
			// header("Pragma: public"); // required
			// header("Expires: 0");
			// header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			// header("Cache-Control: private",false); // required for certain browsers
			// header("Content-Type: $ctype");
			// header("Content-Disposition: attachment; filename=\"Договор.docx\";" );
			// header("Content-Transfer-Encoding: binary");
			// header("Content-Length: ".filesize($tmpfilename));
			// ob_clean();
			// flush();
			// readfile($tmpfilename);
		}
		// unlink($tmpfilename);
	}
}
// die();