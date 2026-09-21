<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
{
	die();
}

/** @global CMain $APPLICATION */
/** @var array $arParams */
/** @var array $arResult */


if (!empty($arResult['ERROR']))
{
	echo $arResult['ERROR'];
	return false;
}

?>


<div class="reports-result-list-wrap">
<div class="report-table-wrap">
<div class="reports-list-left-corner"></div>
<div class="reports-list-right-corner"></div>
<table cellspacing="0" class="reports-list-table" id="report-result-table">
	<!-- head -->
	<tr>
		<?php
		$fieldNames = array_keys($arResult['tableColumns']);
		$fieldNamesCount = count($fieldNames);
		$i = 0;
		foreach($fieldNames as $col):
			$i++;

			if ($i === 1)
			{
				$th_class = 'reports-first-column';
			}
			else if ($i === $fieldNamesCount)
			{
				$th_class = 'reports-last-column';
			}
			else
			{
				$th_class = 'reports-head-cell';
			}

			// title
			$arUserField = $arResult['fields'][$col];
			$title = trim((string)($arUserField["LIST_COLUMN_LABEL"] ?? ''));
			if ($title === '')
			{
				$title = $col;
			}

			// sorting
			$defaultSort = 'DESC';
			//$defaultSort = $col['defaultSort'];

			if ($col === $arResult['sort_id'])
			{
				$th_class .= ' reports-selected-column';

				if($arResult['sort_type'] == 'ASC')
				{
					$th_class .= ' reports-head-cell-top';
				}
			}
			else
			{
				if ($defaultSort == 'ASC')
				{
					$th_class .= ' reports-head-cell-top';
				}
			}

			?>
			<th class="<?=$th_class?>" colId="<?=htmlspecialcharsbx($col)?>" defaultSort="<?=$defaultSort?>">
				<div class="reports-head-cell"><?php
					if($defaultSort):
						?><span class="reports-table-arrow"></span><?php
					endif;
				?><span class="reports-head-cell-title"><?=htmlspecialcharsex($title)?></span></div>
			</th>
			<?php
		endforeach;
		?>
	</tr>

	<!-- data -->
	<?php
	foreach ($arResult['rows'] as $row):
		$type = $row['UF_IP_SOVPADENIE'];
		switch ($type){
                    case 'Черный список':
                        $class = 'black';
                        break;
                    case 'Серый список':
                        $class = 'gray';
                        break;
                    case 'Маска':
                        $class = 'gray';
                        break; 
                    case 'Реферер':
                        $class = 'gray';
                        break;        
                    default:
                        $class = 'white';
                }
        if($row['UF_CAPTCHA'] == 'да'){
        	$check = 'checkCaptcha';
        }        
		?>
	<tr class="reports-list-item <?=$class?> <?=$check;?>">
		<?php
		unset($check);
		$i = 0;
		foreach ($fieldNames as $col):

                

			$i++;
			if ($i === 1)
			{
				$td_class = 'reports-first-column '.$class ;
			}
			else if ($i === $fieldNamesCount)
			{
				$td_class = 'reports-last-column '.$class;
			}
			else
			{
				$td_class = $class;
			}

			//if (CReport::isColumnPercentable($col))
			if (false) // numeric rows
			{
				$td_class .= ' reports-numeric-column';
			}

			$finalValue = $row[$col];

			if ($col === 'ID' && !empty($arParams['DETAIL_URL']))
			{
				$url = str_replace(
					array('#ID#', '#BLOCK_ID#'),
					array($finalValue, intval($arParams['BLOCK_ID'])),
					$arParams['DETAIL_URL']
				);

				$finalValue = '<a href="'.htmlspecialcharsbx($url).'">'.$finalValue.'</a>';
			}





			?>

			<td title="<?=$finalValue?>" class=" <?=$td_class?> <?=$col;?>"><?=$finalValue?></td>
			<?php
		endforeach;
		?>
	</tr>
	<?php
	endforeach;
	?>

</table>

<?php
if ($arParams['ROWS_PER_PAGE'] > 0):
	$APPLICATION->IncludeComponent(
		'bitrix:main.pagenavigation',
		'',
		array(
			'NAV_OBJECT' => $arResult['nav_object'],
			'SEF_MODE' => 'N',
		),
		false
	);
endif;
?>


<form id="hlblock-table-form" action="" method="get">
	<input type="hidden" name="BLOCK_ID" value="<?=htmlspecialcharsbx($arParams['BLOCK_ID'])?>">
	<input type="hidden" name="sort_id" value="">
	<input type="hidden" name="sort_type" value="">
</form>

<script type="text/javascript">
(function () {
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var ths = document.querySelectorAll('#report-result-table th');
		for (var i = 0; i < ths.length; i++) {
			(function (th) {
				var ds = th.getAttribute('defaultSort');
				if (ds === '') {
					th.classList.add('report-column-disabled-sort');
					return;
				}

				th.addEventListener('click', function () {
					var colId = this.getAttribute('colId');
					var sortType = '';
					var isCurrent = this.classList.contains('reports-selected-column');

					if (isCurrent) {
						var currentSortType = this.classList.contains('reports-head-cell-top') ? 'ASC' : 'DESC';
						sortType = currentSortType === 'ASC' ? 'DESC' : 'ASC';
					} else {
						sortType = this.getAttribute('defaultSort');
					}

					var idInp = document.querySelector('#hlblock-table-form input[name="sort_id"]');
					var typeInp = document.querySelector('#hlblock-table-form input[name="sort_type"]');
					if (idInp) { idInp.value = colId; }
					if (typeInp) { typeInp.value = sortType; }

					var form = document.getElementById('hlblock-table-form');
					if (form) { form.submit(); }
				});
			})(ths[i]);
		}
	});
})();
</script>

</div>
</div>