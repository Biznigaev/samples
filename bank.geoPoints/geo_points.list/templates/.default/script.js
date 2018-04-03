// Utils
var jwi = {
    cookie: {
        set: function(name, value, days) {
            if (days) {
                var date = new Date()
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000))
                var expires = "; expires=" + date.toGMTString()
            } else var expires = ""
            document.cookie = name + "=" + value + expires + "; path=/"
        },

        get: function(name) {
            var nameEQ = name + "="
            var ca = document.cookie.split(';')
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i]
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length)
            }
            return null;
        },

        erase: function(name) {
            jwi.cookie.set(name, "", -1)
        }
    }
},
mkbConfig = {
    baseUrl: '/about_bank/address/poi_data/',
    metroMapLoaded: false,
    minZoomLevel: 8,
    maxZoomLevel: 12
},
rurl = /^([\w\+\.\-]+:)(?:\/\/([^\/?#:]*)(?::(\d+))?)?/,
url = rurl.exec( document.location.href.toLowerCase() ) || [],

branchs = [],
metro_stations = [],
listMaps = [],
openedWindows = [],

searchField,
servicesLength = 12,
get_type = '',
viewMode = '',

curr_gps = "37.617633, 55.755786",
m_zoom = 10,

data_xml_point = '',
selected_tab = '',
markerClusterer = null,

geocoder = {},
gmap = null,
gmapsConfig = {},
mkbMap = {},
urlList = {
    points: mkbConfig.baseUrl + 'filials/',
    serviceList: mkbConfig.baseUrl + 'services/',
    filterSearch: mkbConfig.baseUrl + 'search/',
    stringSearch: mkbConfig.baseUrl + 'search/',
    metroMap: mkbConfig.baseUrl + 'new_img/metro-map.gif'
},
customIcons = {
    office: mkbConfig.baseUrl + 'new_img/ico_branch.png',
    atm: mkbConfig.baseUrl + 'new_img/ico_atm.png',
    terminal: mkbConfig.baseUrl + 'new_img/ico_terminals.png',
    operational: mkbConfig.baseUrl + 'new_img/ico_operating.png'
}

$(document).ready(function()
{
    curr_gps = $("#current_gps").html()
    m_zoom = $("#map_zoom").text()
    // тип точки обслуживания, пришедший в GET
    get_type = $("#get_type").val()
    // по умолчанию выбрана карта
    searchField = $('#searchkey')
    viewMode = jwi.cookie.get('viewMode')||'gmap'
    selected_tab = viewMode

    if (typeof hidden_checkbox !== "undefined")
    {
        $(hidden_checkbox).appendTo('#map_checkboxes')
    }

    searchField.val($('#searchkey').attr('title')).focus(function(e)
    {
        if ($(this).val() == $(this).attr('title'))
        {
            $(this).val('')
        }
        $(this).addClass('getfocus')
    }).blur(function(e)
    {
        if (searchField.val() == '')
        {
            //    reser search field
            searchField.removeClass('getfocus').val(searchField.attr('title'))
        }
    }).keypress(function(e)
    {
        $('#mainServiceFilter input:checkbox').removeAttr('checked')
        if ((e.keyCode || e.which) == 13)
        {
            e.preventDefault()
            $("#s_sub").click()
        }
    })
    $('#mapSearchForm').submit(mkbMap.processForm)

    $('.sampleRequest').click(function(e)
    {
        e.preventDefault();
        $('#mainServiceFilter input:checkbox').removeAttr('checked')
        //    set search field
        searchField.addClass('getfocus').val($(this).text())
        $('#s_sub').click()
    })

    $('.type_tabs .tab').click(function(e)
    {
        e.preventDefault()
        if ($(this).hasClass('act'))
        {
            return
        }
        selected_tab = $(this).attr('href').substring(1)
        viewMode = selected_tab
        if (selected_tab == 'gmap')
        {
            if (!gmap)
            {
                initMap(curr_gps, m_zoom)
            }
            // эта строка нужна для того, чтобы карта нормально отображалась после того, как DIV с картой станет видимым
            setTimeout(function()
            {
                google.maps.event.trigger(gmap, 'resize')
            },1)
        }
        mkbMap.processForm()

        // сбрасываем состояние табов
        $('.type_tabs .tab').removeClass('act').filter('[href=#' + viewMode + ']').addClass('act')
        // сбрасываем состояние рабочей области
        $('.viewTab').addClass('n_content')
        $('#n_' + viewMode).removeClass('n_content')
        jwi.cookie.set('viewMode', viewMode)
    })

    mkbMap.getAndDrawPoints(mkbMap.getFilterQuery())
    $('#map_filters .filter-item').change(mkbMap.processForm)
}).mouseup(function(e)
{
    /*  Функция нужна для закрытия открывающегося DIV со списком точек продаж в карте метро,
        закрытие происходит по клику в любой области вне этого DIV
    */
    var container = $("#test_points");
    if (container.has(e.target).length === 0 
        && !container.is(e.target)
    )
    {
        container.hide()
        container.css({
            "height": "auto"
        });
        $('#all_block_points').css({
            "height": "auto"
        })
    }
})

//закрытие окошка со списком точек продаж по клику на крестик
function points_close() {
    var container = $("#test_points")
    container.hide()
    container.css({
        "height": "auto"
    });
    $('#all_block_points').css({
        "height": "auto"
    });
}

//функция достаёт XML с точками продаж и передаёт его для построения карты
var mkbMap = (function()
{
    var xhr
    return {
        getAndDrawPoints: function(path)
        {
            if (xhr)
            {
                xhr.abort()
            }
            $('#loadingIcon').fadeIn('fast')
            xhr = $.ajax({
                url: path,
                method: 'GET',
                dataType: 'json',
                beforeSend: function()
                {
                    if (!$('#gmaps-overlay:visible').length)
                    {
                        $('#gmaps-overlay').show(0)
                    }
                }
            }).done(function(data)
            {
                var f_isset = false
                if (Object.keys(data).indexOf('points') != -1)
                {
                    f_isset = data.points.length
                }
                else
                {
                    f_isset = data.length
                }
                if (!f_isset)
                {
                    $('#gmaps-overlay').hide(0)
                    $('#loadingIcon').fadeOut('fast')
                    if (window.lastSelectedServiceId)
                    {
                        $('#cancel_last_service').removeAttr('style')
                    }
                    else
                    {
                        $('#cancel_last_service').css('display','none')
                    }
                    $('#transparentBlock,#nothingFoundInfo').show(0)
                }
                else
                {
                    $('#transparentBlock,#nothingFoundInfo').hide(0)
                    /**
                     *    @todo: задать при вызове - tab:active
                     */
                    setTimeout(function()
                    {
                        var isset = false
                        if (viewMode == 'metro')
                        {
                            for (var idx=0 in data.metro)
                            {
                                metro_stations.push(data.metro[idx])
                            }
                            for (var idx=0 in data.points)
                            {
                                isset = false
                                for (var i=0;i<branchs.length;++i)
                                {
                                    if (branchs[i].id == data.points[idx].id)
                                    {
                                        isset = true
                                        break
                                    }
                                }
                                if (!isset)
                                {
                                    branchs.push(
                                        new google.maps.Marker({
                                            position: new google.maps.LatLng(
                                                parseFloat(data.points[idx].coords.lat),
                                                parseFloat(data.points[idx].coords.lon)
                                            ),
                                            id: data.points[idx].id,
                                            map: gmap,
                                            addr: data.points[idx].addr,
                                            name: data.points[idx].name,
                                            title: data.points[idx].name+', '+data.points[idx].addr,
                                            icon: customIcons[get_type]
                                        })
                                    )
                                    branchs[idx].click = google.maps.event.addListener(
                                        branchs[idx],'click',pointPopupShow
                                    )
                                }
                            }
                        }
                        else
                        {
                            var items = data
                            if (Object.keys(data).indexOf('points') != -1)
                            {
                                items = items.points
                            }
                            for (var idx in items)
                            {
                                isset = false
                                for (var i=0;i<branchs.length;++i)
                                {
                                    //    существует ли точка в наборе
                                    if (branchs[i].id == items[idx].id)
                                    {
                                        isset = true
                                        break
                                    }
                                }
                                if (isset)
                                {
                                    continue
                                }
                                branchs.push(
                                    new google.maps.Marker({
                                        position: new google.maps.LatLng(
                                            parseFloat(items[idx].coords.lat),
                                            parseFloat(items[idx].coords.lon)
                                        ),
                                        id: items[idx].id,
                                        map: gmap,
                                        addr: items[idx].addr,
                                        name: items[idx].name,
                                        title: items[idx].name+', '+items[idx].addr,
                                        icon: customIcons[get_type]
                                    })
                                )
                                branchs[idx].click = google.maps.event.addListener(
                                    branchs[idx],'click',pointPopupShow
                                )
                            }
                        }
                        switch (viewMode)
                        {
                            case 'metro':
                            {
                                initMetroMap()
                                break
                            }
                            case 'gmap':
                            {
                                mkbMap.buildList()
                                break
                            }
                            case 'list':
                            {
                                buildPointList()
                                break
                            }
                        }
                        $('#gmaps-overlay').hide(0)
                        $('#loadingIcon').fadeOut('fast')
                    },1)
                }
            })
        }
    }
})()
mkbMap.buildList = function()
{
    //    очистка предыдущего представления с кластеризацией
    if (!!markerClusterer)
    {
        markerClusterer.clearMarkers()
    }
    if (!gmap)
    {
        initMap(curr_gps, m_zoom)
    }
    if (!markerClusterer)
    {
        markerClusterer = new MarkerClusterer(gmap, branchs, {
            gridSize: 50,
            maxZoom: 15,
            width: 50,
            height: 50
        })
    }
    else
    {
        markerClusterer.addMarkers(branchs)
    }
}
function pointPopupShow()
{
    var marker = this
    closeAllInfowindows()

    if (this.popup === undefined)
    {
        $.get(mkbMap.getFilterQuery(),
        {id: marker.id}, function (data)
        {
            var arMetro = []
            if ((data[0].metro||[]).length)
            {
                for (var idx in data[0].metro)
                {
                    arMetro.push(
                        '<p class="m_line_'+data[0].metro[idx].line+'" style="margin:3px 0 5px 0;">'+data[0].metro[idx].name+'</p>'
                    )
                }
            }
            marker.popup = new google.maps.InfoWindow({
                content: '\
                <b>\
                    <a href="/about_bank/address/detail.php?id='+marker.id+'">'+marker.name+'</a>\
                </b>\
                <br />'+marker.addr+'<br />\
                '+arMetro.join('')+data[0].workingtime+'<br />'
            })
            openedWindows.push(marker)
            marker.popup.open(gmap,marker)
        }, 'json')
    }
    else
    {
        openedWindows.push(marker)
        marker.popup.open(gmap,marker)
    }
}
function getPointType()
{
    switch (get_type)
    {
        case 'office':
        {
            return {
                type: 'branch',
                img: '/images/icons/office_sm_ico.gif'
            }
            break
        }
        case 'atm':
        {
            return {
                type: 'atm',
                img: '/images/icons/cashmachine_sm_ico.gif'
            }
            break
        }
        case 'terminal':
        {
            return {
                type: 'terminals',
                img: '/images/icons/terminal_sm_ico.gif'
            }
            break
        }
        case 'operational':
        {
            return {
                type: 'operating',
                img: '/images/icons/operroom_sm_ico.gif'
            }
            break
        }
    }
}
function createListItem(strDisplay, arParams, item)
{
    var strItem = '\
    <li style="display:'+strDisplay+'" id="item-'+item.id+'" type="'+arParams.type+'">\
        <img src="'+arParams.img+'" style="float:left;padding-right:7px;" />\
        <p class="n_name">\
            <a href="/about_bank/address/detail.php?id='+item.id+'">'+item.name+'</a>\
        </p>\
        <div class="n_info">\
            <p class="n_adress">\
                <a href="#" onclick="return toggleListMap(this);">'+item.addr+'</a>\
            </p>\
            <div class="listDir" id="directions-'+item.id+'"></div>\
            <div class="list-map-container" id="list-map-'+item.id+'" rel="noinit" lat="'+item.position.lat()+'" lng="'+item.position.lng()+'"></div>\
            <p>'
    if (!!item.metro)
    {
        for (var j=0;j<item.metro.length;++j)
        {
            strItem += '\
                <p class="m_line_'+item.metro[j].line+'" style="margin:3px 0 5px 0;">'+item.metro[j].name+'</p>'
        }
    }
    strItem += item.workingtime+'\
            </p>\
        </div>\
    </li>'
    $('#objectsList').append($(strItem))
}
function buildPointList()
{
    var id = [],
        strDisplay = (branchs.length > 12 && i > 9) ? 'none' : 'block',
        arParams = getPointType()
    for (var i=0; i<10 && i<branchs.length; ++i)
    {
        //    добавление в список на загрузку только если точка ранее не была загружена
        if (Object.keys(branchs[i]).indexOf('metro') == -1)
        {
            id.push(parseInt(branchs[i].id))
        }
    }
    $('#objectsList').empty()
    if (id.length)
    {
        $.ajax({
            url: mkbMap.getFilterQuery(),
            data: {
                'id':id
            },
            success: function (pointsList)
            {
                for (var i=0; i<pointsList.length; ++i)
                {
                    branchs[i].metro = pointsList[i].metro
                    branchs[i].workingtime = pointsList[i].workingtime
                    createListItem(strDisplay, arParams, branchs[i])
                }
            }
        })
    }
    else
    {
        for (var i=0; i<branchs.length; ++i)
        {
            createListItem(strDisplay, arParams, branchs[i])
        }
    }
    if (branchs.length < 10)
    {
        $('#nextElementControls').css('display','none')
    }
    else
    {
        $('#nextElementControls').css('display','block')
    }
}

//====================================================================================================================

//    собрать параметры для xhr
mkbMap.getFilterQuery = function()
{
    var filterServices = [],
        queryParams = []
    $('#map_filters input[type="checkbox"]').each(function()
    {
        if ($(this).attr('checked'))
        {
            var sid = $(this).attr('id').replace('p-serv-', '').replace('c-serv-', '')
            filterServices.push(sid)
        }
    });
    if (filterServices.length)
    {
        filterServices = $.map(filterServices, function(str)
        {
            return 'services[]=' + str
        })
        queryParams = $.merge(queryParams, filterServices)
    }
    switch (get_type)
    {
        case 'office':
        {
            queryParams.push('type=branch')
            break
        }
        case 'atm':
        {
            queryParams.push('type=atm')
            break
        }
        case 'terminal':
        {
            queryParams.push('type=terminal')
            break
        }
        case 'operational':
        {
            queryParams.push('type=operating')
            break
        }
    }
    var str = '/about_bank/address/search.php'
    str += queryParams.length ? '?' + queryParams.join('&') : ''
    str += '&'+[
        'view='+viewMode,
        'sessid='+BX.bitrix_sessid(),
        '_='+$.now()
    ].join('&')
    if (searchField.val() != searchField.attr('title'))
    {
        str += '&query=' + escapeEx(searchField.val())
    }
    return str
}
/**
 *    @todo: Очистка списка точек метро
 */
mkbMap.processForm = function(e)
{
    if (!!e
        && $(this).is('input:checkbox'))
    {
        window.lastSelectedServiceId = $(this).attr('id')
    }
    $('#map_checkboxes .filter-item').each(function()
    {
        var isset = jwi.cookie.get(get_type+'.'+$(this).val()) != undefined
        if ($(this).is(':checked'))
        {
            if (!isset)
            {
                jwi.cookie.set(get_type+'.'+$(this).val(),'1')
            }
        }
        else
        {
            if (isset)
            {
                jwi.cookie.erase(get_type+'.'+$(this).val())
            }
        }
    })
    //    очистка карты от точек
    // if (viewMode == 'gmap')
    // {
        for (var i=0; i<branchs.length; ++i)
        {
            branchs[i].setMap(null)
        }
        branchs = []
    // }
    metro_stations = []

    mkbMap.getAndDrawPoints(mkbMap.getFilterQuery())
    return false
}

// инициализация карты
function initMap(curr_gps, m_zoom)
{
    // приведение координат к типу float
    var array_gps = curr_gps.split(","),
        // координаты в базе и в google надо передавать в порядке наоборот
        mapOptions = {
        zoom: parseInt(m_zoom),
        center: new google.maps.LatLng(
            parseFloat(array_gps[1]), 
            parseFloat(array_gps[0])
        ),
        navigationControl: true,
        navigationControlOptions: {
            style: google.maps.NavigationControlStyle.SMALL
        },
        streetViewControl: false,
        mapTypeId: google.maps.MapTypeId.ROADMAP
    }

    window.gmap = new google.maps.Map(document.getElementById("map_canvas"), mapOptions)
    window.geocoder = new google.maps.Geocoder()

    // Ограничение на ZOOM
    google.maps.event.addListener(window.gmap, 'zoom_changed', function()
    {
        if (window.gmap.getZoom() < window.mkbConfig.minZoomLevel)
        {
            window.gmap.setZoom(window.mkbConfig.minZoomLevel)
        }
    })
    google.maps.event.addListener(window.gmap, 'click', function()
    {
        closeAllInfowindows()
    })

    // Ограничение на область просмотра
    window.gmapsConfig.allowedBounds = new google.maps.LatLngBounds(
        new google.maps.LatLng(55.6557, 37.5176),
        new google.maps.LatLng(55.8757, 37.8676)
    );

    google.maps.event.addListener(gmap, 'dragend', function()
    {
        if (gmapsConfig.allowedBounds.contains(gmap.getCenter()))
        {
            return
        }
        gmap.setCenter(
            new google.maps.LatLng(
                gmap.getCenter().lat(),
                gmap.getCenter().lng()
            )
        )
    });
}
function initMetroMap()
{
    $('#metroMap_div .metro_station').remove()
    for (var i=0;i<metro_stations.length;++i)
    {
        $('<a style="width:'+metro_stations[i].width+'px;\
                     height:'+metro_stations[i].height+'px;\
                     left:'+metro_stations[i].left+'px;\
                     top:'+metro_stations[i].top+'px;\
                     background-position:-'+metro_stations[i].left+'px -'+metro_stations[i].top+'px;" \
                     id="station_'+metro_stations[i].id+'" class="metro_station" onclick="metroPopupShow('+metro_stations[i].id+')" />').appendTo('#metroMap_div')
    }
}
function createMetroList(idx)
{
    var img_type = ""
    switch (get_type)
    {
        case 'office':
            img_type = '/images/icons/office_sm_ico.gif'
            break;
        case 'atm':
            img_type = '/images/icons/cashmachine_sm_ico.gif'
            break;
        case 'terminal':
            img_type = '/images/icons/terminal_sm_ico.gif'
            break;
        case 'operational':
            img_type = '/images/icons/operroom_sm_ico.gif'
            break;
    }
    for (var i=0;i<metro_stations[idx].points.length;++i)
    {
        for (var j=0;j<branchs.length;++j)
        {
            if (branchs[j].id == metro_stations[idx].points[i])
            {
                $('<div class="points_padding">\
                       <table>\
                           <tr>\
                               <td style="padding-right:5px;">\
                                   <img src="'+img_type+'" />\
                               </td>\
                               <td>\
                                   <a href="/about_bank/address/detail.php?id='+branchs[j].id+'">\
                                       <b>'+branchs[j].name+'</b>\
                                   </a>\
                               </td>\
                           </tr>\
                           <tr>\
                               <td></td>\
                               <td>'+branchs[j].addr+'</td>\
                           </tr>\
                           <tr>\
                               <td></td>\
                               <td>'+branchs[j].workingtime+'</td>\
                           </tr>\
                       </table>\
                   </div>').appendTo('#all_block_points')
            }
        }
    }
    //координаты станции метро
    var a_left = parseInt($('#station_' + metro_stations[idx].id).css('left'), 10),
        a_top = parseInt($('#station_' + metro_stations[idx].id).css('top'), 10),
        div_height = 0
    //    вычисление расположения DIV с точками продаж
    div_height = parseInt($('#test_points').css('height'), 10)
    if ($('#all_block_points .points_padding').size() > 5)
    {
        div_height = 158;
    }
    if (a_top < 507)
    {
        div_top = a_top + parseInt($('#station_' + metro_stations[idx].id).css('height'), 10)
    }
    else
    {
        div_top = a_top - div_height
    }
    /*
        Вычисление левого верхнего угла DIV с точками продаж:
        блок должен располагаться слибо влево, либо вправо от станции метро
        всё зависит от её расположения (чтобы лок не выходил за поля карты)
    */
    if (a_left < 420)
    {
        div_left = a_left
    }
    else
    {
        div_left = a_left - (322 - parseInt($('#station_' + metro_stations[idx].id).css('width'), 10))
    }
    if ($('#all_block_points .points_padding').size() > 5)
    {
        $('#test_points').css({
            'display': 'block',
            'height': '158px',
            'border': '1px solid grey',
            'position': 'absolute',
            'z-index': '3',
            'left': div_left,
            'top': div_top
        })
        $('#all_block_points').css({
            'display': 'block',
            'height': '158px'
        })
    }
    else
    {
        $('#test_points').css({
            'display': 'block',
            'border': '1px solid grey',
            'position': 'absolute',
            'z-index': '3',
            'left': div_left,
            'top': div_top,
            'height': 'auto'
        })
        $('#all_block_points').css({
            'display': 'block',
            'height': 'auto'
        })
    }
    $('#div_close').css('display','block')
}
//Вывод точек продаж по выбранной станции метро
function metroPopupShow(id_station) {
    $('#all_block_points').empty();

    var idx=-1
    for (var i=0 in metro_stations)
    {
        if (metro_stations[i].id == id_station)
        {
            idx=i
            break
        }
    }
    var ids = []
    for (var i=0;i<metro_stations[idx].points.length;++i)
    {
        for (var j=0;j<branchs.length;++j)
        {
            if (branchs[j].id == metro_stations[idx].points[i])
            {
                if (branchs[j].workingtime == undefined)
                {
                    ids.push(branchs[j].id)
                }
            }
        }
    }
    if (ids.length)
    {
        $.get(mkbMap.getFilterQuery(),
        {id:ids}, function (data)
        {
            for (var i=0 in data)
            {
                for (var j=0;j<branchs.length;++j)
                {
                    if (data[i].id == branchs[j].id)
                    {
                        branchs[j].metro = data[0].metro
                        branchs[j].workingtime = data[0].workingtime
                        branchs[j].how_to_get = data[0].how_to_get
                    }
                }
            }
            createMetroList(idx)
        }, 'json')
    }
    else
    {
        createMetroList(idx)
    }
}

function toggleListMap(obj)
{
    var id = $(obj).closest('li').attr('id').replace('item-', ''),
        idx = 0
    for (var i=0 in branchs)
    {
        if (branchs[i].id == id)
        {
            idx = i
            break
        }
    }
    var containerID = $(obj).closest('li').attr('id'),
        pointType = $(obj).closest('li').attr('type'),
        listMapContainerID = containerID.replace('item-', 'list-map-'),
        listMapContainer = $('#'+listMapContainerID),
        relAttr = listMapContainer.attr('rel'),
        showState = 'opened' == relAttr

    if ('noinit' == relAttr) {
        listMapContainer.css('display','block')
        listMapContainer.attr('rel', 'opened')

        window.listMaps[id] = new google.maps.Map(listMapContainer[0], {
            zoom: 12,
            center: branchs[idx].position,
            navigationControl: true,
            navigationControlOptions: {
                style: google.maps.NavigationControlStyle.SMALL
            },
            streetViewControl: false,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        })

        // Ограничение на ZOOM
        google.maps.event.addListener(window.listMaps[id], 'zoom_changed', function()
        {
            if (window.listMaps[id].getZoom() < window.mkbConfig.minZoomLevel)
            {
                window.listMaps[id].setZoom(window.config.minZoomLevel);
            }
        })
        /*var marker = */new google.maps.Marker({
            position: branchs[idx].position,
            map: window.listMaps[id],
            icon: customIcons[pointType]
        })
        addDirectionsForm(containerID,idx);
    }

    if (showState) {
        listMapContainer.css('display','none')
        listMapContainer.attr('rel', 'colsed')
        $("#directionsForm-"+id).css('display','none')

    } else {
        listMapContainer.css('display','block')
        listMapContainer.attr('rel', 'opened')
        $("#directionsForm-" + id).css('display','block')
    }

    return false
}

function addDirectionsForm(containerID,idx) {
    var id = containerID.replace('item-', '')
    $('#directions-'+id).html('\
        <div class="directionsForm" id="directionsForm-' + id + '">\
            <table width="100%">\
                <tbody>\
                    <tr>\
                        <td align="right" valign="middle" width="50%">Проложить маршрут от:</td>\
                        <td valign="middle" valign="middle">\
                            <input type="text" style="width: 99%; border: 1px solid #ccc;" id="dstPlace-' + id + '" />\
                        </td>\
                        <td width="30px">\
                            <input type="button" class="btnGetDirections" value="" onclick="searchDirections('+id+','+idx+');" />\
                        </td>\
                    </tr>\
                </tbody>\
            </table>\
        </div>')
}

function searchDirections(id,idx) {
    // Поиск и отрисовка маршрута
    var directionsRenderer = new google.maps.DirectionsRenderer();
    directionsRenderer.setMap(window.listMaps[id])

    var directionsService = new google.maps.DirectionsService();
    var originPlace = window.branchs[idx].position;

    directionsService.route({
        origin: originPlace,
        destination: $("#dstPlace-" + id).val(),
        travelMode: google.maps.DirectionsTravelMode.DRIVING
    }, function(response, status)
    {
        if (status == google.maps.DirectionsStatus.OK) {
            directionsRenderer.setDirections(response);
        } else {
            alert("К сожадению, маршрут не найден." + status);
        }
    })
}

// Функция для отображения следующих 10 точек обслуживания в списке
function getNext()
{
    var id = [],
        offset = $('#objectsList li').length,
        arParams = getPointType()
    for (var i=offset,limit=10; limit && i<branchs.length; ++i,--limit)
    {
        if (Object.keys(branchs[i]).indexOf('metro') == -1)
        {
            id.push(parseInt(branchs[i].id))
        }
    }
    if (id.length)
    {
        $.ajax({
            url: mkbMap.getFilterQuery(),
            data: {
                'id':id,
                'offset':offset
            },
            success: function (pointsList)
            {
                for (var i=0;i<pointsList.length;++i)
                {
                    branchs[offset+i].metro = pointsList[i].metro
                    branchs[offset+i].workingtime = pointsList[i].workingtime
                    createListItem('block', arParams, branchs[offset+i])
                }
            }
        })
    }
    else
    {
        for (var i=offset;i<branchs.length;++i)
        {
            createListItem('block', arParams, branchs[i])
        }
    }
    if (branchs.length - $('#objectsList li').length < 10)
    {
        $('#nextElementControls').css('display','none')
    }
    return false
}

// Функция для отображения следующих всех точек обслуживания в списке
function showAll()
{
    var id = [],
        offset = $('#objectsList li').length,
        arParams = getPointType(),
        f_loaded = false
    $('#loadingIcon').fadeIn('fast')
    for (var i=offset; i<branchs.length; ++i)
    {
        if (Object.keys(branchs[i]).indexOf('metro') == -1)
        {
            id.push(parseInt(branchs[i].id))
        }
    }
    if (id.length)
    {
        $.ajax({
            type:'POST',
            url: mkbMap.getFilterQuery(),
            data: {'id':id},
            success: function (pointsList)
            {
                for (var i=0;i<pointsList.length;++i)
                {
                    branchs[offset+i].metro = pointsList[i].metro
                    branchs[offset+i].workingtime = pointsList[i].workingtime
                    createListItem('block', arParams, branchs[offset+i])
                    if (i == (pointsList.length-1))
                    {
                        f_loaded = true
                    }
                }
            }
        })
    }
    else
    {
        for (var i=offset;i<branchs.length;++i)
        {
            createListItem('block', arParams, branchs[i])
            if (i == (branchs.length-1))
            {
                f_loaded = true
            }
        }
    }
    var ptr = setInterval(function(){
        if (f_loaded)
        {
            clearInterval(ptr)
            $('#loadingIcon').fadeOut('fast')
        }
    },1)
    $('#nextElementControls').hide(0)
    return false
}

function getOtherRequest() {
    $('#searchkey').val('').focus()
    $('#nothingFoundInfo,#transparentBlock').hide(0)
    return false
}

function cancelLastService() {
    $('#nothingFoundInfo,#transparentBlock').hide(0)
    if (window.lastSelectedServiceId)
    {
        $('#'+lastSelectedServiceId)[0].click()
        window.lastSelectedServiceId = null
    }
    return false
}

function escapeEx(str) {
    var ret = '';
    for (i = 0; i < str.length; i++) {
        var n = str.charCodeAt(i);
        if (n >= 0x410 && n <= 0x44F) n -= 0x350;
        else if (n == 0x451) n = 0xB8;
        else if (n == 0x401) n = 0xA8;
        if ((n < 65 || n > 90) && (n < 97 || n > 122) && n < 256) {
            if (n < 16) ret += '%0' + n.toString(16);
            else ret += '%' + n.toString(16);
        } else ret += String.fromCharCode(n);
    }
    return ret;
}

function closeAllInfowindows()
{
    if (openedWindows.length)
    {
        for (var i in openedWindows)
        {
            openedWindows[i].popup.close()
        }
    }
}

//функция показывает информацию о типах точек продаж
function tochki_description_show() {
    document.getElementById('descr_text').style.display = "block";
    document.getElementById('descr_close').style.display = "block";
    document.getElementById('descr_img').style.display = "block";
    document.getElementById('descr_header').style.display = "none";
}


//функция убирает информацию о типах точек продаж
function tochki_description_close() {
    document.getElementById('descr_text').style.display = "none";
    document.getElementById('descr_close').style.display = "none";
    document.getElementById('descr_img').style.display = "none";
    document.getElementById('descr_header').style.display = "block";
}