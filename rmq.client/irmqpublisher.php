<?php

namespace Company\Main\RabbitMQ;

interface IRmqPublisher
{
	public static function sendMessage($message);
	public static function parseMessageFields(&$fields);
}