<?php

echo 'Start ...' . PHP_EOL;
// Засекаем время импорта
$time_start = microtime(true);

// Получаем текущую дату и время, смещенные на 7 часов
$date = date ("Y-m-d H:i:s", strtotime("+7 hours"));

// Подключаем файлы, которые будем анализировать
$import = simplexml_load_file('import.xml');
$offers = simplexml_load_file('offers.xml');

// Функция для экранирования символов XML
/*
function cut($a){
    
    $a = str_replace('"', '&quot;', $a);
    $a = str_replace('&', '&amp;', $a);
    $a = str_replace('>', '&gt;', $a);
    $a = str_replace('<', '&lt;', $a);
    $a = str_replace("'", '&gt;', $a);
    return $a;
}
*/

function cut($a){
    return htmlspecialchars($a, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Куда импортируем
$file = 'price.yml';

// Устанавливаем значение переменной для импорта товаров только в одну категорию
$single_category = 'K0000000603';

// Формируем массив с именами категорий
foreach ($import -> Классификатор -> ХарактеристикиНоменклатуры -> Характеристика as $characteristic) {
    $characteristics[(string)$characteristic -> Ид] = (string)$characteristic -> Наименование;
}

// Функция для проверки, является ли категория дочерней
function check_parent($path, $child, $parent)
{
    foreach ($path as $group) {
        // Если нашли нужную категорию
        if ((string)$group->Ид == $child) {
            // Если родитель и потомок совпадают, возвращаем true
            if ($child == $parent) {
                return true;
            }
            // Если у категории есть родитель, но Ид родителя и потомка не совпадают – запускаем функцию заново, находим родителя родителя
            else if ($group->ИдРодителя) {
                $child = (string)$group->ИдРодителя;
                return check_parent($path, $child, $parent);
            }
            // Если родителя нет, возвращаем false
            else {
                return false;
            }
        }
    }
}


// Переводим коды категорий в индексы

$i = 0;
foreach ($import -> Классификатор -> Группы -> Группа as $category) {

    if(isset($single_category)) {
        if (!check_parent($import -> Классификатор -> Группы -> Группа, (string)$category -> Ид, $single_category)) {
            continue;
        }
    }

    //  Пропускаем товары в уценке
    if ($category -> Ид == 'N0000005545') {
        continue;
    }

    $category_index[(string)$category -> Ид] = $i;

    $categories[$i] = [
        'parent_cat_id' => $category_index[(string)$category -> ИдРодителя],
        'name' => cut((string)$category -> Наименование )
    ];

    $i++;
}

// Перечень категорий
foreach ($categories as $key => $value) {
    if (isset($value['parent_cat_id'])) {
        $parent_id = ' parentId="'. $value['parent_cat_id'] . '"';
    } else {
        $parent_id = '';
    }
    $cat_string .= '    <category id="'. $key .'"'. $parent_id .'>' . $value['name'] . '</category>' . PHP_EOL;
    unset($parent_id);
}

// Формируем массив с характеристиками
$import_products = [];
foreach ($import->Каталог->Товары->Товар as $import_product) {

    $import_products[(string)$import_product->Ид] = [
        'name' => (string)$import_product -> Наименование,
        'group_id' => $category_index[(string)$import_product -> ГруппаИд],
        'images' => $import_product -> Изображения -> Изображение,
        'characteristics' => $import_product -> Характеристики
    ];

}


$string = '<?xml version="1.0" encoding="utf-8"?>
<yml_catalog date="' . $date . '">
    <shop>
        <name>Офис Маркет</name>
        <company>Торговая компания</company>
        <categories>'.
        $cat_string  
        .'</categories>
    <offers>' . PHP_EOL;
file_put_contents($file, $string);

$i = 0;
foreach ($offers->ПакетПредложений->Предложения->Предложение as $offer) {

    // поиск информации о товаре в массиве import_products
    $import_product = $import_products[(string)$offer->Ид] ?? null;
    if (!$import_product) {
        continue; // если информации нет, переходим к следующему товару
    }

    
    if (isset($single_category) && !isset($import_product['group_id']) ) {
        continue;
    }
    
    /*
        if ((string)$offer->Ид != 'M0000028262'){
            continue;
        }
    */
    if (isset($limit) && $i > $limit) {
        break;
    }
    
    // Пропустить товары у которых цена равна 1
    if ($value-> Цены -> Цена -> ЦенаЗаЕдиницу == 1) {
        continue;
    }
      
    // Считаем количество в наличии
    $instock = 0;
    foreach ($offer -> Склады -> Склад as $stock) {
        if($stock -> Ид != 'stock_wait') {
            $instock += $stock -> Количество;
        }
    }
    
    if ($instock == 0) {
        // $instock = 'Под заказ';
        $instock = 'false';
        continue;
    } else {
        // $instock = 'В наличии';
        $instock = 'true';
    }

    // print_r($import_product);

    if($import_product['images'] ){
        $image = null;
        foreach ($import_product['images'] as $import_product_image) {
            
            // Проверяем, есть ли изображение, помеченное как основное
            if($import_product_image['Основное']) {
                $image = $import_product_image;
            }
        }
    }

    // Формируем строку с характеристиками товара

    if ($import_product['characteristics'] -> Характеристика) {
        foreach ($import_product['characteristics'] -> Характеристика as $import_product_characteristic) {

            if($characteristics[(string)$import_product_characteristic -> Ид ]){
                $description .= '        ' . cut($characteristics[(string)$import_product_characteristic -> Ид ]) . ' - ' . cut($import_product_characteristic -> Значение) . PHP_EOL;
            }
            
        }
    }

    // Записываем информацию о товаре в файл
    $string .= '<offer id="' . $offer -> Ид . '" available="' . $instock . '">' . PHP_EOL;
    $string .= '    <categoryId>'. $import_product['group_id'] . '</categoryId>' . PHP_EOL;
    $string .= '    <name>' . cut($import_product['name']) . '</name>' . PHP_EOL;
    if ($description) {
           $string .= '    <description>' . PHP_EOL . $description . '    </description>' . PHP_EOL;
           unset($description);
    }

    // $string .= '    <stock>' . $instock . '</stock>' . PHP_EOL;
    // $string .= '    <condition>Новый</condition>' . PHP_EOL;
    
    $price = $offer-> Цены -> Цена -> ЦенаЗаЕдиницу;
    $string .= '    <price>' . $price . '</price>' . PHP_EOL;
    // $string .= '    <currencyId>RUR</currencyId>' . PHP_EOL;
    if ($image) {
        $string .= '    <picture>ftp://web:321321qz@46.38.4.27/Images/' . $image . '</picture>' . PHP_EOL;
    }
    $string .= '</offer>' . PHP_EOL;
    $i++;
}


$string .= '</offers>
</shop>
</yml_catalog>';

// Засекаем время выполнения задачи
$time_end = microtime(true);
// Вычетаем разницу во времени 
$time = $time_end - $time_start;
echo 'Время выполнения: ' . round($time,1) . ' секунд';

file_put_contents($file, $string, LOCK_EX);