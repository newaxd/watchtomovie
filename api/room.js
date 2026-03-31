import { kv } from '@vercel/kv';

function respond(res, ok, data = {}, status = 200) {
  res.status(status).json({ ok, ...data });
}

function sanitize(str, max = 100) {
  if (typeof str !== 'string') return '';
  return str.replace(/<[^>]*>/g, '').trim().slice(0, max);
}

function generateCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let code = '';
  for (let i = 0; i < 6; i++) code += chars[Math.floor(Math.random() * chars.length)];
  return code;
}

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.status(204).end();

  const action = (req.query.action || req.body?.action || '').toLowerCase();
  const body = typeof req.body === 'object' ? req.body : {};

  // CREATE
  if (action === 'create') {
    const roomname = sanitize(body.roomname || '', 60);
    const username = sanitize(body.username || '', 30);
    const platform = sanitize(body.platform || 'YouTube', 30);
    const type = ['public', 'private'].includes(body.type) ? body.type : 'public';
    const password = sanitize(body.password || '', 50);

    if (roomname.length < 3) return respond(res, false, { message: 'Oda adı en az 3 karakter olmalı.' }, 400);
    if (username.length < 2) return respond(res, false, { message: 'Kullanıcı adı en az 2 karakter olmalı.' }, 400);
    if (type === 'private' && password.length < 3) return respond(res, false, { message: 'Özel oda için şifre gerekli (min. 3 karakter).' }, 400);

    let code;
    do { code = generateCode(); } while (await kv.get(`room:${code}`));

    const room = {
      code, name: roomname, host: username, platform, type,
      password: type === 'private' ? password : null,
      active: true,
      created_at: new Date().toISOString(),
      members: [username],
    };

    await kv.set(`room:${code}`, room, { ex: 60 * 60 * 24 * 7 });

    return respond(res, true, {
      message: 'Oda oluşturuldu!',
      code,
      room: { code, name: roomname, host: username, platform, type },
      session: { username, role: 'host' },
      redirect: `watch/index.html?room=${encodeURIComponent(code)}&user=${encodeURIComponent(username)}&host=1`,
    }, 201);
  }

  // JOIN
  if (action === 'join') {
    const code = sanitize(body.code || '', 10).toUpperCase();
    const username = sanitize(body.username || '', 30);

    if (!code) return respond(res, false, { message: 'Room code required.' }, 400);
    if (username.length < 2) return respond(res, false, { message: 'Username must be at least 2 characters.' }, 400);

    const room = await kv.get(`room:${code}`);
    if (!room) return respond(res, false, { message: `Invalid room code: ${code}` }, 404);
    if (!room.active) return respond(res, false, { message: 'This room is no longer active.' }, 410);

    if (!room.members.includes(username)) {
      room.members.push(username);
      await kv.set(`room:${code}`, room, { ex: 60 * 60 * 24 * 7 });
    }

    return respond(res, true, {
      message: 'Odaya katıldın!',
      room: { code: room.code, name: room.name, host: room.host, platform: room.platform, type: room.type, members: room.members },
      session: { username, role: 'viewer' },
      redirect: `watch/index.html?room=${encodeURIComponent(code)}&user=${encodeURIComponent(username)}`,
    });
  }

  // CHECK
  if (action === 'check') {
    const code = sanitize(req.query.code || '', 10).toUpperCase();
    if (!code) return respond(res, false, { message: 'Code required.' }, 400);

    const room = await kv.get(`room:${code}`);
    if (!room) return respond(res, false, { message: 'Invalid room code.' }, 404);
    if (!room.active) return respond(res, false, { message: 'Room is inactive.' }, 410);

    return respond(res, true, {
      room: { code: room.code, name: room.name, host: room.host, platform: room.platform, type: room.type, members: room.members },
    });
  }

  // LIST
  if (action === 'list') {
    const keys = await kv.keys('room:*');
    const rooms = await Promise.all(keys.map(k => kv.get(k)));
    const list = rooms
      .filter(r => r && r.active && r.type === 'public')
      .map(r => ({ code: r.code, name: r.name, host: r.host, platform: r.platform, members: r.members.length }));
    return respond(res, true, { rooms: list, total: list.length });
  }

  // MEMBER_JOIN
  if (action === 'member_join') {
    const code = sanitize(body.code || '', 10).toUpperCase();
    const username = sanitize(body.username || '', 30);
    if (!code || !username) return respond(res, false, { message: 'Missing parameter.' }, 400);

    const room = await kv.get(`room:${code}`);
    if (!room) return respond(res, false, { message: 'Room not found.' }, 404);

    if (!room.members.includes(username)) {
      room.members.push(username);
      await kv.set(`room:${code}`, room, { ex: 60 * 60 * 24 * 7 });
    }
    return respond(res, true, { members: room.members, host: room.host });
  }

  // MEMBER_LEAVE
  if (action === 'member_leave') {
    const code = sanitize(body.code || '', 10).toUpperCase();
    const username = sanitize(body.username || '', 30);
    if (!code || !username) return respond(res, false, { message: 'Missing parameter.' }, 400);

    const room = await kv.get(`room:${code}`);
    if (!room) return respond(res, false, { message: 'Room not found.' }, 404);

    room.members = room.members.filter(m => m !== username);
    await kv.set(`room:${code}`, room, { ex: 60 * 60 * 24 * 7 });
    return respond(res, true, { members: room.members });
  }

  // MEMBERS
  if (action === 'members') {
    const code = sanitize(req.query.code || '', 10).toUpperCase();
    if (!code) return respond(res, false, { message: 'Code required.' }, 400);

    const room = await kv.get(`room:${code}`);
    if (!room) return respond(res, false, { message: 'Room not found.' }, 404);
    return respond(res, true, { members: room.members, host: room.host });
  }

  return respond(res, false, { message: 'Invalid action.' }, 400);
}
