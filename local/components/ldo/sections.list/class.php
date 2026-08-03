<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

Loader::includeModule("iblock");

class SectionsList extends \CBitrixComponent implements Controllerable
{
    /**
     * ID инфоблока, из которого выводятся разделы.
     * Пока задан константой, при необходимости можно вынести в параметр.
     */
    const IBLOCK_ID = 4;

    public function executeComponent()
    {
        $this->arResult['SECTIONS'] = $this->getSections();

        $this->includeComponentTemplate();
    }

    /**
     * Получить разделы инфоблока.
     *
     * @return array
     */
    private function getSections(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $sections = [];

        $rs = \CIBlockSection::GetList(
            ["SORT" => "ASC", "NAME" => "ASC"],
            [
                "IBLOCK_ID" => self::IBLOCK_ID,
            ],
            false,
            [
                "ID",
                "NAME",
                "CODE",
                "IBLOCK_SECTION_ID",
                "SECTION_PAGE_URL",
                "PICTURE",
                "DESCRIPTION",
                "SORT",
                "UF_ID_RK",
                "UF_VIEW_INDEX",
                "ACTIVE"
            ]
        );

        while ($section = $rs->GetNext()) {
            // Иконка раздела: фото уже сжато при сохранении, выводим напрямую
            $section['ICON_SRC'] = '';
            if (!empty($section['PICTURE'])) {
                $section['ICON_SRC'] = \CFile::GetPath((int)$section['PICTURE']);
            }

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * Конфигурация AJAX-действий.
     *
     * @return array
     */
    public function configureActions()
    {
        return [];
    }

    /**
     * Сохранение параметров раздела через контроллер (runComponentAction).
     * Данные приходят multipart/form-data (FormData): поля + файл 'picture'.
     * Иконка сжимается до 50x50 перед сохранением.
     *
     * @return array
     */
    public function saveSectionAction()
    {
        if (!check_bitrix_sessid()) {
            return [
                'success' => false,
                'error'   => 'Ошибка сессии. Пожалуйста, обновите страницу.',
            ];
        }

        if (!Loader::includeModule('iblock')) {
            return [
                'success' => false,
                'error'   => 'Модуль iblock не найден.',
            ];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return [
                'success' => false,
                'error'   => 'Не передан ID раздела.',
            ];
        }

        $fields = [
            'ACTIVE'             => $request->getPost('active') === 'Y' ? 'Y' : 'N',
            'UF_VIEW_INDEX'      => (int)$request->getPost('viewIndex') > 0 ? 1 : 0,
            'IBLOCK_SECTION_ID'  => (int)$request->getPost('parentId'),
            'DESCRIPTION'        => (string)$request->getPost('description'),
            'SORT'               => (int)$request->getPost('sort'),
        ];

        // Иконка: сжимаем фото перед сохранением (50x50)
        $picture = $request->getFile('picture');

        addMessage2Log($picture);

        if (is_array($picture) && !empty($picture['tmp_name']) && (int)($picture['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $fileId = 0;

            $resized = \CFile::ResizeImageGet(
                $picture,
                ['width' => 50, 'height' => 50],
                BX_RESIZE_IMAGE_EXACT,
                true
            );

            if (is_array($resized) && !empty($resized['src'])) {
                $fileArray = \CFile::MakeFileArray($resized['src']);
                if ($fileArray) {
                    $fileId = (int)\CFile::SaveFile($fileArray, 'iblock');
                }
            }

            // Если сжать не удалось — сохраняем исходный файл
            if (!$fileId) {
                $fileId = (int)\CFile::SaveFile($picture, 'iblock');
            }

            if ($fileId) {
                $fields['PICTURE'] = $fileId;
            }
        }

        $section = new \CIBlockSection();
        $result = $section->Update($id, $fields);

        if (!$result) {
            return [
                'success' => false,
                'error'   => $section->LAST_ERROR ?: 'Не удалось обновить раздел.',
            ];
        }

        return [
            'success' => true,
        ];
    }
}
