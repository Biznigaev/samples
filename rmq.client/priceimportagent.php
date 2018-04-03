<?php
namespace Company\Main\PriceListImport;

use Bitrix\Main\SystemException,
	Bitrix\Main\Diag\Debug,
	Bitrix\Main\Loader,
	Bitrix\Main\Type\DateTime,
	Bitrix\Main\Config\Option,
	Bitrix\Main\Localization\Loc;

use VendorAutoModule,
	VendorAutoDebug;

use Company\Main\Import\PriceListImport,
	Company\Main\Helpers\RedisHelper,
	Company\Main\Helpers\PricesHelper,
	Company\Main\PriceListImport\PriceMarkup;

Loc::loadMessages(__FILE__);
/**
 * Реализация импорта функционала прайслистов. Читает файлы из очереди и пишет в очередь RabbitMQ
 * @todo: заменить все вхождения / на DIRECTORY_SEPARATOR
 */
class PriceImportAgent extends PriceListImport
{
    public static $run_string = 'PriceImportAgent::run();';
    public static $cache;
    /*
     * массив наценок
     */
    private $markups = [];
    public $price_fields = [];

    const OPT_NAME_LAST_EXEC_TIME = 'PriceParserLastExecutionTime';
    // директория до файла
    const NEW_PARSE_DIR_PATH = '/upload/Vendor.auto/pricelists/new';
    const CSV_DELITIMIER = ";";

    protected static $actions = [
    	'searchNewFiles', 'parseFile'
    ];

    /**
     * @todo: полностью перевести класс в статику
     */
    function __construct()
    {}

    // отключение буфферизации
    protected static function disableAllBuffering()
    {
        while (ob_get_level())
        {
            ob_end_flush();
        }
        flush();
    }

    public static function execute($action, $params=[])
    {
        if (!in_array($action, self::$actions)
            || !VendorAutoModule::canRunAnotherCron())
        {
            return;
        }
        // отключение буфферизации
        self::disableAllBuffering();

        // отключение режима отладки
        VendorAutoDebug::$enabled = false;

        // Проверка наличия метода и его вызов
        if (is_callable([self, $action."Action"]))
        {
            call_user_func([self, $action."Action"], $params);
        }
    }

    // найти новые файлы для обработки со времени последнего выполнения
    // в случае их наличия, запуск парсинга каждого файла в отдельном потоке
    protected static function searchNewFilesAction($params = [])
    {
        // А есть ли крон?
        if (!self::checkCron())
        {
            \CAdminNotify::Add([
                "MESSAGE" => Loc::getMessage('LM_AUTO_MAIN_NEED_CRON'),
                "TAG" => "LM_NEED_CRON",
                "MODULE_ID" => "Vendor.auto",
                "ENABLE_CLOSE" => "N"
            ]);

            // Запрещено выполнять импорт не из под крона
            return self::$run_string;
        }
        else
        {
            \CAdminNotify::deleteByTag("LM_NEED_CRON");
        }
        $agent = new self();
        $files = $agent->getFiles();

        if (count($files))
        {
            self::prepareImportData();
            // Парсим файлы
            foreach (array_values($files) as $filename)
            {
                // парсинг файла выполнить в фоне
                $cmd = 'nohup nice php -f '.$_SERVER['DOCUMENT_ROOT'].'/local/modules/Company.main/tools/cron/priceimport.php '.self::$actions[1].' '.$filename.' > /dev/null 2>&1 &';
                self::log('['.date('d.m.Y H:i:s').'] fork: '.$cmd);
                shell_exec($cmd);
            }
        }
        else
        {
            self::log('Нет файлов для выгрузки с '.self::getLastExecTime());
        }
        // Очистим старые прайсы
        self::cleanupOldFiles();

        if (count($files))
        {
            // установить метку последнего выполнения
            $agent->updateLastExecTime();
        }
    }
    // парсинг файла в отдельном потоке
    protected static function parseFileAction($params = [])
    {
        if (!empty($params[0]))
        {
            self::parseFile($params[0]);
        }
    }

