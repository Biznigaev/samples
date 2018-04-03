var redis = require('redis');
var redisConn = redis.createClient(
	6379, 'redis.local', {
		password: '!@!#F$@#T%U$J^',
		socket_keepalive: true
	}
);
var mysql = require('mysql');
var mysqlConn = mysql.createConnection(
{
	host:'db.local',
	user:'nodejs',
	password:'%Vub!@E#$F$%G%@6rU&78',
	database:'products'
});

redisConn.subscribe("prices");
redisConn.on('message', function(channel, message)
{
	var messageFields = JSON.parse(message);
	mysqlConn.query({
			sql: mysql.format('\
				SELECT id \
				FROM b_lm_products \
				WHERE \
					article=?\
				AND brand_title=?\
				AND supplier_id=?', [
					messageFields.article, 
					messageFields.brand_title, 
					messageFields.supplier_id
				]
			),
			timeout: 5000, // 30s
		},
		function (err, rows, fields)
		{
			if (err) 
			{
				throw err;
			}
			if (rows.length)
			{
				mysqlConn.query({
					sql: mysql.format('\
						UPDATE b_lm_products\
						SET title=?,\
							article=?,\
							original_article=?,\
							brand_title=?,\
							price=?,\
							quantity=?,\
							group_id=?,\
							weight=?,\
							supplier_id=?,\
							multiplication_factor=?,\
							distrib_price=?,\
							diller_price=?,\
							opt_price=?,\
							corp_price=?,\
							code_autorus=?,\
							goods_autorus=?,\
							modified=?,\
							commerce_price=?,\
							retail_price=?,\
							minimal_price=?,\
							supplier_price=?\
						\
						WHERE \
							id=?', [
						messageFields.title,
						messageFields.article,
						messageFields.original_article,
						messageFields.brand_title,
						messageFields.price,
						messageFields.quantity,
						messageFields.group_id,
						messageFields.weight,
						messageFields.supplier_id,
						messageFields.multiplication_factor,
						messageFields.distrib_price,
						messageFields.diller_price,
						messageFields.opt_price,
						messageFields.corp_price,
						messageFields.code_autorus,
						messageFields.goods_autorus,
						messageFields.modified,
						messageFields.commerce_price,
						messageFields.retail_price,
						messageFields.minimal_price,
						messageFields.supplier_price,
						rows[0].id
					])
				}, function(error, results, fields)
				{
					if (error)
					{
						throw error;
					}
					console.log('[UPDATE] #'+rows[0].id);
				});				
			}
			else
			{
				mysqlConn.query(
					'INSERT INTO b_lm_products SET ?', {
						'title': messageFields.title,
						'article': messageFields.article,
						'original_article': messageFields.original_article,
						'brand_title': messageFields.brand_title,
						'price': messageFields.price,
						'quantity': messageFields.quantity,
						'group_id': messageFields.group_id,
						'weight': messageFields.weight,
						'supplier_id': messageFields.supplier_id,
						'multiplication_factor': messageFields.multiplication_factor,
						'distrib_price': messageFields.distrib_price,
						'diller_price': messageFields.diller_price,
						'opt_price': messageFields.opt_price,
						'corp_price': messageFields.corp_price,
						'code_autorus': messageFields.code_autorus,
						'goods_autorus': messageFields.goods_autorus,
						'modified': messageFields.modified,
						'commerce_price': messageFields.commerce_price,
						'retail_price': messageFields.retail_price,
						'minimal_price': messageFields.minimal_price,
						'supplier_price': messageFields.supplier_price,
					},
					function(error, results, fields)
					{
						if (error)
						{
							throw error;
						}
						console.log('[INSERT] #'+results.insertId);
					}
				);
			}
		}
	);
});