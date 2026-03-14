<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
/**
 * @global array $arParams
 * @global CUser $USER
 * @global CMain $APPLICATION
 * @global string $cartId
 */
$compositeStub = (isset($arResult['COMPOSITE_STUB']) && $arResult['COMPOSITE_STUB'] == 'Y');
?><?
		if (!$arResult["DISABLE_USE_BASKET"])
		{
			?>

            <a href="<?= $arParams['PATH_TO_BASKET'] ?>" class="cart-page">
                    <?
                    if (!$compositeStub)
                    {
                        if ($arResult['NUM_PRODUCTS'] > 0)
                        {?>

                             <span class="count-cart"><?=$arResult['NUM_PRODUCTS']?></span>

                       <? }
                    }
                    ?>
                <svg width="97" height="97" viewBox="0 0 97 97" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g filter="url(#filter0_d_1049_767)">
                        <circle cx="48.5" cy="43.5" r="27.5" fill="#2B2D2F"/>
                    </g>
                    <path d="M42.0068 37.9868V36.7518C42.0068 33.8872 44.3112 31.0736 47.1758 30.8062C50.5879 30.4752 53.4652 33.1615 53.4652 36.5099V38.2668" fill="#2B2D2F"/>
                    <path d="M42.0068 37.9868V36.7518C42.0068 33.8872 44.3112 31.0736 47.1758 30.8062C50.5879 30.4752 53.4652 33.1615 53.4652 36.5099V38.2668" stroke="#FD1313" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M43.9168 56.2318H51.5557C56.6738 56.2318 57.5905 54.182 57.8578 51.6867L58.8127 44.0478C59.1564 40.9413 58.2652 38.4077 52.8289 38.4077H42.6437C37.2073 38.4077 36.3161 40.9413 36.6599 44.0478L37.6148 51.6867C37.8821 54.182 38.7988 56.2318 43.9168 56.2318Z" fill="#2B2D2F" stroke="#FD1313" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M52.1862 43.5001H52.1977Z" fill="#2B2D2F"/>
                    <path d="M52.1862 43.5001H52.1977" stroke="#FD1313" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M43.2731 43.5001H43.2846Z" fill="#2B2D2F"/>
                    <path d="M43.2731 43.5001H43.2846" stroke="#FD1313" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <defs>
                        <filter id="filter0_d_1049_767" x="0" y="0" width="97" height="97" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                            <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                            <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                            <feMorphology radius="5" operator="dilate" in="SourceAlpha" result="effect1_dropShadow_1049_767"/>
                            <feOffset dy="5"/>
                            <feGaussianBlur stdDeviation="8"/>
                            <feComposite in2="hardAlpha" operator="out"/>
                            <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.4 0"/>
                            <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_1049_767"/>
                            <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_1049_767" result="shape"/>
                        </filter>
                    </defs>
                </svg></a>

			<?
		}

		?>
