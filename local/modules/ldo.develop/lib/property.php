<?php

namespace Ldo\Develop;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

/**
 * Класс пользовательских типов свойств инфоблоков
 */
class Property
{
    /**
     * Регистрация типа свойства "Привязка к сайту"
     *
     * @return array
     */
    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => 'SITE',
            'DESCRIPTION' => 'Привязка к сайту',
            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
            'GetAdminListViewHTML' => [__CLASS__, 'GetAdminListViewHTML'],
            'GetPublicViewHTML' => [__CLASS__, 'GetPublicViewHTML'],
            'GetPublicEditHTML' => [__CLASS__, 'GetPublicEditHTML'],
            'GetPublicEditHTMLMulty' => [__CLASS__, 'GetPublicEditHTMLMulty'],
            'ConvertToDB' => [__CLASS__, 'ConvertToDB'],
            'ConvertFromDB' => [__CLASS__, 'ConvertFromDB'],
            'CheckFields' => [__CLASS__, 'CheckFields'],
        ];
    }

    /**
     * Получить список сайтов
     *
     * @return array
     */
    private static function getSiteList(): array
    {
        $sites = [];
        $rsSites = \CSite::GetList('sort', 'asc', ['ACTIVE' => 'Y']);
        while ($site = $rsSites->Fetch()) {
            $sites[$site['LID']] = '[' . $site['LID'] . '] ' . $site['NAME'];
        }
        return $sites;
    }

    /**
     * HTML поля редактирования в админке
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName): string
    {
        $currentValue = htmlspecialcharsEx($value['VALUE'] ?? '');
        $sites = self::getSiteList();

        $html = '<select name="' . $strHTMLControlName['VALUE'] . '" style="max-width:300px;">';
        $html .= '<option value="">— не выбрано —</option>';
        foreach ($sites as $lid => $name) {
            $selected = $lid === $currentValue ? ' selected' : '';
            $html .= '<option value="' . htmlspecialcharsEx($lid) . '"' . $selected . '>' . htmlspecialcharsEx($name) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Отображение в списке админки
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetAdminListViewHTML($arProperty, $value, $strHTMLControlName): string
    {
        $lid = trim($value['VALUE'] ?? '');
        if ($lid === '') {
            return '&nbsp;';
        }

        $site = \CSite::GetByID($lid)->Fetch();
        $name = $site ? '[' . $site['LID'] . '] ' . $site['NAME'] : $lid;

        return htmlspecialcharsEx($name);
    }

    /**
     * Отображение на публичной части
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPublicViewHTML($arProperty, $value, $strHTMLControlName): string
    {
        $lid = trim($value['VALUE'] ?? '');

        if ($lid === '') {
            return '';
        }

        if (isset($strHTMLControlName['MODE']) && $strHTMLControlName['MODE'] === 'CSV_EXPORT') {
            return $lid;
        }

        $site = \CSite::GetByID($lid)->Fetch();
        return $site ? htmlspecialcharsEx($site['NAME']) : htmlspecialcharsEx($lid);
    }

    /**
     * Поле редактирования на публичной части
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPublicEditHTML($arProperty, $value, $strHTMLControlName): string
    {
        $currentValue = htmlspecialcharsEx($value['VALUE'] ?? '');
        $sites = self::getSiteList();

        $html = '<select name="' . $strHTMLControlName['VALUE'] . '">';
        $html .= '<option value="">— не выбрано —</option>';
        foreach ($sites as $lid => $name) {
            $selected = $lid === $currentValue ? ' selected' : '';
            $html .= '<option value="' . htmlspecialcharsEx($lid) . '"' . $selected . '>' . htmlspecialcharsEx($name) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Поле редактирования для множественного свойства на публичной части
     *
     * @param array $arProperty
     * @param array $value
     * @param array $strHTMLControlName
     * @return string
     */
    public static function GetPublicEditHTMLMulty($arProperty, $value, $strHTMLControlName): string
    {
        $currentValues = [];
        if (is_array($value)) {
            foreach ($value as $v) {
                if (isset($v['VALUE']) && $v['VALUE'] !== '') {
                    $currentValues[] = $v['VALUE'];
                }
            }
        }

        $sites = self::getSiteList();

        $html = '<select name="' . $strHTMLControlName['VALUE'] . '[]" multiple size="5" style="min-width:200px;">';
        foreach ($sites as $lid => $name) {
            $selected = in_array($lid, $currentValues, true) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialcharsEx($lid) . '"' . $selected . '>' . htmlspecialcharsEx($name) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Преобразование перед сохранением
     *
     * @param array $arProperty
     * @param array $value
     * @return array
     */
    public static function ConvertToDB($arProperty, $value): array
    {
        if (is_array($value['VALUE'])) {
            $value['VALUE'] = implode(',', $value['VALUE']);
        }

        $value['VALUE'] = trim($value['VALUE'] ?? '');
        return $value;
    }

    /**
     * Преобразование после чтения из БД
     *
     * @param array $arProperty
     * @param array $value
     * @param string $format
     * @return array
     */
    public static function ConvertFromDB($arProperty, $value, $format = ''): array
    {
        return $value;
    }

    /**
     * Валидация значения
     *
     * @param array $arProperty
     * @param array $value
     * @return array
     */
    public static function CheckFields($arProperty, $value): array
    {
        $errors = [];
        $lid = trim($value['VALUE'] ?? '');

        if ($lid !== '') {
            $site = \CSite::GetByID($lid)->Fetch();
            if (!$site) {
                $errors[] = 'Указан несуществующий сайт: ' . htmlspecialcharsEx($lid);
            }
        }

        return $errors;
    }
}
