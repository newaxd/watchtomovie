<?php
/**
 * Watch4Party — Room Backend
 * PHP 7.0+ uyumlu (düzeltilmiş sürüm)
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('ROOMS_FILE', __DIR__ . '/rooms.txt');

// ── rooms.txt yoksa oluştur ────────────────────────────────────────
if (!file_exists(ROOMS_FILE)) {
    file_put_contents(ROOMS_FILE, '{}');
}

function respond($ok, $data = [], $http = 200) {
    http_response_code($http);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sanitize($str, $max = 100) {
    return mb_substr(trim(strip_tags($str)), 0, $max);
}

function generateCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function loadRooms() {
    if (!file_exists(ROOMS_FILE)) return [];
    $content = file_get_contents(ROOMS_FILE);
    if (!$content || trim($content) === '') return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveRooms($rooms) {
    $result = file_put_contents(
        ROOMS_FILE,
        json_encode($rooms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
    if ($result === false) {
        respond(false, ['message' => 'Dosyaya yazma hatası. rooms.txt izinlerini kontrol edin (chmod 666).'], 500);
    }
}

function getRoom($code) {
    $rooms = loadRooms();
    return isset($rooms[strtoupper(trim($code))]) ? $rooms[strtoupper(trim($code))] : null;
}

// ── Action ve body oku ─────────────────────────────────────────────
$action = strtolower(isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : ''));

// JSON body oku — hem application/json hem form-data destekle
$body = [];
$rawInput = file_get_contents('php://input');
if ($rawInput && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

// Hem $body hem $_POST'tan değer alma yardımcısı
function param($body, $key, $default = '') {
    if (isset($body[$key]) && $body[$key] !== '') return $body[$key];
    if (isset($_POST[$key]) && $_POST[$key] !== '') return $_POST[$key];
    return $default;
}

// ── POST: Oda oluştur ──────────────────────────────────────────────
if ($action === 'create') {
    $roomname = sanitize(param($body, 'roomname'), 60);
    $username = sanitize(param($body, 'username'), 30);
    $platform = sanitize(param($body, 'platform', 'YouTube'), 30);
    $typeRaw  = param($body, 'type', 'public');
    $type     = in_array($typeRaw, ['public', 'private']) ? $typeRaw : 'public';
    $password = sanitize(param($body, 'password'), 50);

    if (strlen($roomname) < 3) respond(false, ['message' => 'Oda adı en az 3 karakter olmalı.'], 400);
    if (strlen($username) < 2) respond(false, ['message' => 'Kullanıcı adı en az 2 karakter olmalı.'], 400);
    if ($type === 'private' && strlen($password) < 3)
        respond(false, ['message' => 'Özel oda için şifre gerekli (min. 3 karakter).'], 400);

    $rooms = loadRooms();
    $attempts = 0;
    do {
        $code = generateCode();
        $attempts++;
        if ($attempts > 100) respond(false, ['message' => 'Oda kodu üretilemedi.'], 500);
    } while (isset($rooms[$code]));

    $rooms[$code] = [
        'code'       => $code,
        'name'       => $roomname,
        'host'       => $username,
        'platform'   => $platform,
        'type'       => $type,
        'password'   => $type === 'private' ? password_hash($password, PASSWORD_BCRYPT) : null,
        'active'     => true,
        'created_at' => date('Y-m-d H:i:s'),
        'members'    => [$username],
    ];
    saveRooms($rooms);

    respond(true, [
        'message' => 'Oda oluşturuldu!',
        'code'    => $code,
        'room'    => [
            'code'     => $code,
            'name'     => $roomname,
            'host'     => $username,
            'platform' => $platform,
            'type'     => $type,
        ],
        'session'  => ['username' => $username, 'role' => 'host'],
        'redirect' => 'watch/index.html?room=' . urlencode($code) . '&user=' . urlencode($username) . '&host=1',
    ], 201);
}

// ── POST: Odaya katıl ──────────────────────────────────────────────
if ($action === 'join') {
    $code     = strtoupper(sanitize(param($body, 'code'), 10));
    $username = sanitize(param($body, 'username'), 30);
    $password = param($body, 'password', '');

    if (!$code)                respond(false, ['message' => 'Oda kodu gerekli.'], 400);
    if (strlen($username) < 2) respond(false, ['message' => 'Kullanıcı adı en az 2 karakter olmalı.'], 400);

    $rooms = loadRooms();
    $room  = isset($rooms[$code]) ? $rooms[$code] : null;

    if (!$room)           respond(false, ['message' => 'Geçersiz oda kodu: ' . htmlspecialchars($code)], 404);
    if (!$room['active']) respond(false, ['message' => 'Bu oda artık aktif değil.'], 410);

    // Özel oda şifre kontrolü
    if ($room['type'] === 'private') {
        if (!$password) respond(false, ['message' => 'Bu oda şifreli, lütfen şifre girin.'], 403);
        if (!password_verify($password, $room['password'])) respond(false, ['message' => 'Yanlış şifre.'], 403);
    }

    if (!in_array($username, isset($room['members']) ? $room['members'] : [])) {
        $rooms[$code]['members'][] = $username;
        saveRooms($rooms);
    }

    respond(true, [
        'message' => 'Odaya katıldın!',
        'room'    => [
            'code'     => $room['code'],
            'name'     => $room['name'],
            'host'     => $room['host'],
            'platform' => $room['platform'],
            'type'     => $room['type'],
            'members'  => $rooms[$code]['members'],
        ],
        'session'  => ['username' => $username, 'role' => 'viewer'],
        'redirect' => 'watch/index.html?room=' . urlencode($code) . '&user=' . urlencode($username),
    ]);
}

// ── GET: Kod kontrol ───────────────────────────────────────────────
if ($action === 'check') {
    $code = strtoupper(sanitize(isset($_GET['code']) ? $_GET['code'] : '', 10));
    if (!$code) respond(false, ['message' => 'Kod belirtilmedi.'], 400);

    $room = getRoom($code);
    if (!$room)           respond(false, ['message' => 'Geçersiz oda kodu.'], 404);
    if (!$room['active']) respond(false, ['message' => 'Bu oda aktif değil.'], 410);

    respond(true, ['room' => [
        'code'     => $room['code'],
        'name'     => $room['name'],
        'host'     => $room['host'],
        'platform' => $room['platform'],
        'type'     => $room['type'],
        'members'  => isset($room['members']) ? $room['members'] : [],
    ]]);
}

// ── GET: Oda listesi ───────────────────────────────────────────────
if ($action === 'list') {
    $rooms = loadRooms();
    $list  = [];
    foreach ($rooms as $code => $room) {
        if ($room['active'] && $room['type'] === 'public') {
            $list[] = [
                'code'     => $code,
                'name'     => $room['name'],
                'host'     => $room['host'],
                'platform' => $room['platform'],
                'members'  => count(isset($room['members']) ? $room['members'] : []),
            ];
        }
    }
    respond(true, ['rooms' => $list, 'total' => count($list)]);
}

// ── POST: Üye ekle ─────────────────────────────────────────────────
if ($action === 'member_join') {
    $code     = strtoupper(sanitize(param($body, 'code'), 10));
    $username = sanitize(param($body, 'username'), 30);
    if (!$code || !$username) respond(false, ['message' => 'Eksik parametre.'], 400);

    $rooms = loadRooms();
    if (!isset($rooms[$code])) respond(false, ['message' => 'Oda bulunamadı.'], 404);

    if (!in_array($username, isset($rooms[$code]['members']) ? $rooms[$code]['members'] : [])) {
        $rooms[$code]['members'][] = $username;
        saveRooms($rooms);
    }
    respond(true, ['members' => $rooms[$code]['members'], 'host' => $rooms[$code]['host']]);
}

// ── POST: Üye çıkar ────────────────────────────────────────────────
if ($action === 'member_leave') {
    $code     = strtoupper(sanitize(param($body, 'code'), 10));
    $username = sanitize(param($body, 'username'), 30);
    if (!$code || !$username) respond(false, ['message' => 'Eksik parametre.'], 400);

    $rooms = loadRooms();
    if (!isset($rooms[$code])) respond(false, ['message' => 'Oda bulunamadı.'], 404);

    $members = isset($rooms[$code]['members']) ? $rooms[$code]['members'] : [];
    $rooms[$code]['members'] = array_values(array_filter($members, function($m) use ($username) {
        return $m !== $username;
    }));
    saveRooms($rooms);
    respond(true, ['members' => $rooms[$code]['members']]);
}

// ── GET: Üye listesi ───────────────────────────────────────────────
if ($action === 'members') {
    $code = strtoupper(sanitize(isset($_GET['code']) ? $_GET['code'] : '', 10));
    if (!$code) respond(false, ['message' => 'Kod gerekli.'], 400);

    $room = getRoom($code);
    if (!$room) respond(false, ['message' => 'Oda bulunamadı.'], 404);
    respond(true, ['members' => isset($room['members']) ? $room['members'] : [], 'host' => $room['host']]);
}

respond(false, ['message' => 'Geçersiz action.'], 400);

