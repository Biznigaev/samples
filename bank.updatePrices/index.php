<?php
//    exec from cli 
if (empty($_SERVER['DOCUMENT_ROOT']))
{
	$_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/www';

	putenv("ORACLE_HOME=/usr/lib/oracle/11.2/client64");
	putenv("LD_LIBRARY_PATH=/usr/lib/oracle/11.2/client64/lib:");
	putenv("TNS_ADMIN=/usr/lib/oracle/11.2/client64/network/admin");
	putenv("PATH=/usr/lib/oracle/11.2/client64/bin:/usr/lib/oracle/11.2/client64/network/admin:/sbin:/usr/sbin:/bin:/usr/bin");
	putenv("NLS_LANG=AMERICAN_AMERICA.CL8MSWIN1251");

	define('SEF_MODE','N');
}
//    from ajax & browser
else
{
	define('SEF_MODE','Y');
}

include_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$APPLICATION->IncludeComponent('crediteurope:terminal','.default',
	array(
		'TREASURY_GROUP_ID' => 138,
		'SEF_MODE' => SEF_MODE,
		'DECIMAL_POINT' => localeconv()["decimal_point"],
		/* test server connection
        'OCI_CONNECTION' => array(
			'LOGIN'    => 'super_user',
			'PASSWORD' => 'smgserver01!',
			'DATABASE' => '(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = rutstdb01)(PORT = 1535)))(CONNECT_DATA = (SERVICE_NAME = cbtru01)))',
		),
        */
		'INTERVALS' => array(
			'REPORT1' => 10,
			'REPORT2' => 5,
			'REPORT3' => 10,
			'REPORT4' => 2,
		),
		'OCI_CONNECTION' => array(
			'LOGIN'    => 'trs_usr',
			'PASSWORD' => 'marketkutlu',
			'DATABASE' => 'cbpru01_shared',
		),
		
		'TIMEOUT' => 300,/*  5 minutes  */
		"SEF_MODE" => SEF_MODE,
		"SET_STATUS_404" => "Y",
		"SEF_FOLDER" => "/applications/terminal/",
		"SEF_URL_TEMPLATES" => array(
			"interface"    => "",
			"form3" => "form3.php",
			"form4" => "form4.php",
			"ajax" => "ajax/#REPORT_ID#/"
		),
	),
	false
);

include_once ($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');