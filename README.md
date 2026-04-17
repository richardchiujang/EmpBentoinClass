# 同心餐點費用申請系統

以 **PHP 8.5 + PostgreSQL** 實作的訂餐費用申請系統，支援申請、多層簽核、月度報表與每日配送清單，前端為純 HTML/JS 四分頁 SPA，無需任何前端框架。

---

## 前置條件

- PHP >= 8.5，啟用擴充：`pdo`、`pdo_pgsql`、`pgsql`

```powershell
php -m | findstr /I "pdo_pgsql pgsql"
```

- PostgreSQL 執行於 `127.0.0.1:5432`
- Composer（安裝套件用）

---

## 安裝

```powershell
composer install
```

---

## 資料庫初始化

```powershell
$env:PGPASSWORD='1234'
psql -h 127.0.0.1 -U postgres -f .\init.sql
```

> 無 `psql` 時可改用：`python create_data.py --sql .\init.sql --host 127.0.0.1 --port 5432 --user postgres`

---

## 啟動伺服器

### 方法一：start.bat（推薦）

```bat
start.bat
```

### 方法二：手動（PowerShell）

```powershell
$env:DB_HOST='127.0.0.1'; $env:DB_PORT='5432'; $env:DB_NAME='tongxin_meal'
$env:DB_USER='postgres'; $env:DB_PASS='1234'
php -S 127.0.0.1:8000 -t src 2>$null
```

啟動後開啟瀏覽器：**http://127.0.0.1:8000**

---

## 系統功能

| 分頁 | 功能 |
|---|---|
| 申請表單 | 填寫訂餐申請單，下拉選單動態載入，明細自動計算合計 |
| 申請單查詢 | 依狀態篩選，一鍵跳轉至簽核作業 |
| 簽核作業 | 輸入單號查看明細與歷程，執行狀態推進（含轉換驗證） |
| 報表 | 月度預算彙總、每日配送清單 |

---

## API 端點

| 方法 | 路徑 | 說明 |
|---|---|---|
| `GET` | `/` | 系統主頁 (HTML SPA) |
| `GET` | `/orders` | 申請單列表（`?status=` `?applicant_id=`） |
| `POST` | `/orders` | 建立申請單（JSON，含 `details` 陣列） |
| `GET` | `/orders/{order_no}` | 單一申請單（含明細與簽核歷程） |
| `POST` | `/workflow/action` | 執行簽核（非法狀態轉換回傳 422） |
| `GET` | `/workflow/logs` | 簽核歷程（`?order_no=`） |
| `POST` | `/auth/login` | 使用者登入 |
| `GET` | `/report/monthly` | 月度預算報表（`?year=&month=`） |
| `GET` | `/report/daily` | 每日配送清單（`?date=YYYY-MM-DD`） |
| `GET` | `/api/users` | 使用者清單 |
| `GET` | `/api/departments` | 單位清單 |
| `GET` | `/api/budgets` | 預算科目清單 |
| `GET` | `/api/meal-items` | 餐點項目清單 |

---

## 測試範例（curl）

**列出申請單**
```bash
curl http://127.0.0.1:8000/orders
```

**依狀態篩選**
```bash
curl "http://127.0.0.1:8000/orders?status=1"
```

**建立申請單**
```bash
curl -X POST http://127.0.0.1:8000/orders ^
  -H "Content-Type: application/json" ^
  -d "{\"applicant_id\":2,\"dept_code\":\"213000\",\"meal_date\":\"2026-04-20\",\"meal_time\":\"12:00\",\"meal_type\":\"午餐\",\"location\":\"C304\",\"purpose\":\"測試\",\"budget_code\":\"92213000-03-05\",\"total_amount\":450,\"details\":[{\"item_id\":1,\"quantity\":10,\"price_per_unit\":45,\"payment_method\":\"招待\",\"subtotal\":450}]}"
```

**執行簽核**
```bash
curl -X POST http://127.0.0.1:8000/workflow/action ^
  -H "Content-Type: application/json" ^
  -d "{\"order_no\":\"S20260417abc123\",\"status_code\":\"2\",\"handler_id\":3,\"opinion\":\"同意\"}"
```

**月度報表**
```bash
curl "http://127.0.0.1:8000/report/monthly?year=2026&month=4"
```

---

## 狀態代碼

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

## 專案結構

```
src/
 index.php                # 路由入口
 bootstrap.php            # Autoload + PDO 初始化
 Config/DB.php            # PDO 連線類別
 Controllers/             # Order / Workflow / Auth / Report
 Models/                  # OrderHeader / OrderDetail / User / Department / Budget / MealItem / WorkflowLog
 Services/                # DateHelper / MealService / WorkflowService / BudgetService
 Templates/order_form.php # 前端 SPA
```

詳細規格請參閱 [new_project_spec_inclass.md](new_project_spec_inclass.md)。
