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
?>
<div class="container">

<div class="row">
    <div class="col-xs-12">

<?foreach($arResult["SECTIONS"] as $arSection):?>
    <?if(count($arSection["ITEMS"]) > 0):?>
    <div class="titleSectionCatalog"><?=$arSection['NAME']?></div>
    <div class="product-line">
        <?foreach($arSection["ITEMS"] as $arElement):?>
            <?
            if(Loader::includeModule('ldo.develop')){
                $webP = Pict::getResizeWebpSrc($arElement['PREVIEW_PICTURE']['ID'], 280, 280, true, 65);
                $webP_768 = Pict::getResizeWebpSrc($arElement['PREVIEW_PICTURE']['ID'], 208, 208, true, 65);
                $webP_400 = Pict::getResizeWebpSrc($arElement['PREVIEW_PICTURE']['ID'], 187, 187, true, 65);
            }

            if($arElement['PROPERTIES']['ATT_NEW']['VALUE'] == 'да'){
                $new = true;
            }
            if($arElement['PROPERTIES']['ATT_POPULAR']['VALUE'] == 'да'){
                $popular = true;
            }
            if($arElement['PROPERTIES']['ATT_OSTRO']['VALUE'] == 'да'){
                $hot = true;
            }
            if($arElement['PROPERTIES']['ATT_VEGAN']['VALUE'] == 'да'){
                $vegan = true;
            }
            if(!$arElement['CAN_BUY']){
                $disabledClass = 'not-avaliable';
            }


            if (Loader::IncludeModule('ldo.favorites')) {
                if (Favorites::isOnList($arElement['ID'])) {
                    $class = 'active';
                }
            }


            $this->AddEditAction($arElement['ID'], $arElement['EDIT_LINK'], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_EDIT"));
            $this->AddDeleteAction($arElement['ID'], $arElement['DELETE_LINK'], CIBlock::GetArrayByID($arParams["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BCST_ELEMENT_DELETE_CONFIRM')));
            ?>
            <div class="product-item-container" id="<?=$this->GetEditAreaId($arElement['ID']);?>" data-entity="item">
                <a  href="<?=$arElement['DETAIL_PAGE_URL']?>" title="<?=$arElement['NAME']?>" data-entity="image-wrapper" tabindex="0">
                    <div class="tags-bottom-product">
                        <span title="Новинка" class="new"></span>
                        <span title="Острое" class="hot"></span>
                    </div>


					</span>
                    <div id="image-product-block">
                        <picture>
                            <source srcset="<?=$webP?>" media="(min-width: 1920px)">
                            <source srcset="<?=$webP_768?>" media="(min-width: 768px)">
                            <source srcset="<?=$webP_400?>" media="(min-width: 400px)">
                            <img id="<?=$this->GetEditAreaId($arElement['ID']);?>" src="/upload/resize_cache/saved/3b2/280_280_1/zwivic085fivmrk5zgmgh5ob8s9qh31s.jpg" alt="Шампиньоны терияки с рисом" title="" itemprop="image">
                        </picture>

                    </div>

                </a>
                <div class="wish-add <?=$class?>" data-id="<?=$arElement['ID']?>">
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
                <?
                $maxLength = 105; // Нужное количество символов
                $shortDescription = $arElement['DETAIL_TEXT'];

                if (mb_strlen($shortDescription) > $maxLength) {
                    $shortDescription = mb_substr($shortDescription, 0, $maxLength) . '...';
                }
                ?>
                <?if($shortDescription ):?>
                    <div class=" <?=$disabledClass?> desc-product">
                        <?=$shortDescription ?>
                    </div>
                <?endif;?>
                <div class="bottom-line">
                    <div class="product-item-info-container product-item-price-container" data-entity="price-block">
                    <?foreach($arElement["PRICES"] as $code=>$arPrice):?>
                        <?if($arPrice["CAN_ACCESS"]):?>
                            <span class="product-item-price-current" id="<?=$this->GetEditAreaId($arElement['ID']);?>">
							    <?=$arPrice["PRINT_VALUE"]?>
                            </span>
                        <?endif;?>
                    <?endforeach;?>

                    </div>

                    <div class="product-item-info-container " data-entity="buttons-block">
                        <div class="product-item-button-container" id="<?=$this->GetEditAreaId($arElement['ID']);?>_basket_actions">
                            <button data-id="<?=$arElement['ID']?>" class=" btn btn-primary addCart btn-md" id="bx_3966226736_2933_7e1b8e3524755c391129a9d7e6f2d206_buy_link" href="javascript:void(0)" rel="nofollow" tabindex="0"><svg width="18" height="21" viewBox="0 0 18 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.88622 6.17594V5.28917C4.88622 3.23221 6.54093 1.21183 8.59788 1.01985C11.0479 0.782153 13.114 2.71112 13.114 5.11547V6.37707M6.25739 19.2764H11.7426C15.4177 19.2764 16.0759 17.8046 16.2679 16.0127L16.9536 10.5275C17.2004 8.29686 16.5604 6.47759 12.6568 6.47759H5.34319C1.43955 6.47759 0.79961 8.29686 1.04644 10.5275L1.7321 16.0127C1.92408 17.8046 2.5823 19.2764 6.25739 19.2764Z" stroke="#F44336" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M12.1958 10.1343H12.204M5.79541 10.1343H5.80362" stroke="#F44336" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?endforeach?>
    </div>
    <?endif;?>
<?endforeach?>
</div>
</div>
</div>
