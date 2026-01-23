<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
$arResult['MAP_COORDINATES_CENTER'] = array(
    '55.762340533944',
    '37.619844505885'
);

$imgType = array(
    3 => CFile::GetPath(COption::GetOptionString( "askaron.settings", "UF_FILE_3")),
    4 => CFile::GetPath(COption::GetOptionString( "askaron.settings", "UF_FILE_4")),
    5 => CFile::GetPath(COption::GetOptionString( "askaron.settings", "UF_FILE_5")),
);

if ($arParams['MAP_COORDINATES_CENTER']) {
    list ($lat, $lon) = explode(',', $arParams['MAP_COORDINATES_CENTER']);
    if ((float) $lat && (float) $lon) {
        $arResult['MAP_COORDINATES_CENTER'] = array(
            $lat,
            $lon
        );
    }
}

$arResult['PINS'] = array();
foreach ($arResult['ITEMS'] as $key => &$value) {
    if (empty($value['PROPERTIES']['YA_MAP']['VALUE']))
        continue;
    if ($value['PROPERTIES']['YA_MAP']['VALUE']) {
        list ($lat, $lng) = explode(',', $value['PROPERTIES']['YA_MAP']['VALUE']);
    }

    if (!empty($value["DISPLAY_PROPERTIES"]["PHONE_FOR_MESSAGING"]["VALUE"])) {
        $value["DISPLAY_PROPERTIES"]["PHONE_FOR_MESSAGING"]['FORMAT_LINK'] = preg_replace('/[^0-9]/', '', $value["DISPLAY_PROPERTIES"]["PHONE_FOR_MESSAGING"]["VALUE"]);
    }
    if (!empty($value["PROPERTIES"]["PHONE_FOR_MESSAGING"]["VALUE"])) {
        $value["PROPERTIES"]["PHONE_FOR_MESSAGING"]['FORMAT_LINK'] = preg_replace('/[^0-9]/', '', $value["PROPERTIES"]["PHONE_FOR_MESSAGING"]["VALUE"]);
    }
    $pin = array(
        $lat,
        $lng,
        'props' => array(
            'id' => $value['ID'],
            'name' => $value['NAME'],
            'address' => $value['PROPERTIES']['ADDRESS']['VALUE'],
            'phone' => $value['PROPERTIES']['PHONE']['VALUE'],
            'mode' => $value['PROPERTIES']['MODE']['~VALUE']['TEXT'] ?? $value['PROPERTIES']['MODE']['~DEFAULT_VALUE']['TEXT'],
			'telegram' => $value['PROPERTIES']['TELEGRAM']['VALUE'],
			'telegram_name' => $value['PROPERTIES']['TELEGRAM_NAME']['VALUE'],
            'phone_for_messaging' => $value["DISPLAY_PROPERTIES"]["PHONE_FOR_MESSAGING"]["VALUE"],
            'phone_for_messaging_format_link' => $value["DISPLAY_PROPERTIES"]["PHONE_FOR_MESSAGING"]["FORMAT_LINK"],
            'telegram_for_messaging' => $value["DISPLAY_PROPERTIES"]["TELEGRAM_FOR_MESSAGING"]["VALUE"],
        )
    );
    if ($value["PROPERTIES"]["LOGO_TYPE"]["VALUE"]){
        $pin['props']['logo'] = 'Y';
    }
    if ($value["PROPERTIES"]["LOGO_TYPE"]["VALUE"]){
        $pin['props']['logo'] = 'Y';
    }
    $types = array();
    $res = CIBlockElement::GetProperty($value['IBLOCK_ID'], $value['ID'], "sort", "asc", array("CODE" => "TYPE"));
    while ($ob = $res->Fetch()) {
        if (!$ob['VALUE']) continue;
        $types[$ob['VALUE']] = $ob;
    }
    if ($types){
        if ($types[4]) {
            $pin['props']['image'] = $imgType[4];
        } else {
            foreach ($types as $key1 => $type) {
                $pin['props']['image'] = $imgType[$key1];
                break;
            }
        }
        foreach ($types as $key1 => $type) {
            $typeText[] = $type['VALUE_ENUM'];
        }
        $pin['props']['type_text'] = implode('<br>', $typeText);

    }
    if ($pin['props']['phone']){
        $pin['props']['phone_link'] = preg_replace('/[ \-]/m','',str_replace('+7','8',$pin['props']['phone']));
    }

    if ($value['PROPERTIES']['NOT_SHOW_DETAIL_LINK']['VALUE'] !== 'Y'){
        $pin['props']['detail_page'] = $value['DETAIL_PAGE_URL'];
    }

    if (!empty($value['PROPERTIES']['SITE']['VALUE'])){
        $pin['props']['site_page'] = $value['PROPERTIES']['SITE']['VALUE'];
    }
    $arResult['PINS'][] = $pin;

}
unset($value);
/*echo '<pre>'.print_r($arResult['REGIONS'], true).'</pre>';
echo '<pre>'.print_r($arResult['REGIONS_SELECT'], true).'</pre>';*/