    // получить метку времени последнего успешного выполнения
    protected function getLastExecTime()
    {
        return Option::get('Company.main', self::OPT_NAME_LAST_EXEC_TIME, 0);
    }
    // обновить метку времени последнего успешного выполнения
    protected function updateLastExecTime()
    {
        Option::set('Company.main', self::OPT_NAME_LAST_EXEC_TIME, time());
    }

    // преобразование файла выгрузки из одной кодировки в другую
    protected static function convertParsedFile($src, $encodingFrom, $encodingTo='utf-8', $tmpNameSuffix = '.tmp')
    {
        $result = false;

        $cmd = 'file -bi ' . escapeShellArg($src);
        $cmd_result = system($cmd, $cmd_result);
        $response = explode(';', $cmd_result);
        $charset = explode('=', $response[1]);
        $encoding = trim($charset[1]);

        if ($encoding != $encodingTo)
        {
            $from = $encodingFrom; // потому что file -bi приравнивает cp1251 к iso-8859-1. но у нас-то или 1251 или юникод
            $cmd = 'iconv -f '.$encodingFrom.' -t '.$encodingTo.' "' . $src . '" -o "' . $src . $tmpNameSuffix;

            self::log('iconv from '.$encodingFrom.' to '.$encodingTo);

            system($cmd, $cmd_result);
            if (file_exists($src))
            {
                unlink($src);
                rename($src.$tmpNameSuffix, $src);
                if (file_exists($src))
                {
                    $result = true;
                }
            }
        }
        else
        {
            self::log('Конвертация файла '. $src . ' не требуется');
            $result = true;
        }
        if (!$result)
        {
            self::log('[ERROR] Ошибка конвертации файла '.$src);
        }

        return $result;
    }

