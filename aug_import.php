<?php

///////////////////////
// Обновление цен и остатков. normal
///////////////////////

/*
Для правильной работы необходимо активировать тему woodmart.
Активировать woocommerce

// %progdir%\modules\wget\bin\wget.exe -q --no-cache http://test10.offt/import/import.php

- По выбору не присваивать категории

- Страна и бренд
- Сопутствующие товары

- Распаковать архивы
- Наладить автоматическую отправку в 2Гис и Фарпост
- Наладить выгрузку фильтров
- Автоматическое создание категорий "Новинка", "Выгодная покупка" →
- Функция для объединения некоторых групп в одну категорию → 

- Отправка новых изображений на FTP-сервер
*/


echo 'Start' . PHP_EOL;
$date = date ("Y-m-d H:i:s", strtotime("+7 hours"));
error_reporting(0);



// Отправляем текст в телеграм
function telegram_send ($text) {
    $bot = 'bot1451450500:AAFhhZ_xE13Le8mXFdZm4E7Pzznt96RCptw';
    $chat_id = '137669248';
    fopen('https://api.telegram.org/'. $bot .'/sendMessage?chat_id='. $chat_id .'&parse_mode=html&text=' . $text ,"r");
}


function break_process () {
    if (file_exists('break')) {
        echo 'Найден файл break... Остановка скрипта';
        telegram_send('Найден файл break. Останавливаем скрипт');
        set_transient( 'interrupt', false, 3 * MINUTE_IN_SECONDS );
        exit;
    }
}

break_process ();

// Засекаем время импорта
$time_start = microtime(true);

// Подключаем WordPress
include( dirname(__FILE__) . "/../wp-load.php" );
$zipfile = 'webdata.zip';


// Извлекаем архив
function unzip_webdata ($file){
    
    // $file = dirname(__FILE__) . '/webdata.zip';
    if(file_exists($file )) {

        $zip = new \ZipArchive;
        $res = $zip->open($file);
        $path = pathinfo(realpath($file), PATHINFO_DIRNAME);
        if ($res === TRUE) {

            $zip->extractTo($path);
            $zip->close();
            // unlink($file);
            return true;
        } else {
            return false;
        }
    } else {
        echo 'Не найден файл ' . $file ;
        exit;
    }
}

// Если есть файл process.log, то не распаковываем архив
if(!file_exists('process.log')) {
    unzip_webdata ($zipfile);
}



// Проверяем, запущен ли скрипт в данный момент
$interrupt = get_transient('interrupt');
if ($interrupt == 1) {
    // Скрипт запущен выходим
    echo 'interrupt - Скрипт уже запущен';
    exit;
} else {
    // 
    set_transient( 'interrupt', 1, 3 * MINUTE_IN_SECONDS );
    telegram_send('Старт выгрузки');
}

// Получаем список выгруженных сейчас изображений
function get_upload_images (){
    $dir = dirname(__FILE__) . '/webdata/import_files';
    if (is_dir($dir)) {
        return scandir($dir);
    } else {
        return false;
    }
}

// Получаем список файлов, которые есть на FTP-сервере. Если изображений нет на FTP, но они есть в zip-папке – выгружаем на FTP
function get_ftp_images () {
    $host= '46.38.4.27';
    $user = 'web';
    $password = '321321qz';

    $ftp_connect = ftp_connect($host);
    $login = ftp_login($ftp_connect, $user, $password);

    if ((!$ftp_connect) || (!$login)) {
            echo 'Ошибка FTP-соединения!';
        } else {
            echo 'Успешное FTP-соединение';

            $uploaded_images = get_upload_images();

            $ftp_images_path = ftp_nlist($ftp_connect,'/Images');

            foreach ($ftp_images_path as $image) {
                $ftp_images[] = basename($image);
            }

            // Если изображения нет на FTP-сервере
            foreach ($uploaded_images as $image) {
                if (!in_array($image, $ftp_images)) {
                    echo $image . PHP_EOL;

                    $remote_file = 'Images/' . $image;
                    $file = dirname(__FILE__) . '/webdata/import_files/' . $image;

                    // загрузка файла 
                    if (ftp_put($ftp_connect, $remote_file, $file, FTP_BINARY)) {
                        echo "$file успешно загружен на сервер\n";
                    } else {
                        echo "Не удалось загрузить $file на сервер\n";
                    }
                }
            }
        }

    ftp_close($ftp_connect);
}

// Отправка прайса

function put_ftp_price ($file, $path) {
    $host= '194.58.96.5';
    $user = 'off_mar_ru';
    $password = 'B2DKgdd8BKxhPqtj';

    $ftp_connect = ftp_connect($host);
    

    $login = ftp_login($ftp_connect, $user, $password);

    if ((!$ftp_connect) || (!$login)) {
            echo 'Ошибка FTP-соединения!';
        } else {
            echo 'Успешное FTP-соединение';
            ftp_pasv($ftp_connect, true);
            $remote_file = $path . $file;

            // загрузка файла 
            if (ftp_put($ftp_connect, $remote_file, $file, FTP_BINARY)) {
                echo "$file успешно загружен на сервер\n";
                telegram_send('Файл отправлен');
            } else {
                echo "Не удалось загрузить $file на сервер\n";
                telegram_send('Ошибка отправки');
            }

        }

    ftp_close($ftp_connect);
}


// Удаление директории

function remove_directory($dir) {
    echo 'Удаляем директорию ' . $dir . PHP_EOL;
	$includes = new FilesystemIterator($dir);

	foreach ($includes as $include) {

		if(is_dir($include) && !is_link($include)) {
			remove_directory($include);
		} else {
			unlink($include);
		}

	}

	rmdir($dir);
}


// Классы для загрузки изображения
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Класс  для обрезки изображения
require_once __DIR__ . '/thumbs.php';


// Класс для работы с базой данных
global $wpdb;


