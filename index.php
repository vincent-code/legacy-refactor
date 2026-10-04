<?php
/**
 * Лендинг со списком квартир из NL_PROP_RESALE.
 *  - описание (NL_PROP_RESALE_DESCRIPTION, URL-кодированный Quill Delta) рендерит nadar/quill-delta-parser;
 *  - вёрстка - шаблонизатор Smarty (landing/templates/index.tpl).
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/functions.php';

/**
 * Отдаём только безопасные адреса /img/<каталог>/<файл>, реально существующие на диске.
 */
function landing_photos($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return array();
    }
    $list = json_decode($raw, true);
    if (!is_array($list)) {
        $list = json_decode(stripslashes($raw), true);
    }
    if (!is_array($list)) {
        preg_match_all('#/img/[A-Za-z0-9_\-]+/[A-Za-z0-9_.\-]+#', $raw, $m);
        $list = $m[0];
    }

    $photos = array();
    foreach ($list as $url) {
        if (!is_string($url) || !preg_match('#^/img/[A-Za-z0-9_\-]+/[A-Za-z0-9_.\-]+$#', $url)) {
            continue; // javascript:, data:, ../ и прочее - не показываем
        }
        if (!is_file(__DIR__ . $url)) {
            continue; // файла нет на диске - не выводим битую картинку
        }
        $photos[] = $url;
    }
    return $photos;
}

/**
 * Описание: rawurldecode -> JSON Quill Delta -> HTML через nadar\quill\Lexer.
 * При любой ошибке формата возвращаем пустую строку, а не ломаем всю страницу.
 */
function landing_description_html($encoded)
{
    $delta = json_decode(rawurldecode((string)$encoded), true);
    if (!is_array($delta) || !isset($delta['ops']) || !is_array($delta['ops'])) {
        return '';
    }

    // Защита от javascript:-ссылок в форматировании: оставляем только http(s), mailto, tel
    foreach ($delta['ops'] as $i => $op) {
        if (isset($op['attributes']['link'])
            && !preg_match('#^(https?://|mailto:|tel:)#i', (string)$op['attributes']['link'])) {
            unset($delta['ops'][$i]['attributes']['link']);
        }
    }

    try {
        $lexer = new \nadar\quill\Lexer(json_encode($delta, JSON_UNESCAPED_UNICODE));
        if (property_exists($lexer, 'escapeInput')) {
            $lexer->escapeInput = true; // текст из Delta экранируется парсером
        }
        return trim($lexer->render());
    } catch (\Throwable $e) {
        error_log('Quill render error: ' . $e->getMessage());
        return '';
    }
}

/** Число без хвоста нулей: 246.00 -> "246", 54.50 -> "54,5" */
function landing_num($value, $decimals = 2)
{
    $s = number_format((float)$value, $decimals, ',', "\u{00A0}");
    return strpos($s, ',') !== false ? rtrim(rtrim($s, '0'), ',') : $s;
}

// ---------------------------------------------------------------------------------------------
// Данные. Колонку «Контакт собственника» (NL_PROP_RESALE_PHONE_OWNER) на публичную страницу
// НЕ выбираем намеренно - это служебные данные для менеджеров (см. этап 2 аудита).
// ---------------------------------------------------------------------------------------------
db_connect();

