<?php // index.php — Watch4Party ?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Watch4Party</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
:root {
  --bg: #09090f; --surface: #0f0f1a;
  --border: rgba(255,255,255,0.07); --border-hot: rgba(255,255,255,0.18);
  --accent: #7c6dfa; --accent2: #fa6d9b; --accent-glow: rgba(124,109,250,0.35);
  --text: #f0eeff; --muted: rgba(240,238,255,0.38);
  --radius: 14px;
}
html,body { min-height:100vh; background:var(--bg); font-family:'Outfit',sans-serif; color:var(--text); display:flex; align-items:center; justify-content:center; overflow-x:hidden; }
body::before { content:''; position:fixed; inset:0; z-index:0; pointer-events:none; background: radial-gradient(ellipse 60% 55% at 20% 10%, rgba(124,109,250,0.13) 0%, transparent 65%), radial-gradient(ellipse 50% 45% at 80% 90%, rgba(250,109,155,0.10) 0%, transparent 60%); }
body::after { content:''; position:fixed; inset:0; z-index:0; pointer-events:none; opacity:0.028; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 512 512' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='g'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23g)'/%3E%3C/svg%3E"); background-size:200px; }
.wrap { position:relative; z-index:1; width:100%; max-width:460px; padding:24px 16px 40px; animation:fadeUp .5s cubic-bezier(.22,1,.36,1) both; }
@keyframes fadeUp { from { opacity:0; transform:translateY(22px); } to { opacity:1; transform:translateY(0); } }
.logo { display:flex; align-items:center; gap:11px; justify-content:center; margin-bottom:40px; }
.logo-icon { width:36px; height:36px; background:linear-gradient(135deg,var(--accent),var(--accent2)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 24px var(--accent-glow); }
.logo-name { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; letter-spacing:-.3px; background:linear-gradient(110deg,#fff 40%,rgba(255,255,255,0.45)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.logo-name sub { font-size:10px; letter-spacing:.14em; font-family:'Outfit',sans-serif; font-weight:500; text-transform:uppercase; -webkit-text-fill-color:rgba(255,255,255,.3); vertical-align:middle; margin-left:3px; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:20px; overflow:hidden; box-shadow:0 32px 80px rgba(0,0,0,0.55),0 0 0 1px rgba(255,255,255,0.04) inset; }
.tabs { display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid var(--border); padding:6px 6px 0; gap:4px; }
.tab { padding:11px 0; background:none; border:none; font-family:'Outfit',sans-serif; font-size:13.5px; font-weight:500; color:var(--muted); cursor:pointer; border-radius:10px 10px 0 0; transition:color .2s; position:relative; letter-spacing:.01em; }
.tab::after { content:''; position:absolute; bottom:-1px; left:20%; right:20%; height:2px; background:linear-gradient(90deg,var(--accent),var(--accent2)); border-radius:2px; opacity:0; transition:opacity .2s; }
.tab.active { color:var(--text); }
.tab.active::after { opacity:1; }
.tab:hover:not(.active) { color:rgba(240,238,255,.7); }
.panel { padding:28px 24px 24px; display:none; }
.panel.active { display:block; }
.section-label { font-family:'Syne',sans-serif; font-size:19px; font-weight:700; margin-bottom:22px; background:linear-gradient(110deg,#fff,rgba(255,255,255,.6)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
.field { margin-bottom:14px; }
.flabel { display:block; font-size:11px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin-bottom:7px; }
.input { width:100%; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:10px; padding:11px 14px; color:var(--text); font-family:'Outfit',sans-serif; font-size:14px; outline:none; transition:border-color .18s,box-shadow .18s; -webkit-appearance:none; }
.input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(124,109,250,0.18); }
.input::placeholder { color:rgba(240,238,255,0.2); }
.input.shake { animation:shake .3s; }
@keyframes shake { 0%,100%{transform:translateX(0);} 25%{transform:translateX(-6px);} 75%{transform:translateX(6px);} }
.code-input { text-align:center; font-family:'Syne',sans-serif; font-size:26px; font-weight:700; letter-spacing:.28em; text-transform:uppercase; padding:14px; }
select.input { cursor:pointer; }
select.input option { background:#1a1a2e; }
.row { display:flex; gap:10px; }
.row .field { flex:1; }
.type-row { display:flex; gap:8px; margin-bottom:14px; }
.type-btn { flex:1; padding:10px 8px; background:rgba(255,255,255,0.03); border:1px solid var(--border); border-radius:10px; color:var(--muted); font-family:'Outfit',sans-serif; font-size:13px; font-weight:500; cursor:pointer; transition:all .18s; display:flex; align-items:center; justify-content:center; gap:6px; }
.type-btn.active { background:rgba(124,109,250,0.14); border-color:rgba(124,109,250,0.5); color:#c8bfff; }
.type-btn:hover:not(.active) { border-color:var(--border-hot); color:rgba(240,238,255,.7); }
.preview { margin:12px 0 16px; padding:14px 16px; background:rgba(124,109,250,0.07); border:1px solid rgba(124,109,250,0.22); border-radius:12px; display:none; animation:fadeIn .2s ease; }
.preview.show { display:block; }
@keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
.prev-name { font-family:'Syne',sans-serif; font-size:15px; font-weight:700; color:var(--text); margin-bottom:8px; }
.prev-tags { display:flex; flex-wrap:wrap; gap:6px; }
.ptag { font-size:11px; font-weight:500; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.09); border-radius:6px; padding:3px 8px; color:var(--muted); }
.ptag.lock { background:rgba(250,109,155,0.12); border-color:rgba(250,109,155,0.3); color:#fa9ec0; }
.msg { padding:10px 14px; border-radius:10px; font-size:13px; line-height:1.5; display:none; margin-top:12px; }
.msg.err { background:rgba(255,95,126,0.1); border:1px solid rgba(255,95,126,0.3); color:#ff9fb5; display:block; }
.msg.ok  { background:rgba(77,255,160,0.08); border:1px solid rgba(77,255,160,0.25); color:#6bffc0; display:block; }
.btn { width:100%; padding:13px; background:linear-gradient(135deg,var(--accent),#a06dfb); border:none; border-radius:11px; color:#fff; font-family:'Outfit',sans-serif; font-size:14px; font-weight:600; cursor:pointer; margin-top:16px; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity .18s,transform .12s,box-shadow .18s; box-shadow:0 4px 20px rgba(124,109,250,0.35); }
.btn:hover:not(:disabled) { opacity:.92; transform:translateY(-1px); box-shadow:0 8px 30px rgba(124,109,250,0.45); }
.btn:active:not(:disabled) { transform:translateY(0); }
.btn:disabled { opacity:.4; cursor:not-allowed; box-shadow:none; transform:none; }
.btn.loading .btn-text { display:none; }
.btn.loading .spin { display:block; }
@keyframes rot { to{transform:rotate(360deg);} }
.spin { display:none; width:16px; height:16px; border:2.5px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:rot .55s linear infinite; }
.overlay { position:fixed; inset:0; z-index:200; background:rgba(5,5,15,.88); display:flex; align-items:center; justify-content:center; padding:16px; opacity:0; pointer-events:none; transition:opacity .22s; backdrop-filter:blur(8px); }
.overlay.open { opacity:1; pointer-events:all; }
.modal { background:#13132a; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:32px 28px; width:100%; max-width:360px; transform:translateY(14px) scale(.97); transition:transform .25s cubic-bezier(.22,1,.36,1); box-shadow:0 40px 100px rgba(0,0,0,.7); }
.overlay.open .modal { transform:translateY(0) scale(1); }
.modal-icon { width:48px; height:48px; background:linear-gradient(135deg,rgba(250,109,155,0.2),rgba(124,109,250,0.2)); border:1px solid rgba(250,109,155,0.3); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:16px; }
.modal-title { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; margin-bottom:4px; }
.modal-sub { font-size:13px; color:var(--muted); margin-bottom:22px; line-height:1.55; }
.modal-sub strong { color:rgba(240,238,255,.75); font-weight:500; }
.modal-cancel { width:100%; margin-top:10px; padding:11px; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:10px; color:var(--muted); font-family:'Outfit',sans-serif; font-size:13px; cursor:pointer; transition:border-color .18s,color .18s; }
.modal-cancel:hover { border-color:var(--border-hot); color:var(--text); }
.foot { text-align:center; margin-top:22px; font-size:11px; color:rgba(240,238,255,0.18); letter-spacing:.04em; }
</style>
</head>
<body>

<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-icon">🔐</div>
    <div class="modal-title">Şifreli Oda</div>
    <div class="modal-sub">Odaya girmek için şifreyi gir:<br><strong id="modal-room-name"></strong></div>
    <div class="field">
      <label class="flabel">Şifre</label>
      <input type="password" class="input" id="pw-input" placeholder="••••••••" maxlength="50">
    </div>
    <div class="msg" id="pw-msg"></div>
    <button class="btn" id="pw-btn" onclick="submitPw()">
      <span class="spin"></span>
      <span class="btn-text">Giriş Yap &rarr;</span>
    </button>
    <button class="modal-cancel" onclick="closeModal()">İptal</button>
  </div>
</div>

<div class="wrap">
  <div class="logo">
    <div class="logo-icon">🎬</div>
    <div class="logo-name">Watch4Party <sub>beta</sub></div>
  </div>

  <div class="card">
    <div class="tabs">
      <button class="tab active" id="tab-join" onclick="switchTab('join')">&#9654; Odaya Gir</button>
      <button class="tab" id="tab-create" onclick="switchTab('create')">&#xFF0B; Oda Oluştur</button>
    </div>

    <div class="panel active" id="panel-join">
      <div class="section-label">Odaya Katıl</div>
      <div class="field">
        <label class="flabel">Oda Kodu</label>
        <input type="text" class="input code-input" id="join-code"
          placeholder="ABC123" maxlength="6" autocomplete="off" spellcheck="false"
          oninput="this.value=this.value.toUpperCase(); onCodeInput()">
      </div>
      <div class="preview" id="preview">
        <div class="prev-name" id="prev-name"></div>
        <div class="prev-tags" id="prev-tags"></div>
      </div>
      <div class="field">
        <label class="flabel">Kullanıcı Adı</label>
        <input type="text" class="input" id="join-user" placeholder="Adın" maxlength="30" autocomplete="off">
      </div>
      <div class="msg" id="join-msg"></div>
      <button class="btn" id="join-btn" onclick="doJoin()">
        <span class="spin"></span>
        <span class="btn-text">Odaya Gir &rarr;</span>
      </button>
    </div>

    <div class="panel" id="panel-create">
      <div class="section-label">Yeni Oda Aç</div>
      <div class="field">
        <label class="flabel">Oda Adı</label>
        <input type="text" class="input" id="c-name" placeholder="Cuma Gecesi Sinema" maxlength="60">
      </div>
      <div class="row">
        <div class="field">
          <label class="flabel">Kullanıcı Adı</label>
          <input type="text" class="input" id="c-user" placeholder="Adın" maxlength="30">
        </div>
        <div class="field">
          <label class="flabel">Platform</label>
          <select class="input" id="c-platform">
            <option>YouTube</option><option>Netflix</option><option>Disney+</option>
            <option>Prime Video</option><option>Twitch</option><option>Diğer</option>
          </select>
        </div>
      </div>
      <label class="flabel" style="margin-bottom:8px">Oda Türü</label>
      <div class="type-row">
        <button class="type-btn active" data-t="public" onclick="setType('public')">🌐 Herkese Açık</button>
        <button class="type-btn" data-t="private" onclick="setType('private')">🔒 Şifreli</button>
      </div>
      <div class="field" id="pw-field" style="display:none">
        <label class="flabel">Şifre</label>
        <input type="password" class="input" id="c-pass" placeholder="Oda Şifresi..." maxlength="50">
      </div>
      <div class="msg" id="create-msg"></div>
      <button class="btn" id="create-btn" onclick="doCreate()">
        <span class="spin"></span>
        <span class="btn-text">Odayı Oluştur &rarr;</span>
      </button>
    </div>
  </div>

  <div class="foot">Watch4Party &copy; 2025 &nbsp;&middot;&nbsp; Birlikte izle</div>
</div>

<script>
var API='rooms.php',currentRoom=null,selectedType='public',pwCallback=null;

function api(action,code,extra){
  return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify(Object.assign({action},code?{code}:{},extra||{}))}).then(r=>r.json());
}
function hashPass(p){return btoa(unescape(encodeURIComponent('w4p_'+p)));}
function genCode(){var c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';return Array.from({length:6},()=>c[Math.floor(Math.random()*c.length)]).join('');}

function switchTab(t){
  ['join','create'].forEach(x=>{
    document.getElementById('tab-'+x).classList.toggle('active',x===t);
    document.getElementById('panel-'+x).classList.toggle('active',x===t);
  });
}

function setType(t){
  selectedType=t;
  document.querySelectorAll('.type-btn').forEach(b=>b.classList.toggle('active',b.dataset.t===t));
  document.getElementById('pw-field').style.display=t==='private'?'block':'none';
}

var codeTimer;
function onCodeInput(){
  var code=document.getElementById('join-code').value.trim();
  var prev=document.getElementById('preview');
  var msg=document.getElementById('join-msg');
  msg.className='msg'; currentRoom=null; prev.classList.remove('show');
  clearTimeout(codeTimer);
  if(code.length!==6)return;
  codeTimer=setTimeout(()=>{
    api('get',code).then(room=>{
      if(room&&room.active){
        currentRoom=room;
        document.getElementById('prev-name').textContent=room.name;
        var mc=Object.keys(room.members||{}).length;
        document.getElementById('prev-tags').innerHTML=
          '<span class="ptag">🎬 '+room.platform+'</span>'+
          '<span class="ptag">👤 '+room.host+'</span>'+
          '<span class="ptag">👥 '+mc+' kişi</span>'+
          (room.type==='private'?'<span class="ptag lock">🔒 Şifreli</span>':'');
        prev.classList.add('show');
      }else{showMsg('join-msg','err','✗ Geçersiz oda kodu.');}
    }).catch(()=>showMsg('join-msg','err','✗ Sunucuya bağlanılamadı.'));
  },380);
}

function doJoin(){
  var code=document.getElementById('join-code').value.trim().toUpperCase();
  var user=document.getElementById('join-user').value.trim();
  var btn=document.getElementById('join-btn');
  if(code.length<4){shake('join-code');return;}
  if(user.length<2){shake('join-user');showMsg('join-msg','err','Kullanıcı adı en az 2 karakter.');return;}
  var proceed=room=>{room.type==='private'?openModal(code,user,room):finalJoin(code,user,room);};
  if(currentRoom&&currentRoom.code===code){proceed(currentRoom);return;}
  setLoad(btn,true);
  api('get',code).then(room=>{
    setLoad(btn,false);
    if(!room||!room.active){showMsg('join-msg','err','✗ Oda bulunamadı.');return;}
    currentRoom=room; proceed(room);
  }).catch(()=>{setLoad(btn,false);showMsg('join-msg','err','✗ Sunucu hatası.');});
}

function finalJoin(code,user,room){
  var btn=document.getElementById('join-btn');
  setLoad(btn,true);
  var members=Object.assign({},room.members||{});
  members[user]=true;
  api('patch',code,{members}).then(()=>{
    setLoad(btn,false);
    showMsg('join-msg','ok','✓ Odaya katılıyorsun...');
    try{sessionStorage.setItem('w4p_room',JSON.stringify({code,name:room.name,host:room.host,platform:room.platform,username:user,role:'viewer'}));}catch(_){}
    setTimeout(()=>{window.location.href='watch/index.html?room='+encodeURIComponent(code)+'&user='+encodeURIComponent(user);},700);
  }).catch(()=>{setLoad(btn,false);showMsg('join-msg','err','✗ Sunucu hatası.');});
}

function doCreate(){
  var name=document.getElementById('c-name').value.trim();
  var user=document.getElementById('c-user').value.trim();
  var plat=document.getElementById('c-platform').value;
  var pass=document.getElementById('c-pass').value;
  var btn=document.getElementById('create-btn');
  if(name.length<3){shake('c-name');showMsg('create-msg','err','Oda adı en az 3 karakter.');return;}
  if(user.length<2){shake('c-user');showMsg('create-msg','err','Kullanıcı adı en az 2 karakter.');return;}
  if(selectedType==='private'&&pass.length<3){shake('c-pass');showMsg('create-msg','err','Şifre en az 3 karakter.');return;}
  setLoad(btn,true);
  document.getElementById('create-msg').className='msg';
  var code=genCode();
  api('get',code).then(ex=>{
    if(ex&&ex.active)code=genCode();
    return api('set',code,{name,host:user,platform:plat,type:selectedType,password:selectedType==='private'?hashPass(pass):null});
  }).then(res=>{
    setLoad(btn,false);
    if(res&&res.error){showMsg('create-msg','err','✗ '+res.error);return;}
    showMsg('create-msg','ok','✓ Oda açıldı! Kod: '+code);
    try{sessionStorage.setItem('w4p_room',JSON.stringify({code,name,host:user,platform:plat,username:user,role:'host'}));}catch(_){}
    setTimeout(()=>{window.location.href='watch/index.html?room='+encodeURIComponent(code)+'&user='+encodeURIComponent(user)+'&host=1';},800);
  }).catch(()=>{setLoad(btn,false);showMsg('create-msg','err','✗ Sunucu hatası.');});
}

function openModal(code,user,room){
  document.getElementById('modal-room-name').textContent=room.name;
  document.getElementById('pw-input').value='';
  document.getElementById('pw-msg').className='msg';
  document.getElementById('overlay').classList.add('open');
  setTimeout(()=>document.getElementById('pw-input').focus(),150);
  pwCallback=hashed=>{
    var btn=document.getElementById('pw-btn');
    setLoad(btn,true);
    api('check_password',code,{password:hashed}).then(res=>{
      setLoad(btn,false);
      if(res&&res.ok){closeModal();finalJoin(code,user,room);}
      else{showMsg('pw-msg','err','✗ '+(res.error||'Yanlış şifre.'));shake('pw-input');}
    }).catch(()=>{setLoad(btn,false);showMsg('pw-msg','err','Sunucu hatası.');});
  };
}

function submitPw(){var pw=document.getElementById('pw-input').value;if(!pw){shake('pw-input');return;}if(pwCallback)pwCallback(hashPass(pw));}
function closeModal(){document.getElementById('overlay').classList.remove('open');pwCallback=null;}
function showMsg(id,type,text){var el=document.getElementById(id);el.textContent=text;el.className='msg '+type;}
function setLoad(btn,v){btn.disabled=v;btn.classList.toggle('loading',v);}
function shake(id){var el=document.getElementById(id);el.classList.remove('shake');void el.offsetWidth;el.classList.add('shake');el.addEventListener('animationend',()=>el.classList.remove('shake'),{once:true});}

document.addEventListener('keydown',e=>{
  if(e.key!=='Enter')return;
  if(document.getElementById('overlay').classList.contains('open')){submitPw();return;}
  var p=document.querySelector('.panel.active').id;
  if(p==='panel-join')doJoin();
  if(p==='panel-create')doCreate();
});
document.getElementById('overlay').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal();});
</script>
</body>
</html>

