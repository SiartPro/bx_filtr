<?php
/**
 * Created by PhpStorm.
 * @author Karikh Dmitriy <demoriz@gmail.com>
 * @copyright Siart <mail@siart.pro>
 * @date 28.10.2020
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\Component\Tools;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('iblock');
Loader::includeModule('highloadblock');

CBitrixComponent::includeComponentClass("bitrix:catalog.smart.filter");

/**
 * Class CSiartCatalogSmartFilter
 */
class CSiartCatalogSmartFilter extends CBitrixCatalogSmartFilter
{
    /**
     * @var string
     */
    public $SECTION_CODE = '';
    public $SECTION_NAME = '';
    public $SECTION_URL = '';
    /**
     * @var array
     */
    private $arTranslitParams = array();

    /**
     * @param $arParams
     * @return array
     */
    public function onPrepareComponentParams($arParams)
    {
        if (empty($arParams['PROPERTIES_USE_CODE'])) {
            $arParams['PROPERTIES_USE_CODE'] = array();

        } else {
            $arParams['PROPERTIES_USE_CODE'] = array_map(function ($strCode) {
                return strtolower($strCode);
            }, $arParams['PROPERTIES_USE_CODE']);
        }

        $this->arParams['MAX_COUNT_ITEM_SEF'] = (int)$this->arParams['MAX_COUNT_ITEM_SEF'];
        if ($this->arParams['MAX_COUNT_ITEM_SEF'] == 0) $this->arParams['MAX_COUNT_ITEM_SEF'] = 3;

        if ($this->arParams['ADD_CHAIN_ITEMS'] !== 'Y') {
            $this->arParams['ADD_CHAIN_ITEMS'] = 'N';
        }
        if ($this->arParams['CALCULATE_ALL_URL'] !== 'Y') {
            $this->arParams['CALCULATE_ALL_URL'] = 'N';
        }
        if ($this->arParams['ADD_META'] !== 'Y') {
            $this->arParams['ADD_META'] = 'N';
        }

        return parent::onPrepareComponentParams($arParams);
    }

    /**
     * @return mixed|string|null
     */
    public function executeComponent()
    {
        if ((int)$this->arParams['SECTION_ID']) {
            $dbSection = CIBlockSection::GetByID($this->arParams['SECTION_ID']);

            if ($arFields = $dbSection->GetNext()) {
                $this->SECTION_CODE = $arFields['CODE'];
                $this->SECTION_NAME = $arFields['NAME'];
                $this->SECTION_URL = $arFields['SECTION_PAGE_URL'];
            }
        }

        // Правила транслитерации
        $this->arTranslitParams = array(
            'max_len' => '100',
            'change_case' => 'L',
            'replace_space' => '_',
            'replace_other' => '_',
            'delete_repeat_replace' => 'true',
            'use_google' => 'false',
        );

        parent::executeComponent();

        return $this->arResult;
    }