$query = "SELECT p.ID_NL_PROP_RESALE, p.NL_PROP_RESALE_FLOOR, p.NL_PROP_RESALE_AREA_FULL,
                 p.NL_PROP_RESALE_PHOTO_URLS, p.NL_PROP_RESALE_COST_TOTAL, p.NL_PROP_RESALE_ADDRESS,
                 p.NL_PROP_RESALE_DESCRIPTION, p.NL_PROP_RESALE_PHONE, p.NL_PROP_RESALE_DATE_INSERT,
                 v.NL_VIEW_SHORT, h.NL_HOUSES_SHORT, m.NL_MATERIAL_SHORT, u.NL_USER_SHORT
          FROM NL_PROP_RESALE p
          LEFT JOIN NL_VIEW v     ON v.ID_NL_VIEW = p.ID_NL_VIEW
          LEFT JOIN NL_HOUSES h   ON h.ID_NL_HOUSES = p.ID_NL_HOUSES
          LEFT JOIN NL_MATERIAL m ON m.ID_NL_MATERIAL = p.ID_NL_MATERIAL
          LEFT JOIN NL_USER u     ON u.ID_NL_USER = p.ID_NL_USER
          ORDER BY p.NL_PROP_RESALE_DATE_INSERT DESC, p.ID_NL_PROP_RESALE DESC";

$res = db_query($query); // константный запрос без пользовательских данных
if ($res === false) {
    db_disconnect();
    http_response_code(500);
    exit('Не удалось загрузить каталог. Попробуйте позже.');
}

$items = array();
$minPrice = null;
while ($row = db_fetch_assoc($res)) {
    $area  = (float)$row['NL_PROP_RESALE_AREA_FULL'];
    $cost  = $row['NL_PROP_RESALE_COST_TOTAL'] === null ? null : (int)$row['NL_PROP_RESALE_COST_TOTAL'];
    $phone = (string)$row['NL_PROP_RESALE_PHONE'];
    $photos = landing_photos($row['NL_PROP_RESALE_PHOTO_URLS']);

    if ($cost !== null && ($minPrice === null || $cost < $minPrice)) {
        $minPrice = $cost;
    }

    $items[] = array(
        'id'          => (int)$row['ID_NL_PROP_RESALE'],
        'address'     => (string)$row['NL_PROP_RESALE_ADDRESS'],
        'area'        => landing_num($area),
        'floor'       => (string)$row['NL_PROP_RESALE_FLOOR'],
        'price'       => $cost === null ? '' : landing_num($cost, 0),
        'price_m2'    => ($cost !== null && $area > 0) ? landing_num($cost / $area, 0) : '',
        'view'        => (string)$row['NL_VIEW_SHORT'],       // вид из окна
        'house'       => (string)$row['NL_HOUSES_SHORT'],     // тип дома
        'material'    => (string)$row['NL_MATERIAL_SHORT'],   // материал дома
        'manager'     => (string)$row['NL_USER_SHORT'],
        'phone'       => $phone,
        'phone_href'  => preg_replace('/[^0-9+]/', '', $phone),
        'added'       => $row['NL_PROP_RESALE_DATE_INSERT'] ? date('d.m.Y', strtotime($row['NL_PROP_RESALE_DATE_INSERT'])) : '',
        'photos'      => $photos,
        'multi'       => count($photos) > 1,
        'description' => landing_description_html($row['NL_PROP_RESALE_DESCRIPTION']),
    );
}
db_disconnect();

// ---------------------------------------------------------------------------------------------
// Smarty (v5: \Smarty\Smarty, v4: \Smarty). Автоэкранирование включено; без экранирования
// выводится только HTML описания, который уже отрендерил парсер ({... nofilter} в шаблоне).
// ---------------------------------------------------------------------------------------------
$smartyClass = class_exists('\Smarty\Smarty') ? '\Smarty\Smarty' : '\Smarty';
$smarty = new $smartyClass();
$smarty->setTemplateDir(__DIR__ . '/landing/templates/');
$smarty->setCompileDir(__DIR__ . '/landing/templates_c/'); // должна быть доступна на запись для веб-сервера
$smarty->setCacheDir(__DIR__ . '/landing/cache/');
$smarty->setEscapeHtml(true);

$smarty->assign('items', $items);
$smarty->assign('total', count($items));
$smarty->assign('min_price', $minPrice === null ? '' : landing_num($minPrice, 0));
$smarty->assign('year', date('Y'));

header('Content-Type: text/html; charset=utf-8');
$smarty->display('index.tpl');
