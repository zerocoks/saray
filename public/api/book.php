<?php
// Заявка на бронь стола -> Telegram. Форма на главной отправляет её через fetch; без JavaScript — обычным POST с редиректом.
declare(strict_types=1);
define('SARAY_API', 1);
require __DIR__ . '/lib.php';

$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json');
function finish(bool $json, array $data, int $code): never {
    if ($json) out($data, $code);
    if (!empty($data['ok'])) { header('Location: /?booked=1#bron', true, 303); exit; }
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><p>' . h($data['error'] ?? 'Ошибка') . '</p><p><a href="/#bron">Назад</a></p>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') finish($json, ['error' => 'Метод не поддерживается.'], 405);
$in = input();
if (text($in['website'] ?? '', 200) !== '') finish($json, ['ok' => true], 200); // приманка для ботов

$name   = text($in['name'] ?? '', 60);
$phone  = phone($in['phone'] ?? '');
$guests = ($in['guests'] ?? '') === 'other' ? (int) ($in['guests_other'] ?? 0) : (int) ($in['guests'] ?? 0);
$when   = text($in['when'] ?? '', 20);
// без JavaScript форма присылает дату и время отдельными полями
if ($when === '' && is_string($in['date'] ?? null) && is_string($in['time'] ?? null)) $when = text($in['date'] . 'T' . $in['time'], 20);
$dt     = DateTime::createFromFormat('Y-m-d\TH:i', $when);
$errors = [];
if (ulen($name) < 2) $errors[] = 'Укажите имя.';
if ($phone === '') $errors[] = 'Укажите телефон в формате +7 900 000-00-00.';
if ($guests < 1 || $guests > 40) $errors[] = 'Количество гостей — от 1 до 40.';
if (!$dt) $errors[] = 'Укажите дату и время.';
if (empty($in['consent'])) $errors[] = 'Нужно согласие на обработку персональных данных.';
if ($errors) finish($json, ['error' => implode(' ', $errors)], 422);

throttle('book', 15);

$msg = "🍽 <b>Заявка на бронь</b>\n\n👤 " . h($name) . "\n📞 " . h($phone) . "\n👥 Гостей: " . $guests
     . "\n🕒 " . $dt->format('d.m.Y H:i') . "\n\n" . date('d.m.Y H:i') . ' (Новосибирск)';
if (!send_telegram($msg)) finish($json, ['error' => 'Не удалось отправить заявку. Позвоните нам: +7 (383) 225-11-25.'], 502);
finish($json, ['ok' => true], 200);
