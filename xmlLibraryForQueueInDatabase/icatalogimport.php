<?php
namespace Company\Main\Import;
/**
 * Имплементируется объектами Шины, Диски, Автомобили
 * @see local/modules/company.main/lib/import/catalogcarsimport.php
 * @see local/modules/company.main/lib/import/catalogdisksimport.php
 * @see local/modules/company.main/lib/import/catalogtyresimport.php
 */
interface ICatalogImport
{
	public static function getEntity();
	public static function bind($fields);
	public static function getFields();
}