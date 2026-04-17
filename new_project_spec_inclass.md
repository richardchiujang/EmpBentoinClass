# 同心餐點費用申請系統  專案計畫文件

> **最後更新**：2026-04-17  
> **狀態**：✅ 已完成開發並通過本機驗證

---

## 零、 環境規格 (Environment)

| 項目 | 規格 |
|---|---|
| 作業系統 | Windows 10/11 x64（無 Docker、無 WSL、無 Linux） |
| PHP | 8.5.x Thread Safe (ZTS) x64，建議路徑 `C:\php` |
| PHP 必要擴充 | `pdo`、`pdo_pgsql`、`pgsql`、`mbstring`、`json` |
| php.ini 路徑 | `C:\php\php.ini`，須取消註解 `extension=pdo_pgsql` / `extension=pgsql` |
| 資料庫 | PostgreSQL，監聽 `127.0.0.1:5432` |
| 目標資料庫 | `tongxin_meal` |
| DB 使用者 | `postgres`，密碼 `1234`（僅限本機開發，正式環境請改用 `.env`） |
| Composer | PSR-4 Autoload，`App\`  `src/` |
| PHP 內建伺服器 | `php -S 127.0.0.1:8000 -t src`（開發用） |
| 時區 | `php.ini` 設定 `date.timezone = Asia/Taipei` |

### 環境驗證指令（PowerShell）

```powershell
# 確認 PostgreSQL TCP 連線
Test-NetConnection -ComputerName 127.0.0.1 -Port 5432

# 確認 PHP pgsql 擴充已載入
php -m | findstr /I "pdo_pgsql pgsql"

# 確認 PDO 連線可用
php -r "var_dump(['pdo'=>extension_loaded('pdo'),'pdo_pgsql'=>extension_loaded('pdo_pgsql')]);"
```

---

## 一、 系統架構

```
同心餐點費用申請系統
 前端  ：原生 HTML/CSS/JS（單頁四分頁 SPA），無外部框架
 後端  ：PHP 8.5，MVC 架構，PSR-4 Autoload，手刻 Router
 資料庫：PostgreSQL，一對多關聯，CASCADE 刪除
 啟動  ：start.bat（推薦）/ start.ps1
```

### 請求流程

```
瀏覽器 GET /
   src/index.php (Router)
   Controller::method()
   Model::query()  ↔  PostgreSQL (tongxin_meal)
   JSON / HTML Response
```

---

## 二、 專案目錄結構（實際）

```text
d:\EmpBentoinClass\
 start.bat                        # 啟動腳本（推薦，無編碼問題）
 start.ps1                        # 啟動腳本（PowerShell）
 init.sql                         # DB 初始化腳本（建表 + 測試資料）
 create_data.py                   # Python DB 初始化輔助工具
 composer.json                    # PSR-4 autoload 設定
 requirements.txt                 # Python 依賴（psycopg2-binary）
 verify.py                        # 連線驗證腳本
 AGENTS.md                        # AI Agent 指引
 new_project_spec_inclass.md      # 本文件

 vendor/                          # Composer 自動生成

 src/
     index.php                    # 入口路由（手刻 Router）
     bootstrap.php                # Autoload + PDO 初始化
    
     Config/
        DB.php                   # PDO 連線類別（App\Config\DB）
    
     Controllers/
        OrderController.php      # 申請單 CRUD
        WorkflowController.php   # 簽核動作 + 歷程查詢（含狀態轉換驗證）
        AuthController.php       # 使用者登入
        ReportController.php     # 月度報表 + 每日配送清單
    
     Models/
        OrderHeader.php          # 申請單主檔 DAO
        OrderDetail.php          # 申請單明細 DAO
        WorkflowLog.php          # 簽核歷程 DAO
        User.php                 # 使用者 DAO
        Department.php           # 單位 DAO
        Budget.php               # 預算科目 DAO（含餘額扣減）
        MealItem.php             # 餐點項目 DAO
    
     Services/
        DateHelper.php           # 民國/西元日期轉換
        MealService.php          # 明細金額計算
        WorkflowService.php      # 簽核引擎輔助邏輯
        BudgetService.php        # 預算餘額驗證
    
     Templates/
        order_form.php           # 前端 SPA（四分頁介面）
    
     tools/
         check_tables.php         # DB 資料表驗證工具
         apply_sql_to_db.py       # 對 tongxin_meal 執行 SQL 的輔助工具
```

---

## 三、 資料庫 Schema（PostgreSQL）

資料庫名稱：`tongxin_meal`

### 主檔資料表

```sql
-- 單位
CREATE TABLE departments (
    dept_code  VARCHAR(20) PRIMARY KEY,
    dept_name  VARCHAR(100) NOT NULL
);

