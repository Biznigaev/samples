var path = window.location.pathname
path = path.substring(0,path.lastIndexOf('/'))
//    если окон не активно, то запросы не посылаются
var hidden = "hidden",
	intervalBlock4,
	sleep4 = 2000

// Standards:
if (hidden in document)
    document.addEventListener("visibilitychange", onchange);
else if ((hidden = "mozHidden") in document)
    document.addEventListener("mozvisibilitychange", onchange);
else if ((hidden = "webkitHidden") in document)
    document.addEventListener("webkitvisibilitychange", onchange);
else if ((hidden = "msHidden") in document)
    document.addEventListener("msvisibilitychange", onchange);
// IE 9 and lower:
else if ('onfocusin' in document)
    document.onfocusin = document.onfocusout = onchange;
// All others:
else
    window.onpageshow = window.onpagehide 
        = window.onfocus = window.onblur = onchange;

function onchange (evt) {
    var v = 'visible', h = 'hidden',
        evtMap = { 
            focus:v, focusin:v, pageshow:v, blur:h, focusout:h, pagehide:h 
        };

    evt = evt || window.event;
    if (evt.type in evtMap)
        document.body.className = evtMap[evt.type];
    else        
        document.body.className = this[hidden] ? "hidden" : "visible";
}
function appendBlock4(data)
{
	if (data == null)
	{
		return
	}
	var time = 0
	for (time in data) break

	if (!time.length || !data[time])
	{
		return
	}
	if ($('#currList tbody tr').size() > 0)
	{
		for (var i=0 in data[time])
		{
			for (var j=0 in data[time][i])
			{
				if ($('#'+i+' .'+j).text() != data[time][i][j])
				{
					$('#'+i+' .'+j).html('<span><b>'+data[time][i][j]+'</b></span>')
				}
			}
		}
	}
	else
	{
		var tableHtml = ''
		for (var i=0 in data[time])
		{
			tableHtml += '<tr id="'+i+'">'
				tableHtml += '<td bgcolor="#e1dae3"><b>'+i+'</b></span></td>'
				tableHtml += '<td class="BUY" align="right"><span><b>'+data[time][i].BUY+'</b></span></td>'
				tableHtml += '<td class="SELL" align="right"><span><b>'+data[time][i].SELL+'</b></span></td>'
				tableHtml += '<td class="RATE" align="right"><span><b>'+data[time][i].RATE+'</b></span></td>'
				tableHtml += '<td class="BUY_SPREAD" align="right"><span><b>'+data[time][i].BUY_SPREAD+'</b></span></td>'
				tableHtml += '<td class="SELL_SPREAD" align="right"><span><b>'+data[time][i].SELL_SPREAD+'</b></span></td>'
			tableHtml += '</tr>'
		}
		$('#currList tbody').html(tableHtml)
	}
	if (time > 0)
	{
		sleep4 = 2000-(Math.floor(new Date().getTime()/1000)-parseInt(time))
		clearInterval(refreshBlock4)
		setTimeout(function(){
			refreshBlock4 = setInterval(function(){intervalBlock4()},sleep4)
		},sleep4)
	}
}
function intervalBlock4()
{
	/*if (document.body.className != 'visible')
	{
		return
	}*/
	$.ajax({
		type:'POST',
		url :path+'/ajax/report4/',
		contentType: 'application/json; charset=utf8',
		dataType: 'json',
		success: appendBlock4
	})
}
if (document.getElementsByTagName('body')[0].getAttribute('id') == 'user_is_authorized')
{
	refreshBlock4 = setInterval(function(){intervalBlock4()}, sleep4)
}