<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/**
 *    @todo добавить CRC32 для каждого ключа, чтобы можно было пропустить запись, если данные не изменились
 *    @todo организовать логирование считывания данных из кэша
 *    @todo убрать статическое объявление пути скрипта-инициатора запуска из формирования ключа
 */
/**
 *    Класс для работы с Memcached, который хранит 
 *	  конечные данные для отчётов приложения /applications/terminal_new/form3.php
 *                                         и /applications/terminal_new/form4.php
 */
class CTreasuryReports
{
	private $host    = '127.0.0.1';
	private $port    = 11211;
	private $timeout = 2;//    sec
	private $expire  = 300;//    5min
	private $db;
	//    конструктор создаёт уникальный long-коннект к кэшу
	private function __construct()
	{
		$this->db = new Memcache();
		$this->db->pconnect($this->host,$this->port,$this->timeout) or die("Could not connect");
	}

	//    получить ttl кэша
	public function getExpire()
	{
		$ob = new self();
		return $ob->expire;
	}
	/**
	 *    Задать ttl кэша
	 *    @tid
	 */
	public function setExpire($val)
	{
		if (!is_int($val))
		{
			return false;
		}
		$this->expire = $val;
		return true;
	}
	/**
	 *    Получить данные по пользователям, которые запросили отчёт №4
	 *    @return array массив пользователей, которые запросили данные по отчёту №4
	 */
	public function GetAjax4Users()
	{
		$ob = new self();
		//    если тэг в кэше отсутствуют, то создать его с пустым наполнением
		if (!$rs = $ob->db->get('USERS'))
		{
			$ob->db->set('USERS',print_r(json_encode([]),1),false,$ob->expire);
			return [];
		}

		return (array)json_decode($rs);
	}
	/**
	 *    Обновить список пользователей, которые запросили ресурсы по отчёту №4
	 */
	private function UpdateAjax4Users()
	{
		//    получаем данные по текущему пользователю и обновляем время по последнему запросу данных для него
		global $USER;
		$login = strToLower($USER->GetLogin());
		$aUsers = $this->GetAjax4Users();
		//    обновляем время последнего обращения
		$aUsers[$login] = time();
		$this->db->set(
			'USERS',
			print_r(
				json_encode($aUsers),1
			),
			false,
			$this->expire
		);
	}
	/**
	 *    Задать время последнего ajax-запроса данных из кэша
	 *    @param int time - время, на которое нужно обновить текущее значение
	 *    @return void
	 */
	public function SetLastQuery($time)
	{
		$ob = new self;
		$ob->db->set('LAST_QUERY', $time, false, 86400);
	}
	/**
	 *    Получить время последнего ajax-запроса данных из кэша
	 *    @param формат даты, в котором нужно отдать данные (по умоланию timestamp)
	 *    @return int|date дата последнего ajax-запроса данных из кэша
	 */
	public function GetLastQuery($strFormat)
	{
		$ob = new self();
		$timestamp = $ob->db->get('LAST_QUERY');
		if (empty($timestamp))
		{
			return false;
		}
		if (strlen($strFormat))
		{
			return date("d.m.Y H:i:s",$timestamp);
		}

		return $timestamp;
	}
	/**
	 *    Получить время последнего изменения отчёта, которое пришло из БД
	 *    @param int $rID идентификатор отчёта
	 *    @return время последнего изменения по отчёту
	 */
	public function GetLastUpdate($rID)
	{
		$ob = new self();
		$tag = 'last_update_'.$rID;
		return (int) $ob->db->get($tag);
	}
	/**
	 *    Изменить время последнего изменения отчёта
	 *    @param int $rID идентификатор отчёта
	 *    @return void
	 */
	private function SetLastUpdate($rID)
	{
		$now = (string) time();
		$tag = 'last_update_'.$rID;
		$this->db->set($tag,$now);
	}
	/**
	 *    Обновить данные по отчёту
	 *    @param string $key - открытый ключ подписи
	 *    @param string $tag - название отчёта, который надо изменить
	 *    @param string $val - данные в формате json
	 *    @return void
	 */
	public function SetReport($key,$tag,$val)
	{
		$ob = new self();
		//    сжатие данных (для оптимизации кол-ва шагов шифрования)
		$val = gzcompress($val,9);
		//    формирование ключа
		$hash = mhash(MHASH_MD5,$key.$arParams['SEF_FOLDER'].'/index.php');
		$len = mb_strlen($val);
		//    шифрование данных
		for ($i=0;$i<$len;$val[$i]=chr(ord($val[$i])^ord($hash[$i%strlen($hash)])),++$i);
		//    обновляем отчёт
		$ob->db->set($tag,$val,false,$ob->expire);
		//    задаём время последнего изменения отчёта
		$ob->SetLastUpdate(substr($tag,-1));
	}
	/**
	 *    Получение и разшифровка отчёта по его назнанию
	 *    @param string $tag тэг отчёта под которым он хранится в кэше
	 *    @param string $key открытый ключ, которым подписаны данные отчёта
	 *    @return array массив данных для текущего пользователя по отчёту №$tag
	 */
	private function GetReport($tag,$key)
	{
		$str = $this->db->get($tag);
		$hash = mhash(MHASH_MD5,$key.$arParams['SEF_FOLDER'].'/index.php');
		$len = mb_strlen($str);
		//    дешифрация
		for ($i=0;$i<$len;$str[$i]=chr(ord($hash[$i % strlen($hash)])^ord($str[$i])),++$i);
		//    задать время последнего запроса (чтобы не сдох демон)
		$this->SetLastQuery(time());
	
		return '{"'.self::GetLastUpdate(substr($tag,-1)).'":'.gzuncompress($str).'}';
	}
	/**
	 *    Получение 1-го отчёта
	 *    @param $key открытый ключ подписи (нужен для расшифрования данных)
	 *    @return array массив данных для текущего пользователя по отчёту №1
	 */
	public function GetFirstReport($key)
	{
		$db = new self();
		return $db->GetReport('ajax1', $key);
	}
	/**
	 *    Получение 2-го отчёта
	 *    @param $key открытый ключ подписи (нужен для расшифрования данных)
	 *    @return array массив данных для текущего пользователя по отчёту №2
	 */
	public function GetSecondReport($key)
	{
		$db = new self();
		return $db->GetReport('ajax2', $key);
	}
	/**
	 *    Получение 3-го отчёта
	 *    @param $key открытый ключ подписи (нужен для расшифрования данных)
	 *    @return array массив данных для текущего пользователя по отчёту №3
	 */
	public function GetThirdReport($key)
	{
		global $APPLICATION;
		$db = new self();
		return $db->GetReport('ajax3', $key);
	}
	/**
	 *    Получение 4-го отчёта
	 *    @param $key открытый ключ подписи (нужен для расшифрования данных)
	 *    @return array массив данных для текущего пользователя по отчёту №4
	 */
	public function GetFourthReport($key)
	{
		//    получение данных по текущему пользователю
		global $USER;
		$ob = new self();
		//    получение логина
		$login = $USER->GetLogin();
		//    получение пароля
		$dbPwd = CUser::GetList(
			($by="id"), 
			($order="asc"),
			array('LOGIN_EQUAL' => $login),
			array('FIELDS' => array('PASSWORD'))
		)->Fetch();
		$pwd = $dbPwd['PASSWORD'];
		//    обновляем последнее время запроса текушего пользователя
		$ob->UpdateAjax4Users();
		//    дополняем открытый ключ паролём текущего пользователя
		return $ob->GetReport(strToLower($login), $key.$pwd);
	}
	/**
	 *    Функция логирования
	 *    @param string $sText - текст, который нужно записать в начало файла
	 */
	public function AddMessage2Log($sText)
	{
		if ($fp = fopen(dirname(__FILE__).'/logs/ctreasuryreports.txt', "ab+"))
        {
            if (flock($fp, LOCK_EX))
            {
                fwrite($fp, $sText."\n");
                fwrite($fp, "----------\n");
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
	}
}