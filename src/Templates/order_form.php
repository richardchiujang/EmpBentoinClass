<!doctype html>
<html lang="zh-Hant">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>同心餐點費用申請系統</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Microsoft JhengHei",sans-serif;background:#f0f4f8;color:#333}
    header{background:#1a4a72;color:#fff;padding:12px 24px;display:flex;align-items:center;gap:16px}
    header h1{font-size:1.1rem;flex:1}
    nav a{color:#cce;text-decoration:none;margin-left:16px;font-size:.88rem;cursor:pointer}
    nav a:hover{color:#fff}
    .page{display:none}.page.active{display:block}
    .container{max-width:920px;margin:24px auto;padding:0 14px}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:22px;margin-bottom:20px}
    .card h2{font-size:.98rem;margin-bottom:14px;border-bottom:2px solid #1a4a72;padding-bottom:7px;color:#1a4a72}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
    .field{margin-bottom:10px}
    label{display:block;font-size:.82rem;margin-bottom:3px;color:#555}
    input,select,textarea{width:100%;padding:7px 9px;border:1px solid #ccc;border-radius:4px;font-size:.88rem;font-family:inherit}
    textarea{resize:vertical;min-height:56px}
    table{width:100%;border-collapse:collapse;font-size:.84rem}
    th{background:#eef2f7;padding:7px 8px;text-align:left;border:1px solid #ddd}
    td{padding:5px 4px;border:1px solid #eee}
    td input,td select{border:none;background:transparent;padding:2px 4px;font-size:.84rem}
    .btn{padding:8px 20px;border:none;border-radius:4px;cursor:pointer;font-size:.88rem;font-family:inherit}
    .btn-primary{background:#1a4a72;color:#fff}
    .btn-success{background:#28a745;color:#fff}
    .btn-warning{background:#e6850e;color:#fff}
    .btn-danger{background:#dc3545;color:#fff;padding:3px 9px;font-size:.78rem}
    .btn-sm{padding:5px 12px;font-size:.8rem}
    .actions{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
    pre#result{white-space:pre-wrap;background:#f0f4f8;border-radius:6px;padding:12px;font-size:.8rem;display:none;margin-top:10px}
    .badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:.75rem;font-weight:bold}
    tr:hover td{background:#f8fafc}
    .msg{padding:10px 14px;border-radius:5px;margin-bottom:10px;font-size:.85rem}
    .msg-ok{background:#d4edda;color:#155724}
    .msg-err{background:#f8d7da;color:#721c24}
    #spinner{display:none;color:#888;font-size:.85rem;margin:8px 0}
  </style>
</head>
<body>
<header>
  <h1>同心餐點費用申請系統</h1>
  <nav>
    <a onclick="showPage('pg-form')">申請表單</a>
    <a onclick="showPage('pg-list')">申請單查詢</a>
    <a onclick="showPage('pg-workflow')">簽核作業</a>
    <a onclick="showPage('pg-report')">報表</a>
  </nav>
</header>

<!-- ══════════════════════════════════════════════════════════ 申請表單 ══ -->
<div id="pg-form" class="page active">
<div class="container">
  <div class="card">
    <h2>訂餐費用申請單</h2>
    <div id="form-msg"></div>
    <form id="orderForm">
      <div class="grid-3" style="margin-bottom:10px">
        <div class="field">
          <label>申請人</label>
          <select name="applicant_id" id="sel-applicant" required></select>
        </div>
        <div class="field">
          <label>單位代碼</label>
          <select name="dept_code" id="sel-dept" required></select>
        </div>
        <div class="field">
          <label>預算科目</label>
          <select name="budget_code" id="sel-budget" required></select>
        </div>
      </div>
      <div class="grid-3" style="margin-bottom:10px">
        <div class="field">
          <label>用餐日期</label>
          <input name="meal_date" type="date" required>
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
          <input name="location" placeholder="如：C304電腦教室">
        </div>
        <div class="field">
          <label>申請配合事項</label>
          <input name="remarks" placeholder="選填">
        </div>
      </div>
      <div class="field" style="margin-bottom:14px">
        <label>事由</label>
        <textarea name="purpose" placeholder="請填寫本次用餐事由"></textarea>
      </div>

      <h2 style="margin-bottom:8px">訂餐明細</h2>
      <table>
        <thead><tr>
          <th>餐點</th><th>份數</th><th>單價(元)</th><th>付費方式</th><th>小計</th><th></th>
        </tr></thead>
        <tbody id="detailRows"></tbody>
      </table>
      <button type="button" class="btn btn-sm" style="margin-top:8px;background:#e8f0fe;color:#1a4a72" onclick="addRow()">＋ 新增明細</button>
      <div style="text-align:right;margin-top:6px;font-size:.9rem;font-weight:bold">
        合計金額：<span id="totalAmt">0</span> 元
      </div>

      <div class="actions">
        <button type="submit" class="btn btn-primary">送出申請</button>
        <button type="button" class="btn btn-sm" style="background:#6c757d;color:#fff" onclick="showPage('pg-list')">查看申請單列表</button>
      </div>
    </form>
    <pre id="result"></pre>
  </div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════ 申請單查詢 ══ -->
<div id="pg-list" class="page">
<div class="container">
  <div class="card">
    <h2>申請單查詢</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:flex-end">
      <div>
        <label style="font-size:.82rem">狀態篩選</label>
        <select id="filter-status" onchange="loadOrders()">
          <option value="">全部</option>
          <option value="1">1-申請中</option>
          <option value="2">2-審核中</option>
          <option value="3">3-已核決</option>
          <option value="4">4-用膳中</option>
          <option value="5">5-待付款</option>
          <option value="6">6-已付款</option>
          <option value="X">X-已銷案</option>
        </select>
      </div>
      <button class="btn btn-sm btn-primary" onclick="loadOrders()">重新整理</button>
      <button class="btn btn-sm" style="background:#28a745;color:#fff" onclick="showPage('pg-form')">新增申請單</button>
    </div>
    <div id="spinner">載入中…</div>
    <div id="orders-list"></div>
  </div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════ 簽核作業 ══ -->
<div id="pg-workflow" class="page">
<div class="container">
  <div class="card">
    <h2>簽核作業</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-end">
      <div style="flex:1">
        <label style="font-size:.82rem">申請單號</label>
        <input id="wf-order-no" placeholder="例：S920005" onkeydown="if(event.key==='Enter')loadWorkflow()">
      </div>
      <button class="btn btn-primary" onclick="loadWorkflow()">查詢</button>
    </div>
    <div id="wf-detail"></div>
  </div>

  <div class="card" id="wf-action-card" style="display:none">
    <h2>執行簽核動作</h2>
    <div id="wf-msg"></div>
    <div class="grid-3">
      <div class="field">
        <label>新狀態</label>
        <select id="wf-new-status">
          <option value="2">2-審核中</option>
          <option value="3">3-已核決</option>
          <option value="4">4-用膳中</option>
          <option value="5">5-待付款</option>
          <option value="6">6-已付款</option>
          <option value="X">X-已銷案</option>
        </select>
      </div>
      <div class="field">
        <label>主辦人</label>
        <select id="wf-handler"></select>
      </div>
      <div class="field">
        <label>簽核意見</label>
        <input id="wf-opinion" placeholder="選填">
      </div>
    </div>
    <div class="actions">
      <button class="btn btn-warning" onclick="submitWorkflow()">確認簽核</button>
    </div>
  </div>
</div>
</div>

<!-- ══════════════════════════════════════════════════════════ 報表 ══ -->
<div id="pg-report" class="page">
<div class="container">
  <div class="card">
    <h2>月度預算報表</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-end">
      <div>
        <label style="font-size:.82rem">年份</label>
        <input id="rpt-year" type="number" value="<?= date('Y') ?>" style="width:80px">
      </div>
      <div>
        <label style="font-size:.82rem">月份</label>
        <input id="rpt-month" type="number" value="<?= date('n') ?>" min="1" max="12" style="width:60px">
      </div>
      <button class="btn btn-primary" onclick="loadMonthly()">查詢</button>
    </div>
    <div id="rpt-monthly"></div>
  </div>

  <div class="card">
    <h2>每日配送清單</h2>
    <div style="display:flex;gap:10px;margin-bottom:12px;align-items:flex-end">
      <div>
        <label style="font-size:.82rem">日期</label>
        <input id="rpt-date" type="date" value="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-primary" onclick="loadDaily()">查詢</button>
    </div>
    <div id="rpt-daily"></div>
  </div>
</div>
</div>

<script>
// ── Globals ──────────────────────────────────────────────────────────────────
const STATUS_LABEL = {'1':'申請中','2':'審核中','3':'已核決','4':'用膳中','5':'待付款','6':'已付款','X':'已銷案'};
const STATUS_COLOR = {'1':'#e6850e','2':'#17a2b8','3':'#28a745','4':'#6f42c1','5':'#fd7e14','6':'#1a4a72','X':'#6c757d'};
let mealItems = [], allUsers = [];

// ── Page navigation ───────────────────────────────────────────────────────────
function showPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  if (id === 'pg-list') loadOrders();
}

// ── Load master data ──────────────────────────────────────────────────────────
async function initMasterData() {
  const [items, users, depts, budgets] = await Promise.all([
    fetch('/api/meal-items').then(r=>r.json()),
    fetch('/api/users').then(r=>r.json()),
    fetch('/api/departments').then(r=>r.json()),
    fetch('/api/budgets').then(r=>r.json()),
  ]);
  mealItems = items;
  allUsers  = users;

  // Populate applicant select
  const selApp = document.getElementById('sel-applicant');
  users.forEach(u => selApp.add(new Option(`${u.full_name} (${u.username})`, u.user_id)));

  // Populate dept select
  const selDept = document.getElementById('sel-dept');
  depts.forEach(d => selDept.add(new Option(`${d.dept_code} ${d.dept_name}`, d.dept_code)));

  // Populate budget select
  const selBudget = document.getElementById('sel-budget');
  budgets.forEach(b => selBudget.add(new Option(`${b.budget_code} (餘額:${parseFloat(b.balance).toLocaleString()})`, b.budget_code)));

  // Populate workflow handler select
  const selHandler = document.getElementById('wf-handler');
  selHandler.add(new Option('（不指定）', ''));
  users.forEach(u => selHandler.add(new Option(`${u.full_name}`, u.user_id)));
}

// ── Detail rows ───────────────────────────────────────────────────────────────
function addRow(d = {}) {
  const tbody = document.getElementById('detailRows');
  const tr = document.createElement('tr');
  // Build meal item options
  const opts = mealItems.map(i =>
    `<option value="${i.item_id}" data-price="${i.standard_price}" ${i.item_id == (d.item_id||1)?'selected':''}>${i.item_name}(${i.standard_price}元)</option>`
  ).join('');
  const qty   = d.quantity || 10;
  const price = d.price_per_unit || (mealItems[0]?.standard_price || 45);
  const sub   = d.subtotal || (qty * price);
  tr.innerHTML = `
    <td><select name="item_id" onchange="onItemChange(this)">${opts}</select></td>
    <td><input type="number" name="quantity" value="${qty}" min="1" style="width:60px" oninput="calcRow(this)"></td>
    <td><input type="number" name="price_per_unit" value="${price}" min="0" step="0.01" style="width:70px" oninput="calcRow(this)"></td>
    <td><select name="payment_method" style="width:70px">
      <option ${(d.payment_method||'招待')==='自付'?'selected':''}>自付</option>
      <option ${(d.payment_method||'招待')==='招待'?'selected':''}>招待</option>
    </select></td>
    <td class="subtotal">${parseFloat(sub).toFixed(0)}</td>
    <td><button type="button" class="btn btn-danger" onclick="this.closest('tr').remove();calcTotal()">✕</button></td>`;
  tbody.appendChild(tr);
  calcTotal();
}

function onItemChange(sel) {
  const opt = sel.selectedOptions[0];
  const price = opt.dataset.price;
  const tr = sel.closest('tr');
  tr.querySelector('[name=price_per_unit]').value = price;
  calcRow(sel);
}
function calcRow(el) {
  const tr = el.closest('tr');
  const q = parseFloat(tr.querySelector('[name=quantity]').value)||0;
  const p = parseFloat(tr.querySelector('[name=price_per_unit]').value)||0;
  tr.querySelector('.subtotal').textContent = (q*p).toFixed(0);
  calcTotal();
}
function calcTotal() {
  let t=0;
  document.querySelectorAll('.subtotal').forEach(td=>{t+=parseFloat(td.textContent)||0});
  document.getElementById('totalAmt').textContent = t.toFixed(0);
}

// ── Submit order ──────────────────────────────────────────────────────────────
document.getElementById('orderForm').addEventListener('submit', async e => {
  e.preventDefault();
  document.getElementById('form-msg').innerHTML = '';
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());
  body.total_amount = parseFloat(document.getElementById('totalAmt').textContent)||0;
  body.details = Array.from(document.querySelectorAll('#detailRows tr')).map(tr => {
    const q = parseFloat(tr.querySelector('[name=quantity]').value)||0;
    const p = parseFloat(tr.querySelector('[name=price_per_unit]').value)||0;
    return {
      item_id:        parseInt(tr.querySelector('[name=item_id]').value),
      quantity:       q, price_per_unit: p,
      payment_method: tr.querySelector('[name=payment_method]').value,
      subtotal:       parseFloat((q*p).toFixed(2)),
    };
  });

  const res = await fetch('/orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const j = await res.json();
  const cls = res.ok ? 'msg-ok' : 'msg-err';
  document.getElementById('form-msg').innerHTML = `<div class="msg ${cls}">${res.ok?'✔ 申請單建立成功，單號：'+j.order_no:'✘ 錯誤：'+JSON.stringify(j)}</div>`;
  const el = document.getElementById('result');
  el.style.display='block';
  el.textContent = JSON.stringify(j,null,2);
});

// ── Load orders list ──────────────────────────────────────────────────────────
async function loadOrders() {
  const container = document.getElementById('orders-list');
  const status = document.getElementById('filter-status').value;
  document.getElementById('spinner').style.display='block';
  container.innerHTML='';
  const url = '/orders' + (status ? '?status='+status : '');
  const res = await fetch(url);
  const rows = await res.json();
  document.getElementById('spinner').style.display='none';
  if (!Array.isArray(rows)||rows.length===0){
    container.innerHTML='<p style="color:#888;font-size:.85rem;padding:8px">尚無申請單</p>';
    return;
  }
  let html=`<table><thead><tr>
    <th>單號</th><th>用餐日期</th><th>餐別</th><th>地點</th><th>事由</th><th>金額</th><th>狀態</th><th>操作</th>
  </tr></thead><tbody>`;
  rows.forEach(r=>{
    const sc=r.status_code||'';
    const lbl=STATUS_LABEL[sc]||sc;
    const clr=STATUS_COLOR[sc]||'#333';
    html+=`<tr>
      <td><b>${r.order_no}</b></td>
      <td>${r.meal_date}</td><td>${r.meal_type||''}</td>
      <td>${r.location||''}</td><td>${r.purpose||''}</td>
      <td style="text-align:right">${parseFloat(r.total_amount).toLocaleString()}</td>
      <td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${lbl}</span></td>
      <td><button class="btn btn-sm" style="background:#1a4a72;color:#fff" onclick="goWorkflow('${r.order_no}')">簽核</button></td>
    </tr>`;
  });
  html+='</tbody></table>';
  container.innerHTML=html;
}

// ── Workflow ──────────────────────────────────────────────────────────────────
function goWorkflow(orderNo) {
  document.getElementById('wf-order-no').value = orderNo;
  showPage('pg-workflow');
  loadWorkflow();
}

async function loadWorkflow() {
  const orderNo = document.getElementById('wf-order-no').value.trim();
  if (!orderNo) return;
  const detail = document.getElementById('wf-detail');
  detail.innerHTML = '<p style="color:#888;font-size:.85rem">查詢中…</p>';
  document.getElementById('wf-action-card').style.display='none';

  const res = await fetch('/orders/'+orderNo);
  if (!res.ok) {
    detail.innerHTML = '<p style="color:#c00">找不到申請單</p>';
    return;
  }
  const d = await res.json();
  const sc = d.status_code||'';
  const clr = STATUS_COLOR[sc]||'#333';

  let html = `<table style="margin-bottom:14px">
    <tr><th>單號</th><td>${d.order_no}</td><th>狀態</th><td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${STATUS_LABEL[sc]||sc}</span></td></tr>
    <tr><th>用餐日期</th><td>${d.meal_date}</td><th>餐別</th><td>${d.meal_type||''}</td></tr>
    <tr><th>地點</th><td>${d.location||''}</td><th>總金額</th><td>${parseFloat(d.total_amount).toLocaleString()} 元</td></tr>
    <tr><th>事由</th><td colspan="3">${d.purpose||''}</td></tr>
  </table>`;

  if (d.details && d.details.length) {
    html += '<h3 style="font-size:.88rem;margin-bottom:6px;color:#555">訂餐明細</h3><table style="margin-bottom:14px"><thead><tr><th>餐點ID</th><th>份數</th><th>單價</th><th>付費方式</th><th>小計</th></tr></thead><tbody>';
    d.details.forEach(dt => {
      html += `<tr><td>${dt.item_id}</td><td>${dt.quantity}</td><td>${dt.price_per_unit}</td><td>${dt.payment_method}</td><td>${dt.subtotal}</td></tr>`;
    });
    html += '</tbody></table>';
  }

  if (d.workflow && d.workflow.length) {
    html += '<h3 style="font-size:.88rem;margin-bottom:6px;color:#555">簽核歷程</h3><table><thead><tr><th>序號</th><th>日期</th><th>狀態</th><th>經辦人</th><th>意見</th></tr></thead><tbody>';
    d.workflow.forEach(w => {
      html += `<tr><td>${w.sequence_no}</td><td>${w.action_date}</td><td>${STATUS_LABEL[w.status_code]||w.status_code}</td><td>${w.full_name||'-'}</td><td>${w.opinion||''}</td></tr>`;
    });
    html += '</tbody></table>';
  }

  detail.innerHTML = html;

  // Show action card only if not terminal status
  if (sc !== '6' && sc !== 'X') {
    document.getElementById('wf-action-card').style.display = 'block';
    document.getElementById('wf-msg').innerHTML = '';
  }
}

async function submitWorkflow() {
  const orderNo = document.getElementById('wf-order-no').value.trim();
  const body = {
    order_no:    orderNo,
    status_code: document.getElementById('wf-new-status').value,
    handler_id:  document.getElementById('wf-handler').value || null,
    opinion:     document.getElementById('wf-opinion').value || null,
  };
  const res = await fetch('/workflow/action',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  const j = await res.json();
  const cls = res.ok ? 'msg-ok' : 'msg-err';
  document.getElementById('wf-msg').innerHTML = `<div class="msg ${cls}">${res.ok?'✔ 簽核成功':'✘ '+JSON.stringify(j)}</div>`;
  if (res.ok) loadWorkflow();
}

// ── Reports ───────────────────────────────────────────────────────────────────
async function loadMonthly() {
  const y = document.getElementById('rpt-year').value;
  const m = document.getElementById('rpt-month').value;
  const res = await fetch(`/report/monthly?year=${y}&month=${m}`);
  const j = await res.json();
  if (!j.data || j.data.length === 0) {
    document.getElementById('rpt-monthly').innerHTML = '<p style="color:#888;font-size:.85rem">無資料</p>';
    return;
  }
  let html = `<table><thead><tr><th>預算代碼</th><th>科目名稱</th><th>申請件數</th><th>合計金額</th></tr></thead><tbody>`;
  j.data.forEach(r => {
    html += `<tr><td>${r.budget_code}</td><td>${r.subject_name||''}</td><td>${r.orders_count}</td><td style="text-align:right">${parseFloat(r.total_amount).toLocaleString()}</td></tr>`;
  });
  html += '</tbody></table>';
  document.getElementById('rpt-monthly').innerHTML = html;
}

async function loadDaily() {
  const date = document.getElementById('rpt-date').value;
  const res = await fetch(`/report/daily?date=${date}`);
  const j = await res.json();
  if (!j.orders || j.orders.length === 0) {
    document.getElementById('rpt-daily').innerHTML = '<p style="color:#888;font-size:.85rem">當日無配送單</p>';
    return;
  }
  let html = `<table><thead><tr><th>單號</th><th>時間</th><th>餐別</th><th>地點</th><th>申請人</th><th>單位</th><th>金額</th><th>狀態</th></tr></thead><tbody>`;
  j.orders.forEach(r => {
    const sc = r.status_code||'';
    const clr = STATUS_COLOR[sc]||'#333';
    html += `<tr><td>${r.order_no}</td><td>${r.meal_time}</td><td>${r.meal_type||''}</td><td>${r.location||''}</td><td>${r.applicant_name||''}</td><td>${r.dept_name||''}</td><td style="text-align:right">${parseFloat(r.total_amount).toLocaleString()}</td><td><span class="badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}88">${STATUS_LABEL[sc]||sc}</span></td></tr>`;
  });
  html += '</tbody></table>';
  document.getElementById('rpt-daily').innerHTML = html;
}

// ── Init ──────────────────────────────────────────────────────────────────────
initMasterData().then(() => {
  addRow(); // Add one default detail row after items loaded
});
</script>
</body>
</html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>同心餐點費用申請系統</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: "Microsoft JhengHei", sans-serif; background: #f5f7fa; color: #333; }
    header { background: #2c5f8a; color: #fff; padding: 14px 24px; }
    header h1 { font-size: 1.2rem; }
    .container { max-width: 860px; margin: 28px auto; padding: 0 16px; }
    .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 24px; margin-bottom: 24px; }
    .card h2 { font-size: 1rem; margin-bottom: 16px; border-bottom: 2px solid #2c5f8a; padding-bottom: 8px; color: #2c5f8a; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
    label { display: block; font-size: .85rem; margin-bottom: 4px; color: #555; }
    input, select, textarea {
      width: 100%; padding: 8px 10px; border: 1px solid #ccc;
      border-radius: 4px; font-size: .9rem; font-family: inherit;
    }
    textarea { resize: vertical; min-height: 60px; }
    .detail-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .detail-table th { background: #f0f4f8; padding: 8px; text-align: left; border: 1px solid #ddd; }
    .detail-table td { padding: 6px 4px; border: 1px solid #ddd; }
    .detail-table input, .detail-table select { border: none; background: transparent; padding: 2px 4px; }
    .btn { padding: 9px 22px; border: none; border-radius: 4px; cursor: pointer; font-size: .9rem; }
    .btn-primary { background: #2c5f8a; color: #fff; }
    .btn-success { background: #28a745; color: #fff; }
    .btn-danger  { background: #dc3545; color: #fff; font-size: .8rem; padding: 4px 10px; }
    .btn-sm      { padding: 5px 12px; font-size: .82rem; }
    .actions { display: flex; gap: 10px; margin-top: 16px; }
    #result { white-space: pre-wrap; background: #f0f4f8; border-radius: 6px; padding: 14px; font-size: .82rem; display: none; }
    .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: .78rem; }
    #orders-list table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    #orders-list th { background: #2c5f8a; color: #fff; padding: 8px; text-align: left; }
    #orders-list td { padding: 7px 8px; border-bottom: 1px solid #eee; }
    #orders-list tr:hover td { background: #f5f8fb; }
  </style>
</head>
<body>
<header><h1>同心餐點費用申請系統</h1></header>

<div class="container">

  <!-- ── 訂餐申請表單 ── -->
  <div class="card">
    <h2>訂餐費用申請單</h2>
    <form id="orderForm">
      <div class="grid-2" style="margin-bottom:12px">
        <div>
          <label>申請人 ID</label>
          <input name="applicant_id" type="number" value="2" required>
        </div>
        <div>
          <label>單位代碼</label>
          <input name="dept_code" value="213000" required>
        </div>
      </div>
      <div class="grid-3" style="margin-bottom:12px">
        <div>
          <label>用餐日期</label>
          <input name="meal_date" type="date" required>
        </div>
        <div>
          <label>用餐時間</label>
          <input name="meal_time" type="time" value="12:00" required>
        </div>
        <div>
          <label>餐別</label>
          <select name="meal_type">
            <option>早餐</option><option selected>午餐</option>
            <option>下午茶</option><option>晚餐</option>
          </select>
        </div>
      </div>
      <div class="grid-2" style="margin-bottom:12px">
        <div>
          <label>用餐地點</label>
          <input name="location" value="C304電腦教室">
        </div>
        <div>
          <label>預算科目代碼</label>
          <input name="budget_code" value="92213000-03-05">
        </div>
      </div>
      <div style="margin-bottom:12px">
        <label>事由</label>
        <textarea name="purpose">電算中心暑期電腦教育訓練課程</textarea>
      </div>

      <!-- 明細表 -->
      <h2 style="margin-bottom:10px">訂餐明細</h2>
      <table class="detail-table">
        <thead>
          <tr>
            <th>餐點 ID</th><th>份數</th><th>單價</th><th>付費方式</th><th>小計</th><th></th>
          </tr>
        </thead>
        <tbody id="detailRows"></tbody>
      </table>
      <button type="button" class="btn btn-sm" style="margin-top:8px;background:#e8f0fe;color:#2c5f8a" onclick="addRow()">＋ 新增明細</button>

      <div style="text-align:right;margin-top:8px;font-weight:bold">
        合計金額：<span id="totalAmt">0</span> 元
      </div>

      <div class="actions">
        <button type="submit" class="btn btn-primary">送出申請</button>
        <button type="button" class="btn btn-sm" style="background:#6c757d;color:#fff" onclick="loadOrders()">重新載入申請單列表</button>
      </div>
    </form>
    <pre id="result"></pre>
  </div>

  <!-- ── 申請單列表 ── -->
  <div class="card">
    <h2>申請單列表</h2>
    <div id="orders-list"><p style="color:#888;font-size:.85rem">載入中…</p></div>
  </div>

</div>

<script>
// ── 新增明細列 ────────────────────────────────
function addRow(d = {}) {
  const tbody = document.getElementById('detailRows');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="number" name="item_id" value="${d.item_id||1}" style="width:60px" oninput="calcRow(this)"></td>
    <td><input type="number" name="quantity" value="${d.quantity||10}" min="1" style="width:60px" oninput="calcRow(this)"></td>
    <td><input type="number" name="price_per_unit" value="${d.price_per_unit||45}" min="0" step="0.01" style="width:70px" oninput="calcRow(this)"></td>
    <td>
      <select name="payment_method" style="width:70px">
        <option ${(d.payment_method||'招待')==='自付'?'selected':''}>自付</option>
        <option ${(d.payment_method||'招待')==='招待'?'selected':''}>招待</option>
      </select>
    </td>
    <td class="subtotal">${(d.subtotal||(10*45)).toFixed(0)}</td>
    <td><button type="button" class="btn btn-danger" onclick="this.closest('tr').remove();calcTotal()">✕</button></td>
  `;
  tbody.appendChild(tr);
  calcTotal();
}

function calcRow(el) {
  const tr = el.closest('tr');
  const q = parseFloat(tr.querySelector('[name=quantity]').value) || 0;
  const p = parseFloat(tr.querySelector('[name=price_per_unit]').value) || 0;
  tr.querySelector('.subtotal').textContent = (q * p).toFixed(0);
  calcTotal();
}

function calcTotal() {
  let total = 0;
  document.querySelectorAll('.subtotal').forEach(td => { total += parseFloat(td.textContent) || 0; });
  document.getElementById('totalAmt').textContent = total.toFixed(0);
}

// ── 提交申請單 ──────────────────────────────────
document.getElementById('orderForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());
  body.total_amount = parseFloat(document.getElementById('totalAmt').textContent) || 0;

  // 收集明細
  const rows = document.querySelectorAll('#detailRows tr');
  body.details = Array.from(rows).map(tr => {
    const q = parseFloat(tr.querySelector('[name=quantity]').value) || 0;
    const p = parseFloat(tr.querySelector('[name=price_per_unit]').value) || 0;
    return {
      item_id:        parseInt(tr.querySelector('[name=item_id]').value),
      quantity:       q,
      price_per_unit: p,
      payment_method: tr.querySelector('[name=payment_method]').value,
      subtotal:       parseFloat((q * p).toFixed(2)),
    };
  });

  const res = await fetch('/orders', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const j = await res.json();
  const el = document.getElementById('result');
  el.style.display = 'block';
  el.textContent = JSON.stringify(j, null, 2);
  if (res.ok) loadOrders();
});

// ── 載入申請單列表 ────────────────────────────────
async function loadOrders() {
  const container = document.getElementById('orders-list');
  const res = await fetch('/orders');
  const rows = await res.json();
  if (!Array.isArray(rows) || rows.length === 0) {
    container.innerHTML = '<p style="color:#888;font-size:.85rem">尚無申請單</p>';
    return;
  }
  const statusLabel = { '1':'申請中','2':'審核中','3':'已核決','4':'用膳中','5':'待付款','6':'已付款','X':'已銷案' };
  const statusColor = { '1':'#ffc107','2':'#17a2b8','3':'#28a745','4':'#6f42c1','5':'#fd7e14','6':'#007bff','X':'#6c757d' };
  let html = `<table>
    <thead><tr><th>單號</th><th>用餐日期</th><th>餐別</th><th>地點</th><th>事由</th><th>金額</th><th>狀態</th></tr></thead><tbody>`;
  rows.forEach(r => {
    const sc = r.status_code || '';
    const lbl = statusLabel[sc] || sc;
    const clr = statusColor[sc] || '#333';
    html += `<tr>
      <td>${r.order_no}</td>
      <td>${r.meal_date}</td>
      <td>${r.meal_type||''}</td>
      <td>${r.location||''}</td>
      <td>${r.purpose||''}</td>
      <td style="text-align:right">${parseFloat(r.total_amount).toLocaleString()}</td>
      <td><span class="status-badge" style="background:${clr}22;color:${clr};border:1px solid ${clr}66">${lbl}</span></td>
    </tr>`;
  });
  html += '</tbody></table>';
  container.innerHTML = html;
}

// ── 初始化 ────────────────────────────────────────
addRow();
loadOrders();
</script>
</body>
</html>

