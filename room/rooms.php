<?php
/*
 * rooms.php — Watch4Party Oda Backend
 * Odaları rooms_data.json dosyasına kaydeder.
 * 
 * Desteklenen işlemler (action parametresi):
 *   get    → Tek oda getir (code gerekli)
 *   set    → Oda oluştur / üzerine yaz
 *   patch  → Oda güncelle (sadece gönderilen alanlar)
 *   list   → Tüm aktif odaları listele
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Veri dosyası ──────────────────────────────────────────────────
define('DATA_FILE', __DIR__ . '/rooms_data.json');

function loadRooms(): array {
    if (!file_exists(DATA_FILE)) return [];
    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveRooms(array $rooms): void {
    file_put_contents(DATA_FILE, json_encode($rooms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error(string $msg, int $code = 400): void {
    respond(['error' => $msg], $code);
}

// ── Parametreleri al ──────────────────────────────────────────────
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');
$code   = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));

// POST body (JSON) de desteklenir
$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) $body = $parsed;
    }
    // form-data ile de çalışsın
    foreach ($_POST as $k => $v) {
        if (!isset($body[$k])) $body[$k] = $v;
    }
    if (!$action && isset($body['action'])) $action = $body['action'];
    if (!$code   && isset($body['code']))   $code   = strtoupper(trim($body['code']));
}

// ── Eski/pasif odaları temizle (7 günden eski) ────────────────────
function cleanOldRooms(array &$rooms): void {
    $cutoff = time() - 7 * 86400;
    foreach ($rooms as $c => $room) {
        $created = isset($room['created_at']) ? strtotime($room['created_at']) : 0;
        if ($created && $created < $cutoff) unset($rooms[$c]);
    }
}

// ─────────────────────────────────────────────────────────────────
// GET — Tek oda getir
// ─────────────────────────────────────────────────────────────────
if ($action === 'get') {
    if (!$code) error('Oda kodu gerekli.');
    $rooms = loadRooms();
    if (!isset($rooms[$code])) respond(null);
    $room = $rooms[$code];
    // Şifreyi asla dönme (sadece type bilgisi yeterli)
    unset($room['password']);
    respond($room);
}

// ─────────────────────────────────────────────────────────────────
// SET — Oda oluştur / üzerine yaz
// ─────────────────────────────────────────────────────────────────
if ($action === 'set') {
    if (!$code) error('Oda kodu gerekli.');

    // Zorunlu alanları doğrula
    $name     = trim($body['name']     ?? '');
    $host     = trim($body['host']     ?? '');
    $platform = trim($body['platform'] ?? 'Diğer');
    $type     = ($body['type'] ?? 'public') === 'private' ? 'private' : 'public';
    $password = trim($body['password'] ?? '');

    if (strlen($name)  < 3) error('Oda adı en az 3 karakter olmalı.');
    if (strlen($host)  < 2) error('Kullanıcı adı en az 2 karakter olmalı.');
    if ($type === 'private' && strlen($password) < 3) error('Özel oda için şifre gerekli (min. 3 karakter).');

    $rooms = loadRooms();
    cleanOldRooms($rooms);

    // Kod çakışma kontrolü
    if (isset($rooms[$code]) && ($rooms[$code]['active'] ?? false)) {
        // Yeni kod üret — client da yapabilir ama burada da önlem al
        error('Bu oda kodu zaten aktif. Lütfen tekrar dene.');
    }

    $members = [$host => true];

    $rooms[$code] = [
        'code'       => $code,
        'name'       => $name,
        'host'       => $host,
        'platform'   => $platform,
        'type'       => $type,
        'password'   => $type === 'private' ? $password : null, // hash client'ta yapılıyor ama sunucuda sakla
        'active'     => true,
        'created_at' => date('c'),
        'members'    => $members,
    ];

    saveRooms($rooms);
    $safe = $rooms[$code];
    unset($safe['password']);
    respond($safe);
}

// ─────────────────────────────────────────────────────────────────
// PATCH — Oda güncelle (üye ekle vs.)
// ─────────────────────────────────────────────────────────────────
if ($action === 'patch') {
    if (!$code) error('Oda kodu gerekli.');
    $rooms = loadRooms();
    if (!isset($rooms[$code])) error('Oda bulunamadı.', 404);

    // Sadece izin verilen alanları güncelle
    $allowed = ['members', 'active', 'name', 'platform'];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $rooms[$code][$field] = $body[$field];
        }
    }

    saveRooms($rooms);
    $safe = $rooms[$code];
    unset($safe['password']);
    respond($safe);
}

// ─────────────────────────────────────────────────────────────────
// CHECK_PASSWORD — Şifre doğrula (join sırasında)
// ─────────────────────────────────────────────────────────────────
if ($action === 'check_password') {
    if (!$code) error('Oda kodu gerekli.');
    $rooms = loadRooms();
    if (!isset($rooms[$code])) error('Oda bulunamadı.', 404);

    $room = $rooms[$code];
    if (($room['type'] ?? 'public') !== 'private') {
        respond(['ok' => true]); // şifre gerektirmiyor
    }

    $submitted = trim($body['password'] ?? '');
    // Şifre client'ta hashlanarak gönderilir (btoa("w4p_" + sifre))
    if ($submitted === $room['password']) {
        respond(['ok' => true]);
    } else {
        respond(['ok' => false, 'error' => 'Yanlış şifre.']);
    }
}

// ─────────────────────────────────────────────────────────────────
// LIST — Tüm aktif odalar (opsiyonel, yönetim için)
// ─────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $rooms = loadRooms();
    cleanOldRooms($rooms);
    $result = [];
    foreach ($rooms as $c => $room) {
        if (!($room['active'] ?? false)) continue;
        $safe = $room;
        unset($safe['password']);
        $result[$c] = $safe;
    }
    respond($result);
}

// ─────────────────────────────────────────────────────────────────
error('Geçersiz işlem.', 400);
