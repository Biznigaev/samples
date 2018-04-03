<?if(!Defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

class EmailSending
{
	/**
	 * @todo: Хранение шаблона в БД
	 */
	const MESSAGE = '
		<html>
			<body>
				...
			</body>
		</html>';
	private static $eol;
	private static $un;
	private static $additionalParams;
	private static $from = 'infosupport@company.ru';
	private $to;
	private $subject;
	private $body='';
	private $head='';

	public function __construct($_to, $_subject, $bodyPlaceholders)
	{
		if (is_null(self::$un))
		{
			self::$un = strtoupper(uniqid(time()));
		}
		if (is_null(self::$eol))
		{
			self::$eol = CAllEvent::GetMailEOL();
		}
		if (is_null(self::$additionalParams))
		{
			self::$additionalParams = COption::GetOptionString("main", "mail_additional_parameters", "");
		}
		$this->to = $_to;
		$this->subject = $_subject;
		// header
		$this->head .= "Mime-Version: 1.0".self::$eol;
		$this->head .= "From: ".self::$from.self::$eol;
		// $head .= "To: $to".$eol;
		$this->head .= "X-Priority: 3 (Normal)".self::$eol;
		$this->head .= "X-EVENT_NAME: ISALE_KEY_F_SEND".self::$eol;
		$this->head .= "Content-Type: multipart/mixed; ";
		$this->head .= "boundary=\"----".self::$un."\"".self::$eol.self::$eol;

		// body
		$this->body = "------".self::$un.self::$eol;
		$this->body .= "Content-Type:text/html; charset=UTF-8".self::$eol;
		$this->body .= "Content-Transfer-Encoding: 8bit".self::$eol.self::$eol;
		$this->body .= str_replace(array_keys($bodyPlaceholders), array_values($bodyPlaceholders), self::MESSAGE).self::$eol.self::$eol;
	}
	public function attachFromComponentTemplate($filename, $filepath, $unlinkAfterAttachment = false)
	{
		$this->body .= "------".self::$un.self::$eol;
		$this->body .= "Content-Type: application/octet-stream; name=\"".$filename."\"".self::$eol;
		$this->body .= "Content-Disposition:attachment; filename=\"".$filename."\"".self::$eol;
		$this->body .= "Content-Transfer-Encoding: base64".self::$eol.self::$eol;
		
		$fd = fopen($filepath, "rb");
		$this->body .= chunk_split(base64_encode(fread($fd, filesize($filepath)))).self::$eol.self::$eol;
		fclose($fd);
		
		if ($unlinkAfterAttachment)
		{
			unlink($filepath);
		}
	}
	public function send()
	{
		$this->body .= "------".self::$un."--";
		return  mail($this->to, $this->subject, $this->body, $this->head, self::$additionalParams);
	}
}