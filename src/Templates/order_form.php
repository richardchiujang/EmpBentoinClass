<?php
// Guard: must be logged in
$user = getCurrentUser();
if (!$user) {
    header('Location: /login');
    exit;
}
$role        = $user['role'];
$displayName = htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8');
$username    = htmlspecialchars($user['username'],     ENT_QUOTES, 'UTF-8');

// Tabs visible per role
$canApply    = in_array($role, ['staff','manager','admin']);
$canApprove  = in_array($role, ['manager','restaurant','finance','admin']);
$canReport   = in_array($role, ['finance','admin']);
?>
<!doctype html>
<html lang="zh-TW">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>同心餐點費用申請系統</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Microsoft JhengHei",sans-serif;background:#f0f4f8;color:#333;font-size:.92rem}
    header{background:#1a4a72;color:#fff;padding:10px 24px;display:flex;align-items:center;gap:16px}
    header h1{font-size:1.08rem;flex:1}
    .user-info{font-size:.82rem;color:#cce}
    .logout-btn{background:#c0392b;color:#fff;border:none;padding:5px 14px;border-radius:4px;cursor:pointer;font-size:.8rem}
    nav{background:#2c3e50;display:flex;gap:2px;padding:0 16px}
    nav button{background:transparent;color:#aac;border:none;padding:10px 18px;cursor:pointer;font-size:.88rem;border-bottom:3px solid transparent}
    nav button:hover{color:#fff}
    nav button.active{color:#fff;border-bottom-color:#3498db}
    .tab{display:none}.tab.active{display:block}
    .container{max-width:960px;margin:24px auto;padding:0 14px}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:22px;margin-bottom:20px}
    .card h2{font-size:.96rem;margin-bottom:14px;border-bottom:2px solid #1a4a72;padding-bottom:6px;color:#1a4a72}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
    .field{margin-bottom:10px}
    label{display:block;font-size:.8rem;margin-bottom:3px;color:#555}
    input,select,textarea{width:100%;padding:7px 9px;border:1px solid #ccc;border-radius:4px;font-size:.88rem;font-family:inherit}
    textarea{resize:vertical;min-height:52px}
    table{width:100%;border-collapse:collapse;font-size:.84rem}
    th{background:#eef2f7;padding:7px 8px;text-align:left;border:1px solid #ddd;white-space:nowrap}
    td{padding:5px 6px;border:1px solid #eee;vertical-align:middle}
    td input,td select{border:none;background:transparent;padding:2px 4px;font-size:.84rem}
    .btn{padding:8px 20px;border:none;border-radius:4px;cursor:pointer;font-size:.88rem;font-family:inherit}
    .btn-primary{background:#1a4a72;color:#fff}.btn-primary:hover{background:#133658}
    .btn-success{background:#28a745;color:#fff}
    .btn-danger{background:#dc3545;color:#fff;padding:4px 10px;font-size:.8rem}
    .btn-warning{background:#e6850e;color:#fff}
    .btn-sm{padding:5px 12px;font-size:.8rem}
    .actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
    .badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:.75rem;font-weight:600}
    .msg{padding:10px 14px;border-radius:4px;margin-bottom:10px;font-size:.85rem}
    .msg-ok{background:#d4edda;color:#155724}
    .msg-err{background:#f8d7da;color:#721c24}
    #spinner{color:#888;font-size:.85rem;margin:6px 0;display:none}
    .detail-row-custom{display:none}
  </style>
</head>
<body>

<header>
  <h1>同心餐點費用申請系統</h1>
  <span class="user-info"><?= $displayName ?> (<?= $username ?> / <?= $role ?>)</span>
  <form method="POST" action="/logout" style="margin:0">
    <button type="submit" class="logout-btn">登出</button>
  </form>
</header>

<nav>
  <?php if ($canApply): ?>
  <button class="active" onclick="showTab('tab-apply',this)">申請表單</button>
  <?php endif; ?>
  <button <?= $canApply ? '' : 'class="active"' ?> onclick="showTab('tab-list',this)">申請查詢</button>
  <?php if ($canApprove): ?>
  <button onclick="showTab('tab-approve',this)">簽核作業</button>
  <?php endif; ?>
  <?php if ($canReport): ?>
  <button onclick="showTab('tab-report',this)">報表</button>
  <?php endif; ?>
</nav>

<!-- ═══════════════════════════════════════ 申請表單 ═══════════════════════ -->
<?php if ($canApply): ?>
<div id="tab-apply" class="tab active">
<div class="container">
  <div class="card">
    <h2>訂餐費用申請單</h2>
    <div id="apply-msg"></div>
    <form id="applyForm" onsubmit="submitRequest(event)">
      <div class="grid-3" style="margin-bottom:10px">
        <div class="field">
          <label>用餐日期</label>
          <input name="meal_date" type="date" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="field">
          <label>用餐時間</label>
          <input name="meal_time" type="time" value="12:00" required>
        </div>
        <div class="field">
          <label>餐別</label>
          <select name="meal_type">
            <option>早餐</option><option selected>午餐</option>
            <option>下午茶</option><option>晚餐</option>
          </select>
        </div>
      </div>
      <div class="grid-2" style="margin-bottom:10px">
        <div class="field">
          <label>用餐地點</label>
          <input name="location" placeholder="如：C304電腦教室" required>
        </div>
        <div class="field">
          <label>單位</label>
          <select name="unit_id" id="sel-unit" required></select>
        </div>
      </div>
      <div class="field" style="margin-bottom:12px">
        <label>事由</label>
        <textarea name="purpose" placeholder="請填寫本次用餐事由" required></textarea>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label>備註</label>
        <textarea name="notes" placeholder="選填"></textarea>
      </div>

      <h3 style="font-size:.92rem;color:#1a4a72;margin-bottom:8px">訂餐明細</h3>
      <table>
        <thead><tr>
          <th>類別</th><th>餐點</th><th>份數</th><th>單價(元)</th><th>小計</th><th></th>
        </tr></thead>
        <tbody id="applyRows"></tbody>
      </table>
      <button type="button" class="btn btn-sm" style="margin-top:8px;background:#e8f0fe;color:#1a4a72" onclick="addApplyRow()">＋ 新增明細</button>
      <div style="text-align:right;margin-top:6px;font-weight:bold">
        合計：<span id="applyTotal">0</span> 元
      </div>
      <div class="actions">
        <button type="submit" class="btn btn-primary">送出申請</button>
      </div>
    </form>
  </div>
</div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════ 申請查詢 ═══════════════════════ -->
<div id="tab-list" class="tab <?= $canApply ? '' : 'active' ?>">
<div class="container">
  <div class="card">
    <h2>申請單查詢</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label>狀態篩選</label>
        <select id="filter-status" onchange="loadRequests()">
          <option value="">全部</option>
          <option value="draft">草稿</option>
          <option value="submitted">已送出</option>
          <option value="reviewing">審核中</option>
          <option value="approved">已核准</option>
          <option value="completed">已完成</option>
          <option value="rejected">已退回</option>
          <option value="cancelled">已取消</option>
        </select>
      </div>
      <button class="btn btn-sm btn-primary" onclick="loadRequests()">重新整理</button>
    </div>
    <div id="spinner">載入中…</div>
    <div id="list-container"></div>
  </div>

  <!-- 詳細資料面板 -->
  <div class="card" id="detail-panel" style="display:none">
    <h2>申請單詳細 <button class="btn btn-sm" style="float:right;background:#6c757d;color:#fff" onclick="document.getElementById('detail-panel').style.display='none'">關閉</button></h2>
    <div id="detail-content"></div>
  </div>
</div>
</div>

<!-- ═══════════════════════════════════════ 簽核作業 ═══════════════════════ -->
<?php if ($canApprove): ?>
<div id="tab-approve" class="tab">
<div class="container">
  <div class="card">
    <h2>待簽核申請單</h2>
    <div id="approve-spinner">載入中…</div>
    <div id="approve-list"></div>
  </div>

  <div class="card" id="approve-action-card" style="display:none">
    <h2>執行簽核</h2>
    <div id="approve-msg"></div>
    <p id="approve-info" style="font-size:.85rem;margin-bottom:12px;color:#555"></p>
    <div class="grid-2">
      <div class="field">
        <label>動作</label>
        <select id="wf-new-status">
          <option value="reviewing">審核通過 → 下一關</option>
          <option value="rejected">退回申請</option>
          <option value="cancelled">取消申請</option>
        </select>
      </div>
      <div class="field">
        <label>簽核意見</label>
        <input id="wf-comment" placeholder="選填">
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-warning" onclick="submitApproval()">確認</button>
      <button class="btn btn-sm" style="background:#6c757d;color:#fff" onclick="document.getElementById('approve-action-card').style.display='none'">取消</button>
    </div>
  </div>
</div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════ 報表 ═══════════════════════════ -->
<?php if ($canReport): ?>
<div id="tab-report" class="tab">
<div class="container">
  <div class="card">
    <h2>月度預算報表</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-end">
      <div><label>年份</label><input id="rpt-year" type="number" value="<?= date('Y') ?>" style="width:80px"></div>
      <div><label>月份</label><input id="rpt-month" type="number" value="<?= date('n') ?>" min="1" max="12" style="width:60px"></div>
      <button class="btn btn-primary" onclick="loadMonthly()">查詢</button>
    </div>
    <div id="rpt-monthly"></div>
  </div>
  <div class="card">
    <h2>每日配送清單</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-end">
      <div><label>日期</label><input id="rpt-date" type="date" value="<?= date('Y-m-d') ?>"></div>
      <button class="btn btn-primary" onclick="loadDaily()">查詢</button>
    </div>
    <div id="rpt-daily"></div>
  </div>
</div>
</div>
<?php endif; ?>

<script>
// ── 常數 ─────────────────────────────────────────────────────────────────────
const STATUS_LABEL = {
  draft:'草稿', submitted:'已送出', reviewing:'審核中',
  approved:'已核准', completed:'已完成', rejected:'已退回', cancelled:'已取消'
};
const STATUS_COLOR = {
  draft:'#6c757d', submitted:'#e6850e', reviewing:'#17a2b8',
  approved:'#28a745', completed:'#1a4a72', rejected:'#dc3545', cancelled:'#aaa'
};

const ROLE = <?= json_encode($role) ?>;
let mealsData = [], unitsData = [];
let currentApproveNo = null;

// ── Tab navigation ────────────────────────────────────────────────────────────
function showTab(id, btn) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('nav button').forEach(b => b.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  if (btn) btn.classList.add('active');
  if (id === 'tab-list')    loadRequests();
  if (id === 'tab-approve') loadApproveList();
}

// ── Master data ───────────────────────────────────────────────────────────────
async function initMasterData() {
  const [meals, units] = await Promise.all([
    fetch('/api/meals').then(r => r.json()),
    fetch('/api/units').then(r => r.json()),
  ]);
  mealsData = meals;
  unitsData  = units;

  // Populate unit dropdown
  const sel = document.getElementById('sel-unit');
  if (sel) units.forEach(u => sel.add(new Option(u.name + ' (' + u.code + ')', u.id)));

  // Add initial row
  if (document.getElementById('applyRows')) addApplyRow();
}

// ── 申請表單明細 ──────────────────────────────────────────────────────────────
function buildCategoryOpts(selCat) {
  const cats = [...new Set(mealsData.map(m => m.category))];
  cats.forEach(c => selCat.add(new Option(c, c)));
}
function buildMealOpts(selMeal, category) {
  selMeal.innerHTML = '';
  const filtered = mealsData.filter(m => m.category === category);
  filtered.forEach(m => selMeal.add(new Option(m.name, m.id, false, false)));
  // Set default price
  const first = filtered[0];
  if (first) {
    const tr = selMeal.closest('tr');
    tr.querySelector('[name=unit_price]').value = first.default_price || 0;
    calcRow(tr.querySelector('[name=unit_price]'));
  }
}

function addApplyRow() {
  const tbody = document.getElementById('applyRows');
  const tr = document.createElement('tr');
  const firstCat = mealsData.length ? mealsData[0].category : '';
  const catOpts = [...new Set(mealsData.map(m => m.category))].map(c => `<option>${c}</option>`).join('');
  const firstMeals = mealsData.filter(m => m.category === firstCat);
  const mealOpts = firstMeals.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
  const defaultPrice = firstMeals[0]?.default_price || 0;
  tr.innerHTML = `
    <td><select name="category" onchange="onCatChange(this)">${catOpts}</select></td>
    <td><select name="meal_id" onchange="onMealChange(this)">${mealOpts}</select></td>
    <td><input type="number" name="quantity" value="10" min="1" style="width:60px" oninput="calcRow(this)"></td>
    <td><input type="number" name="unit_price" value="${defaultPrice}" min="0" step="0.01" style="width:70px" oninput="calcRow(this)"></td>
    <td class="subtotal">${(10 * defaultPrice).toFixed(0)}</td>
    <td><button type="button" class="btn btn-danger" onclick="this.closest('tr').remove();calcTotal()">✕</button></td>
  `;
  tbody.appendChild(tr);
  calcTotal();
}

function onCatChange(sel) {
  const tr = sel.closest('tr');
  const selMeal = tr.querySelector('[name=meal_id]');
  const category = sel.value;
  selMeal.innerHTML = '';
  const filtered = mealsData.filter(m => m.category === category);
  filtered.forEach(m => selMeal.add(new Option(m.name, m.id)));
  const first = filtered[0];
  if (first) {
    tr.querySelector('[name=unit_price]').value = first.default_price || 0;
    calcRow(tr.querySelector('[name=unit_price]'));
  }
}

function onMealChange(sel) {
  const tr = sel.closest('tr');
  const mealId = parseInt(sel.value);
  const meal = mealsData.find(m => m.id === mealId);
  if (meal) {
    tr.querySelector('[name=unit_price]').value = meal.default_price || 0;
    calcRow(tr.querySelector('[name=unit_price]'));
  }
}

function calcRow(el) {
  const tr = el.closest('tr');
  const q = parseFloat(tr.querySelector('[name=quantity]').value) || 0;
  const p = parseFloat(tr.querySelector('[name=unit_price]').value) || 0;
  tr.querySelector('.subtotal').textContent = (q * p).toFixed(0);
  calcTotal();
}

function calcTotal() {
  let t = 0;
  document.querySelectorAll('#applyRows .subtotal').forEach(td => { t += parseFloat(td.textContent) || 0; });
  document.getElementById('applyTotal').textContent = t.toFixed(0);
}

// ── 送出申請 ──────────────────────────────────────────────────────────────────
async function submitRequest(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());
  body.total_amount = parseFloat(document.getElementById('applyTotal').textContent) || 0;

  body.items = Array.from(document.querySelectorAll('#applyRows tr')).map(tr => ({
    meal_id:    parseInt(tr.querySelector('[name=meal_id]').value),
    quantity:   parseInt(tr.querySelector('[name=quantity]').value) || 0,
    unit_price: parseFloat(tr.querySelector('[name=unit_price]').value) || 0,
  }));

  const res  = await fetch('/requests', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
  const json = await res.json();
  const msgEl = document.getElementById('apply-msg');
  if (res.ok) {
    msgEl.innerHTML = `<div class="msg msg-ok">✔ 申請單建立成功，單號：<b>${json.request_no}</b></div>`;
    e.target.reset();
    document.getElementById('applyRows').innerHTML = '';
    addApplyRow();
  } else {
    msgEl.innerHTML = `<div class="msg msg-err">✘ ${json.error || JSON.stringify(json)}</div>`;
  }
}

// ── 申請查詢 ──────────────────────────────────────────────────────────────────
async function loadRequests() {
  const status = document.getElementById('filter-status').value;
  document.getElementById('spinner').style.display = 'block';
  document.getElementById('list-container').innerHTML = '';
  document.getElementById('detail-panel').style.display = 'none';

  const url = '/requests' + (status ? '?status=' + status : '');
  const res  = await fetch(url);
  const rows = await res.json();
  document.getElementById('spinner').style.display = 'none';

  if (!Array.isArray(rows) || rows.length === 0) {
    document.getElementById('list-container').innerHTML = '<p style="color:#888;font-size:.85rem;padding:8px">尚無申請單</p>';
    return;
  }

  let html = `<table><thead><tr>
    <th>單號</th><th>用餐日期</th><th>餐別</th><th>地點</th><th>事由</th><th>金額</th><th>狀態</th><th>操作</th>
  </tr></thead><tbody>`;
  rows.forEach(r => {
    const sc  = r.status || '';
    const lbl = STATUS_LABEL[sc] || sc;
    const clr = STATUS_COLOR[sc] || '#333';
    html += `<tr>
      <td><b>${r.request_no}</b></td>
      <td>${r.meal_date || ''}</td>
      <td>${r.meal_type || ''}</td>
      <td>${r.location || ''}</td>
      <td>${r.purpose || ''}</td>
      <td style="text-align:right">${parseFloat(r.total_amount || 0).toLocaleString()}</td>
      <td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${lbl}</span></td>
      <td><button class="btn btn-sm btn-primary" onclick="showDetail('${r.request_no}')">詳細</button></td>
    </tr>`;
  });
  html += '</tbody></table>';
  document.getElementById('list-container').innerHTML = html;
}

async function showDetail(requestNo) {
  const panel = document.getElementById('detail-panel');
  panel.style.display = 'block';
  document.getElementById('detail-content').innerHTML = '<p style="color:#888">載入中…</p>';

  const res  = await fetch('/requests/' + requestNo);
  const d    = await res.json();
  if (!res.ok) {
    document.getElementById('detail-content').innerHTML = '<p style="color:#c00">查詢失敗</p>';
    return;
  }

  const sc  = d.status || '';
  const clr = STATUS_COLOR[sc] || '#333';
  let html = `<table style="margin-bottom:14px">
    <tr><th>單號</th><td><b>${d.request_no}</b></td>
        <th>狀態</th><td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${STATUS_LABEL[sc] || sc}</span></td></tr>
    <tr><th>用餐日期</th><td>${d.meal_date || ''}</td><th>餐別</th><td>${d.meal_type || ''}</td></tr>
    <tr><th>地點</th><td>${d.location || ''}</td><th>金額</th><td>${parseFloat(d.total_amount || 0).toLocaleString()} 元</td></tr>
    <tr><th>事由</th><td colspan="3">${d.purpose || ''}</td></tr>
  </table>`;

  if (d.items && d.items.length) {
    html += '<h3 style="font-size:.86rem;margin-bottom:6px;color:#555">訂餐明細</h3>';
    html += '<table style="margin-bottom:14px"><thead><tr><th>餐點</th><th>份數</th><th>單價</th><th>小計</th></tr></thead><tbody>';
    d.items.forEach(it => {
      html += `<tr><td>${it.meal_name || it.meal_id}</td><td>${it.quantity}</td><td>${it.unit_price}</td><td>${it.subtotal}</td></tr>`;
    });
    html += '</tbody></table>';
  }

  if (d.audit_logs && d.audit_logs.length) {
    html += '<h3 style="font-size:.86rem;margin-bottom:6px;color:#555">簽核歷程</h3>';
    html += '<table><thead><tr><th>時間</th><th>階段</th><th>操作者</th><th>結果</th><th>意見</th></tr></thead><tbody>';
    d.audit_logs.forEach(lg => {
      html += `<tr><td>${(lg.created_at||'').substring(0,16)}</td><td>${lg.stage||''}</td><td>${lg.operator}</td><td>${STATUS_LABEL[lg.new_status]||lg.new_status||''}</td><td>${lg.comment||''}</td></tr>`;
    });
    html += '</tbody></table>';
  }

  document.getElementById('detail-content').innerHTML = html;
  panel.scrollIntoView({behavior:'smooth'});
}

// ── 簽核作業 ──────────────────────────────────────────────────────────────────
async function loadApproveList() {
  const el = document.getElementById('approve-list');
  if (!el) return;
  document.getElementById('approve-spinner').style.display = 'block';
  el.innerHTML = '';

  // Load requests where current user is next_operator (submitted/reviewing)
  const res  = await fetch('/requests?status=submitted');
  const res2 = await fetch('/requests?status=reviewing');
  const [r1, r2] = await Promise.all([res.json(), res2.json()]);
  document.getElementById('approve-spinner').style.display = 'none';

  const rows = [...(Array.isArray(r1) ? r1 : []), ...(Array.isArray(r2) ? r2 : [])];

  if (rows.length === 0) {
    el.innerHTML = '<p style="color:#888;font-size:.85rem;padding:8px">目前無待簽核申請單</p>';
    return;
  }

  let html = `<table><thead><tr>
    <th>單號</th><th>用餐日期</th><th>地點</th><th>金額</th><th>狀態</th><th>操作</th>
  </tr></thead><tbody>`;
  rows.forEach(r => {
    const sc  = r.status || '';
    const clr = STATUS_COLOR[sc] || '#333';
    html += `<tr>
      <td><b>${r.request_no}</b></td>
      <td>${r.meal_date || ''}</td>
      <td>${r.location || ''}</td>
      <td style="text-align:right">${parseFloat(r.total_amount || 0).toLocaleString()}</td>
      <td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${STATUS_LABEL[sc]||sc}</span></td>
      <td><button class="btn btn-sm btn-warning" onclick="openApprove('${r.request_no}','${sc}')">簽核</button></td>
    </tr>`;
  });
  html += '</tbody></table>';
  el.innerHTML = html;
}

function openApprove(requestNo, currentStatus) {
  currentApproveNo = requestNo;
  document.getElementById('approve-info').textContent = `申請單：${requestNo}  目前狀態：${STATUS_LABEL[currentStatus] || currentStatus}`;
  document.getElementById('approve-msg').innerHTML = '';
  document.getElementById('approve-action-card').style.display = 'block';
  document.getElementById('approve-action-card').scrollIntoView({behavior:'smooth'});
}

async function submitApproval() {
  if (!currentApproveNo) return;
  const body = {
    request_no: currentApproveNo,
    new_status: document.getElementById('wf-new-status').value,
    comment:    document.getElementById('wf-comment').value || '',
  };
  const res  = await fetch('/workflow/action', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body) });
  const json = await res.json();
  const msgEl = document.getElementById('approve-msg');
  if (res.ok) {
    msgEl.innerHTML = `<div class="msg msg-ok">✔ 簽核成功</div>`;
    document.getElementById('approve-action-card').style.display = 'none';
    loadApproveList();
  } else {
    msgEl.innerHTML = `<div class="msg msg-err">✘ ${json.error || JSON.stringify(json)}</div>`;
  }
}

// ── 報表 ──────────────────────────────────────────────────────────────────────
async function loadMonthly() {
  const y = document.getElementById('rpt-year').value;
  const m = document.getElementById('rpt-month').value;
  const res  = await fetch(`/report/monthly?year=${y}&month=${m}`);
  const json = await res.json();
  const el = document.getElementById('rpt-monthly');
  if (!json.data || json.data.length === 0) {
    el.innerHTML = '<p style="color:#888;font-size:.85rem">無資料</p>';
    return;
  }
  let html = '<table><thead><tr><th>預算來源</th><th>件數</th><th>合計金額</th></tr></thead><tbody>';
  json.data.forEach(r => {
    html += `<tr><td>${r.budget_source||'（未分類）'}</td><td>${r.count}</td><td style="text-align:right">${parseFloat(r.total||0).toLocaleString()}</td></tr>`;
  });
  html += '</tbody></table>';
  el.innerHTML = html;
}

async function loadDaily() {
  const date = document.getElementById('rpt-date').value;
  const res  = await fetch(`/report/daily?date=${date}`);
  const json = await res.json();
  const el = document.getElementById('rpt-daily');
  if (!json.orders || json.orders.length === 0) {
    el.innerHTML = '<p style="color:#888;font-size:.85rem">當日無配送單</p>';
    return;
  }
  let html = '<table><thead><tr><th>單號</th><th>時間</th><th>餐別</th><th>地點</th><th>單位</th><th>金額</th><th>狀態</th></tr></thead><tbody>';
  json.orders.forEach(r => {
    const sc  = r.status || '';
    const clr = STATUS_COLOR[sc] || '#333';
    html += `<tr>
      <td>${r.request_no}</td><td>${r.meal_time||''}</td><td>${r.meal_type||''}</td>
      <td>${r.location||''}</td><td>${r.unit_name||''}</td>
      <td style="text-align:right">${parseFloat(r.total_amount||0).toLocaleString()}</td>
      <td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${STATUS_LABEL[sc]||sc}</span></td>
    </tr>`;
  });
  html += '</tbody></table>';
  el.innerHTML = html;
}

// ── 初始化 ────────────────────────────────────────────────────────────────────
initMasterData();
loadRequests();
</script>
</body>
</html>
