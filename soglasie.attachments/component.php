<?if(!Defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
global $USER;

if (!$USER->IsAuthorized())
{
	return;
}
include_once 'HrDb.php';
$arResult = HrDb::getFields();
// проверка на заполненность обязательных полей
$sendEmpty = false;
foreach (array(
	'LASTNAME','FIRSTNAME','HISNAME','BIRTHDATE','GENDER','CITIZENSHIP','INN',
	'PASSPORT_SER','PASSPORT_NUM','PASSPORT_ISSUED','PASSPORT_CODE','PASSPORT_ISSUING_DATE',
	'REG_POSTCODE','ACT_POSTCODE','SNILS','EMAIL','HOME_PHONE','FULLNAME'
) as $key)
{
	if (!array_key_exists($key, $arResult)
		|| empty($arResult[$key]))
	{
		$sendEmpty = true;
		break;
	}
}
$arResult['APPEAL'] = 'ый';
if ($arResult['GENDER'] == 'Ж')
{
	$arResult['APPEAL'] = 'ая';
}
include_once 'EmailSending.php';
$emailMessage = new EmailSending($arResult['EMAIL'], 'Вступление в «НПФ Согласие»', array(
	'#APPEAL#' => $arResult['APPEAL'],
	'#FIRSTNAME#' => $arResult['FIRSTNAME'],
	'#HISNAME#' => $arResult['HISNAME']
));
$successMessage = '';
$failedMessage = '{"status":"failed","message":"Не удалось отправить письмо на '.$arResult['EMAIL'].'. Повторите позже или обратитесь в отдел кадрового администрирования Департамент персонала и документооборота банка"}';
// найдены все обязательный поля - генерация документов
if (count($arResult)
	&& !$sendEmpty)
{
	if (empty($arResult['OLD_LASTNAME']))
	{
		$arResult['OLD_LASTNAME'] = $arResult['LASTNAME'];
	}
	
	// attach files
	$this->__templateName = 'anketa';
	$this->includeComponentTemplate();
	$emailMessage->attachFromComponentTemplate($arResult['FILE']['NAME'], $arResult['FILE']['PATH'], true);

	$this->__templateName = 'dogovor';
	$this->includeComponentTemplate();
	$emailMessage->attachFromComponentTemplate($arResult['FILE']['NAME'], $arResult['FILE']['PATH'], true);

	$this->__templateName = 'zayavlenie';
	$this->includeComponentTemplate();
	$emailMessage->attachFromComponentTemplate($arResult['FILE']['NAME'], $arResult['FILE']['PATH'], true);

	$successMessage = '{"status":"success","message":"На вашу почту '.$arResult['EMAIL'].' направлено письмо"}';
}
// не найдены все обязательный поля - отправка шаблонов документов
else
{
	// attach files
	$this->__templateName = 'empty_anketa';
	$this->includeComponentTemplate();
	$emailMessage->attachFromComponentTemplate($arResult['FILE']['NAME'], $arResult['FILE']['PATH']);

	$this->__templateName = 'empty_dogovor';
	$this->includeComponentTemplate();
	$emailMessage->attachFromComponentTemplate($arResult['FILE']['NAME'], $arResult['FILE']['PATH']);

	$this->__templateName = 'empty_zayavlenie';
	$this->includeComponentTemplate();
	$emailMessage->attachFromComponentTemplate($arResult['FILE']['NAME'], $arResult['FILE']['PATH']);
	
	$successMessage = '{"status":"success","message":"Не удалось заполнить поля в документах. Шаблоны документов направлены на вашу почту '.$arResult['EMAIL'].'"}';
}
$sent = false;
// отправлять письмо, если найдено поле MAIL 
if (array_key_exists('EMAIL', $arResult))
{
	// sending message
	$sent = $emailMessage->send();
}
echo $sent ? $successMessage : $failedMessage;