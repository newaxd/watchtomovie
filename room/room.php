<?php
/**
 * Watch4Party — Room Backend
 * PHP 7.4+
 * Odalar rooms.txt dosyasına JSON olarak kaydedilir.
 *
 * Endpoint'ler:
 *   POST ?action=create       — Oda oluştur (random kod üretir, rooms.txt'e kaydeder)
 *   POST ?action=join         — Odaya katıl (rooms.txt'den kodu doğrular)
 *   GET  ?action=check&code=  — Kod geçerli mi?
 *   GET  ?action=list         — Public odaları listele
 *   POST ?action=member_join  — Üye ekle
 *   POST ?action=member_leave — Üye çıkar
 *   GET  ?action=members&code=— Üye listesi al
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('ROOMS_FILE', __DIR__ . '/rooms.txt');

function respond(bool $ok, array $data = [], int $http = 200): void {
    http_response_code($http);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sanitize(string $str, int $max = 100): string {
    return mb_substr(trim(strip_tags($str)), 0, $max);
}

function generateCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function loadRooms(): array {
    if (!file_exists(ROOMS_FILE)) return [];
    $content = @file_get_contents(ROOMS_FILE);
    if (!$content) return [];
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveRooms(array $rooms): void {
    file_put_contents(ROOMS_FILE, json_encode($rooms, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function getRoom(string $code): ?array {
    $rooms = loadRooms();
    return $rooms[strtoupper(trim($code))] ?? null;
}

$action = strtolower($_GET['action'] ?? $_POST['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST: Oda oluştur ──────────────────────────────────────────────
if ($action === 'create') {
    $roomname = sanitize($body['roomname'] ?? $_POST['roomname'] ?? '', 60);
    $username = sanitize($body['username'] ?? $_POST['username'] ?? '', 30);
    $platform = sanitize($body['platform'] ?? $_POST['platform'] ?? 'YouTube', 30);
    $type     = in_array($body['type'] ?? $_POST['type'] ?? '', ['public','private'])
                ? ($body['type'] ?? $_POST['type']) : 'public';
    $password = sanitize($body['password'] ?? $_POST['password'] ?? '', 50);

    if (strlen($roomname) < 3) respond(false, ['message' => 'Oda adı en az 3 karakter olmalı.'], 400);
    if (strlen($username) < 2) respond(false, ['message' => 'Kullanıcı adı en az 2 karakter olmalı.'], 400);
    if ($type === 'private' && strlen($password) < 3)
        respond(false, ['message' => 'Özel oda için şifre gerekli (min. 3 karakter).'], 400);

    $rooms = loadRooms();
    do { $code = generateCode(); } while (isset($rooms[$code]));

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
        'message'  => 'Oda oluşturuldu!',
        'code'     => $code,
        'room'     => [
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
    $code     = strtoupper(sanitize($body['code'] ?? $_POST['code'] ?? '', 10));
    $username = sanitize($body['username'] ?? $_POST['username'] ?? '', 30);

    if (!$code)                respond(false, ['message' => 'Oda kodu gerekli.'], 400);
    if (strlen($username) < 2) respond(false, ['message' => 'Kullanıcı adı en az 2 karakter olmalı.'], 400);

    $rooms = loadRooms();
    $room  = $rooms[$code] ?? null;

    if (!$room)           respond(false, ['message' => 'Geçersiz oda kodu: ' . htmlspecialchars($code)], 404);
    if (!$room['active']) respond(false, ['message' => 'Bu oda artık aktif değil.'], 410);

    if (!in_array($username, $room['members'] ?? [])) {
        $rooms[$code]['members'][] = $username;
        saveRooms($rooms);
    }

    respond(true, [
        'message'  => 'Odaya katıldın!',
        'room'     => [
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
    $code = strtoupper(sanitize($_GET['code'] ?? '', 10));
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
        'members'  => $room['members'] ?? [],
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
                'members'  => count($room['members'] ?? []),
            ];
        }
    }
    respond(true, ['rooms' => $list, 'total' => count($list)]);
}

// ── POST: Üye ekle ─────────────────────────────────────────────────
if ($action === 'member_join') {
    $code     = strtoupper(sanitize($body['code'] ?? '', 10));
    $username = sanitize($body['username'] ?? '', 30);
    if (!$code || !$username) respond(false, ['message' => 'Eksik parametre.'], 400);

    $rooms = loadRooms();
    if (!isset($rooms[$code])) respond(false, ['message' => 'Oda bulunamadı.'], 404);

    if (!in_array($username, $rooms[$code]['members'] ?? [])) {
        $rooms[$code]['members'][] = $username;
        saveRooms($rooms);
    }
    respond(true, ['members' => $rooms[$code]['members'], 'host' => $rooms[$code]['host']]);
}

// ── POST: Üye çıkar ────────────────────────────────────────────────
if ($action === 'member_leave') {
    $code     = strtoupper(sanitize($body['code'] ?? '', 10));
    $username = sanitize($body['username'] ?? '', 30);
    if (!$code || !$username) respond(false, ['message' => 'Eksik parametre.'], 400);

    $rooms = loadRooms();
    if (!isset($rooms[$code])) respond(false, ['message' => 'Oda bulunamadı.'], 404);

    $rooms[$code]['members'] = array_values(array_filter(
        $rooms[$code]['members'] ?? [],
        fn($m) => $m !== $username
    ));
    saveRooms($rooms);
    respond(true, ['members' => $rooms[$code]['members']]);
}

// ── GET: Üye listesi ───────────────────────────────────────────────
if ($action === 'members') {
    $code = strtoupper(sanitize($_GET['code'] ?? '', 10));
    if (!$code) respond(false, ['message' => 'Kod gerekli.'], 400);

    $room = getRoom($code);
    if (!$room) respond(false, ['message' => 'Oda bulunamadı.'], 404);
    respond(true, ['members' => $room['members'] ?? [], 'host' => $room['host']]);
}

respond(false, ['message' => 'Geçersiz action.'], 400);
