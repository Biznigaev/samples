<?if(!Defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$time = strtotime($arResult['PASSPORT_ISSUING_DATE']);

$d = date('d',$time);
$m = date('m',$time);
$y = date('Y',$time);

$tmpMonthes = array(
	'01' => 'января',
	'02' => 'февраля',
	'03' => 'марта',
	'04' => 'апреля',
	'05' => 'мая',
	'06' => 'июня',
	'07' => 'июля',
	'08' => 'августа',
	'09' => 'сентрября',
	'10' => 'октября',
	'11' => 'ноября',
	'12' => 'декабря',
);

$arResult['PASSPORT_ISSUING_DATE_DATE'] = $d;
$arResult['PASSPORT_ISSUING_DATE_MONTH'] = $tmpMonthes[$m];
$arResult['PASSPORT_ISSUING_DATE_YEAR'] = $y;
$arResult['FIO_SHORT'] = $arResult['LASTNAME'].'  '.substr($arResult['FIRSTNAME'], 0, 1).'. '.substr($arResult['HISNAME'], 0, 1).'.';

$filename = dirname(__FILE__).'/Заявление на ЭЦП.docx';
if (file_exists($filename))
{
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
				if (in_array($key, array('FULLNAME','PASSPORT_ISSUED','FIO_SHORT')))
				{
					$multiplier = strlen($val);
					for (;
						 $multiplier > 0 && strpos($doc, '#'.$key.'#'.str_repeat('_', $multiplier)) === FALSE;
						 $multiplier--
					);
					$doc = str_replace('#'.$key.'#'.str_repeat('_', strlen($val)), $val, $doc);
				}
				$doc = str_replace('#'.$key.'#', $val, $doc);
			}
			$zip->deleteName('word/document.xml');
			$zip->addFromString('word/document.xml', $doc);
			$zip->close();
			
			$arResult['FILE'] = array(
				'NAME' => 'Заявление на ЭЦП.docx',
				'PATH' => $tmpfilename
			);
			// header("Pragma: public"); // required
			// header("Expires: 0");
			// header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			// header("Cache-Control: private",false); // required for certain browsers
			// header("Content-Type: $ctype");
			// header("Content-Disposition: attachment; filename=\"Заявление на ЭЦП.docx\";" );
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