    /**
     * @param $url
     * @return array
     */
    public function convertUrlToCheck($url)
    {
        $arResult = array();
        $arSmartParts = explode("/", $url);
        foreach ($arSmartParts as $arSmartPart) {
            $item = false;
            $arSmartPart = preg_split("/(-from-|-to-|-)/", $arSmartPart, 3, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($arSmartPart as $i => $smartElement) {
                if ($i == 0) {
                    if ($smartElement == 'price') {
                        //$itemId = $this->searchPrice($this->arResult["ITEMS"], $match[1]);
                        //$itemId = array_key_first($this->arResult['PRICES']);
                        $itemId = array_keys($this->arResult['PRICES'])[0];

                    } else {
                        $itemId = $this->searchProperty($this->arResult["ITEMS"], $smartElement);
                    }

                    if (!empty($itemId)) {
                        $item = &$this->arResult["ITEMS"][$itemId];

                    } else {
                        Tools::process404('', true, true, true);
                    }

                }

                if ($i != 0 || count($arSmartPart) == 1) {
                    if ($smartElement === "-from-") {
                        $arResult[$item["VALUES"]["MIN"]["CONTROL_NAME"]] = $arSmartPart[$i + 1];

                    } elseif ($smartElement === "-to-") {
                        $arResult[$item["VALUES"]["MAX"]["CONTROL_NAME"]] = $arSmartPart[$i + 1];

                    } elseif ($smartElement === "-") {
                        $valueId = $this->searchValue($item["VALUES"], $arSmartPart[$i + 1]);
                        if (!empty($valueId)) {
                            $arResult[$item["VALUES"][$valueId]["CONTROL_NAME"]] = $item["VALUES"][$valueId]["HTML_VALUE"];
                        }

                    } else {
                        $valueId = $this->searchValue($item["VALUES"], $smartElement);
                        if (!empty($valueId)) {
                            $arResult[$item["VALUES"][$valueId]["CONTROL_NAME"]] = $item["VALUES"][$valueId]["HTML_VALUE"];
                        }
                    }
                }
            }
            unset($item);
        }
        return $arResult;
    }

    /**
     * @param $items
     * @param $strLookupValue
     * @return int|string|null
     */
    public function searchProperty($items, $strLookupValue)
    {
        $intItemId = 0;

        foreach ($items as $itemId => $arItem) {
            if ($arItem["PRICE"]) break;

            $strCode = toLower($arItem['CODE']);

            // TODO - доработать с календарями и диапазонами
            if (!empty($this->arParams['PROPERTIES_USE_CODE']) && in_array($strLookupValue, $this->arParams['PROPERTIES_USE_CODE'])) {
                // если код указан в опциях как добавляемый
                if ($strLookupValue === $strCode) $intItemId = (int)$itemId;
                if ($strLookupValue == (int)$arItem["ID"]) $intItemId = (int)$itemId;

            } else {
                // было только значение, ищем по значениям
                foreach ($arItem['VALUES'] as $arValue) {
                    // если в опциях транслитерация значения
                    if ($this->arParams['TRANSLATE_LIST_VALUE'] == 'Y') {
                        $arValue['VALUE'] = CUtil::translit(ToLower($arValue['VALUE']), 'ru', $this->arTranslitParams);
                    }

                    if ($arValue['VALUE'] === $strLookupValue) $intItemId = (int)$itemId;
                }

            }

        }

        return $intItemId;
    }

    /**
     * @param $arItemValues
     * @param $lookupValue
     * @return false|int|string
     */
    public function searchValue($arItemValues, $lookupValue)
    {
        $strError = '';
        $strSearchValue = Bitrix\Main\Text\Encoding::convertEncoding($lookupValue, LANG_CHARSET, "utf-8", $strError);
        if (!$strError) {
            $strEncodedValue = rawurlencode($strSearchValue);
            foreach ($arItemValues as $itemId => $arValue) {
                // если в опциях транслитерация значения
                if ($this->arParams['TRANSLATE_LIST_VALUE'] == 'Y') {
                    $arValue['VALUE'] = CUtil::translit(ToLower($arValue['VALUE']), 'ru', $this->arTranslitParams);
                    if ($strEncodedValue === $arValue['VALUE']) return $itemId;

                } else {
                    if ($strEncodedValue === $arValue['URL_ID']) return $itemId;
                }
            }
        }

        return false;
    }

    /**
     * @param $url
     * @param $isApply
     * @param false $strCheckedControlId
     * @return string|string[]
     */
    public function makeSmartUrl($url, $isApply, $strCheckedControlId = false)
    {
        $arSmartParts = array();

        if ($isApply) {
            foreach ($this->arResult["ITEMS"] as $id => $arItem) {
                $arSmartPart = array();
                // Цены
                if ($arItem["PRICE"]) {
                    if (!empty($arItem["VALUES"]["MIN"]["HTML_VALUE"])) {
                        $arSmartPart["from"] = $arItem["VALUES"]["MIN"]["HTML_VALUE"];
                    }

                    if (!empty($arItem["VALUES"]["MAX"]["HTML_VALUE"])) {
                        $arSmartPart["to"] = $arItem["VALUES"]["MAX"]["HTML_VALUE"];
                    }
                }

                if ($arSmartPart) {
                    array_unshift($arSmartPart, "price");

                    $arSmartParts[] = $arSmartPart;
                }
            }

            foreach ($this->arResult["ITEMS"] as $id => $arItem) {
                $arSmartPart = array();
                if ($arItem["PRICE"]) continue;

                if ($arItem["PROPERTY_TYPE"] == "N" || $arItem["DISPLAY_TYPE"] == "U") {
                    // Цифры и календарь - диапазоны
                    if (!empty($arItem["VALUES"]["MIN"]["HTML_VALUE"])) {
                        $arSmartPart["from"] = $arItem["VALUES"]["MIN"]["HTML_VALUE"];
                    }

                    if (!empty($arItem["VALUES"]["MAX"]["HTML_VALUE"])) {
                        $arSmartPart["to"] = $arItem["VALUES"]["MAX"]["HTML_VALUE"];
                    }

                } else {
                    // Прочие свойства
                    foreach ($arItem["VALUES"] as $key => $arValue) {
                        if (($arValue["CHECKED"] || $arValue["CONTROL_ID"] === $strCheckedControlId) && mb_strlen($arValue["URL_ID"])) {
                            // если свойство список или справочник, а в опциях транслитерация значения
                            if (
                                (
                                    $arItem['PROPERTY_TYPE'] == 'S'
                                    || $arItem['PROPERTY_TYPE'] == 'L'
                                )
                                && (
                                    $this->arParams['TRANSLATE_LIST_VALUE'] == 'Y'
                                    || (strrchr($arValue['URL_ID'], '-') !== false)
                                )
                            ) {
                                $arSmartPart[] = CUtil::translit(ToLower($arValue['VALUE']), 'ru', $this->arTranslitParams);

                            } else {
                                $arSmartPart[] = $arValue['URL_ID'];
                            }
                        }
                    }
                }

                if ($arSmartPart) {
                    if ($arItem["CODE"]) {
                        array_unshift($arSmartPart, toLower($arItem["CODE"]));
                    } else {
                        array_unshift($arSmartPart, $arItem["ID"]);
                    }

                    $arSmartParts[] = $arSmartPart;
                }
            }
        }

        if (!empty($arSmartParts)) {
            $strUrl = str_replace("#SMART_FILTER_PATH#", implode("/", $this->encodeSmartParts($arSmartParts)), $url);

        } else {
            $strUrl = str_replace("#SMART_FILTER_PATH#", '', $url);
            $strUrl = str_replace('//', '/', $strUrl);
        }

        return $strUrl;
    }

    /**
     * @param $arSmartParts
     * @return mixed
     */
    public function encodeSmartParts($arSmartParts)
    {
        foreach ($arSmartParts as &$arSmartPart) {
            $urlPart = '';
            $i = 0;
            foreach ($arSmartPart as $key => $smartElement) {
                if ($i == 0) {// первая итерация
                    // TODO - доработать с календарями и диапазонами
                    // если цена
                    if ($smartElement == 'price') {
                        $urlPart .= $smartElement;

                    } elseif (!empty($this->arParams['PROPERTIES_USE_CODE']) && in_array($smartElement, $this->arParams['PROPERTIES_USE_CODE'])) {
                        // если код указан в опциях как добавляемый
                        $urlPart .= $smartElement . '-';
                    }
                } elseif ($key == 'from' || $key == 'to') {
                    $urlPart .= '-' . $key . '-' . $smartElement;

                } elseif ($key == 1) {
                    $urlPart .= $smartElement;
                }

                $i++;
            }
            $arSmartPart = $urlPart;
        }
        unset($arSmartPart);

        return $arSmartParts;
    }

    /**
     * @return mixed|string
     */
    public function checkMode()
    {
        $strSefMode = $this->arParams['SEF_MODE'];

        if ($strSefMode == 'Y') {
            $intAllCountChecked = 0; // общее количество выбранных параметров
            foreach ($this->arResult['ITEMS'] as $arItemFields) {
                // все ли свойства относятся к разрешённым
                if (!empty($this->arParams['PROPERTIES_ALLOW_SEF']) && !in_array($arItemFields['CODE'], $this->arParams['PROPERTIES_ALLOW_SEF'])) {
                    $strSefMode = 'N';
                    break;
                }

                $intDoubleCountChecked = 0; // количество выбранных параметров одного свойства
                foreach ($arItemFields['VALUES'] as $arValueFields) {
                    if ($arValueFields['CHECKED']) {
                        $intAllCountChecked++;
                        $intDoubleCountChecked++;
                    }
                }

                // количество выбранных параметров свойства более одного
                if ($intDoubleCountChecked > 1) {
                    $strSefMode = 'N';
                    break;
                }
            }

            // количество выбранных параметров больше чем установлено
            if ($intAllCountChecked > $this->arParams['MAX_COUNT_ITEM_SEF']) $strSefMode = 'N';
        }

        return $strSefMode;
    }

    /**
     *
     */
    public function addChainItems()
    {
        global $APPLICATION;

        if ($this->arParams['SEF_MODE'] == 'Y' && $this->arParams['ADD_CHAIN_ITEMS'] == 'Y') {
            // добавим раздел в цепочку навигации
            if (!empty($this->SECTION_NAME)) {
                $APPLICATION->AddChainItem($this->SECTION_NAME, $this->SECTION_URL);
            }

            foreach ($this->arResult["ITEMS"] as $id => $arItem) {
                $strUrl = '';
                $strUrlTemplate = str_replace('#SECTION_CODE#', $this->SECTION_CODE, $this->arParams['SEF_RULE']);

                if ($arItem["PRICE"]) {
                    // TODO - доработать с ценами

                } elseif ($arItem["PROPERTY_TYPE"] == "N" || $arItem["DISPLAY_TYPE"] == "U") {
                    // TODO - доработать с диапазонами и календарями

                } else {
                    // Прочие свойства
                    foreach ($arItem["VALUES"] as $key => $arValue) {
                        if (($arValue["CHECKED"]) && mb_strlen($arValue["URL_ID"])) {
                            // если код указан в опциях как добавляемый
                            if (!empty($this->arParams['PROPERTIES_USE_CODE']) && in_array(toLower($arItem['CODE']), $this->arParams['PROPERTIES_USE_CODE'])) {
                                $strUrl .= toLower($arItem['CODE']) . '-';
                            }
                            // если свойство список или справочник, а в опциях транслитерация значения
                            if ($arItem['PROPERTY_TYPE'] == 'S' && $this->arParams['TRANSLATE_LIST_VALUE'] == 'Y') {
                                $strUrl .= CUtil::translit(ToLower($arValue['VALUE']), 'ru', $this->arTranslitParams);

                            } else {
                                $strUrl .= $arValue["URL_ID"];
                            }

                            $strUrl = str_replace('#SMART_FILTER_PATH#', $strUrl, $strUrlTemplate);
                            $strUrl = preg_replace('/\/+/', '/', $strUrl);
                            $APPLICATION->AddChainItem($this->upperFirst($arItem['NAME']) . ' ' . $arValue['VALUE'], $strUrl);
                        }
                    }
                }
            }
        }
    }

    /**
     *
     */
    public function makeSeo()
    {
        global $APPLICATION;

        if ($this->arParams['SEF_MODE'] == 'Y' && $this->arParams['ADD_META'] == 'Y') {

            $strH1 = '';

            // есть ли в highload
            if (!empty($this->arParams['HL_TABLE_NAME'])) {
                try {
                    $arHLBlock = HighloadBlockTable::getList(array('filter' => array('TABLE_NAME' => $this->arParams['HL_TABLE_NAME'])))->fetch();
                    $entity = HighloadBlockTable::compileEntity($arHLBlock);
                    $entityDataClass = $entity->getDataClass();
                    $dbData = $entityDataClass::getList(array(
                        'filter' => array(
                            'UF_ACTIVE' => true,
                            'UF_URL' => trim($this->request->getRequestedPage(), 'index.php')
                        ),
                        'select' => array(
                            'UF_H1',
                            'UF_TITLE',
                            'UF_DESCRIPTION'
                        )
                    ));
                    $arData = $dbData->fetch();
                    $strH1 = $arData['UF_H1'];
                    $strTitle = $arData['UF_TITLE'];
                    $strDescription = $arData['UF_DESCRIPTION'];

                } catch (Exception $e) {
                }
            }

            if (empty($strH1)) {
                if (!empty($this->SECTION_NAME)) {
                    $strH1 = $this->SECTION_NAME;
                }
                foreach ($this->arResult["ITEMS"] as $id => $arItem) {
                    if ($arItem["PRICE"]) {
                        // TODO - доработать с ценами

                    } elseif ($arItem["PROPERTY_TYPE"] == "N" || $arItem["DISPLAY_TYPE"] == "U") {
                        // TODO - доработать с диапазонами и календарями

                    } else {
                        // Прочие свойства
                        foreach ($arItem["VALUES"] as $key => $arValue) {
                            if ($arValue['CHECKED']) {
                                if ($arValue['URL_ID'] == 'true' || $arValue['URL_ID'] == 'false') {
                                    // для свойств которые имеют значение "да" или "нет"
                                    if ($arValue['URL_ID'] == 'false') $strH1 .= ' не';
                                    $strH1 .= ' ' . $arItem['NAME'];

                                } elseif (!empty($this->arParams['PROPERTIES_USE_CODE']) && in_array(toLower($arItem['CODE']), $this->arParams['PROPERTIES_USE_CODE'])) {
                                    // если код указан в опциях как добавляемый
                                    $strH1 .= ' ' . $arItem['NAME'] . ' ' . $arValue['VALUE'];

                                } else {
                                    $strH1 .= ' ' . $arValue['VALUE'];
                                }
                            }
                        }
                    }
                }

                $strH1 = trim(preg_replace('/\s+/', ' ', $strH1));
                $strTitle = (string)$APPLICATION->GetPageProperty('title');
                $strDescription = (string)$APPLICATION->GetPageProperty('description');
                if (!empty($strH1)) {
                    $strTitle = $this->upperFirst($strH1 . ' ' . $strTitle);
                    $strDescription = $this->upperFirst($strH1 . ' ' . $strDescription);
                }
            }

            if (!empty($strH1)) {
                $APPLICATION->SetTitle($this->upperFirst($strH1));
                $APPLICATION->SetPageProperty('title', $strTitle);
                $APPLICATION->SetPageProperty('description', $strDescription);
            }
        }
    }

    public function calculateAllUrl($url)
    {
        if ($this->arParams['CALCULATE_ALL_URL'] == 'Y') {
            foreach ($this->arResult['ITEMS'] as &$arItem) {
                foreach ($arItem['VALUES'] as &$arValue) {
                    $arValue['URL'] = $this->makeSmartUrl($url, true, $arValue['CONTROL_ID']);
                }
                unset($arValue);
            }
            unset($arItem);
        }
    }

    /**
     * @param $str
     * @param string $encoding
     * @return string
     */
    private function upperFirst($str, $encoding = 'UTF8')
    {
        return
            mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) .
            mb_strtolower(mb_substr($str, 1, mb_strlen($str, $encoding), $encoding));
    }
}


