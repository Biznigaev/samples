'use strict';

var amqp = require('amqplib/callback_api');
var redis = require('redis');
var zlib = require('zlib');
var Buffer = require('buffer').Buffer;

var amqpConn = null;
var redisConn = null;

var mappingFields = require('./fields.json');

function workWithQueue(options)
{
	amqp.connect('amqp://'+options.user+':'+options.pass+'@'+options.host+':'+options.port+'/?heartbeat=60', function(err, conn) 
	{
		if (err)
		{
			console.error("[AMQP]", err.message);
			return setTimeout(workWithQueue, 1000);
		}
		conn.on("error", function(err) 
		{
			if (err.message !== "Connection closing") 
			{
				console.error("[AMQP] conn error", err.message);
			}
		});
		conn.on("close", function() 
		{
			console.error("[AMQP] reconnecting");
			return setTimeout(workWithQueue, 1000);
		});
		console.log("[AMQP] connected");
		amqpConn = conn;
		whenQueueConnected();
	});
}

function whenQueueConnected()
{
	amqpConn.createChannel(function(err, ch)
	{
		if (closeOnErr(err)) 
			return;

		ch.on("error", function(err) 
		{
			console.error("[AMQP] channel error", err.message);
		});

	    ch.on("close", function() 
	    {
			console.log("[AMQP] channel closed");
	    });

	    ch.prefetch(1);

		var qName = 'api.products.price.set';
		ch.assertQueue(qName, { durable: true }, function(err, _ok) 
		{
			if (closeOnErr(err))
				return;
		
			ch.consume(qName, function(msg)
			{
				messageProcessing(msg, function(ok) 
				{
					try
					{
						if (ok)
						{
							ch.ack(msg);
						}
						else
						{
							console.log('[WARNING] Сообщение отклонено: '+msg.content.toString());
							ch.reject(msg, false/* удалять сообщение из очереди */);
						}
					}
					catch (e)
					{
						closeOnErr(e);
					}
				});
			}, { 
				noAck: false 
			});
		});
	});
}

function bindKeys(row)
{
	var result = {};

	for (var i in row)
	{
		if (Object.keys(mappingFields).indexOf(i) == -1)
		{
			continue;
		}
		// исключение пустых полей
		if (row[i] === null 
			|| row[i].toString().length === 0)
		{
			continue;
		}
		result[mappingFields[i]] = row[i];
	}

	return result;
}

function messageProcessing(msg, callback) 
{
	if (msg.content.toString() === 'false')
	{
		console.error('[ERROR] Fail to read message: '+(new Buffer(JSON.stringify(msg))));
		callback(false);
	}
	else
	{
		console.log("Got msg", msg.content.toString());
		var row = {};
		try
		{
			row = JSON.parse(
				msg.content.toString('utf8')
			);
		}
		catch (e)
		{
			console.error('[ERROR] Ошибка преобразования в JSON. Передано не валидное сообщение');
			callback(false);
			return;
		}
		// проверка просроченности записи
		// if (checkExpire(row['ttl']))
		if (0)
		{
			console.error('[ERROR] Передана просроченная запись ', row);
			callback(true);
		}
		else
		{
			row['modified'] = new Date().getTime()/1000;
			if (row.brand_title === undefined || row.article === undefined){
				console.log("Нет ключевых полей объекта");
				callback(false);
				return;
			}

			const key = row.article.toString().toUpperCase()+':'+row.brand_title.toString().toUpperCase();
			const hashKey = row.supplier_id;
			const value = bindKeys(row);
			var valueAsJson = JSON.stringify(value)

			zlib.deflate(
				new Buffer(valueAsJson),
				{
					level: zlib.Z_BEST_COMPRESSION
				},
				function(err, buffer)
				{
					if (err)
					{
						console.error('[ERROR] Не удалось выполнить сжатие данных');
						callback(false);
					}
					else
					{
						if (redisConn.hset(key, hashKey, new Buffer(valueAsJson), redis.print) === true)
						{
							redisConn.publish('prices', JSON.stringify(row));
							callback(true);
						}
						else
						{
							console.error('[ERROR] Не удалось записать в redis');
							callback(false);
						}
					}
				}
			);
		}
	}
}

function checkExpire(ttl)
{
	return (new Date().getTime()) < (ttl*1000);
}

function closeOnErr(err)
{
	if (!err)
	{
		return false;
	}

	console.error("[AMQP] error", err);
	amqpConn.close();

	return true;
}

function workWithRedis(options)
{
	try
	{
		if (options.socket != undefined)
		{
			redisConn = redis.createClient(
		    	options.socket, {
		    		password: options.pass,
		    		socket_keepalive: true
		    	}
		    );
	    }
	    else
	    {
			redisConn = redis.createClient(
		    	options.port, options.host, {
		    		password: options.pass,
		    		socket_keepalive: true
		    	}
		    );
	    }
    }
    catch (e)
    {
    	console.error('Ошибка подключения к redis:\n'+e);
    }
    console.log('[Redis] connected');
    // whenDbConnected();
}

workWithRedis({
	// socket: '/tmp/redis.sock',
	host: 'redis.local',
	port: 6379,
	pass: '!q2W#e4Rasdsfrwgwrg4'
});

workWithQueue({
	user: 'superrabbit',
	pass: '!@$Y%&!q21342W#tfe4R',
	host: 'mq.local',
	port: 5672
});