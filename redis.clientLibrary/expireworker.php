<?php
namespace Company\Main\Redis;
/**
 * @class Увеличение списка 
 */
class ExpireWorker extends Client
{
	/**
	 * Проверяет ключи, который были 
	 * @param $limit int кол-во ключей выгружаемых за раз
	 */
	public function run($limit=10000)
	{
		$redis = parent::getInstance();
		$connection = parent::getConnection();
		$it = null;
		do
		{
		    $arKeys = $connection->scan($it, '*', $limit);
		    if ($arKeys !== FALSE)
		    {
		        foreach ($arKeys as $arKey)
		        {
		        	$arSuppliers = $connection->hkeys($arKey);
					foreach ($arSuppliers as $supplierId)
					{
						if ($value = parent::unpackValue($connection->hget($arKey, $supplierId)))
						{
							if (parent::isExpired($value['ttl'], $arKey, $supplierId)
								&& count($arSuppliers) == 1)
							{
								$connection->delete($arKey);
							}
						}
					}
		        }
		    }
		}
		while ($it > 0);
	}
}