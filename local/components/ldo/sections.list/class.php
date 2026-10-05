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
                $path = \CFile::GetPath((int)$section['PICTURE']);
                $section['ICON_SRC'] = $path;

                // Диагностика: файл может быть не сохранён физически
                if (!empty($path) && !file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
                    addMessage2Log('sections.icon: файл ID ' . (int)$section['PICTURE'] . ' по пути ' . $path . ' НЕ существует на диске');
                }
            }

            // SEO-данные раздела (meta title/description/keywords)
            $seo = $this->getSeoParams((int)$section['ID']);
            $section['SEO_TITLE']       = $seo['title'];
            $section['SEO_DESCRIPTION'] = $seo['description'];
            $section['SEO_KEYWORDS']    = $seo['keywords'];

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * SEO-параметры раздела (meta title/description/keywords).
     * Данные берутся из Prokhorov\Api\Helpers\Seo (модуль ldo.develop).
     *
     * @param int $sectionId
     * @return array{title: string, description: string, keywords: string}
     */
    private function getSeoParams(int $sectionId): array
    {
        if (
            !Loader::includeModule('ldo.develop')
            || !class_exists('\\Prokhorov\\Api\\Helpers\\Seo')
        ) {
            return ['title' => '', 'description' => '', 'keywords' => ''];
        }

        try {
            $params = \Prokhorov\Api\Helpers\Seo::getSectionParams(self::IBLOCK_ID, $sectionId);
        } catch (\Throwable $e) {
            return ['title' => '', 'description' => '', 'keywords' => ''];
        }

        return [
            'title'       => (string)($params['title'] ?? ''),
            'description' => (string)($params['description'] ?? ''),
            'keywords'    => (string)($params['keywords'] ?? ''),
        ];
    }

    /**
     * Разметка визуального HTML-редактора описания раздела (Bitrix CHTMLEditor).
     * Скелет выводится скрытым (display=false) и лишь сохраняет конфиг —
     * сам редактор создаётся на клиенте в момент вставки (BXHtmlEditor.Show).
     * HTML редактора отдаётся по AJAX (getEditorAction), чтобы не включать
     * тяжёлую разметку на каждый раздел в исходную страницу.
     * Если модуль fileman недоступен — фолбэк на обычный textarea.
     *
     * @param int $id
     * @param string $content
     * @return string
     */
    private function renderSectionEditor(int $id, string $content): string
    {
        if (!Loader::includeModule('fileman') || !class_exists('\CHTMLEditor')) {
            return '<textarea class="section-desc" rows="6" disabled>' . htmlspecialcharsbx($content) . '</textarea>';
        }

        ob_start();
        $editor = new \CHTMLEditor();
        $editor->Show([
            'id'                        => 'sd' . $id,
            'inputName'                 => 'section_description_' . $id,
            'inputId'                   => 'section_description_' . $id,
            'content'                   => $content,
            'display'                   => false,
            // height — целое число (пиксели): JS-редактор использует его в арифметике.
            'width'                     => '100%',
            'height'                    => 320,
            'showNodeNavi'              => false,
            'arTemplates'               => [],
            'useFileDialogs'            => false,
            'showTaskbars'              => false,
            'showComponents'            => false,
            'showSnippets'              => false,
            'bAllowPhp'                 => false,
            'allowPhp'                  => false,
            'askBeforeUnloadPage'       => false,
            'uploadImagesFromClipboard' => false,
            'setFocusAfterShow'         => false,
            'placeholder'               => 'Описание раздела',
            'fontSize'                  => '14px',
            'iframeCss'                 => $this->getIframeCss(),
        ]);

        return (string)ob_get_clean();
    }

    /**
     * CSS для содержимого iframe редактора (папка редактора изолирована,
     * поэтому стили темы передаются текстом отдельно).
     *
     * @return string
     */
    private function getIframeCss(): string
    {
        static $css = null;
        if ($css !== null) {
            return $css;
        }

        $path = $_SERVER['DOCUMENT_ROOT']
            . '/local/components/ldo/sections.list/templates/.default/editor-iframe.css';

        $css = is_file($path) ? (string)file_get_contents($path) : '';

        return $css;
    }

    /**
     * Очистка пользовательского HTML описания раздела (защита от XSS).
     * SECURE_LEVEL_LOW убирает script/iframe/embed и on*-атрибуты,
     * но сохраняет форматирование (div/span/style/class).
     *
     * @param string $html
     * @return string
     */
    private function sanitizeDescriptionHtml(string $html): string
    {
        if ($html === '' || !class_exists('\CBXSanitizer')) {
            return $html;
        }

        $sanitizer = new \CBXSanitizer();
        $sanitizer->SetLevel(\CBXSanitizer::SECURE_LEVEL_LOW);

        return (string)$sanitizer->SanitizeHtml($html);
    }

    /**
     * Конфигурация AJAX-действий.
     *
     * @return array
     */
    public function configureActions()
    {
        return [
            'saveSection' => [
                'prefilters' => [],
            ],
            'getEditor' => [
                'prefilters' => [],
            ],
        ];
    }

    /**
     * AJAX-действие: разметка визуального редактора описания для раздела.
     *
     * @return array{html: string, error?: string}
     */
    public function getEditorAction(): array
    {
        if (!check_bitrix_sessid()) {
            return [
                'html'  => '',
                'error' => 'Ошибка сессии. Пожалуйста, обновите страницу.',
            ];
        }

        if (!Loader::includeModule('iblock')) {
            return [
                'html'  => '',
                'error' => 'Модуль iblock не найден.',
            ];
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $id = (int)$request->getPost('id');
        if ($id <= 0) {
            return [
                'html'  => '',
                'error' => 'Не передан ID раздела.',
            ];
        }

        $rs = \CIBlockSection::GetList(
            [],
            ['ID' => $id, 'IBLOCK_ID' => self::IBLOCK_ID],
            false,
            ['ID', 'DESCRIPTION']
        );

        $row = $rs->Fetch();
        if (!$row) {
            return [
                'html'  => '',
                'error' => 'Раздел не найден.',
            ];
        }

        return [
            'html' => $this->renderSectionEditor($id, (string)$row['DESCRIPTION']),
        ];
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

        // Описание приходит из визуального редактора — очищаем HTML и
        // сохраняем как HTML (по умолчанию у раздела DESCRIPTION_TYPE=text).
        $description = $this->sanitizeDescriptionHtml((string)$request->getPost('description'));

        $fields = [
            'ACTIVE'             => $request->getPost('active') === 'Y' ? 'Y' : 'N',
            'UF_VIEW_INDEX'      => (int)$request->getPost('viewIndex') > 0 ? 1 : 0,
            'IBLOCK_SECTION_ID'  => (int)$request->getPost('parentId'),
            'DESCRIPTION'        => $description,
            'DESCRIPTION_TYPE'   => 'html',
            'SORT'               => (int)$request->getPost('sort'),
        ];

        // SEO-данные раздела (inherited properties) — meta title/description/keywords.
        // Пустое значение означает отказ от собственного значения (наследуется
        // с родительского раздела/инфоблока).
        $seoFields = [];
        if ($request->getPost('seoTitle') !== null) {
            $seoFields['SECTION_META_TITLE'] = trim((string)$request->getPost('seoTitle'));
        }
        if ($request->getPost('seoDescription') !== null) {
            $seoFields['SECTION_META_DESCRIPTION'] = trim((string)$request->getPost('seoDescription'));
        }
        if ($request->getPost('seoKeywords') !== null) {
            $seoFields['SECTION_META_KEYWORDS'] = trim((string)$request->getPost('seoKeywords'));
        }
        if (!empty($seoFields)) {
            $fields['IPROPERTY_TEMPLATES'] = $seoFields;
        }


        $picture = $request->getFile('picture');



        if (is_array($picture) && !empty($picture['tmp_name']) && (int)($picture['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            // В PICTURE передаём МАССИВ файла — CIBlockSection::Update сам
            // сохранит его через CFile и запишет ID (передача ID не обновляет фото).
            $fields['PICTURE'] = $picture;

            // Пытаемся сжать до 50x50 и передать сжатую копию
            $resized = \CFile::ResizeImageGet(
                $picture,
                ['width' => 50, 'height' => 50],
                BX_RESIZE_IMAGE_EXACT,
                true
            );

            if (is_array($resized) && !empty($resized['src'])) {
                $fileArray = \CFile::MakeFileArray($resized['src']);
                if ($fileArray) {
                    $fields['PICTURE'] = $fileArray;
                }
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

        // Сброс кеша SEO-параметров раздела
        $this->clearSeoCache($id);

        return [
            'success'     => true,
            'description' => $description,
            'seo'         => [
                'title'       => $seoFields['SECTION_META_TITLE'] ?? null,
                'description' => $seoFields['SECTION_META_DESCRIPTION'] ?? null,
                'keywords'    => $seoFields['SECTION_META_KEYWORDS'] ?? null,
            ],
        ];
    }

    /**
     * Сброс кеша SEO-параметров раздела (см. Prokhorov\Api\Helpers\Seo).
     *
     * @param int $sectionId
     * @return void
     */
    private function clearSeoCache(int $sectionId): void
    {
        try {
            \Bitrix\Main\Data\Cache::createInstance()->cleanDir('/prokhorov/api/seo/');

            \Bitrix\Main\Application::getInstance()
                ->getTaggedCache()
                ->clearByTag('section_' . $sectionId);

            if (Loader::includeModule('iblock')) {
                (new \Bitrix\Iblock\InheritedProperty\SectionValues(self::IBLOCK_ID, $sectionId))
                    ->clearValues();
            }
        } catch (\Throwable $e) {
            // Кеш не критичен — не прерываем сохранение.
        }
    }
}
