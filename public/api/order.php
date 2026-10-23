<?php
// Приём заказа с корзины: проверяет данные, пересчитывает сумму по ценам с сервера и отправляет заказ в Telegram.
declare(strict_types=1);
define('SARAY_API', 1);
require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['error' => 'Метод не поддерживается.'], 405);

$in = input();
if (text($in['website'] ?? '', 200) !== '') out(['ok' => true, 'number' => '0000']); // приманка для ботов

$menu = json_decode((string) @file_get_contents(__DIR__ . '/menu.json'), true);
if (!is_array($menu) || !isset($menu['items'])) {
    error_log('saray: нет public/api/menu.json — соберите сайт (npm run build)');
    out(['error' => 'Заказы временно недоступны. Позвоните нам: +7 (383) 225-11-25.'], 503);
}
$prices = $menu['items'];
$dl = $menu['delivery'] ?? [];

$mode    = ($in['mode'] ?? '') === 'pickup' ? 'pickup' : 'delivery';
$name    = text($in['name'] ?? '', 60);
$phone   = phone($in['phone'] ?? '');
$address = text($in['address'] ?? '', 200);
$comment = text($in['comment'] ?? '', 500);
$payment = text($in['payment'] ?? '', 40);
$errors = [];
if (ulen($name) < 2) $errors[] = 'Укажите имя.';
if ($phone === '') $errors[] = 'Укажите телефон в формате +7 900 000-00-00.';
if ($mode === 'delivery' && ulen($address) < 5) $errors[] = 'Укажите адрес доставки.';
if (empty($in['consent'])) $errors[] = 'Нужно согласие на обработку персональных данных.';

$lines = []; $sum = 0; $count = 0;
$cart = $in['items'] ?? [];
if (!is_array($cart) || !$cart || count($cart) > 80) $errors[] = 'Корзина пуста.';
else foreach ($cart as $row) {
    $key = is_array($row) ? (string) ($row['key'] ?? '') : '';
    $qty = is_array($row) ? (int) ($row['qty'] ?? 0) : 0;
    if (!isset($prices[$key])) { $errors[] = 'Меню обновилось: одна из позиций недоступна. Обновите страницу.'; break; }
    if ($qty < 1 || $qty > 50) { $errors[] = 'Некорректное количество блюд.'; break; }
    $p = (int) $prices[$key]['price'];
    $lines[] = [$prices[$key]['name'], $qty, $p];
    $sum += $p * $qty; $count += $qty;
}
if ($count > 150) $errors[] = 'Слишком большой заказ. Позвоните нам: +7 (383) 225-11-25.';

$fee = 0;
if (!$errors && $mode === 'delivery') {
    $min = (int) ($dl['minOrder'] ?? 0);
    if ($min > 0 && $sum < $min) $errors[] = 'Минимальная сумма заказа на доставку — ' . money($min) . '.';
    $free = (int) ($dl['freeFrom'] ?? 0);
    $fee = ($free > 0 && $sum >= $free) ? 0 : (int) ($dl['deliveryFee'] ?? 0);
}
if ($errors) out(['error' => implode(' ', array_unique($errors))], 422);

throttle('order', 15);

$number = date('md') . '-' . random_int(100, 999);
$msg  = '🧾 <b>Заказ № ' . $number . '</b> · ' . ($mode === 'delivery' ? '🚚 доставка' : '🏃 самовывоз') . "\n\n";
$msg .= '👤 ' . h($name) . "\n📞 " . h($phone) . "\n";
if ($mode === 'delivery') $msg .= '📍 ' . h($address) . "\n";
if ($payment !== '') $msg .= '💳 ' . h($payment) . "\n";
if ($comment !== '') $msg .= '💬 ' . h($comment) . "\n";
$msg .= "\n<b>Состав:</b>\n";
foreach ($lines as $i => [$n, $q, $p]) $msg .= ($i + 1) . '. ' . h($n) . ' × ' . $q . ' = ' . money($p * $q) . "\n";
$msg .= "\nБлюда: " . money($sum);
if ($mode === 'delivery') $msg .= "\nДоставка: " . ($fee > 0 ? money($fee) : 'уточнить');
$msg .= "\n<b>Итого: " . money($sum + $fee) . '</b>' . ($mode === 'delivery' && $fee === 0 ? ' (без доставки)' : '');
$msg .= "\n\n" . date('d.m.Y H:i') . ' (Новосибирск)';

if (!send_telegram($msg)) {
    out(['error' => 'Не удалось отправить заказ. Позвоните нам: +7 (383) 225-11-25.'], 502);
}
out(['ok' => true, 'number' => $number]);
