<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<div id="map_filters" class="filterParamsContainer">
	<div id="map_checkboxes">
<? foreach ($arResult as $idx => &$arItem): ?>
		<div>
			<input type="checkbox" name="services[]" id="p-serv-<?=$arItem['ID']?>" value="<?=$arItem['ID']?>" class="filter-item" <? if ($arItem['CHECKED']) { ?>checked="true"<? } ?> />
			<label for="p-serv-<?=$arItem['ID']?>"><?=$arItem['TITLE']?></label>
			<sup class="typeBranch"><?=$arItem['COUNT']?></sup>
		</div>
<? endforeach ?>
	</div>
	<div class="clb"></div>
</div>