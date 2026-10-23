<?php
// Общие функции для order.php и book.php. Напрямую не вызывается.
declare(strict_types=1);
if (!defined('SARAY_API')) { http_response_code(403); exit; }

date_default_timezone_set('Asia/Novosibirsk');

function out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function config(): array {
    $cfg = [];
    if (is_file(__DIR__ . '/config.php')) $cfg = (array) require __DIR__ . '/config.php';
    // можно задать и переменными окружения
    $cfg['bot_token'] = $cfg['bot_token'] ?? (getenv('TG_BOT_TOKEN') ?: '');
    $cfg['chat_id']   = $cfg['chat_id']   ?? (getenv('TG_CHAT_ID') ?: '');
    $cfg['api_base']  = $cfg['api_base']  ?? 'https://api.telegram.org';
    return $cfg;
}

function input(): array {
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw !== false && strlen($raw) > 65536) out(['error' => 'Слишком большой запрос.'], 413);
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
        $d = json_decode((string) $raw, true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}

function ulen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }

function text(mixed $v, int $max): string {
    $s = is_string($v) ? trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v) ?? '') : '';
    return ulen($s) > $max ? '' : $s; // слишком длинное значение считаем пустым -> ошибка валидации
}

function phone(mixed $v): string {
    $d = preg_replace('/\D+/', '', is_string($v) ? $v : '') ?? '';
    if (strlen($d) === 11 && ($d[0] === '8' || $d[0] === '7')) return '+7' . substr($d, 1);
    if (strlen($d) === 10) return '+7' . $d;
    return '';
}

function throttle(string $scope, int $seconds): void {
    $f = sys_get_temp_dir() . '/saray_' . $scope . '_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    if (is_file($f) && time() - (int) filemtime($f) < $seconds) {
        out(['error' => 'Слишком часто. Подождите несколько секунд и повторите.'], 429);
    }
    @touch($f);
}

function money(int $n): string { return number_format($n, 0, '', ' ') . ' ₽'; }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function send_telegram(string $html): bool {
    $cfg = config();
    if ($cfg['bot_token'] === '' || $cfg['chat_id'] === '') {
        error_log('saray: не заданы bot_token / chat_id (public/api/config.php)');
        return false;
    }
    $url = rtrim((string) $cfg['api_base'], '/') . '/bot' . $cfg['bot_token'] . '/sendMessage';
    $body = http_build_query([
        'chat_id' => $cfg['chat_id'], 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
    ]);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 10, 'ignore_errors' => true,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body]]);
        $res = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $line) if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) $code = (int) $m[1];
    }
    if ($res === false || $code !== 200) {
        error_log('saray: Telegram вернул ' . $code . ' ' . (is_string($res) ? substr($res, 0, 300) : ''));
        return false;
    }
    return true;
}
