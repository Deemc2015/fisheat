<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>




<?if (!empty($arResult)):?>
    <!-- Навигация -->
    <nav class="p-sidebar__nav">
        <ul class="p-sidebar__menu">
            <?
            foreach($arResult as $arItem):
                if($arParams["MAX_LEVEL"] == 1 && $arItem["DEPTH_LEVEL"] > 1)
                    continue;
                ?>
            <li class="p-sidebar__menu-item">
                <a href="<?=$arItem["LINK"]?>" class="p-sidebar__menu-link <?if($arItem["SELECTED"]){echo 'active';} ?>">
                    <?=$arItem['PARAMS']["ICON"]?>
                    <span><?=$arItem["TEXT"]?></span>
                </a>
            </li>
            <?endforeach?>



        </ul>
    </nav>

<?endif?>

