<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

use \Ldo\Favorites\Favorites;
use \Bitrix\Main\Loader;
use Ldo\Develop\Pict;

$this->setFrameMode(true);

// Загружаем модули
$developLoaded = Loader::includeModule('ldo.develop');
$favoritesLoaded = Loader::includeModule('ldo.favorites');

// Подпись для JS
$signer = new \Bitrix\Main\Security\Sign\Signer();
$signedTemplate = $signer->sign($templateName, 'catalog.section.top');
$signedParams = $signer->sign(base64_encode(serialize($arParams)), 'catalog.section.top');

// Собираем ID элементов для избранного
$allElementIds = [];
foreach ($arResult["SECTIONS"] as $arSection) {
    foreach ($arSection["ITEMS"] as $arElement) {
        $allElementIds[] = $arElement['ID'];
    }
}

// Карта избранного
$favoritesMap = [];
if ($favoritesLoaded && method_exists(Favorites::class, 'getItems')) {
    $favoritesMap = array_flip(Favorites::getItems());
}
?>
<div class="container">
    <div class="row">
        <div class="col-xs-12">
            <?foreach($arResult["SECTIONS"] as $arSection):?>
                <?if(count($arSection["ITEMS"]) > 0):?>
                    <div class="titleSectionCatalog"><?=$arSection['NAME']?></div>
                    <div class="product-line">
                        <?foreach($arSection["ITEMS"] as $arElement):
                            $itemId = $this->GetEditAreaId($arElement['ID']);

                            // Подготовка изображений
                            $webP = '';
                            $bgProduct = '';
                            if($developLoaded && $arElement['PREVIEW_PICTURE']['ID']){
                                $webP = Pict::getResizeWebpSrc($arElement['PREVIEW_PICTURE']['ID'], 280, 280, true, 65);
                                $bgProductResize = CFile::ResizeImageGet($arElement['PREVIEW_PICTURE']['ID'], ['width'=>280, 'height'=>280], BX_RESIZE_IMAGE_PROPORTIONAL, true);
                                $bgProduct = $bgProductResize['src'];
                            }

                            // Флаги товара
                            $new = ($arElement['PROPERTIES']['ATT_NEW']['VALUE'] == 'да');
                            $popular = ($arElement['PROPERTIES']['ATT_POPULAR']['VALUE'] == 'да');
                            $hot = ($arElement['PROPERTIES']['ATT_OSTRO']['VALUE'] == 'да');
                            $vegan = ($arElement['PROPERTIES']['ATT_VEGAN']['VALUE'] == 'да');
                            $disabledClass = $arElement['CAN_BUY'] ? '' : 'not-avaliable';

                            // Избранное
                            $favoriteClass = ($favoritesLoaded && isset($favoritesMap[$arElement['ID']])) ? 'active' : '';

                            $this->AddEditAction($arElement['ID'], $arElement['EDIT_LINK'], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_EDIT"));
                            $this->AddDeleteAction($arElement['ID'], $arElement['DELETE_LINK'], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BCST_ELEMENT_DELETE_CONFIRM')));

                            // Обрезаем описание
                            $shortDescription = $arElement['DETAIL_TEXT'];
                            if (mb_strlen($shortDescription) > 100) {
                                $shortDescription = mb_substr($shortDescription, 0, 100) . '...';
                            }

                            // Цена
                            $priceValue = '';
                            $priceOld = '';
                            $discountPercent = '';
                            foreach($arElement["PRICES"] as $arPrice){
                                if($arPrice["CAN_ACCESS"]){
                                    $priceValue = $arPrice["PRINT_VALUE"];
                                    if($arPrice['DISCOUNT'] > 0) {
                                        $priceOld = $arPrice['PRINT_BASE_VALUE'];
                                        $discountPercent = round($arPrice['DISCOUNT'], 0);
                                    }
                                    break;
                                }
                            }

                            $measureRatio = $arElement['ITEM_MEASURE_RATIO'] ?? 1;
                            ?>
                            <div class="product-item-container" id="<?=$itemId?>" data-entity="item">
                                <a href="<?=$arElement['DETAIL_PAGE_URL']?>" title="<?=$arElement['NAME']?>" data-entity="image-wrapper" tabindex="0">
                                    <div class="tags-bottom-product">
                                        <?if($new):?><span title="Новинка" class="new"></span><?endif;?>
                                        <?if($hot):?><span title="Острое" class="hot"></span><?endif;?>
                                        <?if($popular):?><span title="Популярное" class="popular"></span><?endif;?>
                                        <?if($vegan):?><span title="Вегетарианское" class="vegan"></span><?endif;?>
                                    </div>
                                    <div id="image-product-block">
                                        <?if($webP):?>
                                            <picture>
                                                <source srcset="<?=$webP?>" media="(min-width: 1920px)">
                                                <img loading="lazy" src="<?=$bgProduct?>" alt="<?=$arElement['NAME']?>" title="<?=$arElement['NAME']?>" itemprop="image">
                                            </picture>
                                        <?else:?>
                                            <img loading="lazy" src="<?=$bgProduct?>" alt="<?=$arElement['NAME']?>" title="<?=$arElement['NAME']?>" itemprop="image">
                                        <?endif;?>
                                    </div>
                                </a>
                                <div class="wish-add <?=$favoriteClass?>" data-id="<?=$arElement['ID']?>">
                                    <svg width="20" height="17" viewBox="0 0 20 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9.99999 16C9.90893 16 9.81792 15.9773 9.73635 15.9319C9.64776 15.8827 7.54293 14.7052 5.4079 12.9309C4.14249 11.8793 3.13238 10.8363 2.4057 9.83094C1.46535 8.52996 0.992463 7.27858 1.00009 6.1115C1.00902 4.75348 1.51383 3.47635 2.42163 2.51533C3.34476 1.53813 4.5767 1 5.89059 1C7.57446 1 9.11398 1.90885 10 3.34858C10.8861 1.90888 12.4256 1 14.1095 1C15.3508 1 16.5351 1.48555 17.4443 2.36724C18.4422 3.33479 19.0092 4.70189 18.9999 6.11794C18.9922 7.28298 18.5105 8.53247 17.568 9.83165C16.8391 10.8365 15.8304 11.8791 14.57 12.9303C12.4427 14.7044 10.353 15.8819 10.2651 15.9312C10.1846 15.9763 10.0931 16 9.99999 16Z" fill="white" stroke="#2B2D2F"></path>
                                    </svg>
                                </div>
                                <h3 class="<?=$disabledClass?> product-item-title">
                                    <a href="<?=$arElement['DETAIL_PAGE_URL']?>" title="<?=$arElement['NAME']?>" tabindex="0">
                                        <?=$arElement['NAME']?>
                                    </a>
                                    <?if($arElement['DISPLAY_PROPERTIES']['ATT_VES']['VALUE']):?>
                                        <div class="weight-product"><?=$arElement['DISPLAY_PROPERTIES']['ATT_VES']['VALUE']?></div>
                                    <?endif;?>
                                </h3>
                                <?if($shortDescription):?>
                                    <div class="<?=$disabledClass?> desc-product"><?=$shortDescription?></div>
                                <?endif;?>
                                <div class="bottom-line">
                                    <div class="product-item-info-container product-item-price-container" data-entity="price-block">
                                        <?if($priceOld):?>
                                            <span class="product-item-price-old"><?=$priceOld?></span>&nbsp;
                                        <?endif;?>
                                        <span class="product-item-price-current"><?=$priceValue?></span>
                                        <?if($discountPercent):?>
                                            <span class="product-item-price-discount">-<?=$discountPercent?>%</span>
                                        <?endif;?>
                                    </div>

                                    <?if($arElement['CAN_BUY']):?>
                                        <div class="product-item-info-container" data-entity="buttons-block">
                                            <div class="product-item-button-container">
                                                <button class="btn btn-primary addCart btn-md"
                                                        data-id="<?=$arElement['ID']?>"
                                                        data-quantity="<?=$measureRatio?>"
                                                        href="javascript:void(0)"
                                                        rel="nofollow"
                                                        tabindex="0">
                                                    <svg width="18" height="21" viewBox="0 0 18 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M4.88622 6.17594V5.28917C4.88622 3.23221 6.54093 1.21183 8.59788 1.01985C11.0479 0.782153 13.114 2.71112 13.114 5.11547V6.37707M6.25739 19.2764H11.7426C15.4177 19.2764 16.0759 17.8046 16.2679 16.0127L16.9536 10.5275C17.2004 8.29686 16.5604 6.47759 12.6568 6.47759H5.34319C1.43955 6.47759 0.79961 8.29686 1.04644 10.5275L1.7321 16.0127C1.92408 17.8046 2.5823 19.2764 6.25739 19.2764Z" stroke="#F44336" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        <path d="M12.1958 10.1343H12.204M5.79541 10.1343H5.80362" stroke="#F44336" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    <?else:?>
                                        <div class="product-item-info-container" data-entity="buttons-block">
                                            <div class="disabled-product-btn">Будет позже</div>
                                        </div>
                                    <?endif;?>
                                </div>
                            </div>
                        <?endforeach?>
                    </div>
                <?endif;?>
            <?endforeach?>
        </div>
    </div>
</div>

<script>
    // Кастомная обработка добавления в корзину
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.addCart').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                var productId = this.getAttribute('data-id');
                var quantity = this.getAttribute('data-quantity') || 1;

                if (typeof BX !== 'undefined' && BX.ajax) {
                    BX.ajax({
                        url: '<?=$arParams["~ADD_URL_TEMPLATE"]?>',
                        method: 'POST',
                        data: {
                            'action': 'ADD2BASKET',
                            'id': productId,
                            'quantity': quantity
                        },
                        dataType: 'json',
                        onsuccess: function(data) {
                            if (data && data.STATUS === 'OK') {
                                // Обновляем счетчик корзины
                                if (typeof updateBasketCount === 'function') {
                                    updateBasketCount();
                                }
                                // Показываем уведомление
                                alert('Товар добавлен в корзину');
                            }
                        },
                        onfailure: function() {
                            alert('Ошибка при добавлении в корзину');
                        }
                    });
                }
            });
        });
    });
</script>