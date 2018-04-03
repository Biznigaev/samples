<?php
namespace Company\Main\RabbitMQ;

use Bitrix\Main\Config,
	Bitrix\Main\Diag\Debug,
	Bitrix\Main\SystemException,
	Bitrix\Main\Localization\Loc;

use PhpAmqpLib\Connection\AMQPStreamConnection,
	PhpAmqpLib\Message\AMQPMessage;

Loc::loadMessages(__FILE__);
/**
 * Клиент подключения к очереди rabbitmq
 * @todo: вынести в языковой файл сообщения об ошибках
 * @todo: создать свой класс исключений 
 */
class Client
{
	const CONFIG_KEY = 'rabbitmq';

	private static $connection,
				   $instance = null;

	private function __construct()
	{
		if (is_null(Config\Configuration::getValue(self::CONFIG_KEY)))
		{
			throw new SystemException('В файле /bitrix/.settings_extra.php отсутствует конфигурация для rabbitmq');
		}
		$config = Config\Configuration::getValue(self::CONFIG_KEY);
        try
        {
        	/**
        	 * @todo: протестировать на pconnect-е
        	 */
            self::$connection = new AMQPStreamConnection(
        		$config['host'], $config["port"],
        		$config["user"], $config["password"],
        		$config["vhost"]
        	);
        }
        catch (\ErrorException $e)
        {
            throw new SystemException('[ERROR] Ошибка подключения к rabbitmq: '.$e->getMessage());
            return false;
        }
	}

	protected function __clone() {}

	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	// создание канала и binding к точке обмена
	protected static function bindToExchange($exchangeName, $queueName)
	{
		// если канал успешно создан
		if ($channel = self::$connection->channel())
		{
			// объявление точки обмена если она не создана
			$channel->exchange_declare($exchangeName, 'direct', false, true, false);
			// объявление очереди если она не создана
			$channel->queue_declare($queueName, false, true, false, false);
			// привязка точки обмена к очереди для возможности последующей отправки сообщений в очередь
			$channel->queue_bind($queueName, $exchangeName);

			return $channel;
		}
		// ошибка создания канала
		else
		{
			throw new SystemException('[ERROR] Не удалось создать канал через текущее подключение');
		}
	}

	// разорвать соедиенние
	public function __destruct()
	{
		if (self::$connection)
		{
			self::$connection->close();
		}
	}
}