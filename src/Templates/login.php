<!doctype html>
<html lang="zh-TW">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>同心餐點費用申請系統 — 登入</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:"Microsoft JhengHei",Arial,sans-serif}
    .card{background:#fff;border-radius:8px;box-shadow:0 2px 14px rgba(0,0,0,.13);padding:40px;width:360px}
    h1{text-align:center;color:#1a4a72;margin-bottom:6px;font-size:1.2rem}
    .subtitle{text-align:center;color:#666;font-size:.82rem;margin-bottom:28px}
    label{display:block;font-size:.82rem;color:#444;margin-bottom:4px}
    input{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:4px;font-size:.9rem;margin-bottom:16px}
    input:focus{outline:none;border-color:#1a4a72}
    button{width:100%;padding:11px;background:#1a4a72;color:#fff;border:none;border-radius:4px;font-size:.95rem;cursor:pointer}
    button:hover{background:#133658}
    .error{background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;border-radius:4px;padding:10px;margin-bottom:16px;font-size:.82rem}
    .hint{margin-top:20px;background:#f9fafb;border-radius:4px;padding:12px;font-size:.78rem;color:#555;line-height:1.7}
    .hint strong{color:#333}
  </style>
</head>
<body>
<div class="card">
  <h1>同心餐點費用申請</h1>
  <p class="subtitle">Agentic Workflow 教學示範系統</p>

  <?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="POST" action="/login">
    <label for="username">帳號</label>
    <input type="text" id="username" name="username"
           placeholder="例：staff01" required autocomplete="username">

    <label for="password">密碼</label>
    <input type="password" id="password" name="password"
           placeholder="密碼" required autocomplete="current-password">

    <button type="submit">登入</button>
  </form>

  <div class="hint">
    <strong>教學帳號（密碼均為 1234）</strong><br>
    一般員工：staff01 ~ staff20<br>
    主管：manager01 ~ manager05<br>
    供膳審核：restaurant01<br>
    財務審核：finance01<br>
    管理員：admin
  </div>
</div>
</body>
</html>
