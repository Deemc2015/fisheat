<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>
<?
/**
 * Футер шаблона "partners" (раздел /partners/).
 * Закрывает layout, открытый в header.php шаблона, и документ.
 * Подключается в самом конце страницы (после контента и скриптов).
 *
 * Опция: $partnersRequireEpilog = true — подключать epilog.php
 * (для страниц, работающих на полном prolog, например dashboard и delivery-zones).
 */
?>
    </main>
</div><!-- /.partners-page -->

<?if (!empty($partnersRequireEpilog) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php')):?>
    <?require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php');?>
<?endif;?>
</body>
</html>
Низ партнерки