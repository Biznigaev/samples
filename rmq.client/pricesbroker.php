<?php
namespace Company\Main\RabbitMQ;

use Bitrix\Main\SystemException,
	Bitrix\Main\Localization\Loc;

use PhpAmqpLib\Message\AMQPMessage;

Loc::loadMessages(__FILE__);
/**
 * Класс отвечает за отправку сообщений в брокер очередей
 * @todo: вынести опредение обязательных полей в абстрактный класс
 */
class PricesBroker extends Client implements IRmqPublisher
{
	const MESSAGE_CONTENT_TYPE = "text/plain";

	private static $mappingFields = [
		0 => 'title', 
		1 => 'quantity', 
		2 => 'weight', 
		3 => 'multiplication_factor', 
		4 => 'code_company',
		5 => 'commerce_price',
		6 => 'retail_price',
		7 => 'minimal_price',
		8 => 'supplier_price',
		9 => 'ttl',
		10=> 'goods_company',
		11=> 'article',
		12=> 'brand_title',
		13=> 'supplier_id',
        14=> 'original_brand_title',
	];

	private static $channel=null,
				   $queue = 'api.Company.products.price.set',
				   $exchange = 'api.Company.priceparser.product.price.add';
	/*
	private static $exchangeTest = 'api.Company.priceparser.product.price.add.test',
				   $queueTest = 'api.Company.products.price.set.test';
	*/

	public static function sendMessage($message)
	{
		if (!is_array($message))
		{
			throw new SystemException('Передан не верный формат сообещения. На входе ожидается array');
		}
		self::parseMessageFields($message);
		$broker = parent::getInstance();
		// канал создается во время отправки первого сообщения
		if (is_null(self::$channel))
		{
			self::$channel = $broker->bindToExchange(self::$exchange, self::$queue);
		}
		if ($messageBody = json_encode(
			    $message,
			    JSON_UNESCAPED_UNICODE
			    |JSON_UNESCAPED_SLASHES
			    |JSON_PARTIAL_OUTPUT_ON_ERROR
			)
		)
		{
	        $message = new AMQPMessage($messageBody, [
		        	'content_type' => self::MESSAGE_CONTENT_TYPE, 
		        	'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
		        ]
			);
			/**
			 * @todo: обрабатывать ответ из очереди
			 */
    		self::$channel->basic_publish($message, self::$exchange);

	        return true;
	    }
	    else
	    {
	    	return false;
	    }
	}
	
	// проверить форма передаваемого в очередь сообщения
	public static function parseMessageFields(&$fields)
	{
		// проверка на лишние поля
		foreach (array_keys($fields) as $fieldName)
		{
			if (!in_array($fieldName, self::$mappingFields))
			{
				unset($fields[$fieldName]);
				// throw new SystemException('[ERROR] Не верный формат сообщения! Переданное поле '.$fieldName.' не найдено в шаблоне');
			}
		}
		// проверка на наличие обязательных полей
		foreach (self::$mappingFields as $fieldName)
		{
			if (!array_key_exists($fieldName, $fields))
			{
				throw new SystemException('[ERROR] Не верный формат сообщения! Поле '.$fieldName.' не было передано в сообщении');
			}
		}
	}

	// уничтожить канал
	public function __destruct()
	{
		if (self::$channel)
		{
			self::$channel->close();
		}
	}
}