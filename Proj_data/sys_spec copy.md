# 訂餐系統 Agentic Workflow 教學專案 - 系統規格書 (System Specification)

## 1. 專案背景
本文件基於既有傳統餐廳用膳申請單系統介面，提取核心業務邏輯與欄位設計，用於「訂餐系統 Agentic Workflow 教學專案」中的極簡系統實作設計參考。目的為在簡化欄位的同時，保留能體現多 Agent 協作特性的表單元素與狀態流轉機制。

## 2. 系統架構概念
* **前端 UI (PHP原生)**: 申請人填寫表單介面、查詢進度介面。
* **後端邏輯 (PHP原生)**: 處理表單提交，將資料寫入 PostgreSQL。
* **資料庫 (PostgreSQL + Docker)**: 儲存申請單主檔、單位清單、狀態變更紀錄。
* **智慧流程層 (OpenClaw Agents)**: 
  * 讀取未處理表單
  * 根據單位與其他條件判斷簽核層級（主管、承辦人）
  * 變更狀態並寫入審核歷程
  * 觸發通知

## 3. 資料欄位規劃 (Data Schema) - Refactored

本章節經過重構，以支援標準化的餐點品項選擇，取代原有的手動輸入。資料庫結構遵循第三正規化(3NF)，將訂單主檔與訂單明細分離。

### 3.1 申請單主檔 (`requests`)

| 欄位名稱 (中文) | 欄位名稱 (英/資料庫) | 型態 | 說明 / 來源對照 |
| --- | --- | --- | --- |
| 申請單號 | `id` | SERIAL (PK) | 系統自增主鍵 |
| 申請狀態 | `status` | VARCHAR | 記錄當前進度 (draft / submitted / approved 等) |
| 申請人ID | `applicant_id` | INTEGER | 申請者ID (對應教學帳號) |
| 申請人 | `applicant_name` | VARCHAR | 申請者姓名 |
| 申請單位 | `unit_id` | INTEGER (FK) | 關聯到 `units.id` |
| 申請日期 | `created_at` | TIMESTAMP | 系統自動帶入填寫日期 |

### 3.2 餐點主檔 (`meals`) (NEW)

存放所有可訂購的餐點品項。

| 欄位名稱 (中文) | 欄位名稱 (英/資料庫) | 型態 | 說明 |
| --- | --- | --- | --- |
| 餐點代碼 | `id` | SERIAL (PK) | 系統自增主鍵 |
| 分類 | `category` | VARCHAR | 主餐, 單點, 飲料 |
| 品項名稱 | `name` | VARCHAR | 例如: 排骨便當 |
| 單價 | `price` | INTEGER | 品項金額 |

### 3.3 訂單明細 (`order_items`) (NEW)

連結 `requests` 與 `meals`，記錄每筆申請單的詳細訂購內容。

| 欄位名稱 (中文) | 欄位名稱 (英/資料庫) | 型態 | 說明 |
| --- | --- | --- | --- |
| 明細代碼 | `id` | SERIAL (PK) | 系統自增主鍵 |
| 申請單號 | `request_id` | INTEGER (FK) | 關聯到 `requests.id` |
| 餐點代碼 | `meal_id` | INTEGER (FK) | 關聯到 `meals.id` |
| 數量 | `quantity` | INTEGER | 訂購此品項的數量 |

### 3.4 單位主檔 (`units`)

| 欄位名稱 (中文) | 欄位名稱 (英/資料庫) | 型態 | 說明 |
| --- | --- | --- | --- |
| 單位代碼 | `id` | SERIAL (PK) | 單位 ID (例如 213000) |
| 單位名稱 | `unit_name` | VARCHAR | 例如：電算中心 |
| 單位分類 | `unit_type` | VARCHAR | 用於 Agent 判斷流程的標籤 (general, teaching, center等) |

### 3.5 簽辦歷程 (`audit_logs`)

紀錄 Agent(或人員) 經手歷程，實作「簽辦過程」區塊。

| 欄位名稱 (中文) | 欄位名稱 (英/資料庫) | 型態 | 說明 / 來源對照 |
| --- | --- | --- | --- |
| 歷程代碼 | `id` | SERIAL (PK) | |
| 申請單號 | `request_id` | INTEGER (FK) | 關聯 `requests.id` |
| 簽辦日期 | `action_date` | TIMESTAMP | 處理時間 |
| 狀態階段 | `stage` | VARCHAR | 1,申請中 / 2,審核中 / 3,已核決 |
| 經辦人 | `operator` | VARCHAR | 處理者姓名 或 Agent 名稱 |
| 簽核意見 | `comment` | TEXT | 同意/退回理由 |

### 3.6 PostgreSQL 資料庫規格 (Table Spec / DDL)

