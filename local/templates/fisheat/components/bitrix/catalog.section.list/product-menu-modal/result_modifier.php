<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arViewModeList = array('LIST', 'LINE', 'TEXT', 'TILE');

$arDefaultParams = array(
	'VIEW_MODE' => 'LIST',
	'SHOW_PARENT_NAME' => 'Y',
	'HIDE_SECTION_NAME' => 'N'
);

$arParams = array_merge($arDefaultParams, $arParams);

if (!in_array($arParams['VIEW_MODE'], $arViewModeList))
	$arParams['VIEW_MODE'] = 'LIST';
if ('N' != $arParams['SHOW_PARENT_NAME'])
	$arParams['SHOW_PARENT_NAME'] = 'Y';
if ('Y' != $arParams['HIDE_SECTION_NAME'])
	$arParams['HIDE_SECTION_NAME'] = 'N';

$arResult['VIEW_MODE_LIST'] = $arViewModeList;

if (0 < $arResult['SECTIONS_COUNT'])
{
	if ('LIST' != $arParams['VIEW_MODE'])
	{
		$boolClear = false;
		$arNewSections = array();
		foreach ($arResult['SECTIONS'] as &$arOneSection)
		{
			if (1 < $arOneSection['RELATIVE_DEPTH_LEVEL'])
			{
				$boolClear = true;
				continue;
			}
			$arNewSections[] = $arOneSection;
		}
		unset($arOneSection);
		if ($boolClear)
		{
			$arResult['SECTIONS'] = $arNewSections;
			$arResult['SECTIONS_COUNT'] = count($arNewSections);
		}
		unset($arNewSections);
	}
}

/*
 * Уменьшаем картинки разделов до размеров, в которых они реально выводятся.
 *
 * ВАЖНО, почему «обычные» способы не работали:
 * компонент bitrix:catalog.section.list уже сам вызывает
 * \Bitrix\Iblock\Component\Tools::getFieldImageData($arSection, array('PICTURE'), ...)
 * (см. bitrix/components/bitrix/catalog.section.list/component.php:471-479),
 * поэтому в $arResult['SECTIONS'][..]['PICTURE'] лежит НЕ ID файла, а уже готовый
 * МАССИВ от CFile::GetFileArray() с ключами ID, SRC (полный размер!), WIDTH, HEIGHT и т.д.
 *
 * Из-за этого:
 *  - проверка `!isset($arCurrent['PICTURE'])` всегда ложна -> старый блок ресайза не выполнялся;
 *  - `intval($arSection['PICTURE'])` от массива даёт 1, поэтому ресайз «по привычке» не срабатывал;
 *  - в шаблон уходил $arSection['PICTURE']['SRC'] — ссылка на ОРИГИНАЛ картинки.
 *
 * Здесь берём ID файла из готового массива и подменяем PICTURE на уменьшенную версию.
 * Результат (готовые файлы ресайза) кешируется на диске, поэтому повторных генераций не будет.
 */
if (0 < $arResult['SECTIONS_COUNT'])
{
	foreach ($arResult['SECTIONS'] as $key => $arSection)
	{
		if (empty($arSection['PICTURE']) || !is_array($arSection['PICTURE']))
		{
			continue;
		}

		$fileId = (int)($arSection['PICTURE']['ID'] ?? 0);
		if ($fileId <= 0)
		{
			continue;
		}

		$arResize = CFile::ResizeImageGet(
			$fileId,
			array('width' => 120, 'height' => 120),
			BX_RESIZE_IMAGE_PROPORTIONAL,
			true
		);

		if (!empty($arResize['src']))
		{
			$arResult['SECTIONS'][$key]['PICTURE']['SRC']    = $arResize['src'];
			$arResult['SECTIONS'][$key]['PICTURE']['WIDTH']  = (int)$arResize['width'];
			$arResult['SECTIONS'][$key]['PICTURE']['HEIGHT'] = (int)$arResize['height'];
		}
	}
	unset($key, $arSection, $arResize, $fileId);
}
?>