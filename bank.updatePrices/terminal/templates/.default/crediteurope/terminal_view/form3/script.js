var path = window.location.pathname.substring(0,window.location.pathname.lastIndexOf('/')),
//    если окон не активно, то запросы не посылаются
hidden = "hidden",
refreshBlock1,refreshBlock2,refreshBlock3,
sleep1 = 10000,
sleep2 = 5000,
sleep3 = 10000

// Standards:
if (hidden in document)
{
	document.addEventListener("visibilitychange", onchange)
}
else if ((hidden = "mozHidden") in document)
{
	document.addEventListener("mozvisibilitychange", onchange)
}
else if ((hidden = "webkitHidden") in document)
{
	document.addEventListener("webkitvisibilitychange", onchange)
}
else if ((hidden = "msHidden") in document)
{
	document.addEventListener("msvisibilitychange", onchange)
}
// IE 9 and lower:
else if ('onfocusin' in document)
{
	document.onfocusin = document.onfocusout = onchange
}
// All others:
else
{
	window.onpageshow = window.onpagehide = window.onfocus = window.onblur = onchange
}
function onchange (evt)
{
    var v = 'visible',
    	h = 'hidden',
        evtMap = { 
            focus:    v, 
            focusin:  v, 
            pageshow: v, 
            blur:     h, 
            focusout: h, 
            pagehide: h 
        }

    evt = evt || window.event
    if (evt.type in evtMap)
    {
        document.body.className = evtMap[evt.type]
    }
    else
    {
        document.body.className = this[hidden] ? "hidden" : "visible"
    }
}
function appendBlock1(data)
{
	if (data == null)
	{
		return
	}
	var ptr = $('#currList tbody tr').size() > 0,
		time = 0
	for (time in data) break

	if (!time.length || !Object.keys(data[time]).length)
	{
		return
	}
	if (ptr)
	{
		$('#currList tbody tr').each(function()
		{
			$(this).find('.CUR').html('<span><b>'+data[time][$(this).attr('id')].CUR+'</b></span>').end()
				   .find('.AVG').html('<span><b>'+data[time][$(this).attr('id')].AVG+'</b></span>').end()
				   .find('.RUB').html('<span><b>'+data[time][$(this).attr('id')].RUB+'</b></span>')
		})
	}
	else
	{
		var htmlResult = ''
		for (var i=0 in data[time])
		{
			htmlResult += '<tr id="'+i+'">'
				htmlResult += '<td align="right"><span><b>'+i+'</b></span></td>'
				htmlResult += '<td class="CUR" align="right"><span><b>'+data[time][i].CUR+'</b></span></td>'
				htmlResult += '<td class="AVG" align="right"><span><b>'+data[time][i].AVG+'</b></span></td>'
				htmlResult += '<td class="RUB" align="right"><span><b>'+data[time][i].RUB+'</b></span></td>'
			htmlResult += '</tr>'
		}
		$('#data-div1 tbody').html(htmlResult)
	}
	//    автокоррекция интервала между запросами (относительно последнего времени обновления данных по репорту)
	if (time > 0)
	{
		sleep1 = 10000-(Math.floor(new Date().getTime()/1000)-parseInt(time))
		clearInterval(refreshBlock1)
		setTimeout(function(){
			refreshBlock1 = setInterval(intervalBlock1(),sleep1)
		},sleep1)
	}
}
function appendBlock2(data)
{
	var ptr = $('#operList tbody tr').size() > 0,
		time = 0
	for (time in data) break

	if (!time.length || !Object.keys(data[time]).length)
	{
		return
	}
	for (var i=0 in data[time])
	{
		if (data[time][i] == false)
		{
			continue
		}
		//    форматированный вывод времени
		var formatTime = ''
		for (var j=0;j<data[time][i].ENTRY_TIME.length;++j)
		{
			if (j > 0 && j % 2 == 0)
			{
				formatTime += ':'
			}
			formatTime += data[time][i].ENTRY_TIME[j]
		}
		data[time][i].ENTRY_TIME = formatTime
		//    если таблица - заполняем её полностью
		if (!ptr)
		{
			$($('<tr id="'+i+'" />').append($('<td class="ENTRY_TIME" />').html(data[time][i].ENTRY_TIME))
									.append($('<td class="REFERENCE_NUMBER" />').html(i))
									.append($('<td class="BUY_CURRENCY_CODE" />').html(data[time][i].BUY_CURRENCY_CODE))
									.append($('<td class="BUY_AMOUNT" />').html(data[time][i].BUY_AMOUNT))
									.append($('<td class="SELL_CURRENCY_CODE" />').html(data[time][i].SELL_CURRENCY_CODE))
									.append($('<td class="SELL_AMOUNT" />').html(data[time][i].SELL_AMOUNT))
									.append($('<td class="OPERATION_RATE" />').html(data[time][i].OPERATION_RATE))
									.append($('<td class="OPERATION_USER" />').html(data[time][i].OPERATION_USER))
									.append($('<td class="APPROVE_USER" />').html(data[time][i].APPROVE_USER))
									.append($('<td class="RATE" />').html(data[time][i].RATE))
									.append($('<td class="COMMENTS" />').html(data[time][i].COMMENTS))
			).prependTo('#operList tbody')
		}
		//    иначе - пуста: дополняем
		else
		{
			//    если элемент уже выведен, то обновляем его
			if ($('#'+i).size())
			{
				$('#'+i+' .ENTRY_TIME').html(data[time][i].ENTRY_TIME)
				$('#'+i+' .BUY_CURRENCY_CODE').html(data[time][i].BUY_CURRENCY_CODE)
				$('#'+i+' .BUY_AMOUNT').html(data[time][i].BUY_AMOUNT)
				$('#'+i+' .SELL_CURRENCY_CODE').html(data[time][i].SELL_CURRENCY_CODE)
				$('#'+i+' .SELL_AMOUNT').html(data[time][i].SELL_AMOUNT)
				$('#'+i+' .OPERATION_RATE').html(data[time][i].OPERATION_RATE)
				$('#'+i+' .OPERATION_USER').html(data[time][i].OPERATION_USER)
				$('#'+i+' .APPROVE_USER').html(data[time][i].APPROVE_USER)
				$('#'+i+' .RATE').html(data[time][i].RATE)
				$('#'+i+' .COMMENTS').html(data[time][i].COMMENTS)
				continue
			}
			$($('<tr id="'+i+'" />').append($('<td class="ENTRY_TIME" />').html(data[time][i].ENTRY_TIME))
									.append($('<td class="REFERENCE_NUMBER" />').html(i))
									.append($('<td class="BUY_CURRENCY_CODE" />').html(data[time][i].BUY_CURRENCY_CODE))
									.append($('<td class="BUY_AMOUNT" />').html(data[time][i].BUY_AMOUNT))
									.append($('<td class="SELL_CURRENCY_CODE" />').html(data[time][i].SELL_CURRENCY_CODE))
									.append($('<td class="SELL_AMOUNT" />').html(data[time][i].SELL_AMOUNT))
									.append($('<td class="OPERATION_RATE" />').html(data[time][i].OPERATION_RATE))
									.append($('<td class="OPERATION_USER" />').html(data[time][i].OPERATION_USER))
									.append($('<td class="APPROVE_USER" />').html(data[time][i].APPROVE_USER))
									.append($('<td class="RATE" />').html(data[time][i].RATE))
									.append($('<td class="COMMENTS" />').html(data[time][i].COMMENTS))
			).prependTo('#operList tbody')
		}
	}
	//    автокоррекция интервала между запросами (относительно последнего времени обновления данных по репорту
	if (time > 0)
	{
		sleep2 = 5000-(Math.floor(new Date().getTime()/1000)-parseInt(time))
		clearInterval(refreshBlock2)
		setTimeout(function(){
			refreshBlock2 = setInterval(function(){intervalBlock2()},sleep2)
		},sleep2)
	}
}
function appendBlock3(data)
{
	var time = 0
	for (time in data) break
	
	if (!time.length || !Object.keys(data[time]).length)
	{
		$('#currList3').attr('style','display:none;')
		return
	}
	var htmlResult = ''
	for (var i in data[time])
	{
		htmlResult += '<tr>'
			htmlResult += '<td align="left"><b>'+data[time][i].OPERATIONTIME+'</b></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].BUYCURRENCY+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].BUYAMOUNT+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].SELLCURRENCY+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].SELLAMOUNT+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].MICEXRATE+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].SWAPPOINT+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].RATE+'</b></span></td>'
			htmlResult += '<td align="right"><span><b>'+data[time][i].VALUEDATE+'</b></span></td>'
		htmlResult += '</tr>'
	}
	$('#currList3').removeAttr('style').find('tbody').html(htmlResult)
	//    автокоррекция интервала между запросами (относительно последнего времени обновления данных по репорту
	if (time > 0)
	{
		sleep3 = 10000-(Math.floor(new Date().getTime()/1000)-parseInt(time))
		clearInterval(refreshBlock3)
		setTimeout(function(){
			refreshBlock3 = setInterval(intervalBlock3(),sleep3)
		},sleep3)
	}
}
function intervalBlock1()
{
	/*if (document.body.className != 'visible')
	{
		return
	}*/
	$.ajax({
		type:'POST',
		url :path+'/ajax/report1/',
		contentType: 'application/json; charset=utf8',
		dataType: 'json',
		success: appendBlock1
	})
}
function intervalBlock2()
{
	/*if (document.body.className != 'visible')
	{
		return
	}*/
	$.ajax({
		type:'POST',
		url :path+'/ajax/report2/',
		contentType: 'application/json; charset=utf8',
		dataType: 'json',
		success: appendBlock2
	})
}
function intervalBlock3()
{
	/*if (document.body.className != 'visible')
	{
		return
	}*/
	$.ajax({
		type:'POST',
		url :path+'/ajax/report3/',
		contentType: 'application/json; charset=utf8',
		dataType: 'json',
		success: appendBlock3
	})
}
if (document.getElementsByTagName('body')[0].getAttribute('id') == 'user_is_authorized')
{
	refreshBlock1 = setInterval(function(){intervalBlock1()}, sleep1)
	refreshBlock2 = setInterval(function(){intervalBlock2()}, sleep2)
	refreshBlock3 = setInterval(function(){intervalBlock3()}, sleep3)
}