```sql
-- 單位主檔
CREATE TABLE units (
    id SERIAL PRIMARY KEY,
    unit_name VARCHAR(100) NOT NULL,
    unit_type VARCHAR(50) NOT NULL
);

-- 餐點主檔 (NEW)
CREATE TABLE meals (
    id SERIAL PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    price INTEGER NOT NULL
);

-- 申請單主檔 (Refactored)
CREATE TABLE requests (
    id SERIAL PRIMARY KEY,
    status VARCHAR(50) DEFAULT 'draft',
    applicant_id INTEGER,
    applicant_name VARCHAR(100) NOT NULL,
    unit_id INTEGER REFERENCES units(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_flag BOOLEAN DEFAULT FALSE,
    audit_log TEXT
);

-- 訂單明細 (NEW)
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES requests(id),
    meal_id INTEGER REFERENCES meals(id),
    quantity INTEGER NOT NULL
);

-- 簽辦歷程表
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES requests(id),
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stage VARCHAR(50),
    operator VARCHAR(100),
    comment TEXT
);

-- 通知紀錄表
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES requests(id),
    content TEXT,
    channel VARCHAR(20) DEFAULT 'email',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 4. 系統狀態流轉定義 (State Machine)

教學版將原系統 13 個狀態收斂為 7 個，聚焦在 Agent 介入點的示範。

| 狀態值 | 中文顯示 | 觸發者 | 對應原系統狀態 |
| --- | --- | --- | --- |
| `draft` | 填寫中 | 使用者 | ─ |
| `submitted` | 申請中 | 使用者（送案） | 狀態 1 申請中 |
| `reviewing` | 審核中 | Application Agent | 狀態 2 審核中 |
| `approved` | 已核決 | Review Agent | 狀態 3 已核決 |
| `completed` | 已用膳 | Agent / 人工確認 | 狀態 4 用膳狀況 |
| `rejected` | 已退回 | Review Agent | ─ |
| `cancelled` | 已銷案 | 使用者 | 狀態 X 已銷案 |

**流轉圖：**
```
[draft] ──送案──► [submitted] ──App Agent──► [reviewing]
                                                   │
                                      ┌────────────┴────────────┐
                                   核准▼                      退回▼
                               [approved]                  [rejected]
                                   │
                               確認用膳▼
                               [completed]

[draft / submitted] ──使用者撤銷──► [cancelled]
```

---

## 5. Agentic Workflow 介入點 (Teaching Pivot)

系統本體（PHP）**不做任何流程判斷**，僅負責「存資料」。流程判斷完全由 Agent 負責。

| Agent 名稱 | 監聽狀態 | 動作 | 寫入結果 |
| --- | --- | --- | --- |
| Application Agent | `submitted` | 解析 `unit_type` 與申請內容，產生 JSON Context | 將 `status` 改為 `reviewing` |
| Review Agent | `reviewing` | 依 `unit_type` 判斷路徑，模擬多層審核 | 將 `status` 改為 `approved` 或 `rejected`，寫入 `audit_logs` |
| Notification Agent | `approved` / `rejected` | 依狀態生成通知文案 | 寫入 `notifications` 表 |

**單位差異流程（Review Agent 核心邏輯）：**

| unit_type | 流程路徑 |
| --- | --- |
| `general` | 直接審核 → 核准 |
| `teaching` | 模擬多一道確認（教學用途標注）→ 核准 |
| `center` | 走管理端路徑 → 核准 |

## 6. 使用者介面需求與畫面規格 (UI Wireframe)

### 6.1 申請頁面 (`create.php`)

**【畫面佈局】**
```text
+-------------------------------------------------------------+
| [LOGO]                 同心圓餐廳用膳申請單         [申請日期]  |
+-------------------------------------------------------------+
| 申請人: [自動帶入]           | 申請單位: [下拉選單]             |
| 分機號碼: [文字輸入框]       | 預算來源: [文字輸入框]             |
+-------------------------------------------------------------+
| 用餐日期: [日期選擇器]       | 用餐別: [早餐/午餐/晚餐/其他]    |
| 用餐時間: [時間選擇器]       | 用餐地點: [文字輸入框]             |
| 事由: [多行文字輸入框]                                        |
+-------------------------------------------------------------+
|               --- 餐點明細 (動態新增一列) ---                 |
| [類別下拉] [品項/單價下拉] [份數]               [小計: $$$] |
| [+] 新增品項                                                |
+-------------------------------------------------------------+
|                                           總金額: [ $$$ ] |
+-------------------------------------------------------------+
| 注意事項:                                                     |
| 1. 訂餐請於三日前提出申請...                                  |
| 2. 請...                                                    |
+-------------------------------------------------------------+
| 下站主辦人: [下拉選單]                 [ 確定送案 (Submit) ] |
+-------------------------------------------------------------+
```

**【行為規格】**
* **載入時**：預設帶入當日日期與登入者(模擬)名稱。
* **餐點明細區**：此區域對應到 `order_items` 與 `meals` 資料表。使用者可點擊 `[+]` 動態增加列數。
  * `[類別下拉]`：對應 `meals.category`。
  * `[品項/單價下拉]`：根據選擇的類別，動態載入 `meals.name` 與 `meals.price`。
  * `[份數]`：使用者輸入，對應 `order_items.quantity`。
  * `[小計]` 與 `總金額`: 透過 JavaScript 或 AJAX 即時計算。
* **送案按鈕**：點擊後，一筆資料寫入 `requests` 表，多筆資料寫入 `order_items` 表。狀態設為 `submitted`，並跳轉至列表頁。

---

### 6.2 查詢列表頁面 / 看板 (`list.php`)

**【畫面佈局】**
```text
+-------------------------------------------------------------+
|  [首頁] [新增申請]                       篩選狀態: [下拉選單] |
+-------------------------------------------------------------+
| 單號   | 申請人 | 單位     | 總金額 | 當前狀態   | 動作         |
|--------|--------|----------|--------|------------|--------------|
| S001   | 林承訓 | 電算中心 | $125   | 審核中     | [檢視明細]   |
| S002   | 王小明 | 醫學系   | $180   | 申請中     | [檢視明細]   |
| S003   | 陳怡君 | 庶務組   | $155   | 已核決     | [檢視明細]   |
+-------------------------------------------------------------+
| < 上一頁                                   第 1/5 頁 下一頁 > |
+-------------------------------------------------------------+
```

**【行為規格】**
* **總金額**: 此欄位需在後端透過 `JOIN order_items and meals` 動態計算 `SUM(price * quantity)` 而得。
* **狀態篩選**：允許使用者根據狀態篩選單據。
* **動作**：點擊 `[檢視明細]` 即跳轉至 `view.php?id=xxx`。

---

### 6.3 單據明細與歷程頁面 (`view.php`)

**【畫面佈局】**
```text
+-------------------------------------------------------------+
| [LOGO]                 同心圓餐廳用膳申請單         [返回列表]  |
+-------------------------------------------------------------+
|              ... [唯讀的表單主檔資訊] ...                      |
+-------------------------------------------------------------+
|                     --- 訂單明細 ---                          |
| 品項 (Category) | 名稱 (Name) | 單價 | 數量 | 小計      |
|-----------------|-------------|------|------|-----------|
| 主餐            | 排骨便當    | 100  | 1    | 100       |
| 飲料            | 冰紅茶      | 25   | 1    | 25        |
+-------------------------------------------------------------+
|                                           總金額: [ 125 ]   |
+-------------------------------------------------------------+

                  ↓↓↓ 重點教學展示區 ↓↓↓

