<?if(!Defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$filename = dirname(__FILE__).'/Анкета_'.$arResult['GENDER'].'.docx';
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
			// адрес регистрации совпадает с адресом проживания
			if (crc32(print_r(array(
				$arResult['ACT_POSTCODE'],
				$arResult['ACT_REGION'],
				$arResult['ACT_DISTRICT'],
				$arResult['ACT_CITY'],
				$arResult['ACT_STREET'],
				$arResult['ACT_HOUSE'],
				$arResult['ACT_FLAT']
			),1)) == crc32(print_r(array(
				$arResult['REG_POSTCODE'],
				$arResult['REG_REGION'],
				$arResult['REG_DISTRICT'],
				$arResult['REG_CITY'],
				$arResult['REG_STREET'],
				$arResult['REG_HOUSE'],
				$arResult['REG_FLAT']
			),1)))
			{
				$rels = str_replace('image4','image5', $zip->getFromName('word/_rels/document.xml.rels'));
				$zip->deleteName('word/_rels/document.xml.rels');
				$zip->addFromString('word/_rels/document.xml.rels', $rels);
			}
			foreach ($arResult as $key => &$val)
			{
				if (in_array($key, array('FULLNAME')))
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
				'NAME' => 'Анкета.docx',
				'PATH' => $tmpfilename
			);
			// header("Pragma: public"); // required
			// header("Expires: 0");
			// header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			// header("Cache-Control: private",false); // required for certain browsers
			// header("Content-Type: $ctype");
			// header("Content-Disposition: attachment; filename=\"Анкета.docx\";" );
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