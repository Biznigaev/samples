<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
?>
<div id="gmaps-overlay"></div>
<div class="n_wr_map">
	<span class="d4"></span>
	<span class="d5"></span>
	<span class="d6"></span>
	<div class="to_ram">
		<div class="n_search">
			<form action="" method="" accept-charset="utf-8" id="mapSearchForm">
				<div class="fieldsContainer">
					<input type="text" autocomplete="off" id="searchkey" title="�������� �����, �����, �����, ������" class="text" />
					<input id="s_sub" type="submit" value="�����" class="submit" onkeypress="return false" />
				</div>
			</form>
		</div>
		<?$APPLICATION->IncludeComponent('mkb:geo_points.filter', '.default', array(
			'IBLOCK_ID' => $arParams['IBLOCK_ID'],
			'POINT_TYPE' => $arParams['TYPE'],
			'FILTER' => $arParams['FILTER'],
			'CACHE_TYPE' => $arParams['CACHE_TYPE'],
			'CACHE_TIME' => $arParams['CACHE_TIME'],
		));?>
		<div class="type_tabs">
			<a href="#gmap" id="type_tab_map" class="<? if (empty($_COOKIE['viewMode']) || $_COOKIE['viewMode'] == 'gmap'): ?>act <? endif ?>tab"><span>�� �����</span></a>
			<a href="#metro" id="type_tab_metro" class="<? if ($_COOKIE['viewMode'] == 'metro'): ?>act <?endif?>tab"><span>�� ����� �����</span></a>
			<a href="#list" id="type_tab_list" class="<? if ($_COOKIE['viewMode'] == 'list'): ?>act <?endif?>tab"><span>�������</span></a>
		</div><!--/n_tabs-->

		<div id="n_gmap" class="<? if (!empty($_COOKIE['viewMode']) && $_COOKIE['viewMode'] != 'gmap'): ?>n_content <?endif?>viewTab">
			<div id="map_canvas"></div>
			<br />
			<div id="transparentBlock"></div>
			<div class="dialog-round not_found_box" id="nothingFoundInfo">
				<span class="b1"></span>
				<span class="b2"></span>
				<span class="b3"></span>
				<div>
					<p class="n_notfound"><strong>�� ������ ������� ������ �� �������</strong></p>
					<p>����������, ��������:<br />
					<span id="get_other_request">� <a href="#" onclick="return getOtherRequest()">������ ������ ������</a><br /></span>
					<span id="cancel_last_service">� <a href="#" onclick="return cancelLastService()">�������� ��������� ��������� ������</a></span>
					</p>
				</div>
				<span class="b3"></span>
				<span class="b2"></span>
				<span class="b1"></span>
			</div>
		</div><!--/n_map-->
		<div id="n_metro" class="<? if ($_COOKIE['viewMode'] != 'metro'): ?>n_content <?endif?>viewTab">
            <div id="metroMap_div">
				<div id="test_points">
					<div id="all_block_points"></div>
					<div id="div_close" onClick="points_close();">
						<img src="poi_data/img/close.png" />
					</div>
				</div>	
			</div>
            <p>������ ����� ������� ���� �� ������� ������� ����� ������������ ����������� ����� � ���.</p>
        </div><!--/n_metro-->
		<div id="n_list" class="<? if ($_COOKIE['viewMode'] != 'list'): ?>n_content <?endif?>viewTab">
			<ul id="objectsList"></ul>
			<hr />
			<p class="n_adress" id="nextElementControls">
				�������� <a href="#" onclick="return getNext();">��� 10</a>,
				<a href="#" onclick="return showAll();">����� ��� ����� ������������</a>
			</p>
		</div><!--/n_list-->
				
		<div class="loading" id="loadingIcon">�������� ������...</div>
    </div>
    <span class="d3"></span>
    <span class="d2"></span>
    <span class="d1"></span>
</div><!--/n_wr_map-->