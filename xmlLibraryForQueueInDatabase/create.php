<?php
/**
 * Скрипт осуществляет выгрузку информации по шинам, дискам, автомобилям из xml-файла в БД 
 * @see local/modules/company.main/tools/cron/load_images.php
 * @see local/modules/company.main/lib/import/catalogcarsimport.php
 * @see local/modules/company.main/lib/import/catalogdisksimport.php
 * @see local/modules/company.main/lib/import/catalogtyresimport.php
 * @see local/modules/company.main/lib/import/icatalogimport.php
 */

// запрет прямого вызова (только из CLI)
if (!empty($_SERVER['DOCUMENT_ROOT']))
{
	die();
}
$_SERVER['DOCUMENT_ROOT'] = __DIR__.'/..';

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true); 
define('CHK_EVENT', false);
$logFilename = '/_td_/create.log';

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

set_time_limit(0);
ignore_user_abort(true);

use Bitrix\Main\Loader;
use Bitrix\Main\Diag\Debug;
use Company\Main\Import\CatalogCarsImagesQueue;
use Company\Main\Import\CatalogCarsImport;
use Company\Main\Import\CatalogDisksImport;
use Company\Main\Import\CatalogTyresImport;

Loader::includeModule('company.main');
Loader::includeModule('highloadblock');

$file = array_shift(glob(__DIR__ . '/sources/xml/*.xml'));
foreach (simplexml_load_file($file) as $item)
{
	try
	{
		$carId = (int) CatalogCarsImport::bind([
			'UF_Y2' => (float) $item->y2,
			'UF_X2' => (float) $item->x2,
			'UF_X1' => (float) $item->x1,
			'UF_Y1' => (float) $item->y1,
			'UF_DIAMAX' => (string) $item->diamax,
			'UF_DIA' => (string) $item->dia,
			'UF_PCD' => (string) $item->pcd,
			'UF_HOLE' => (string) $item->hole,
			'UF_KREPEZHAZ2' => (string) $item->krepezhraz2,
			'UF_KREPEZHAZ' => (string) $item->krepezhraz,
			'UF_KREPEZH' => (string) $item->krepezh,
			'UF_MODIFICATION' => (string) $item->modification,
			'UF_KUZOV_YEARS' => intVal($item->beginyear).'-'.intVal($item->endyear).' '.strval($item->kuzov),
			'UF_ENDYEAR' => (int) $item->endyear,
			'UF_BEGINYEAR' => (int) $item->beginyear,
			'UF_KUZOV' => (string) $item->kuzov,
			'UF_MODEL' => (string) $item->model,
			'UF_MARKA' => (string) $item->marka,
			'UF_VENDOR_CARID' => (int) $item->carid
		]);
	}
	catch (Exception $e)
	{
		Debug::writeToFile($e->getMessage(),"", $logFilename);
		// прервать выполнение загрузки по текущему авто и перейти на следующий
		continue;
	}
	// обработка изображений
	foreach ((array)$item->images as $color => $src)
	{
		try
		{
			CatalogCarsImagesQueue::push($carId, $src, $color);
		}
		catch (Exception $e)
		{
			Debug::writeToFile($e->getMessage(),"", $logFilename);
			// прервать выполнение загрузки по текущему авто и перейти на следующий
			continue 2;
		}
	}
	// обработка дисков
	if (isset($item->diski->beforewheels))
	{
		foreach ($item->diski->beforewheels as $disk)
		{
			// запись инфы по объекту Диск
			$diskId = CatalogDisksImport::bind([
				'UF_CARID' => $carId,
				'UF_VENDOR_DISKID' => (int)$disk->diskid,
				'UF_OEM' => (string) $disk->oem,
				'UF_ET' => (string) $disk->et,
				'UF_DIAMETER' => (string) $disk->diameter,
				'UF_ETMAX' => (string) $disk->etmax,
				'UF_WIDTH' => (string) $disk->width,
				'UF_BACK_OS' => (string) $disk->back_os
			]);
			// запись инфы по объекту Шина
			CatalogTyresImport::bind([
				'UF_CARID' => $carId,
				'UF_OEM' => (string) $disk->oem,
				'UF_DISKID' => $diskId,
				'UF_WIDTH' => empty(strval($disk->tyres->width)) ? '' : floatval($disk->tyres->width),
				'UF_HEIGHT' => empty(strval($disk->tyres->height)) ? '' : floatval($disk->tyres->height),
				'UF_DIAMETER' => empty(strval($disk->tyres->diameter)) ? '' : floatval($disk->tyres->diameter)
			]);
		}
	}
	if (isset($item->diski->backwheels))
	{
		foreach ($item->diski->backwheels as $disk)
		{
			// запись инфы по объекту Диск
			$diskid = CatalogDisksImport::bind([
				'UF_CARID' => $carId,
				'UF_VENDOR_DISKID' => (int)$disk->diskid,
				'UF_OEM' => (string) $disk->oem,
				'UF_ET' => (string) $disk->et,
				'UF_DIAMETER' => (string) $disk->diameter,
				'UF_ETMAX' => (string) $disk->etmax,
				'UF_WIDTH' => (string) $disk->width,
				'UF_BACK_OS' => (string) $disk->back_os
			]);
			// запись инфы по объекту Шина
			CatalogTyresImport::bind([
				'UF_CARID' => $carId,
				'UF_OEM' => (string) $disk->oem,
				'UF_DISKID' => $diskId,
				'UF_WIDTH' => empty(strval($disk->tyres->width)) ? '' : floatval($disk->tyres->width),
				'UF_HEIGHT' => empty(strval($disk->tyres->height)) ? '' : floatval($disk->tyres->height),
				'UF_DIAMETER' => empty(strval($disk->tyres->diameter)) ? '' : floatval($disk->tyres->diameter)
			]);
		}
	}
}
unlink($file);