    /**
     * Разбор файла.
     * @todo: вынести в отдельный класс парсинг файла, оставить запись в очередь
     */
    public function parseFile($file)
    {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . self::NEW_PARSE_DIR_PATH))
        {
            self::log('Error! Doesn\'t exists '.self::NEW_PARSE_DIR_PATH.' folder!');
            return false;
        }

        $filename = $_SERVER['DOCUMENT_ROOT'] . self::NEW_PARSE_DIR_PATH . '/' . $file;

        // Вывод отладочной информации
        self::log(
            Loc::getMessage('LM_PRICELIST_START', [
                '#FILE#' => $file
            ])
        );

        list($supplier_id, $task_id, $other) = explode('_', $file);

        if (!$supplier_id
            || !$task_id)
        {
            throw new SystemException('[ERROR] Не верный формат наименования файла выгрузки '.$filename);
        }

        // проверка наличия поставщика в БД
        if ((new \VendorAutoSupplier($supplier_id))->exists() === false)
        {
            self::log(
                Loc::getMessage('LM_PRICELIST_SUPPLIER_NOT_FOUND', [
                    '#SUPPLIER#' => $supplier_id
                ]),
                false,
                LM_AUTO_DEBUG_ERROR
            );
            self::moveIncorrectFile($file);
            return;
        }

        // Проверим кодировку файла. Она должна быть UTF-8.
        if (!self::convertParsedFile($filename, 'cp1251'))
        {
            return;
        }

        // получение дескриптора на чтение файла
        try
        {
            $fd = fopen($filename, "r");
            // попытаемся заблокировать файл.
            if (!flock($fd, LOCK_EX))
            {
                fclose($fd);
                return;
            }
        }
        catch (\Exception $e)
        {
            self::log(
                Loc::getMessage('LM_PRICELIST_OPEN_ERROR', [
                    '#ERROR#' => $e->getMessage()
                ])
            );
            return;
        }
        // получение время жизни запчастей по идентификатору задания
        $ttl = self::getExpireByTaskId($task_id);
        if (!$ttl)
        {
            throw new SystemException('[ERROR] Не удалось получить ttl по текущий задаче #'.$task_id);
            return;
        }

        // Установка локали
        @setlocale(LC_COLLATE, "ru_RU.UTF-8");
        @setlocale(LC_CTYPE, "ru_RU.UTF-8");

        // Получаем пользовательские поля.
        $custom_fields = (new \VendorAutoCustomFields())->getFields();
        // Построчно импортируем данные
        $count = 0;
        $errors = 0;
        $cnt = 0;
        $time_per_iteration = microtime(1);

        self::log('Start import');
        $brands = [];
        while (($data = fgets($fd)) !== FALSE)
        {
            $cnt++;
            $row = self::preparePartFields(
                str_getcsv(
                    stripcslashes($data),
                    self::CSV_DELITIMIER
                ),
                $custom_fields,
                $supplier_id,
                $task_id,
                $ttl
            );
            if ($row)
            {
                $original_brand_title = $row["original_brand_title"];
                if (!array_key_exists($original_brand_title, $brands)) {

                    $brandObj = new \Company\Main\Api\Brands();
                    $stdBrand = $brandObj->standardizeBrand($original_brand_title);
                    if ($stdBrand !== false){
                        $brands[$original_brand_title] = $stdBrand;
                    }
                    else {
                        $brands[$original_brand_title] = $original_brand_title;
                    }
                    unset($stdBrand);
                    unset($brandObj);
                }
                $row["brand_title"] = $brands[$original_brand_title];
                /* Отправляем данные в очередь*/
                try
                {
                    \Company\Main\RabbitMQ\PricesBroker::sendMessage($row);
                }
                catch (\ErrorException $e)
                {
                    self::log('EXCEPTION CATCHED!!!\n');
                    Debug::writeToFile($e->getMessage(), 'ampq_price_parcer.log');
                    Debug::writeToFile($row, 'ampq_price_parser.log');
                    Debug::dumpToFile($row);
                }
                $count++;

                if ($count % 500 == 0)
                {
                    $spent = microtime(1) - $time_per_iteration;
                    $time_per_iteration = microtime(1);
                    self::log(
                        $count . ' entries imported [50000 per ' . number_format($spent, 2) . 's]'
                    );
                    Debug::writeToFile(
                        $count . ' entries imported [50000 per ' . number_format($spent, 2) . 's]', 'ampq_price_parser_success.log'
                    );
                }
            }
            else
            {
                self::log('[ERROR] Не удалось выполнить преобразование полей товара на позиции '.$cnt);
            }
            unset($row);
        }
        fclose($fd);

        echo $count;

        self::log('Total ' . $count . ' entries imported');
        self::log(
            Loc::getMessage('LM_PRICELIST_FINISHED', [
                    '#FILE#' => $file
                ]
            )
        );
        // переместим файл в успешно добавленные
        self::moveCorrectFile($file);
        // запишем в статистику
        self::$suppliers_stat[] = [
            'supplier_id' => $supplier_id,
            'count' => $count,
            'error' => $errors,
        ];
    }

    protected static function preparePartFields($data, $custom_fields, $supplier_id, $task_id, $ttl)
    {
        /*
         * Определение полей в CSV
         *
         * Старые прайсы:
         * AN113K;AKEBONO;"Название детали AN-113K";1052;10;-1
         *
         * Новые прайсы
         * AN113K;AKEBONO;"Название детали AN-113K";1052;10;-1;300;custom1;custom2;custom3
         */
        $brand_title = trim($data[0]);
        $original_brand_title = trim($data[0]);
        $article = trim($data[1]);
        $title = trim($data[2]);
        $price = trim($data[3]);
        // clean price
        $price = preg_replace("/[^0-9,.]/", "", $price);
        $quantity = trim($data[4]);
        // clean quantity
        $quantity = preg_replace("/[^0-9]/", "", $quantity);
        $group_id = trim($data[5]);
        $weight = trim($data[6]);
        // Почистим поле price от пробелов и заменим , на . (иначе пропадет дробная часть при вызове метода ForSQL)
        $price = str_replace(',', '.', $price);
        $price = str_replace(' ', '', $price);
        // Количество фиксированных полей.
        $index = 7;
        // $original_article = $article;
        $article = RedisHelper::cleanArticle($article);
        /*
         * Не импортируем товар с количеством 0 или менее
         */
        if (floatval($quantity) <= 0)
        {
            $errors++;
            self::log('Null quantity [' . $cnt . ']: ' . "'" . join("', '", $data) . "'");
            return false;
        }
        /*
         * Заменим запятую в цене.
         */
        $price = self::formatPrice($price);
        /*
         * Вставим кастомные значения в БД.
         */
        $custom = array();
        /*
         * Доп. поля
         * Сюда попадают все доп. цены
         */
        if (!empty($custom_fields))
        {
            foreach ($custom_fields as $custom_field)
            {
                $custom[$custom_field['code']] = trim($data[$index]);
                $index++;
                if (strstr($custom_field['code'],"_price")!==false)
                {
                    $custom[$custom_field['code']] = preg_replace("/[^0-9,.]/", "", $custom[$custom_field['code']]);
                }
                elseif ($custom_field['code'] == 'multiplication_factor')
                {
                    $custom[$custom_field['code']] = floatval($custom[$custom_field['code']]);
                }
            }
        }
        $brand_title = trim($brand_title);
        $row = array_merge([
            "title" => $title,
            "article" => strToUpper($article),
            // "original_article" => $original_article,
            "brand_title" => strToUpper($brand_title),
            "original_brand_title" => $original_brand_title,
            // "price" => floatval($price),
            "quantity" => doubleVal($quantity),
            // "group_id" => $group_id,
            "weight" => floatval($weight),
            "supplier_id" => (int) $supplier_id,
            "ttl" => (int) $ttl,
        ],
            $custom
        );

        if (!self::getPricesList($row, $task_id))
        {
            Debug::dumpToFile($row);
            return false;
        }

        return $row;
    }

    /*
     * На входе получаем строку прайс-листа
     * работаем с ценой и значением supplier_id, дабы вычислить наценки и скидки нужного поставщика
     */
    private function getPricesList(&$row, $task_id)
    {
        // Получение доступных типов цен
        $markups = PriceMarkup::getMarkupsByImportTaskId(intVal($task_id), true);
        foreach (array_keys(PricesHelper::getTypes()) as &$code)
        {
            if (!floatval($row[$code]))
            {
                // если не определена основная цена, то пропустить товар
                if (strtolower($code) == PricesHelper::INNER_PRICE)
                {
                    self::log('[ERROR] Не определена цена поставки. Поля товара: '.print_r($row, 1));
                    return false;
                }
                else
                {
                    continue;
                }
            }
            // если найдена наценка по текущему типу цены
            if (isset($markups[$code]))
            {
                // рассчет наценки
                $newValue = $row[$code] * (1 + ($markups[$code]*.01));
                // если после проценки невозможно продать товар
                if ($newValue <= 0)
                {
                    self::log('[ERROR] Не верно установлена наценка! Код цены: ['.$code.']; Исходная цена товара: '.$row[$code].'; Итоговая цена после установки наценки: '.$newValue);
                    continue;
                }
                $row[$code] = (float) self::formatPrice($newValue);
            }
        }

        return $row;
    }

    public static function formatPrice($price)
    {
        return number_format(str_replace(',', '.', $price), 2, '.', '');
    }

    /**
     * Проверка наличия новых файлов
     */
    protected function getFiles()
    {
        // получение времени последнего парсинга
        $lastExec = $this->getLastExecTime();
        $files = [];

        foreach (glob($_SERVER['DOCUMENT_ROOT'] . self::NEW_PARSE_DIR_PATH . '/*.csv') as $filename)
        {
            // получить файлы, которые были изменены(созданы) с момента последней выгрузки
            if (1 || filemtime($filename) > $lastExec)
            {
                $basename = basename($filename);
                $file = $_SERVER['DOCUMENT_ROOT'] . self::NEW_PARSE_DIR_PATH .'/' . $basename;
                // Вывод отладочной информации
                $this->log(
                    Loc::getMessage('LM_PRICELIST_FOUND', [
                            '#FILE#' => $basename
                        ]
                    )
                );
                // Читаем ли файл
                if (!is_readable($file))
                {
                    $this->log(
                        Loc::getMessage('LM_PRICELIST_NOT_READABLE', [
                                '#FILE#' => $basename
                            ]
                        )
                    );
                    $this->moveIncorrectFile($basename);
                    continue;
                }
                // Загружен ли файл до конца?
                elseif ($fd = fopen($file, 'r'))
                {
                    if (flock($fd, LOCK_EX))
                    {
                        flock($fd, LOCK_UN);
                        $files[] = $basename;
                    }
                    fclose($fd);
                }
            }
            else
            {
                $this->moveIncorrectFile($basename);
            }
        }

        return $files;
    }

    /**
     * Вычисление даты и времени жизни записей, которые будут получены в рамках текущего job-a
     * @param $task_id int идентификатор задания из таблицы b_lm_task_sheduler
     * @see: Базовая реализация /bitrix/modules/Vendor.autodownloader/classes/general/download_agent.php#108
     * @return integer время жизни
     */
    public static function getExpireByTaskId($task_id)
    {
        static $dayInSeconds = 86400,
        $hourInSeconds = 3600,
        $monthInSeconds = 2592000;

        if ($task = \VendorAutoTaskShedule::getById($task_id))
        {
            $task = $task->fetch();

            $now = time();
            $last_exec = strtotime($task['last_exec']);
            $start_time = explode(':', $task['start_time']);
            $start_time_seconds = ($start_time[0] * 60 * 60) + ($start_time[1] * 60) + $start_time[2];
            $today_seconds = $now - strtotime('today');

            $ttl = $now;
            // проверка текущего типа интервала обновления: час/ день (недели)/ дата(месяца)
            switch ((int) $task['interval'])
            {
                case $hourInSeconds:
                {
                    $ttl += strtotime('+1 hours');
                    break;
                }
                case $dayInSeconds:
                {
                    $currentDayNum = date('w');
                    $days = array_map('intval', explode(',', $task['days']));
                    // в расписании доступен только один день
                    if (count($days) == 1)
                    {
                        $nextDayNum = $days[0];
                    }
                    // получение ближайшего дня недели, следующего за текущем в расписании
                    else
                    {
                        $nextDayNum = 0;
                        // если текущий день больше последнего в расписании,
                        // то взять первый день из расписания
                        if (end($days) <= $currentDayNum)
                        {
                            $nextDayNum = reset($days);
                        }
                        // иначе взять из расписания ближайший больший день
                        else
                        {
                            for ($i=0; $i < count($days); ++$i)
                            {
                                if ($days[$i] > $currentDayNum)
                                {
                                    $nextDayNum = $days[$i];
                                    break;
                                }
                            }
                        }
                    }
                    //Вычисляем, сколько дней прибавить к текущей дате для получения TTL
                    $diff = $nextDayNum - $currentDayNum;
                    //$diff равно 0 в случае force-выполнения
                    if ($diff == 0) {
                        $diff = 1;
                    }
                    elseif ($diff < 0) {
                        $diff += 7;
                    }
                    $ttl = strtotime("+$diff day");
                    // Получаем полночь
                    $ttl = strtotime(date('Y-m-d', $ttl).'00:00:00');
                    // Стартовое время таска + 1 час
                    $ttl += $start_time_seconds + 3600;
                    break;
                }
                case $monthInSeconds:
                {
                    /*
                     * последний день месяца?
                     */
                    if (in_array($task['start_day'], ['last', '0']) == 'last')
                    {
                        if (date('d') == date('t'))
                        {
                            $ttl = strtotime('+1 month') + $start_time_seconds;
                        }
                        else
                        {
                            $ttl = strtotime(date('t.m.Y')) + $start_time_seconds;
                        }
                    }
                    else
                    {
                        // проверка чтобы день не выходил за рамки текушего месяца
                        $start_day = intval((int) $task['start_day'] <= (int) date('t') ? $task['start_day'] : date('t'));
                        // если дата запуска больше текущей даты
                        if ($start_day > (int) date('d'))
                        {
                            $ttl = strtotime(implode('.', [$start_day, date('m'), date('Y')])) + $start_time_seconds;
                        }
                        // иначе + 1 месяц
                        else
                        {
                            $ttl = strtotime(implode('.', [$start_day, date('m'), date('Y')]).' +1 month') + $start_time_seconds;
                        }
                    }
                    break;
                }
                // есть задачи у которых значение '0'
                default:
                {
                    $ttl = 2;
                    break;
                }
            }
            return $ttl;
        }
        return false;
    }
}