-- 使用者
CREATE TABLE users (
    user_id    SERIAL PRIMARY KEY,
    username   VARCHAR(50) NOT NULL,
    full_name  VARCHAR(100) NOT NULL,
    dept_code  VARCHAR(20) REFERENCES departments(dept_code),
    extension  VARCHAR(10),
    role       VARCHAR(20) DEFAULT 'applicant'  -- applicant, manager, admin, finance
);

-- 餐點項目
CREATE TABLE meal_items (
    item_id        SERIAL PRIMARY KEY,
    item_name      VARCHAR(50) NOT NULL,
    description    VARCHAR(100),
    standard_price DECIMAL(10,2) DEFAULT 0,
    unit           VARCHAR(10) DEFAULT '份'
);

-- 預算科目
CREATE TABLE budgets (
    budget_code  VARCHAR(50) PRIMARY KEY,
    subject_name VARCHAR(100),
    balance      DECIMAL(15,2) DEFAULT 0
);
```

### 交易資料表

```sql
-- 申請單主檔
CREATE TABLE order_headers (
    order_no           VARCHAR(20) PRIMARY KEY,  -- 如: S20260417abc123
    applicant_id       INTEGER REFERENCES users(user_id),
    dept_code          VARCHAR(20) REFERENCES departments(dept_code),
    apply_date         DATE DEFAULT CURRENT_DATE,
    meal_date          DATE NOT NULL,
    meal_time          TIME NOT NULL,
    meal_type          VARCHAR(20),
    location           TEXT,
    purpose            TEXT,
    budget_code        VARCHAR(50) REFERENCES budgets(budget_code),
    total_amount       DECIMAL(15,2) DEFAULT 0,
    status_code        VARCHAR(2) DEFAULT '1',
    current_handler_id INTEGER REFERENCES users(user_id),
    remarks            TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 申請單明細
CREATE TABLE order_details (
    detail_id      SERIAL PRIMARY KEY,
    order_no       VARCHAR(20) REFERENCES order_headers(order_no) ON DELETE CASCADE,
    item_id        INTEGER REFERENCES meal_items(item_id),
    quantity       INTEGER DEFAULT 0,
    price_per_unit DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(10) DEFAULT '自付',
    subtotal       DECIMAL(15,2) NOT NULL
);

-- 簽核歷程
CREATE TABLE workflow_logs (
    log_id      SERIAL PRIMARY KEY,
    order_no    VARCHAR(20) REFERENCES order_headers(order_no),
    sequence_no INTEGER NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_code VARCHAR(2),
    handler_id  INTEGER REFERENCES users(user_id),
    opinion     TEXT
);
```

### 狀態代碼表

| 代碼 | 說明 | 允許轉入 |
|---|---|---|
| `1` | 申請中 | `2`、`X` |
| `2` | 審核中 | `3`、`X` |
| `3` | 已核決 | `4`、`X` |
| `4` | 用膳中 | `5` |
| `5` | 待付款 | `6` |
| `6` | 已付款 | 終端 |
| `X` | 已銷案 | 終端 |

---

## 四、 API 端點一覽

伺服器預設監聽：`http://127.0.0.1:8000`

### 前端頁面

| 方法 | 路徑 | 說明 |
|---|---|---|
| `GET` | `/` | 系統主頁（四分頁 SPA HTML） |

### 申請單

| 方法 | 路徑 | 說明 |
|---|---|---|
| `GET` | `/orders` | 查詢列表，支援 `?status=` `?applicant_id=` |
| `POST` | `/orders` | 建立新申請單（JSON body，含 `details` 陣列） |
| `GET` | `/orders/{order_no}` | 取得單一申請單（含明細與簽核歷程） |

### 簽核

| 方法 | 路徑 | 說明 |
|---|---|---|
| `POST` | `/workflow/action` | 執行簽核（含狀態轉換驗證，非法轉換回傳 422） |
| `GET` | `/workflow/logs` | 查詢簽核歷程，需帶 `?order_no=` |

### 認證

| 方法 | 路徑 | 說明 |
|---|---|---|
| `POST` | `/auth/login` | 使用者登入（Demo：依 username 查詢，不驗密碼） |

### 報表

| 方法 | 路徑 | 說明 |
|---|---|---|
| `GET` | `/report/monthly` | 月度預算彙總，`?year=&month=` |
| `GET` | `/report/daily` | 每日配送清單，`?date=YYYY-MM-DD` |

### 主檔資料（供前端下拉）

| 方法 | 路徑 | 說明 |
|---|---|---|
| `GET` | `/api/meal-items` | 餐點項目清單 |
| `GET` | `/api/budgets` | 預算科目清單（含餘額） |
| `GET` | `/api/users` | 使用者清單 |
| `GET` | `/api/departments` | 單位清單 |

---

## 五、 前端功能說明（order_form.php）

單一 PHP 模板，載入後渲染為四分頁 SPA，無需任何前端框架：

### 分頁 1：申請表單
- 申請人、單位、預算科目下拉選單  動態從 `/api/users`、`/api/departments`、`/api/budgets` 載入
- 用餐日期、時間、餐別、地點、事由
- 訂餐明細動態列表：餐點下拉（從 `/api/meal-items` 載入）、份數、單價、付費方式、小計（即時計算）、合計金額
- 送出  `POST /orders`  顯示成功/失敗訊息與回傳 JSON

### 分頁 2：申請單查詢
- 狀態篩選下拉  `GET /orders?status=`
- 申請單列表（單號、日期、餐別、地點、金額、狀態徽章）
- 一鍵跳轉至「簽核作業」分頁

### 分頁 3：簽核作業
- 輸入單號  顯示申請單基本資訊、訂餐明細、簽核歷程
- 選擇新狀態、指定主辦人、輸入意見  `POST /workflow/action`
- 後端驗證狀態轉換合法性（非法轉換回傳 HTTP 422）

### 分頁 4：報表
- 月度預算報表：依年月查詢各預算科目申請金額彙總
- 每日配送清單：依日期查詢當日所有用餐申請（含 JSON 聚合明細）

---

## 六、 啟動指令

### 方法一：start.bat（推薦）

```bat
start.bat
```

自動設定 DB 環境變數、清除 Port 8000 舊程序、啟動 PHP 內建伺服器（`2>nul` 抑制 stderr）。

### 方法二：start.ps1

```powershell
powershell -ExecutionPolicy Bypass -File .\start.ps1
```

### 手動啟動

```powershell
$env:DB_HOST='127.0.0.1'; $env:DB_PORT='5432'; $env:DB_NAME='tongxin_meal'
$env:DB_USER='postgres'; $env:DB_PASS='1234'
php -S 127.0.0.1:8000 -t src 2>$null
```

---

## 七、 資料庫初始化

### 方法一：psql（推薦）

```powershell
$env:PGPASSWORD='1234'
psql -h 127.0.0.1 -U postgres -f .\init.sql
```

### 方法二：Python 輔助腳本（無 psql 環境時）

```powershell
$env:PGPASSWORD='1234'
python create_data.py --sql .\init.sql --host 127.0.0.1 --port 5432 --user postgres
```

### 驗證資料表

```powershell
$env:PGPASSWORD='1234'
psql -h 127.0.0.1 -U postgres -d tongxin_meal -c "\dt"
```

---

## 八、 開發規範

### PSR-4 Autoload

| 命名空間 | 對應目錄 |
|---|---|
| `App\Config\` | `src/Config/` |
| `App\Controllers\` | `src/Controllers/` |
| `App\Models\` | `src/Models/` |
| `App\Services\` | `src/Services/` |

### 路由慣例（index.php）

```php
if ($path === '/orders' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    (new OrderController($pdo))->list();
    exit;
}
// 含 ID 的路由使用 preg_match
if (preg_match('#^/orders/([A-Za-z0-9]+)$#', $path, $m) && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    (new OrderController($pdo))->show($m[1]);
    exit;
}
```

### 單號產生規則

```php
$order_no = 'S' . date('Ymd') . substr(uniqid(), -6);
// 範例：S20260417abc123
```

### 狀態轉換驗證

```php
private const TRANSITIONS = [
    '1' => ['2', 'X'],
    '2' => ['3', 'X'],
    '3' => ['4', 'X'],
    '4' => ['5'],
    '5' => ['6'],
    '6' => [],
    'X' => [],
];
```

非法轉換回傳 HTTP 422 + `{"error":"狀態轉換不合法：X  Y","allowed":[...]}`.

---

## 九、 已知限制與後續建議

| 項目 | 現況 | 建議改善 |
|---|---|---|
| 身份驗證 | Demo 模式，不驗密碼 | 加入 `password_hash` / Session / JWT |
| 預算扣款 | `Budget::deduct()` 已實作，但建立申請單時未自動呼叫 | 在 `OrderController::create()` 後呼叫 `BudgetService::reserve()` |
| 附件上傳 | 尚未實作 | 新增 `POST /orders/{order_no}/attachments` 端點 |
| 民國日期顯示 | 資料庫存西元，前端直接顯示西元 | 使用 `DateHelper::toROC()` 在前端格式化 |
| 分頁/搜尋 | `/orders` 一次回傳全部 | 加入 `?page=&limit=` 分頁參數 |
| 正式環境部署 | 僅支援 PHP 內建伺服器 | 改用 Apache + mod_php 或 Nginx + PHP-FPM（Windows 版） |
| 密碼/金鑰管理 | 硬編碼於環境變數腳本 | 使用 `.env` 檔 + `vlucas/phpdotenv` |
