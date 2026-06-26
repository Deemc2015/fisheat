<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var CBitrixComponentTemplate $this
 * @var CatalogSectionComponent $component
 */

$component = $this->getComponent();
$arParams = $component->applyTemplateModifications();




$categoryId = $arResult['ORIGINAL_PARAMETERS']['SECTION_ID'];

if($categoryId){
    $categoryParams = CIBlockSection::GetByID($categoryId);
    if($result = $categoryParams->GetNext()){
        $categoryInfo = $result;
    }
	
    
    if($categoryInfo){
        $arResult['CATEGORY_INFO'] = [
            "NAME" =>  $categoryInfo['NAME'],
            "URL" => $categoryInfo['SECTION_PAGE_URL'],
            "CODE" => $categoryInfo['CODE']
        ];
    }
    
    //фильтру указываем ID раздела и ID его инфоблока
    $arFilter = array('SECTION_ID' => $categoryId); // устанавливаем фильр - что ищем. Если у раздела родитель имеет ID равный текущему, что это наш пациент
    $rsSect = CIBlockSection::GetList(
        Array("SORT"=>"ASC"),
        $arFilter, 
        false,
        ['NAME', 'SECTION_PAGE_URL','UF_VIEW_INDEX','CODE','ID']
    );
    while ($arSect = $rsSect->GetNext()) {
        
        $arResult['CATEGORY_INFO']['PODRAZDEL'][] = $arSect;
        
        
    }

    unset($arSect,$categoryInfo,$categoryParams,$categoryId);
}


// Добавляем скрипт через AddBufferContent
global $APPLICATION;
$APPLICATION->AddBufferContent(function() {
    return '<script>
    (function() {
        // Флаг, чтобы скрипт выполнился только один раз
        if (window._basketScriptLoaded) return;
        window._basketScriptLoaded = true;
        
        // Получаем ID из localStorage или запрашиваем
        function updateButtons(ids) {
            if (!ids || !Array.isArray(ids)) return;
            
            var buttons = document.querySelectorAll(".addCart");
            for (var i = 0; i < buttons.length; i++) {
                var id = parseInt(buttons[i].getAttribute("data-id"));
                if (ids.indexOf(id) !== -1) {
                    buttons[i].classList.add("in_cart");
                }
            }
        }
        
        // Проверяем localStorage
        var cached = localStorage.getItem("basket_ids");
        if (cached) {
            try {
                var ids = JSON.parse(cached);
                if (Array.isArray(ids) && ids.length > 0) {
                    updateButtons(ids);
                }
            } catch(e) {}
        }
        
        // Запрашиваем актуальные данные
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "/local/ajax/get_basket_ids.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data && data.ids) {
                        localStorage.setItem("basket_ids", JSON.stringify(data.ids));
                        updateButtons(data.ids);
                    }
                } catch(e) {
                    console.error("Basket error:", e);
                }
            }
        };
        xhr.send("action=getBasketIds&sessid=" + BX.bitrix_sessid());
    })();
    </script>';
});