+-------------------------------------------------------------+
| 簽辦過程 (Audit Logs)                                       |
+-------------------------------------------------------------+
| 序號 | 簽辦日期        | 狀態   | 經辦人         | 意見           |
|------|-----------------|--------|----------------|----------------|
|  1   | 08/06 10:00     | 申請中 | 林承訓         | 申請送案給劉永森 |
|  2   | 08/06 10:30     | 審核中 | Review_Agent   | 敬表同意 [自動]  |
|  3   | 08/07 09:15     | 已核決 | Notif_Agent    | 已發送取餐通知  |
+-------------------------------------------------------------+
```

**【行為規格】**
* **唯讀顯示**：主檔資料不可編輯。
* **訂單明細**：以 Table 形式條列出 `order_items` 關聯紀錄。
* **簽辦歷程**：以 Table 形式條列列出 `audit_logs` 關聯紀錄，按時間正序排列。這裡是展示 Agent 運作軌跡的關鍵畫面。

## 7. 系統帳號與權限規劃 (Permission Planning)

### 7.1 角色定義

教學版採 **PHP Session + Hardcoded 帳號**，不建 `users` 資料庫表，符合「極簡化」原則。

| 角色 | 英文代號 | 測試帳號 / 密碼 | 說明 |
| --- | --- | --- | --- |
| 一般員工 | `staff` | `staff01 / 1234` | 申請者角色 |
| 審核主管 | `manager` | `manager01 / 1234` | 模擬審核端 |
| 系統管理員 | `admin` | `admin / 1234` | 課堂展示用 |

### 7.2 功能權限矩陣

| 功能 | staff | manager | admin |
| --- | --- | --- | --- |
| 新增申請 | ✅ | ✅ | ✅ |
| 查看自己的申請 | ✅ | ✅ | ✅ |
| 查看全部申請 | ❌ | ✅ | ✅ |
| 預設篩選 submitted | ❌ | ✅（模擬審核端）| ─ |
| 刪除自己的草稿 | ✅ | ✅ | ✅ |
| 刪除任何人的草稿 | ❌ | ❌ | ✅ |
| 查看通知中心 | ✅（自己的）| ✅ | ✅ |

### 7.3 實作說明

* `login.php`：極簡登入頁，比對 hardcoded 帳號，寫入 `$_SESSION['user']` / `$_SESSION['role']`。
* `config.php` 新增三個輔助函式：
  * `requireLogin()` — 未登入時跳轉 `login.php`
  * `getCurrentRole()` — 回傳 `$_SESSION['role']`
  * `getCurrentUser()` — 回傳 `$_SESSION['user']`
