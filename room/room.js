/**
 * Watch4Party — Room API (Vercel Serverless Function)
 * Node.js — rooms JSONBlob'da saklanır (ücretsiz, kalıcı)
 *
 * İlk kurulumda: JSONBLOB_ID env değişkenini Vercel'e ekleyin.
 * Nasıl alınır: https://jsonblob.com/api/jsonBlob ile POST atın,
 * dönen Location header'dan ID'yi alın (otomatik yapılıyor ilk istekte).
 */

const BLOB_BASE = 'https://jsonblob.com/api/jsonBlob';

// ---------- Yardımcı: JSONBlob'dan oda verisini yükle ----------
async function loadRooms() {
  const id = process.env.JSONBLOB_ID;
  if (!id) return {};
  try {
    const res = await fetch(`${BLOB_BASE}/${id}`);
    if (!res.ok) return {};
    return await res.json();
  } catch {
    return {};
  }
}

// ---------- Yardımcı: JSONBlob'a kaydet ----------
async function saveRooms(rooms) {
  const id = process.env.JSONBLOB_ID;
  if (!id) throw new Error('JSONBLOB_ID env değişkeni tanımlı değil.');
  const res = await fetch(`${BLOB_BASE}/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(rooms),
  });
  if (!res.ok) throw new Error('JSONBlob kayıt hatası: ' + res.status);
}

// ---------- Temizleyici ----------
function sanitize(str, max = 100) {
  if (typeof str !== 'string') return '';
  return str.replace(/<[^>]*>/g, '').trim().slice(0, max);
}

// ---------- Kod üretici ----------
function generateCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let code = '';
  for (let i = 0; i < 6; i++) {
    code += chars[Math.floor(Math.random() * chars.length)];
  }
  return code;
}

// ---------- Cevap helper ----------
function respond(res, ok, data = {}, status = 200) {
  res.status(status).json({ ok, ...data });
}

// ================================================================
// Ana handler
// ================================================================
export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') return res.status(204).end();

  const action =
    (req.query.action || req.body?.action || '').toLowerCase();

  // Body: JSON veya form
  const body = typeof req.body === 'object' ? req.body : {};

  // ── CREATE ────────────────────────────────────────────────────
  if (action === 'create') {
    const roomname = sanitize(body.roomname || '', 60);
    const username = sanitize(body.username || '', 30);
    const platform = sanitize(body.platform || 'YouTube', 30);
    const type = ['public', 'private'].includes(body.type) ? body.type : 'public';
    const password = sanitize(body.password || '', 50);

    if (roomname.length < 3)
      return respond(res, false, { message: 'Oda adı en az 3 karakter olmalı.' }, 400);
    if (username.length < 2)
      return respond(res, false, { message: 'Kullanıcı adı en az 2 karakter olmalı.' }, 400);
    if (type === 'private' && password.length < 3)
      return respond(res, false, { message: 'Özel oda için şifre gerekli (min. 3 karakter).' }, 400);

    const rooms = await loadRooms();

    let code;
    do { code = generateCode(); } while (rooms[code]);

    rooms[code] = {
      code,
      name: roomname,
      host: username,
      platform,
      type,
      // Şifreyi düz metin saklıyoruz (bcrypt Vercel Edge'de sorun çıkarır)
      // Güvenlik için ileride Edge-compatible hash eklenebilir
      password: type === 'private' ? password : null,
      active: true,
      created_at: new Date().toISOString(),
      members: [username],
    };

    await saveRooms(rooms);

    return respond(res, true, {
      message: 'Oda oluşturuldu!',
      code,
      room: { code, name: roomname, host: username, platform, type },
      session: { username, role: 'host' },
      redirect: `watch/index.html?room=${encodeURIComponent(code)}&user=${encodeURIComponent(username)}&host=1`,
    }, 201);
  }

  // ── JOIN ──────────────────────────────────────────────────────
  if (action === 'join') {
    const code = sanitize(body.code || '', 10).toUpperCase();
    const username = sanitize(body.username || '', 30);

    if (!code) return respond(res, false, { message: 'Room code required.' }, 400);
    if (username.length < 2)
      return respond(res, false, { message: 'The username must be at least two characters long.' }, 400);

    const rooms = await loadRooms();
    const room = rooms[code];

    if (!room) return respond(res, false, { message: `Invalid room code: ${code}` }, 404);
    if (!room.active) return respond(res, false, { message: 'This room is no longer active.' }, 410);

    if (!room.members.includes(username)) {
      rooms[code].members.push(username);
      await saveRooms(rooms);
    }

    return respond(res, true, {
      message: 'Odaya katıldın!',
      room: {
        code: room.code,
        name: room.name,
        host: room.host,
        platform: room.platform,
        type: room.type,
        members: rooms[code].members,
      },
      session: { username, role: 'viewer' },
      redirect: `watch/index.html?room=${encodeURIComponent(code)}&user=${encodeURIComponent(username)}`,
    });
  }

  // ── CHECK ─────────────────────────────────────────────────────
  if (action === 'check') {
    const code = sanitize(req.query.code || '', 10).toUpperCase();
    if (!code) return respond(res, false, { message: 'Code not specified.' }, 400);

    const rooms = await loadRooms();
    const room = rooms[code];

    if (!room) return respond(res, false, { message: 'Invalid room code.' }, 404);
    if (!room.active) return respond(res, false, { message: 'This room is inactive.' }, 410);

    return respond(res, true, {
      room: {
        code: room.code,
        name: room.name,
        host: room.host,
        platform: room.platform,
        type: room.type,
        members: room.members || [],
      },
    });
  }

  // ── LIST ──────────────────────────────────────────────────────
  if (action === 'list') {
    const rooms = await loadRooms();
    const list = Object.values(rooms)
      .filter(r => r.active && r.type === 'public')
      .map(r => ({
        code: r.code,
        name: r.name,
        host: r.host,
        platform: r.platform,
        members: (r.members || []).length,
      }));
    return respond(res, true, { rooms: list, total: list.length });
  }

  // ── MEMBER_JOIN ───────────────────────────────────────────────
  if (action === 'member_join') {
    const code = sanitize(body.code || '', 10).toUpperCase();
    const username = sanitize(body.username || '', 30);
    if (!code || !username) return respond(res, false, { message: 'Missing parameter.' }, 400);

    const rooms = await loadRooms();
    if (!rooms[code]) return respond(res, false, { message: 'Room not found.' }, 404);

    if (!rooms[code].members.includes(username)) {
      rooms[code].members.push(username);
      await saveRooms(rooms);
    }
    return respond(res, true, { members: rooms[code].members, host: rooms[code].host });
  }

  // ── MEMBER_LEAVE ──────────────────────────────────────────────
  if (action === 'member_leave') {
    const code = sanitize(body.code || '', 10).toUpperCase();
    const username = sanitize(body.username || '', 30);
    if (!code || !username) return respond(res, false, { message: 'Missing parameter.' }, 400);

    const rooms = await loadRooms();
    if (!rooms[code]) return respond(res, false, { message: 'Room not found.' }, 404);

    rooms[code].members = (rooms[code].members || []).filter(m => m !== username);
    await saveRooms(rooms);
    return respond(res, true, { members: rooms[code].members });
  }

  // ── MEMBERS ───────────────────────────────────────────────────
  if (action === 'members') {
    const code = sanitize(req.query.code || '', 10).toUpperCase();
    if (!code) return respond(res, false, { message: 'Code required.' }, 400);

    const rooms = await loadRooms();
    const room = rooms[code];
    if (!room) return respond(res, false, { message: 'Room not found.' }, 404);
    return respond(res, true, { members: room.members || [], host: room.host });
  }

  return respond(res, false, { message: 'Invalid action.' }, 400);
}
