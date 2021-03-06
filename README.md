# bx_filtr

Личный кабинет

Распространяется под лицензией [MIT](https://en.wikipedia.org/wiki/MIT_License). Автор не принимает на себя никаких гарантийных обязательств в отношении данного компонента и не несет ответственности за:

  * любой прямой или косвенный ущерб и упущенную выгоду, даже если это стало результатом использования или невозможности использования компонента;
  * убытки, включая общие, предвидимые, реальные, прямые, косвенные и прочие убытки, включая утрату или искажение информации, убытки, понесенные Пользователем или третьими лицами, невозможность работы компонента и несовместимость с любым другим модулем, компонентом и т.д.
  * за любые повреждения оборудования или программного обеспечения Пользователя, возникшие в результате использовании компонента.

-----------------------------------
**Описание:**

Компонент наследуется от класса стандартного компонента "bitrix:catalog.smart.filter" и добавляет дополнительную логику:
* Гибкие настройки формирования ЧПУ;
* Формирование хлебных крошек согласно параметрам фильтрации;
* SEO настройки (метатеги) параметров фильтрации.

-----------------------------------
**Рекомендации по установке:**

В параметрах комплексного компонента "bitrix:catalog" необходимо указать в "SEF_URL_TEMPLATES"->"smart_filter" шаблон URL без ключевых слов "filter" и "apply", "#SECTION_CODE#/#SMART_FILTER_PATH#/".
```php
"SEF_URL_TEMPLATES" => array(
    "sections" => "",
    "section" => "#SECTION_CODE#/",
    "element" => "#SECTION_CODE#/#ELEMENT_CODE#/",
    "compare" => "compare.php?action=#ACTION_CODE#",
    "smart_filter" => "#SECTION_CODE#/#SMART_FILTER_PATH#/",
)
```
Для корректной работы распознавания такого URL в шаблоне комплексного компонента, конкретно в  файле "element.php" в начале проверить наличие в каталоге элемента с символьным кодом "$arResult\['VARIABLES'\]\['ELEMENT_CODE'\]". И если такой элемент не найден, то присвоить "$arResult\['VARIABLES'\]\['SMART_FILTER_PATH'\]" значение "$arResult\['VARIABLES'\]\['ELEMENT_CODE'\]" и подключить "section.php". Пример:
```php
// если нет элемента, то воспринимаем это как фильтр
$arSorting = array(
    'SORT' => 'ASC'
);
$arFilter = array(
    'IBLOCK_ID' => $arParams['IBLOCK_ID'],
    'CODE' => $arResult['VARIABLES']['ELEMENT_CODE'],
    'ACTIVE' => 'Y'
);
$intElement = CIBlockElement::GetList($arSorting, $arFilter, array());
if ((int)$intElement == 0) {
    $arResult['VARIABLES']['SMART_FILTER_PATH'] = $arResult['VARIABLES']['ELEMENT_CODE'];
    unset($arResult['VARIABLES']['ELEMENT_CODE']);
    include(__DIR__ . '/section.php');

} else {
    // тут изначальный код "element.php", который реализует детальную страницу компонента.
}
```
При необходимости иметь возможность устанавливать в ручную для определённых URL определённые метатеги, необходимо создать highload инфоблок со следующими полями:
* UF_ACTIVE
* UF_H1
* UF_TITLE
* UF_DESCRIPTION

-----------------------------------
**Перечень дополнительных параметров:**
```php
MAX_COUNT_ITEM_SEF
```
_Максимальное количество выбранных опций, для которых формируется ЧПУ. По умолчанию 3._
```php
PROPERTIES_ALLOW_SEF
```
_Список свойств, для которых формируется ЧПУ. Если список не указан, то ЧПУ формирует для всех свойств фильтра._
```php
PROPERTIES_USE_CODE
```
_Список свойств, для которых необходимо использовать символьный код самого свойства в ЧПУ. Необходим для тех случаев, когда значение свойства не отражает смысла._
```php
ADD_CHAIN_ITEMS
```
_Список свойств, которые в случае их выбора, необходимо добавлять в цепочку навигации._
```php
ADD_CHAIN_ITEMS
```
_Если отмечено как "Y", то будут добавляться выбранные параметры в цепочку навигации._
```php
ADD_META
```
_Если отмечено как "Y", то будут формироваться метатеги._
```php
CALCULATE_ALL_URL
```
_Если отмечено как "Y", то в результирующем массиве, для каждого варианта свойств, будет добавлено поле URL с адресом фильтрации._
```php
HL_TABLE_NAME
```
_Наименование таблицы highload инфоблока, в котором будут храниться установленные варианты метатегов для определённых URL. Если не указано то данный функционал будет игнорироваться. Используется только при "ADD_META" => "Y""._