// Подключаем файлы, которые будем анализировать
$offers = simplexml_load_file(dirname(__FILE__) . '/webdata/offers.xml');
if (file_exists(dirname(__FILE__) . '/webdata/import.xml')) {
    $import = simplexml_load_file(dirname(__FILE__) . '/webdata/import.xml');
    get_ftp_images();

        
////////////////////
// Настройки
////////////////////

// Если нужно выгрузить лишь один товар
//$single = 'M0000028505';
// $single_category = 'K0000000603';
// Если нужен импорт перечня товаров
// $new_prod = "K0000061658, K0000014768, K0000014769, M0000028757, M0000019524, M0000029678, N0000005048, N0000005049, M0000030024, M0000030132, M0000029679, M0000030062, M0000030131, M0000030130, M0000030124, M0000030128, M0000030025, M0000030129";
// $new_prod = explode(', ', $new_prod);


// Переводить ли изображения в формат webp
$webp = true;

// Если нужно игнорировать контрольные суммы
// $forced = true;
// $offers_forced = true;


// Создавать ли категории (нужно только при первом запуске скрипта)
// $create_category = true;

// $limit = 10;




// Категории, которые будут помещены в родительскую категорию "Другие товары"
$other_category = [
    'N0000006637', //  0. Видеонаблюдение
    'K0000001429', //  7. Внешние накопители
    'N0000000124', //  8. ТВ, аудио, аксессуары
    'K0000002019', //  9. Сетевое оборудование
    'N0000000280', // 10. Полезные мелочи
    'K0000000742', // 11. Банковское оборудование
    'K0000000157', // 12. Торговое оборудование
    'N0000005545', // 14. Уцененные товары
    'K0000002156', // 15. Услуги
    'N0000002004', // 16. Бытовая техника
    'K0000053413', // 17. Товары для детей
    'K0000058114', // 18. Строительные товары
    'K0000001520', // 19. Мебель
    'M0000001510', // 21. Выгодная покупка
    'M0000006783', // 22. Спецодежда и СИЗ
    'M0000006663'  // 23. Радиодетали
];

// Категории, которые нужно пропустить
$skip = [
    'K0000001980', // -АРХИВ-
    'K0000000593', // -Бухгалтерия-
    'M0000022433', // Выведен из ассортимента
    'M0000012488', // Сервисный центр
    'N0000002831' // ТОЛЬКО под заказ (не выгружается на сайт)
];

// Категории, которых нет в XML, но их нужно создать
$required_categories = [
    'hit' => 'Хит продаж',
    'new' => 'Новинка',
    'computers' => 'Компьютеры',
    'other_products' => 'Другие товары'
];



// Функция присваивания атрибутов товарам
function set_attributes ($post_id, $feature_id, $feature_label, $feature_value) {
                  
    global $wpdb;
    global $characteristics_bool;
    $add_postmeta = null;
    // Некоторые характеристики присутствуют в товаре, но отутствуют их имена в файле import.xml пропускаем такие характеристики
    if ($feature_value && $feature_label) { 

        
        // Значения с 1/0 меняем на есть/нет
        if(in_array($feature_id, $characteristics_bool)) {
            if ($feature_value == 1) {
                $feature_value = 'есть';
            } else {
                $feature_value = 'нет';
            }
        }
        

        // Проверяем существование атрибута в базе
        $sql = "SELECT `attribute_id` FROM `wp_woocommerce_attribute_taxonomies` WHERE `attribute_name` LIKE '". $feature_id ."'";
        $get_prop = $wpdb->get_var( $sql);

        // Если атрибут не существует - добавляем
        if (!$get_prop) {

            // Добавляем атрибуты в список всех атрибутов
            $sql = "INSERT INTO
            `wp_woocommerce_attribute_taxonomies`
            (
                `attribute_id`, 
                `attribute_name`, 
                `attribute_label`, 
                `attribute_type`, 
                `attribute_orderby`, 
                `attribute_public`
            )
            VALUES (
                NULL,
                '" . $feature_id . "', 
                '" . $feature_label . "', 
                'select', 
                'menu_order', 
                '1'
            )";

            $wpdb->query( $sql);
            
            // Получаем id добавленного атрибута
            $last_id = $wpdb->insert_id;

            // Формируем массив для wp_options - кэш таблицы 'wp_woocommerce_attribute_taxonomies'
            $array_add[] = (object)[
                'attribute_id' => (string)$last_id,
                'attribute_name' => (string)$feature_id,
                'attribute_label' => (string)$feature_label,
                'attribute_type' => 'select',
                'attribute_orderby' => 'menu_order',
                'attribute_public' => 0
            ];

        } else {

            // Если атрибут есть в базе - получаем его id. Проверяем, не изменился ли label

        }

        

        $sql = "SELECT 
        wp_term_taxonomy.term_taxonomy_id
        FROM 
            wp_term_taxonomy 
        INNER JOIN 
            wp_terms 
        ON 
            wp_terms.term_id = wp_term_taxonomy.term_id  
        AND 
            wp_terms.slug LIKE '". translit($feature_value) ."'
        AND 
            wp_term_taxonomy.taxonomy LIKE 'pa_". $feature_id ."'";

        $get_prop = $wpdb->get_var( $sql);

        if (!$get_prop) {
            
            echo ' -- Значения нет в базе ';
            // Если значения нет в базе - добавляем
            $sql = "INSERT INTO `wp_terms` (`term_id`, `name`, `slug`, `term_group`) VALUES (NULL, '" .  $feature_value . "', '". translit($feature_value) ."', '0')";
            $wpdb->query( $sql);
            $wp_term_id = $wpdb -> insert_id;


            $sql = "INSERT INTO `wp_term_taxonomy` (`term_taxonomy_id`, `term_id`, `taxonomy`, `description`, `parent`, `count`) VALUES (NULL, '". $wp_term_id ."', 'pa_". $feature_id ."', '', '0', '2')";
            $wpdb->query( $sql);
            $wp_term_taxonomy_id = $wpdb->insert_id;
            //echo 'Значения нет в базе. Добавляем ' . $wp_term_id . '<br/>';

        } else {
            // echo ' -- Значение есть в базе. term_id: ' .  $get_prop;
            // Если такое значение атрибута уже существет, получаем его ID
            $wp_term_taxonomy_id = $get_prop;

        }



        $sql = "
        select
            wp_term_relationships.object_id,
            wp_term_relationships.term_taxonomy_id,
            wp_term_taxonomy.taxonomy,
            wp_terms.name 
        from 
            wp_term_relationships 
        left join wp_term_taxonomy on wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id 
        left join wp_terms on wp_terms.term_id = wp_term_taxonomy.term_id
        where
            wp_term_taxonomy.taxonomy like 'pa_". $feature_id ."'
        and
            wp_term_relationships.object_id =" . $post_id;
        
        $relationships_exists = $wpdb -> get_results($sql, ARRAY_A);

        // Если запись по данному товару существует
        if ($relationships_exists) {
            $was_changed = 0;
            // Проверяем значения характеристик для текущего поста:   867, 	990, pa_k-0002553, бежевый
            foreach ($relationships_exists as $rel_value) {
                // Если значения в базе и в XML не совпадают - удаляем старое значение, удаляем возможные дубликаты
                if ((string)$rel_value['name'] != $feature_value) {
                    $was_changed = 1; 
                    $wpdb->delete( 'wp_term_relationships', [ 'term_taxonomy_id' => $rel_value['term_taxonomy_id'] ] );
                } 
            }
        } else {
            $was_changed = 1;
        }

        // Если значение характеристики менялось
        if ($was_changed == 1) {
            // Привязываем пост к таксономии
            $sql = "INSERT INTO `wp_term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES ('". $post_id ."', '". $wp_term_taxonomy_id ."', '0');";
            $wpdb->query( $sql);
            //echo ' + ' . $feature_value . ' - term_taxonomy_id: ' . $wp_term_taxonomy_id . PHP_EOL;
        } else {
            //echo ' - ' . $feature_value . ' - term_taxonomy_id: ' . $wp_term_taxonomy_id . PHP_EOL;
        }
    }


    if (isset($array_add)) {

        // Вносим все атрибуты в wp_options
        $sql = "SELECT `option_value` FROM `wp_options` WHERE `option_name` LIKE '_transient_wc_attribute_taxonomies'";
        $get_options = $wpdb->get_var($sql);
        $get_options = unserialize($get_options);

        if (is_array($array_add) && is_array($get_options) || $get_options == null && is_array($array_add)) {
                $new_options = array_merge($get_options, $array_add);
                $new_options = serialize($new_options);
                $wpdb->update( 'wp_options',
                [ 'option_value' => $new_options ],
                [ 'option_name' => '_transient_wc_attribute_taxonomies' ]
            );
        }
    }


    // Получив ID можем  присваивать товару свойства
    $meta_values = get_post_meta( $post_id, '_product_attributes' );

    
    // Если опция существует
    $meta_values[0]['pa_' . $feature_id] = [
        'name' => 'pa_' . $feature_id,
        'value' => $feature_value,
        'position' => 1,
        'is_visible' => 1,
        'is_variation' => 0,
        'is_taxonomy' => 1
    ];


    // Свойства товара
    $update_attribute = update_post_meta( $post_id, '_product_attributes', $meta_values[0]); 

    if ($update_attribute) {
        echo 'Атрибут обновлен ' . PHP_EOL;
    } else {
        echo 'Не удалось обновить атрибут ' . PHP_EOL;
    }


}



if (isset($create_category)) {

    
    // Проверяем существование таблицы 1c_id
    $sql = "show tables like'1c_id'";
    $table_1c_id_exists = $wpdb -> get_results($sql);
    if (!$table_1c_id_exists) {

        echo 'Таблица 1c_id не существует' . PHP_EOL;

        $sql = "
        create table `1c_id` (
            `id` int(9) NOT NULL,
            `post_id` int(9) NOT NULL,
            `sku` varchar(15) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `sum_import` bigint(20) NOT NULL,
            `sum_offer` bigint(20) NOT NULL,
            `date` datetime DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ";

        if ($wpdb -> query($sql)) {
            echo 'Таблица 1c_id успешно создана' . PHP_EOL;
            $sql = "ALTER TABLE `1c_id` ADD UNIQUE KEY `id` (`id`)";

            if ($wpdb -> query($sql)) {
                echo '- Уникальный ключ назначен' . PHP_EOL;
            } else {
                echo '- Ключ не назначен';
                exit;
            }
            
            $sql = "
                ALTER TABLE `1c_id`
                MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
            ";
            if ($wpdb -> query($sql)) {
                echo '- Назначен автоинкремент для id' . PHP_EOL;
            } else {
                echo '- Автоинкремент не назначен' . PHP_EOL;
                exit;
            }

        } else {
            echo 'Не удалось создать таблицу 1c_id' . PHP_EOL;
        }
    }

    // Проверяем существование таблицы offt_categories_id
    $sql = "SHOW TABLES LIKE 'offt_categories_id'";
    $table_offt_categories_id_exists = $wpdb -> get_results($sql);
    if (!$table_offt_categories_id_exists) {
        echo 'Таблица offt_categories_id не существует' . PHP_EOL;

        $sql = "
            CREATE TABLE `offt_categories_id` (
            `id` int(9) NOT NULL,
            `term_id` int(9) NOT NULL,
            `1c_id` varchar(15) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ";

        if ($wpdb -> query($sql)) {
            echo '- Таблица offt_categories_id успешно создана' . PHP_EOL;

            $sql = "
                ALTER TABLE `offt_categories_id`
                ADD PRIMARY KEY (`id`),
                ADD UNIQUE KEY `term_id` (`term_id`),
                ADD UNIQUE KEY `1c_id` (`1c_id`);
            ";

            if ($wpdb -> query($sql)) {
                echo '- Уникальный ключ назначен' . PHP_EOL;
            } else {
                echo '- Ключ не назначен';
                exit;
            }
            
            $sql = "ALTER TABLE `offt_categories_id` MODIFY `id` int(9) NOT NULL AUTO_INCREMENT";

            if ($wpdb -> query($sql)) {
                echo '- Назначен автоинкремент для id' . PHP_EOL;
            } else {
                echo '- Автоинкремент не назначен' . PHP_EOL;
                exit;
            }
        } else {
            echo '- Не удалось создать таблицу offt_categories_id' . PHP_EOL;
        }
    }

    ////////////////////
    // Чистим промежуточную таблицу от несуществующих категорий
    ////////////////////

    $sql = "SELECT * FROM `offt_categories_id`";
    $compare1 = $wpdb -> get_col($sql, 1);

    $sql = "SELECT * FROM `wp_terms`";
    $compare2 = $wpdb -> get_col($sql, 0);

    // Если в промежуточной таблице есть лишние категории, удаляем их
    foreach ($compare1 as $value) {
        if (!in_array($value, $compare2)) {
            echo $value . ' нет в таблице. Удаляем<br/>';
            $sql = "DELETE FROM `offt_categories_id` WHERE `term_id` = " . $value;
            $wpdb -> query($sql);
        } 
    }


    ////////////////////
    // Создаем категории 
    ////////////////////

    // Сначала создаем категории, которых нет в XML
    foreach($required_categories as $required_category_key => $required_category_val) {

        // Проверяем, есть ли такая категория в базе
        $sql = "SELECT `term_id` FROM `offt_categories_id` where `1c_id` like '" . $required_category_key . "'";
        $term_id = $wpdb -> get_var($sql);

        if ($term_id == null) {
            $insert_cat = wp_insert_term (
                (string)$required_category_val,
                    'product_cat',
                array(
                    'slug' => (string)$required_category_key,
                // 'parent' => 0,
                )
            );

        

            // Если нет ошибок, добавляем данные в сводную таблицу
            if( !is_wp_error( $insert_cat )) {
                $term_id = $insert_cat['term_id'];
                $sql = "INSERT INTO `offt_categories_id` (`id`, `term_id`, `1c_id`) VALUES (NULL, '" . $term_id . "', '" . $required_category_key . "')";
                $wpdb -> query ($sql);
            }
        }

        $required_category_total[$required_category_key] = $term_id;
    }



    $i = 0;
    foreach ($import -> Классификатор -> Группы -> Группа as $category) {
        $i++;


        $sql = "SELECT * FROM `offt_categories_id` where `1c_id` like '" . $category -> Ид . "'";
        $check_code = $wpdb -> get_var($sql);


        // Проверяем, есть ли такой код в базе

        if ($check_code == null) {
            echo $category -> Ид . ' - ' . $category -> Наименование . '<br/>' . PHP_EOL;

            $insert_cat = wp_insert_term (
                (string)$category -> Наименование,
                    'product_cat',
                array(
                    'slug' => translit($category -> Наименование),
                )
            );
            
            // Выводим ошибки, если они есть
            if( is_wp_error( $insert_cat )) {

                // echo $insert_cat -> get_error_message();

                // Как правило ошибки связаны с тем, что такой слаг уже существует. Заново вставляем категорию. Добавляем цифру в конце
                $ii = 0;
            
                while(is_wp_error( $insert_cat ) && $ii < 50) {
                    $ii++;
                    $insert_cat = wp_insert_term (
                        (string)$category -> Наименование,
                        'product_cat', 
                        array(
                        'slug' => translit($category -> Наименование) . '-' . $ii,
                        )
                    );
                }
            } 

            $term_id = $insert_cat['term_id'];

            // woocommerce не позволяет использовать 2 одинаковых названия категории, а в нашей базе встречается по 8 одинаковых названий.
            // Использовать код 1с в ссылках нежелательно, поэтому используем промежуточную таблицу с кодами

            $sql = "INSERT INTO `offt_categories_id` (`id`, `term_id`, `1c_id`) VALUES (NULL, '" . $term_id . "', '" . $category -> Ид . "')";
            $wpdb -> query ($sql);
        } else {
            // echo 'Категория уже существует. Пропускаем' . PHP_EOL;
        }
    }


    // Присваиваем категории
    echo PHP_EOL . 'Присваиваем категории...' . PHP_EOL;
    $i = 0;
    foreach ($import -> Классификатор -> Группы -> Группа as $category) {
        // echo $category -> Ид . PHP_EOL;
        $i++;

        $sql = "SELECT `term_id` FROM `offt_categories_id` where `1c_id` like '". $category -> Ид ."'";
        $current = intval($wpdb -> get_var($sql));

        // Присваиваем родительскую категорию
        if ($category -> ИдРодителя) {

            // Получаем term_id
            
            $sql = "SELECT `term_id` FROM `offt_categories_id` where `1c_id` like '". $category -> ИдРодителя ."'";
            $parent = intval($wpdb -> get_var($sql));
            // echo $parent . PHP_EOL;

            $check = wp_update_term ($current, 'product_cat', array ('parent' => $parent));
            
            
        } 
        
        // Присваиваем категориям родительскую категорию "Другие товары"
        if (in_array($category -> Ид, $other_category)) {
            $parent = $required_category_total['other_products'];
            wp_update_term ($current, 'product_cat', array ('parent' => $parent));
        }

        $childs_cat = ['K0000001537', 'K0000001446', 'K0000001636'];
        if(in_array($category -> Ид, $childs_cat)) {
        // Присваиваем родительскую категорию
            
            foreach ($childs_cat as $item ) {
                $parent = $required_category_total['computers'];
                $sql = "SELECT `term_id` FROM `offt_categories_id` where `1c_id` like '". $category -> Ид ."'";
                $current = intval($wpdb -> get_var($sql));
                wp_update_term ($current, 'product_cat', array ('parent' => $parent));
            }
        }
    }
}



// Транслит и перевод в нижний регистр для атрибутов
function translit($value) {
    $value = mb_strtolower($value);

	$converter = array (
		'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
		'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
		'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
		'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
		'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
		'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
		'э' => 'e',    'ю' => 'yu',   'я' => 'ya',   ' ' => '-',    '(' => '',
        ')' => '',     '*' => 'x',    '.' => '',     ',' => ''
	);

	$value = strtr($value, $converter);
	return $value;
}
}








// Перевод байтов в кило/мегабайты для подсчета памяти
function convert($size)
{
    $unit = array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

// Является ли категория дочерней. Путь / категория, которую проверяем / категория в которую она входит или не входит
function check_parent($path, $child, $parent)
{
    // echo 'Проверка является ли категория ' . $child . ' потомком категории ' . $parent . PHP_EOL;
    foreach ($path as $group) {
        if ((string)$group->Ид == $child) {

            // Если родитель и потомок совпадают, возвращаем true
            if ($child == $parent) {
                return true;
            }
            
            // Если у категории есть родитель, но Ид родителя и потомка не совпадают – запускаем функцию заново. Находим родителя родителя
            else if ($group->ИдРодителя) {
                $child = (string)$group->ИдРодителя;
                return check_parent($path, $child, $parent);
            }
            
            // Если родителя нет, возращаем false
            else {
                return false;
            }
        }
    }
}



// Функция для объединения нескольких категорий в одну. Например Epson и HP обединяем в одну категорию "Принтеры"
function unite($path, $was, $became)
{
    if (in_array($path, $was)) {
        return $became;
    } else {
        return $path;
    }
}

function unite_group($group_id){
    // Объединяем тетради в одну категорию
    $group_id = unite($group_id, ['M0000016018', 'M0000016017', 'M0000021250', 'M0000016019'], 'K0000052435');
    // Объединяем альбомы в одну категорию
    $group_id = unite($group_id, ['M0000016015', 'M0000016014'], 'M0000016012');
    // Фотобумага
    $group_id = unite($group_id, ['N0000001271', 'K0000000076', 'K0000000103', 'N0000001272'], 'K0000000033');
    // Цветная бумага
    $group_id = unite($group_id, ['M0000020741', 'K0000000011', 'K0000000006'], 'K0000000005');
    // Бумага для факса
    $group_id = unite($group_id, ['N0000001275', 'N0000001595', 'K0000054198'], 'N0000001264');
    // Для цифровой печати
    $group_id = unite($group_id, ['N0000001266', 'K0000060324', 'K0000000041', 'N0000001274', 'K0000000048'], 'K0000000039');
    // Для цифровой печати
    $group_id = unite($group_id, ['M0000022653', 'N0000003987', 'N0000001886', 'N0000003114', 'K0000063656', 'K0000057038'], 'K0000000019');

    return $group_id;
}

// Получаем имя фалйа без расширения
function get_filename ($filename) {
    $filename = pathinfo($filename);
    $filename = $filename['filename'];
    return $filename;
}


////////////////////////////////////
// Обновляем товары import.xml
////////////////////////////////////



if (isset($import)) {

    
    // Получаем все характеристики из xml в виде массива ['К-0002578'] => 'Формат'
    foreach ($import -> Классификатор -> ХарактеристикиНоменклатуры -> Характеристика as $characteristics_name) {
        $characteristics_name_array[translit((string)$characteristics_name -> Ид)] = (string)$characteristics_name -> Наименование;

        // Заносим в отдельный массив характеристики Есть/нет
        if ($characteristics_name -> ТипЗначенияИд == 'КЮ000000004') {
            $characteristics_bool[] = translit((string)$characteristics_name -> Ид);
        }
    }

    // Получаем названия всех брендов
    foreach ($import -> Классификатор -> БрендыНоменклатуры -> БрендНоменклатуры as $brand) {
        $brands[(string)$brand -> Ид] = (string)$brand -> Наименование;
    }

    // Получаем список всех товаров, которые есть в offers.xml Работать будем только с этими товарами
    foreach($offers -> ПакетПредложений -> Предложения -> Предложение as $offer) {
        $all_products[] = (string)$offer -> Ид; 
    }

    echo PHP_EOL . 'Обновление свойств товаров' . PHP_EOL;
    $i = 1;
    $updated_products = 1;

    foreach ($import -> Каталог -> Товары -> Товар as $import_product) {


        $i++;
    
        $cat_id = [];   
        // Если нужно выгрузить только один товар, остальные пропускаем
        if (isset($single) && $import_product -> Ид != $single) {
            continue;
        }
        
        break_process ();

        // Если выгружаем перечень товаров
        if (isset($new_prod) && !in_array((string)$import_product -> Ид, $new_prod)){
            continue;
        }
        
        // Выгрузка только товаров, которые есть в offers.xml
        if (!in_array((string)$import_product -> Ид, $all_products)) {
            continue;
        }
    
        if (isset($limit) && $updated_products > $limit ) {
            break;
        }
    
        // Если нужно выгрузить одну категорию и товар не входит в нее - пропускаем
        if(isset($single_category)) {
            if (!check_parent($import -> Классификатор -> Группы -> Группа, $import_product -> ГруппаИд, $single_category)) {
                continue;
            }
        }
        
        // Обновляем время блокировки скрипта - 3 минуты
        set_transient( 'interrupt', 1, 3 * MINUTE_IN_SECONDS );

        file_put_contents('process.log', $i . ' - ' . $import_product -> Ид . PHP_EOL);

        // Проверяем сумму в таблице
        $sql = "SELECT 
                    `sum_import`, `post_id` 
                FROM
                    `1c_id`
                WHERE
                    `sku`
                LIKE 
                    '". $import_product -> Ид ."'
                ";
    
        $sum_import = $wpdb -> get_row ($sql, ARRAY_A);  
     
        echo $i . ' - ' . $import_product -> Ид . ' - ' . mb_strimwidth((string)$import_product -> Наименование, 0, 40, '...');
        
    
            // Если суммы равны
            if (!isset($forced) && isset($sum_import['sum_import']) && $sum_import['sum_import'] == $import_product -> КонтрольнаяСумма) {
    
                $equals = 'Суммы равны';
                echo $equals . PHP_EOL;
                continue;
            
            // Если суммы не равны
            } else { 
                
                $updated_products++;
                $equals = 'Суммы не равны';
                echo $equals . PHP_EOL;
    
                echo $import_product -> КонтрольнаяСумма . ' - ' . $sum_import['sum_import'] . PHP_EOL;
    
                $group_id = $import_product->ГруппаИд;
                $group_id = unite_group($group_id);
    
                $post_imgs = [];
    
                // Получаем ID категории
                $sql = "SELECT
                            `term_id` 
                        FROM 
                            `offt_categories_id` 
                        WHERE
                            `1c_id` 
                        LIKE 
                            '". $group_id ."'
                        ";
    
                $cat_id[] = $wpdb -> get_var($sql);
    
    
    
    
                // Проверяем существует ли вообще такой товар. Получаем ID
                if (isset($sum_import['post_id'])) {
                    $post_id = $sum_import['post_id'];
                } else {
                // Если в смежной таблице его нет. Проверяем. Может товар существует, но просто в таблицу не добавлен
                    $sql = "SELECT 
                                `post_id`
                            FROM 
                                `wp_postmeta`
                            WHERE 
                                `meta_key`
                            LIKE
                                '_sku' 
                            AND 
                                `meta_value`
                            LIKE 
                                '". $import_product -> Ид ."'
                            ";
    
                    $post_id = $wpdb -> get_var($sql);
                }
    
                if (isset($post_id)) {
    
                    // Обновляем товар
                    echo 'Обновляем товар ID: ' . $post_id . PHP_EOL;
                    $product = new WC_Product($post_id);
    
                    // Получаем список изображений, привязанных к посту, чтобы не загружать изображения повторно
                    $sql = "select 
                        `ID`,`guid` 
                    from 
                        `wp_posts` 
                    where 
                        `post_mime_type` like 'image/png' and `post_parent` = ". $post_id . "  
                    or 
                        `post_mime_type` like 'image/jpeg' and `post_parent` = ". $post_id . "
                    or 
                        `post_mime_type` like 'image/webp' and `post_parent` = ". $post_id;
    
                    $has_img = $wpdb -> get_results($sql, ARRAY_A);
                    
                    
                    foreach ($has_img as $filename) {
                        $post_imgs[] = get_filename ($filename['guid']);
                    }
    
                } else {
    
                    // Добавляем товар
                    echo 'Создаем товар ' . $import_product -> Ид . PHP_EOL;
                    $product = new WC_Product();
    
                }
    
                $product->set_name($import_product -> Наименование);
                $product->set_slug( translit($import_product -> Наименование));
                $product->set_description((string)$import_product -> ОписаниеНоменклатуры);
                $product->set_sku($import_product -> Ид);
                $product->set_category_ids($cat_id);
                $post_id = $product -> save();
    
    
                    ///////////////////////////////////
                    // Загрузка изображений для товара
                    ///////////////////////////////////
    
                    $images = null;
    
                    if ($import_product -> Изображения -> Изображение) {
                        foreach ($import_product -> Изображения -> Изображение as $image) {
                            
    
    
                        echo 'Проверка изображения ' . $image . PHP_EOL;   
                        
                        // Получаем название без расширения (типа 0cf3728a-1444-11ec-bab8-1831bf2721f6)
                        $image_name = explode('.', $image);
                        $image_name = $image_name[0];
    
                        
    
                        // Если изображения еще нет на сервере
                        if (!in_array(get_filename($image), $post_imgs)) {
                            echo '-- Изображения нет на сервере' . PHP_EOL;
                            $image_ftp = 'ftp://web:321321qz@46.38.4.27/Images/' . $image;
                            
                            if (file_exists($image_ftp)) {
    
                                // echo $image . PHP_EOL;
    
                                // Wordpress не может загружать по FTP, поэтому сначала скачиваем файл
                                $image_save_directory = dirname(__FILE__) . '/' . $image;
                                $image_info = getimagesize($image_ftp);
                                $image_get = file_get_contents($image_ftp);
                                
    
                                if($image_get) {
                                    file_put_contents($image_save_directory, $image_get);
                                }
    
                                $image_format = '.jpg'; // Формат по умолчанию
    
                                // Обрезка неформата и перевод изображений в webp
                                if ($webp) { 
    
                                    $image_format = '.webp';
    
                                    echo '-- Обрезаем изображение/сохраняем в ' . $image_format . '<br/>' . PHP_EOL;
                                    $image_crop = new Thumbs($image_save_directory);
    
                                    // Обрезаем до 768px с белым фоном
                                    $image_crop -> resizeCanvas(768, 768, array(255, 255, 255));
                        
                                    $image_new_save_directory = dirname(__FILE__) . '/' . $image_name . '.webp';
    
                                    // Сохраняем
                                    $image_crop -> saveWEBP($image_new_save_directory, 80);
    
                                } else if 
                                // Если не нужно переводить в webp, то обрезаем/сохраняем в jpg
                                ($image_info[0] != $image_info[1] || ($image_info[0] + $image_info[1]) < 600) { 
    
                                    echo '-- Обрезаем изображение/сохраняем в ' . $image_format . '<br/>' . PHP_EOL;
                                    $image_crop = new Thumbs($image_new_save_directory);
    
                                    // Обрезаем до 768px с белым фоном
                                    $image_crop -> resizeCanvas(768, 768, array(255, 255, 255));
                        
                                    // Сохраняем
                                    $image_crop -> saveJpg($image_new_save_directory, 90);
    
                                } 
    
                                // Формируем ссылку для Wordpress'а
                                $image_url = get_site_url() . '/import/'. $image_name . $image_format;
                                echo $image_url . PHP_EOL;
                                $image_description = $import_product -> Наименование;
    
                                $image_upload = media_sideload_image( $image_url, $post_id, $image_description, 'id' );
    
                                if (is_wp_error($image_upload)) {
                                    echo 'Ошибка! ' . $image_upload -> get_error_message() . PHP_EOL;
                                } else {
                                    echo 'Изображение загружено' . PHP_EOL;
                                    // добавлено 
                                }
    
    
                                // Проверяем, является ли основное изображение первым
                                if ($image['Основное']) {
    
                                    // Если в массиве уже есть другие изображения
                                    if ($images) {
                                        // Добавляем основное изображение в начало
                                        array_unshift($images, $image_upload);
                                    } else {
                                        $images[] = $image_upload;
                                    }
    
                                } else {
                                    // Если изображение не явялется основным
                                    $images[] = $image_upload;
                                }
    
                                // Удаляем временный файл изображения
                                $delete_image = unlink($image_save_directory);
                                if ($delete_image) {
                                    echo 'Временный файл удалён' . PHP_EOL;
                                } else {
                                    echo 'Не удалось удалить временны файл' . PHP_EOL;
                                }
    
                                $delete_image = unlink($image_new_save_directory);
                                if ($delete_image) {
                                    echo 'Временный файл удалён' . PHP_EOL;
                                } else {
                                    echo 'Не удалось удалить временны файл' . PHP_EOL;
                                }
                            }
                            
                        } else {
    
                            echo '-- Изображения есть на сервере' . PHP_EOL;
                            // Если изображения уже есть, получаем их ID, чтобы затем присвоить посту
                            if ($has_img) {
                                // print_r($has_img);
                                foreach ($has_img as $filename) {
    
                                    // Название файла из xml без расширения
                                    $image = explode('.',$image);
                                    $image = $image[0];
    
                                    // Название файла в базе без расширения
                                    $image_exists = explode('.', basename($filename['guid']));
                                    $image_exists = $image_exists[0];
    
    
                                        echo $image . ' - ' .  $image_exists . PHP_EOL;
                                    if ($image == $image_exists) {
                                        $images[] = $filename['ID'];
                                    }
                                }
                            }
    
                        }
                    }
                }
    
    
                // Функция присваивания картинок
                if ($images) {
                    
                    // Первое изображение
                    set_post_thumbnail($post_id, $images[0]);
    
                    print_r($images[0]);
                    
                    // Если больше одного изображения
                    if(count($images) > 1) { 
                        echo 'Обновляем галерею' . PHP_EOL;
                        array_shift($images); 
                        $images = implode(',', $images);
                        update_post_meta($post_id, '_product_image_gallery', $images);
                    }
                }
    
            }
    
    
     
    
    
    
            // Присваиваем бренд
            if ($import_product -> БрендИд ) {      
    
                $feature_id = 'brand';
                $feature_label = 'Бренд';
                $feature_value = 'brand_' . $brands[(string)$import_product -> БрендИд];
                    
                set_attributes($post_id, $feature_id, $feature_label, $feature_value);
            }
    
    
            // Обновляем страну
            if ($import_product -> СтранаПроисхождения ) {       
                
                $feature_id = 'country';
                $feature_label = 'Страна';
    
                // Переводим название страны в нужный регистр
                $feature_value = 'country_' . mb_convert_case((string)$import_product -> СтранаПроисхождения, MB_CASE_TITLE, "UTF-8");
                
                set_attributes($post_id, $feature_id, $feature_label, $feature_value);
    
            }
    
    
            // Добавляем / обновляем характеристики
            if ($import_product -> Характеристики -> Характеристика ) {
       
                foreach($import_product -> Характеристики -> Характеристика as $feature) {
    
                    $feature_id = translit($feature -> Ид);
                    $feature_label = $characteristics_name_array[$feature_id];
                    $feature_value = (string)$feature -> Значение;
    
                    set_attributes($post_id, $feature_id, $feature_label, $feature_value);
    
                }
            }
    
    
            // Обновляем кол-во в упаковке
            if ($import_product -> ЕдиницыИзмерения ) {       
               // print_r($import_product -> ЕдиницыИзмерения);
                foreach ($import_product -> ЕдиницыИзмерения -> ЕдиницаИзмерения as $unit) {
                    
                    // print_r($unit) . PHP_EOL;
                    if ($unit == 'кор') {
                        // echo PHP_EOL . 'В коробке ' . $unit['Коэффициент'];
    
                        $feature_id = 'unit_box';
                        $feature_label = 'Кол-во в коробке';
                        $feature_value = (string)$unit['Коэффициент'];
    
                        set_attributes($post_id, $feature_id, $feature_label, $feature_value);
    
                    }
    
                    if ($unit == 'упак') {
                        // echo PHP_EOL . 'В упаковке ' . $unit['Коэффициент'];
    
                        $feature_id = 'unit_pack';
                        $feature_label = 'Кол-во в упаковке';
                        $feature_value = (string)$unit['Коэффициент'];
    
                        set_attributes($post_id, $feature_id, $feature_label, $feature_value);
    
                    }
    
                }
    
            }
    
        
            // Если сумма есть в базе - обновляем
            if (isset($sum_import['sum_import'])) {
    
                $sql = "UPDATE `1c_id` SET `sum_import` = '". $import_product -> КонтрольнаяСумма ."' WHERE `post_id` = " . (int)$post_id;
               $check_add = $wpdb -> query($sql);
               if ($check_add) {
                   echo 'Обновлено успешно';
               } else {
                   echo 'Не удалось обновить сумму';
               }
           } else {
               // Если суммы нет - добавляем
               $sql = "INSERT INTO `1c_id` (`id`, `post_id`, `sku`, `sum_import`, `sum_offer`, `date`) VALUES (NULL, '". $post_id ."', '". $import_product -> Ид ."', '". $import_product -> КонтрольнаяСумма ."', '0', '" . $date . "')";
               $check_add = $wpdb -> query($sql); 
               if ($check_add) {
                   echo 'Сумма добавлена';
               } else {
                   echo 'Не удалось добавить сумму';
               }
           }
    
           echo PHP_EOL . PHP_EOL;
    }
    
}








///////////////////////////////////
// Обновляем цены и остатки
///////////////////////////////////



function stock_update($post_id, $stock_kind, $stock_opt) {
    // Обновление оптового склада
    if ($stock_opt) {
        update_post_meta( $post_id, $stock_kind, intval($stock_opt) );
        echo 'Обновляем склад' . $stock_kind .PHP_EOL;
    } else {
        update_post_meta( $post_id, $stock_kind, 0 );
        echo 'Нулевые остатки' . $stock_kind . PHP_EOL;
    }
}

// Получаем строку Склады/Цены для получения контрольной суммы
function check_sum_string ($stock_array, $key) {
    $check_sum = null;
    foreach ($stock_array as $stock) {
        $check_sum .= (string)$stock -> $key;
    }
    return $check_sum;
}

$products_count = 0;

// Получаем коды всех товаров в базе данных
$sql = "SELECT `sku` FROM `1c_id`";
$products_in_base = $wpdb -> get_col($sql);

// массив со всеми товарами из xml
foreach($offers -> ПакетПредложений -> Предложения -> Предложение as $offer) {
    $products_in_xml[] = (string)$offer -> Ид;
};


// Товары, которых нет в XML 
foreach($products_in_base as $product) {
    if (!in_array($product, $products_in_xml)) {
        
        $sql = "SELECT `post_id` FROM `1c_id` WHERE `sku` LIKE '". $product ."'";
        $no_xml_id = $wpdb -> get_var($sql);
        $no_xml[] = $no_xml_id;

    }
}

// Обнуляем остатки у товаров, которых нет в xml
foreach ($no_xml as $no_xml_product) {

    delete_post_meta( $no_xml_product, '_stock_opt');
    update_post_meta( $no_xml_product, '_stock_status', 'onbackorder');
    update_post_meta( $no_xml_product, '_stock', 0);

    // Удаляем метку "В пути"
    $sql = "SELECT `term_taxonomy_id` FROM `wp_term_relationships` WHERE `object_id` = " . $no_xml_product;
    $get_tag = $wpdb -> get_var($sql);
    if ($get_tag) {
        $sql = "DELETE FROM wp_term_relationships WHERE `wp_term_relationships`.`object_id` = ". $no_xml_product ." AND `wp_term_relationships`.`term_taxonomy_id` = 15623";
        $wpdb -> query ($sql);
    }

}


$i = 0;

foreach ($offers -> ПакетПредложений -> Предложения -> Предложение as $offer) {

    $i++;
    
            // Если есть ограничение на количество итераций, прерываем цикл
            if ($i > $products_count && $products_count != 0) {
                break;
            }
     
            break_process ();

            // Получаем контрольную сумму цена/количество
            $check_sum = sprintf ( "%u", crc32 (
                check_sum_string ($offer->Цены-> Цена, 'ЦенаЗаЕдиницу') . check_sum_string ($offer-> Склады-> Склад, 'Количество')
            ));

            


            $sql = "SELECT `id`, `post_id`, `sum_offer`,`sum_import` FROM `1c_id` WHERE `sku` LIKE '" . trim($offer->Ид) . "' ";
            $get_info = $wpdb->get_row( $sql, OBJECT);
            $today = date("Y-m-d H:i:s");

            // Обновляем время блокировки
            set_transient( 'interrupt', 1, 3 * MINUTE_IN_SECONDS );

            // Если нет данных в сводной таблице
            if ($get_info == null) {

                echo $i . ' - ' . (string) $offer->Ид . ' - Нет информации в сводной таблице. Добавляем' . '<br/>' . PHP_EOL;

                // Получаем информацию из postmet'ы
                $sql = "SELECT `post_id` FROM `wp_postmeta` WHERE `meta_value` LIKE '". (string) $offer->Ид ."' ";
                $post_id = $wpdb -> get_var( $sql);
                // echo 'ok' . $post_id . PHP_EOL;

                // echo 'Вставляем данные в сводную таблицу' . PHP_EOL;
                if ($post_id) {

                    // Считаем количество в наличии
                    $instock = 0;
                    $stock_wait = null;
                    $stock_opt = null;
                    $stock_k = null;
                    $stock_s = null;

                    foreach ($offer -> Склады -> Склад as $stock) {
                        
                        // Если stock wait
                        if (strval($stock -> Ид) == 'stock_wait') {
                            $stock_wait = $stock -> Количество;
                        } else {
                            $instock += $stock -> Количество;
                        }

                        // Если opt
                        if (strval($stock -> Ид) == 'stock_opt') {
                            $stock_opt = $stock -> Количество;
                        } 

                        // Ким Ю Чена
                        if (strval($stock -> Ид) == 'stock_k') {
                            $stock_k = $stock -> Количество;
                        } 

                        // Склад
                        if (strval($stock -> Ид) == 'stock_s') {
                            $stock_s = $stock -> Количество;
                        } 

                    }

                    update_post_meta( $post_id, '_stock', $instock);
                    stock_update($post_id, '_stock_opt', $stock_opt);
                    stock_update($post_id, '_stock_k', $stock_k);
                    stock_update($post_id, '_stock_s', $stock_s);


                    // Обновление остатков
                    update_post_meta( $post_id, '_stock', $instock);
                    if ($instock > 0) {
                        update_post_meta( $post_id, '_stock_status', 'instock');
                    } else if ($stock_wait) {
                        update_post_meta( $post_id, '_stock_status', 'intransit');
                    } else {
                        update_post_meta( $post_id, '_stock_status', 'onbackorder');
                    }


    
                    $price_sale = null;
                    // Если есть старая цена, формируем цену распродажи
                    foreach ($offer-> Цены -> Цена as  $price) {
            
                        if((string) $price->Ид == 'price') {
                            $price_current = (string) $price->ЦенаЗаЕдиницу;
                            $update_price1 = update_post_meta( $post_id, '_regular_price', $price_current);
                            $update_price2 = update_post_meta( $post_id, '_price', $price_current);
                        }

                        if((string) $price->Ид == 'old') {
                            $price_sale = (string) $price_current;
                            $price_current = (string) $price->ЦенаЗаЕдиницу;
                            echo $offer->Ид . ' - price_sale - ' . $price_sale . ' - price_current' . $price_current . PHP_EOL;
                            update_post_meta( $post_id, '_sale_price', $price_sale);
                            update_post_meta( $post_id, '_regular_price', $price_current);
                            update_post_meta( $post_id, '_price', $price_sale);
                        } 

                        
                        if((string) $price->Ид == 'partner') {
                            // Обновляем партнерские цены
                            update_post_meta( $post_id, '_partner_price', (string) $price->ЦенаЗаЕдиницу);
                        }
                        // DELETE FROM wp_postmeta WHERE `meta_key` LIKE '_stock_opt'
                        // SELECT * FROM `wp_postmeta` WHERE `meta_key` LIKE '_stock_opt' 
                        if((string) $price->Ид == 'spec') {
                            // Обновляем спец-цены
                            update_post_meta( $post_id, '_spec_price', (string) $price->ЦенаЗаЕдиницу);
                        }

                        if((string) $price->Ид == 'opt') {
                            // Обновляем оптовые цены
                            update_post_meta( $post_id, '_opt_price', (string) $price->ЦенаЗаЕдиницу);
                        }
                    }
    
                    

                } else {
                    // Заносим не найденные товары в отдельный массив
                    $not_found .= (string) $offer->Ид . ', ';
                    echo '       Не найден ID товара '. (string) $offer->Ид .' . Обновление цен невозможно' . PHP_EOL;
                }

            } else if ($get_info -> sum_offer == $check_sum && !isset($offers_forced)) {
                echo $i . ' - ' . (string) $offer->Ид . ' - Суммы равны offer: ' . $get_info -> sum_offer . ' - бд: ' . $check_sum . '<br/>' . PHP_EOL;
                continue;

            } else {
                echo $i . ' - ' . (string) $offer->Ид  . ' - Суммы не равны. Обновляем' . '<br/>' . PHP_EOL;

                $post_id = $get_info -> post_id;

                // Считаем количество в наличии
                $instock = 0;
                $stock_wait = null;
                $stock_opt = null;
                $stock_k = null;
                $stock_s = null;

                foreach ($offer -> Склады -> Склад as $stock) {
                    
                    // Если stock wait
                    if (strval($stock -> Ид) == 'stock_wait') {
                        $stock_wait = $stock -> Количество;
                    } else {
                        $instock += $stock -> Количество;
                    }

                    // Если opt
                    if (strval($stock -> Ид) == 'stock_opt') {
                        $stock_opt = $stock -> Количество;
                    } 

                    // Ким Ю Чена
                    if (strval($stock -> Ид) == 'stock_k') {
                        $stock_k = $stock -> Количество;
                    } 

                    // Склад
                    if (strval($stock -> Ид) == 'stock_s') {
                        $stock_s = $stock -> Количество;
                    } 

                }

                update_post_meta( $post_id, '_stock', $instock);
                // echo 'Обновляем' . PHP_EOL;
                stock_update($post_id, '_stock_opt', $stock_opt);
                stock_update($post_id, '_stock_k', $stock_k);
                stock_update($post_id, '_stock_s', $stock_s);
                stock_update($post_id, '_stock_intransit', $stock_wait);


                // Обновление остатков
                update_post_meta( $post_id, '_stock', $instock);
                if ($instock > 0) {
                    update_post_meta( $post_id, '_stock_status', 'instock');
                    // Удаляем метку "В пути"
                    $sql = "SELECT `term_taxonomy_id` FROM `wp_term_relationships` WHERE `object_id` = " . $post_id;
                    $get_tag = $wpdb -> get_var($sql);
                    if ($get_tag) {
                        $sql = "DELETE FROM wp_term_relationships WHERE `wp_term_relationships`.`object_id` = ". $post_id ." AND `wp_term_relationships`.`term_taxonomy_id` = 15623";
                        $wpdb -> query ($sql);
                    }
                } else if ($stock_wait) {
                    update_post_meta( $post_id, '_stock_status', 'intransit');
                    // Присваиваем метку "В пути" соответствующим товарам
                    $sql = "SELECT `term_taxonomy_id` FROM `wp_term_relationships` WHERE `object_id` = " . $post_id;
                    $get_tag = $wpdb -> get_var($sql);
                    if (!$get_tag) {
                        $sql = "INSERT INTO `wp_term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES ('". $post_id ."', '15623', '0')";
                        $wpdb -> query ($sql);
                    }
                } else {
                    update_post_meta( $post_id, '_stock_status', 'onbackorder');
                    // Удаляем метку "В пути"
                    $sql = "SELECT `term_taxonomy_id` FROM `wp_term_relationships` WHERE `object_id` = " . $post_id;
                    $get_tag = $wpdb -> get_var($sql);
                    if ($get_tag) {
                        $sql = "DELETE FROM wp_term_relationships WHERE `wp_term_relationships`.`object_id` = ". $post_id ." AND `wp_term_relationships`.`term_taxonomy_id` = 15623";
                        $wpdb -> query ($sql);
                    }
                }









                $price_sale = null;
                // Если есть старая цена, формируем цену распродажи
                foreach ($offer-> Цены -> Цена as  $price) {

                    echo '-- ' . $price->Ид . ' ' . $price_current . PHP_EOL;

                    if((string) $price->Ид == 'price') {
                        $price_current = (string) $price->ЦенаЗаЕдиницу;
                        update_post_meta( $post_id, '_regular_price', $price_current);
                        update_post_meta( $post_id, '_price', $price_current);
                    }

                    if((string) $price->Ид == 'old') {
                        
                        $price_sale = (string) $price_current;
                        $price_current = (string) $price->ЦенаЗаЕдиницу;
                        echo $offer->Ид . ' - price_sale - ' . $price_sale . ' - price_current' . $price_current . PHP_EOL;
                        update_post_meta( $post_id, '_sale_price', $price_sale);
                        update_post_meta( $post_id, '_regular_price', $price_current);
                        update_post_meta( $post_id, '_price', $price_sale);
                    } 

                    if((string) $price->Ид == 'partner') {
                        // Обновляем партнерские цены
                        update_post_meta( $post_id, '_partner_price', (string) $price->ЦенаЗаЕдиницу);
                    }

                    if((string) $price->Ид == 'spec') {
                        // Обновляем спец-цены
                        update_post_meta( $post_id, '_spec_price', (string) $price->ЦенаЗаЕдиницу);
                    }

                    if((string) $price->Ид == 'opt') {
                        // Обновляем оптовые цены
                        update_post_meta( $post_id, '_opt_price', (string) $price->ЦенаЗаЕдиницу);
                    }
                    
                }





                if (isset($get_info -> sum_offer)) {
                    // Обновляем суммы
                    $sql = "UPDATE `1c_id` SET `sum_offer` = '" . $check_sum . "', `date` = '". $today ."' WHERE `sku` like '" . (string) $offer->Ид . "'";
                    $sum_update = $wpdb -> query($sql);
                    if ($sum_update) {
                        echo 'Успешно обновлено' . PHP_EOL;
                    } else {
                        echo 'Ошибка обновления' . PHP_EOL;
                    }
                } else {
                    echo 'Отсутствует значение в таблице' . PHP_EOL;
                }


            }
            
           //  echo $i . ' - ' . $post_id . ' - ' . (string)$offer->Ид . ' - ' . $instock . ' - ' . $price_current . ' - ' . $check_sum . PHP_EOL;
            
            
}










///////////////////////////////////////




///////////////////////
// Импорт для 2Гис. От 27-07-2021
///////////////////////




/* Подключаем PHPExcel */
// require_once dirname(__FILE__) . '/Classes/PHPExcel.php';

$date = date ("Y-m-d\TH:i", strtotime("+7 hours")) . '+10:00';





// Подключаем файлы, которые будем анализировать
// $import = simplexml_load_file('import.xml');
// $offers = simplexml_load_file('offers.xml');

$url = 'https://off-mar.ru/sku/';
// $url = 'https://offt.ru/sku/';



// Удаляем символы, запрещенные в XML ( 9 пункт - https://yandex.ru/support/webmaster/goods-prices/technical-requirements.html)

function cut($input) {
    $characters = array('"', '&', '>', '<', "'");
    $pattern = "/[" . implode("", $characters) . "]/";
    $input = preg_replace($pattern, ' ', $input);
    return $input;
}

// Куда импортируем
$file = 'price.yml';

$single_category = 'K0000000603';
// $limit = 10;





/*
function check_parent($path, $child, $parent)
{
    // echo 'Проверка является ли категория ' . $child . ' потомком категории ' . $parent . PHP_EOL;
    foreach ($path as $group) {
        if ((string)$group->Ид == $child) {

            // Если родитель и потомок совпадают, возвращаем true
            if ($child == $parent) {
                return true;
            }
            
            // Если у категории есть родитель, но Ид родителя и потомка не совпадают – запускаем функцию заново. Находим родителя родителя
            else if ($group->ИдРодителя) {
                $child = (string)$group->ИдРодителя;
                return check_parent($path, $child, $parent);
            }
            
            // Если родителя нет, возращаем false
            else {
                return false;
            }
        }
    }
}
*/

// Переводим коды категорий в индексы
$i = 0;
foreach ($import -> Классификатор -> Группы -> Группа as $category) {

    if(isset($single_category)) {
        if (!check_parent($import -> Классификатор -> Группы -> Группа, (string)$category -> Ид, $single_category)) {
            continue;
        }
    }

    $category_index[(string)$category -> Ид] = $i;
    $category_slag[] = (string)$category -> Ид;
    $i++;
}


$i = 0;
foreach ($import -> Классификатор -> Группы -> Группа as $category) {

    if (!in_array((string)$category -> Ид, $category_slag)) {
        continue;
    }

    $categories[] = [
        'index' => $i,
        'cat_id' => $category_index[(string)$category -> Ид],
        'parent_cat_id' => $category_index[(string)$category -> ИдРодителя],
        'name' => cut((string)$category -> Наименование )
    ];

    $i++;
}


foreach ($categories as $value) {
    if (isset($value['parent_cat_id'])) {
        $parent_id = ' parentId="'. $value['parent_cat_id'] . '"';
    } else {
        $parent_id = '';
    }
    $cat_string .= '    <category id="'. $value['cat_id'] .'"'. $parent_id .'>' . $value['name'] . '</category>' . PHP_EOL;
    unset($parent_id);
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
foreach ($import -> Каталог -> Товары -> Товар as $import_product) {

    // Если есть лимит на количество

    if (isset($limit) && $i > $limit) {
        break;
    }

    break_process ();

    if (!in_array((string)$import_product -> ГруппаИд, $category_slag)) {
        continue;
    }

    /*
    // Если нужно выгрузить одну категорию и товар не входит в нее - пропускаем
    if(isset($single_category)) {
        if (!check_parent($import -> Классификатор -> Группы -> Группа, $import_product -> ГруппаИд, $single_category)) {
            continue;
        }
    }
    */

    set_transient( 'interrupt', 1, 3 * MINUTE_IN_SECONDS );
    
    $string = '<offer id="'. $import_product -> Ид .'" available="true">' . PHP_EOL;
    $string .= '    <categoryId>'. $category_index[(string)$import_product -> ГруппаИд] .'</categoryId>' . PHP_EOL;
    $string .= '    <name>'. cut((string)$import_product -> Наименование) . '</name>' . PHP_EOL;
    $string .= '    <url>' . $url . $import_product -> Ид . '</url>' . PHP_EOL;
    $string .= '    <picture>ftp://web:321321qz@46.38.4.27/Images/' . $import_product -> Изображения -> Изображение[0] . '</picture>' . PHP_EOL;

    

    if ($import_product -> Характеристики -> Характеристика) {
        $description = '';
        foreach ($import_product -> Характеристики -> Характеристика as $characteristic) {
            
            // Получаем названия характеристик
            foreach ($import -> Классификатор -> ХарактеристикиНоменклатуры -> Характеристика as $characteristics_name) {
                
                if (strcasecmp($characteristic -> Ид, $characteristics_name -> Ид) == 0) {
                    $description .= PHP_EOL . '       ' . cut($characteristics_name -> Наименование) . ' - ' . cut($characteristic -> Значение) ;
                    break;
                } else {
                    continue;
                }

            }                
        }
        $string .= '    <description>' . $description. PHP_EOL . '    </description>' . PHP_EOL;
    }

    $price = null;
    $instock = null;
    foreach ($offers -> ПакетПредложений -> Предложения -> Предложение as $value) {

        if ((string)$value -> Ид == (string)$import_product -> Ид) {
            $price = $value-> Цены -> Цена -> ЦенаЗаЕдиницу;
            $string .= '    <price>' . str_replace(',', '.', $price) . '</price>' . PHP_EOL;

            foreach ($value -> Склады -> Склад as $stock) {
                if($stock -> Ид != 'stock_wait') {
                    $instock += $stock -> Количество;
                }
            }
            break;
        }

    }
    


    // Если цены нет, пропускаем товар
    if (!isset($price)) {
        continue;
    }

    // Если товар отсутствует на складе - пропускаем
    if (!isset($instock)) {
        continue;
    }
    
    $string .= '</offer>' . PHP_EOL;   
    file_put_contents($file, $string, FILE_APPEND);
    echo $string;
    $i++;

}

$string = '        </offers>
    </shop>
</yml_catalog>';

file_put_contents($file, $string, FILE_APPEND);



put_ftp_price($file, '/');
// put_ftp_price('webdata.zip');

/*
$fp = fopen($file, "w");
 
// записываем в файл текст
fwrite($fp, $string);
 
// закрываем
fclose($fp);

*/

// print_r($categories);


// Засекаем время выполнения задачи
$time_end = microtime(true);
// Вычетаем разницу во времени 
$time = $time_end - $time_start;
echo 'Время выполнения: ' . round($time,1) . ' секунд';



///////////////////////////////////////

// Записываем дату создания прайс-листа, если он существует dirname(__FILE__) . 

$input_price = dirname(__FILE__) . '/webdata/price_list_ot.xls';
$output_price = '../offt-price.xls';

if (file_exists($excel_price)) {
    if(copy($excel_price, $output_price)) {
        $date = date("d.m.Y H:i", filectime(dirname(__FILE__) . '/webdata/price_list_ot.xls'));
        echo $date . PHP_EOL;
        update_option( 'price_update', $date );
        echo 'Копирование прошло успешно!';
        telegram_send('Прайс-лист успешно скопирован');
    } else {
        echo 'Не удалось копировать';
        telegram_send('Ошибка копирования прайса!');
    }
} else {
    echo 'Ошибка';
    telegram_send('Прайс не найден!');
}

// Отменяем блокировку
set_transient( 'interrupt', false, 3 * MINUTE_IN_SECONDS );

// Удаляем директорию с файлами
remove_directory('webdata');

// Сбрасываем кэш страниц
// echo 'Сбрасываем кэш' . PHP_EOL;
// fopen('https://offt.ru/?action=wpfastestcache&type=clearcache&token=offt2023_clear_cache' ,"r");



put_ftp_price($zipfile, '/import/');



unlink('process.log');

echo 'Удаляем архив...' . PHP_EOL;
unlink($zipfile);

if (isset($not_found)) {
    // $not_found = implode(', ', $not_found);
    $not_found = 'Не удалось обновить цены для следующих товаров: ' . $not_found . PHP_EOL;
    telegram_send('Остатки обновлены' . $not_found );
} else {
    telegram_send('Остатки обновлены');
}

// Засекаем время выполнения задачи
$time_end = microtime(true);
// Вычетаем разницу во времени 
$time = $time_end - $time_start;
echo PHP_EOL . 'Время выполнения: ' . round($time, 1) . ' секунд' . PHP_EOL . PHP_EOL;