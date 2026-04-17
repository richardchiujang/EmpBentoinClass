# 系統環境邊界 

**這專案系統環境變更為 沒有 wsl linux docker 以下與此相關說明均忽視**


# 訂餐系統 Agentic Workflow 教學專案 - 系統規格書 (System Specification)

## 1. 專案背景
本文件基於既有真實系統「**同心餐點費用申請系統**」的介面截圖分析（詳見 `原訂餐系統畫面規格分析.md`），提取核心業務邏輯與欄位設計，用於「訂餐系統 Agentic Workflow 教學專案」中的極簡系統實作參考。目的在簡化欄位的同時，保留能體現多 Agent 協作特性的表單元素與狀態流轉機制。

## 2. 系統架構概念
* **前端 UI (PHP原生)**: 申請人填寫表單介面、查詢進度介面。
* **後端邏輯 (PHP原生)**: 處理表單提交，將資料寫入 PostgreSQL。
* **資料庫 (PostgreSQL + Docker)**: 儲存 6 張資料表：`units`、`meals`、`requests`、`order_items`、`audit_logs`、`notifications`。
* **智慧流程層 (OpenClaw Agents)**:
  * 讀取 `status = submitted` 的未處理申請
  * 根據單位類型（`unit_type`）判斷簽核路徑
  * 變更狀態並寫入審核歷程
  * 觸發通知

注意：`PHP + PostgreSQL` 可獨立啟動並支援表單的建立、查詢與顯示；有無 Agent 都能正常運作基本 CRUD。Agent 層提供的是「自動化流程」能力（例如自動從 `submitted` 推進到 `reviewing`、自動核准或發送通知），在沒有 Agent 時，該等流程需改由人工或系統內的手動操作推進（或由 PHP 實作備援邏輯）。

## 3. 資料欄位規劃 (Data Schema)

資料庫結構遵循第三正規化 (3NF)，將訂單主檔與訂單明細分離。欄位設計已依原系統介面分析（`原訂餐系統畫面規格分析.md`）完整納入所有必要欄位。

### 3.1 申請單主檔 (`requests`)

| 欄位名稱 (中文) | 欄位名稱 (DB) | 型態 | 說明 |
| --- | --- | --- | --- |
| 申請單號 (系統) | `id` | SERIAL (PK) | 系統自增主鍵 |
| 申請單號 (展示) | `request_no` | VARCHAR(20) | 系統產生，格式 `S260001`（S + 年後2碼 + 4位序） |
| 申請狀態 | `status` | VARCHAR(50) | 詳見 Section 4 狀態機 |
| 申請人ID | `applicant_id` | INTEGER | 對應 Session 教學帳號 |
| 申請人姓名 | `applicant_name` | VARCHAR(100) | 必填 |
| 申請人分機 | `extension` | VARCHAR(20) | 聯絡分機，對應原系統「申請人電話分機」 |
| 申請單位 | `unit_id` | INTEGER (FK) | 關聯到 `units.id` |
| 預算來源 | `budget_source` | VARCHAR(150) | 計畫/科目描述 |
| 用餐日期 | `meal_date` | DATE | 必填 |
| 用餐別 | `meal_type` | VARCHAR(20) | breakfast / lunch / afternoon_tea / dinner / other |
| 用餐時間 | `meal_time` | TIME | HH:MM 格式 |
| 用餐地點 | `meal_location` | VARCHAR(200) | 自由輸入 |
| 用餐事由 | `meal_reason` | TEXT | 活動或會議說明 |
| 補充說明 | `notes` | TEXT | 申請配合事項，對應原系統大文字框 |
| 下站主辦人 | `next_operator` | VARCHAR(100) | 送案給下一位經手人 |
| 申請日期 | `created_at` | TIMESTAMP | 系統自動 |
| Agent 處理旗標 | `processed_flag` | BOOLEAN | 防止 Agent 重複處理 |
| Agent 決策紀錄 | `audit_log` | TEXT | Agent 決策摘要（除錯用）|

### 3.2 餐點主檔 (`meals`)

存放所有可訂購的餐點品項，對齊原系統可見的 9 類餐別。

| 欄位名稱 (中文) | 欄位名稱 (DB) | 型態 | 說明 |
| --- | --- | --- | --- |
| 餐點代碼 | `id` | SERIAL (PK) | 系統自增主鍵 |
| 分類 | `category` | VARCHAR(50) | 見下方種子資料 |
| 品項名稱 | `name` | VARCHAR(100) | 例如: 環保便當 |
| 單價 | `price` | INTEGER | 品項金額；「其他」類別初始為 0，前端可手動輸入 |

**種子資料（對齊原系統 9 類）：**

| 類別 | 品項 | 單價 | 備註 |
| --- | --- | --- | --- |
| 便當 | 環保便當 | 45 | 原系統 45元 |
| 茶水 | 紅茶飲料 | 200 | 原系統 200元/批 |
| 中式快餐 | 中式快餐 | 100 | 每人份 |
| 點心 | 綜合點心 | 30 | 三樣點心每份 |
| 歐式自助餐 | 歐式自助餐 | 100 | 每人份 |
| 早餐 | 早餐 | 25 | 每人份 |
| 午餐 | 午餐（打菜） | 45 | 每人份 |
| 晚餐 | 晚餐（打菜） | 45 | 每人份 |
| 其他 | 其他（自訂） | 0 | 前端允許手動輸入單價 |

### 3.3 訂單明細 (`order_items`)

連結 `requests` 與 `meals`，記錄每筆申請單的詳細訂購內容。

| 欄位名稱 (中文) | 欄位名稱 (DB) | 型態 | 說明 |
| --- | --- | --- | --- |
| 明細代碼 | `id` | SERIAL (PK) | 系統自增主鍵 |
| 申請單號 | `request_id` | INTEGER (FK) | 關聯到 `requests.id` |
| 餐點代碼 | `meal_id` | INTEGER (FK) | 關聯到 `meals.id` |
| 份數 | `quantity` | INTEGER | 訂購份數 |
| 付費方式 | `payment_method` | VARCHAR(10) | `自付` / `招待`（原系統每列餐點明細獨立設定） |

### 3.4 單位主檔 (`units`)

| 欄位名稱 (中文) | 欄位名稱 (DB) | 型態 | 說明 |
| --- | --- | --- | --- |
| 單位代碼 | `id` | SERIAL (PK) | |
| 單位名稱 | `unit_name` | VARCHAR(100) | 例如：電算中心 |
| 單位分類 | `unit_type` | VARCHAR(50) | Agent 判斷流程標籤：`general` / `teaching` / `center` |

### 3.5 簽辦歷程 (`audit_logs`)

紀錄 Agent（或人員）經手歷程，為教學核心展示區塊。

| 欄位名稱 (中文) | 欄位名稱 (DB) | 型態 | 說明 |
| --- | --- | --- | --- |
| 歷程代碼 | `id` | SERIAL (PK) | |
| 申請單號 | `request_id` | INTEGER (FK) | 關聯 `requests.id` |
| 簽辦日期 | `action_date` | TIMESTAMP | 處理時間 |
| 狀態階段 | `stage` | VARCHAR(50) | 申請中 / 審核中 / 已核決 等 |
| 經辦人 | `operator` | VARCHAR(100) | 人員姓名或 Agent 名稱（如 `Review_Agent`）|
| 簽核意見 | `comment` | TEXT | 同意 / 退回理由 / Agent 決策說明 |

### 3.6 通知紀錄表 (`notifications`)

| 欄位名稱 (中文) | 欄位名稱 (DB) | 型態 | 說明 |
| --- | --- | --- | --- |
| 通知代碼 | `id` | SERIAL (PK) | |
| 申請單號 | `request_id` | INTEGER (FK) | 關聯 `requests.id` |
| 訊息內容 | `message` | TEXT | 通知正文 |
| 目標角色 | `target_role` | VARCHAR(50) | `staff` / `manager` / `admin` |
| 已讀旗標 | `read_flag` | BOOLEAN | 預設 FALSE |
| 建立時間 | `created_at` | TIMESTAMP | |

### 3.7 完整 DDL (PostgreSQL)

```sql
-- 單位主檔
CREATE TABLE units (
    id SERIAL PRIMARY KEY,
    unit_name VARCHAR(100) NOT NULL,
    unit_type VARCHAR(50) NOT NULL
);

-- 餐點主檔
CREATE TABLE meals (
    id SERIAL PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    price INTEGER NOT NULL DEFAULT 0
);

-- 申請單主檔
CREATE TABLE requests (
    id SERIAL PRIMARY KEY,
    request_no VARCHAR(20),
    status VARCHAR(50) DEFAULT 'draft',
    applicant_id INTEGER,
    applicant_name VARCHAR(100) NOT NULL,
    extension VARCHAR(20),
    unit_id INTEGER REFERENCES units(id),
    budget_source VARCHAR(150),
    meal_date DATE,
    meal_type VARCHAR(20),
    meal_time TIME,
    meal_location VARCHAR(200),
    meal_reason TEXT,
    notes TEXT,
    next_operator VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_flag BOOLEAN DEFAULT FALSE,
    audit_log TEXT
);

-- 訂單明細
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES requests(id) ON DELETE CASCADE,
    meal_id INTEGER REFERENCES meals(id),
    quantity INTEGER NOT NULL DEFAULT 1,
    payment_method VARCHAR(10) DEFAULT '自付'
);

-- 簽辦歷程
CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES requests(id),
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stage VARCHAR(50),
    operator VARCHAR(100),
    comment TEXT
);

-- 通知紀錄
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    request_id INTEGER REFERENCES requests(id),
    message TEXT,
    target_role VARCHAR(50) DEFAULT 'staff',
    read_flag BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 4. 系統狀態流轉定義 (State Machine)

本章提供「可直接實作」的流程規格，重點是：

1. 角色可視資料必須隔離。
2. 狀態轉移必須由後端硬性驗證。
3. 通知必須依角色與關卡精準發送。
4. 前端只負責呈現，實際權限由後端決定。

### 4.1 狀態定義

| 狀態值 | 中文顯示 | 意義 |
| --- | --- | --- |
| `draft` | 填寫中 | 尚未送案，僅申請人可編修 |
| `submitted` | 已送案 | 已送出，等待第一關簽核 |
| `reviewing` | 審核中 | 正在簽核鏈中流轉 |
| `approved` | 已核決 | 最終簽核完成 |
| `completed` | 已用膳 | 管理端確認結案 |
| `rejected` | 已退回 | 任一簽核節點退回（終態） |
| `cancelled` | 已銷案 | 申請人撤銷（終態） |

### 4.2 標準流轉圖（強制）

```
[draft] --送案--> [submitted] --簽核接手--> [reviewing]
                                             |          \
                                             |本關核准   \本關退回
                                             v           v
                                        [reviewing]   [rejected]
                                             |
                                             |最後一關核准
                                             v
                                        [approved] --確認用膳--> [completed]

[draft/submitted] --申請人撤銷--> [cancelled]
```

### 4.3 狀態轉移矩陣（後端必檢）

| 當前狀態 | 動作 | 執行者 | 目標狀態 | 必要條件 |
| --- | --- | --- | --- | --- |
| `draft` | 儲存草稿 | 申請人 | `draft` | 僅本人可編修 |
| `draft` | 送案 | 申請人 | `submitted` | 至少 1 筆 `order_items` |
| `submitted` | 撤銷 | 申請人 | `cancelled` | 僅申請人本人 |
| `submitted`/`reviewing` | 本關核准 | `next_operator` 或 `admin` | `reviewing` 或 `approved` | 若仍有下一關，維持 `reviewing` 並更新 `next_operator` |
| `submitted`/`reviewing` | 本關退回 | `next_operator` 或 `admin` | `rejected` | 必填退回意見 |
| `approved` | 標記已用膳 | `manager` 或 `admin` | `completed` | 不可回退 |

**終態規則：** `cancelled`、`rejected`、`completed` 為終態，不可再變更。

### 4.4 固定簽核鏈（教學版）

| 順序 | 節點帳號 | 角色 | 說明 |
| --- | --- | --- | --- |
| 1 | `manager01` | `manager` | 主管初審 |
| 2 | `restaurant01` | `restaurant` | 餐廳供膳審核 |
| 3 | `finance01` | `finance` | 財務預算審核（最終核准） |

### 4.5 角色可視資料範圍（Data Visibility）

| 角色 | 可以看到哪些資料 | 不可看到 |
| --- | --- | --- |
| `staff` | `applicant_id = 自己` 的申請（含 draft、歷史） | 其他員工所有申請 |
| `manager` | 自己提出的申請 + 指派到自己簽核的申請 + 自己所屬員工（`users.manager_username = 自己`）提出的申請 | 非自己、非自己待簽核、非自己轄下員工的案件 |
| `restaurant` | `next_operator = restaurant01` 的待簽核案件 + 必要已核決查詢 | 與供膳無關案件 |
| `finance` | `next_operator = finance01` 的待簽核案件 + 必要已核決/已完成查詢 | 非財務關卡案件 |
| `admin` | 全部資料 | 無 |

**實作要求：**

1. 以上可視範圍必須在 SQL `WHERE` 條件實作，不可只在前端隱藏。
2. 所有 `view/edit/update/delete` API 或頁面都必須套用同一授權邏輯。
3. 主管視角必須以 `users.manager_username` 判斷轄下員工，不得以前端傳入名單判斷。

### 4.6 角色操作權限（Action Permission）

| 功能 | staff | manager | restaurant | finance | admin |
| --- | --- | --- | --- | --- | --- |
| 建立/編輯自己的草稿 | ✅ | ✅ | ❌ | ❌ | ✅ |
| 送案 | ✅ | ✅ | ❌ | ❌ | ✅ |
| 撤銷（draft/submitted） | ✅（僅自己） | ✅（僅自己） | ❌ | ❌ | ✅ |
| 本關核准/退回 | ❌ | ✅（限 `next_operator=manager01`） | ✅（限 `next_operator=restaurant01`） | ✅（限 `next_operator=finance01`） | ✅ |
| 標記 `completed` | ❌ | ✅ | ❌ | ❌ | ✅ |
| 管理資料表 CRUD | ❌ | ❌ | ❌ | ❌ | ✅ |

### 4.7 通知規則（Role-based Notifications）

狀態異動時，通知寫入 `notifications`，並依角色路由：

| 觸發事件 | 通知對象 | target_role | 範例訊息 |
| --- | --- | --- | --- |
| 送案 `draft -> submitted` | 第一關簽核人（主管） | `manager` | `單號 Sxxxxxx 待您審核` |
| 本關核准且有下一關 | 下一關簽核人 | `restaurant` 或 `finance` | `單號 Sxxxxxx 已流轉至您的簽核關卡` |
| 最終核准 `-> approved` | 申請人 + 管理端 | `staff` / `manager` | `單號 Sxxxxxx 已核准` |
| 退回 `-> rejected` | 申請人 | `staff` | `單號 Sxxxxxx 已退回，請查看意見` |
| 撤銷 `-> cancelled` | 相關簽核角色（可選） | `manager` | `單號 Sxxxxxx 已由申請人撤銷` |
| 完成 `-> completed` | 申請人 + 財務 | `staff` / `finance` | `單號 Sxxxxxx 已完成用膳` |

### 4.8 後端防呆與一致性規範（Vibe Coding 實作重點）

1. 所有狀態變更以 transaction 包覆：更新 `requests` + 寫 `audit_logs` + 寫 `notifications` 必須同成敗。
2. 狀態更新必須包含 guard 條件：
   `WHERE id=:id AND status IN (...) AND (next_operator=:currentUser OR :isAdmin=true)`。
3. 退回必須有 comment，空字串視為非法操作。
4. 終態更新必須直接拒絕，回傳明確錯誤碼（例如 `invalid_transition`）。
5. 所有人工操作與 Agent 操作都必須寫入 `audit_logs`，且標明 operator。

### 4.9 Migration SQL 清單（不刪資料，正式推薦）

以下為增量 migration（可重複執行、保留既有資料）：

```sql
-- M01: requests 補強欄位
ALTER TABLE requests ADD COLUMN IF NOT EXISTS next_operator VARCHAR(100);
ALTER TABLE requests ADD COLUMN IF NOT EXISTS processed_flag BOOLEAN DEFAULT FALSE;
ALTER TABLE requests ADD COLUMN IF NOT EXISTS audit_log TEXT;
ALTER TABLE requests ADD COLUMN IF NOT EXISTS version_no INTEGER DEFAULT 1;

-- M02: 使用者主檔（角色與資料隔離）
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL,
    unit_id INTEGER REFERENCES units(id),
    manager_username VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- M03: 簽核鏈設定表
CREATE TABLE IF NOT EXISTS approval_steps (
    id SERIAL PRIMARY KEY,
    step_order INTEGER NOT NULL,
    operator_username VARCHAR(50) NOT NULL,
    role VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    UNIQUE(step_order)
);

-- M04: 狀態轉移審計表
CREATE TABLE IF NOT EXISTS request_transitions (
    id SERIAL PRIMARY KEY,
    request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    operator VARCHAR(100) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- M05: 索引優化
CREATE INDEX IF NOT EXISTS idx_requests_status ON requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_next_operator ON requests(next_operator);
CREATE INDEX IF NOT EXISTS idx_requests_unit_id ON requests(unit_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_request_id ON audit_logs(request_id);
CREATE INDEX IF NOT EXISTS idx_notifications_request_id ON notifications(request_id);
CREATE INDEX IF NOT EXISTS idx_users_manager_username ON users(manager_username);

-- M06: 角色種子
INSERT INTO users (username, display_name, role, unit_id, manager_username)
VALUES
('admin', 'Admin', 'admin', NULL, NULL),
('restaurant01', 'Restaurant01', 'restaurant', 5, NULL),
('finance01', 'Finance01', 'finance', 6, NULL),
('manager01', 'Manager01', 'manager', 1, NULL),
('manager02', 'Manager02', 'manager', 2, NULL),
('manager03', 'Manager03', 'manager', 3, NULL),
('manager04', 'Manager04', 'manager', 4, NULL),
('manager05', 'Manager05', 'manager', 5, NULL),
('staff01', 'Staff01', 'staff', 1, 'manager01'),
('staff02', 'Staff02', 'staff', 1, 'manager01'),
('staff03', 'Staff03', 'staff', 2, 'manager02'),
('staff04', 'Staff04', 'staff', 2, 'manager02'),
('staff05', 'Staff05', 'staff', 2, 'manager02'),
('staff06', 'Staff06', 'staff', 3, 'manager03'),
('staff07', 'Staff07', 'staff', 3, 'manager03'),
('staff08', 'Staff08', 'staff', 3, 'manager03'),
('staff09', 'Staff09', 'staff', 3, 'manager03'),
('staff10', 'Staff10', 'staff', 4, 'manager04'),
('staff11', 'Staff11', 'staff', 4, 'manager04'),
('staff12', 'Staff12', 'staff', 4, 'manager04'),
('staff13', 'Staff13', 'staff', 4, 'manager04'),
('staff14', 'Staff14', 'staff', 4, 'manager04'),
('staff15', 'Staff15', 'staff', 5, 'manager05'),
('staff16', 'Staff16', 'staff', 5, 'manager05'),
('staff17', 'Staff17', 'staff', 5, 'manager05'),
('staff18', 'Staff18', 'staff', 5, 'manager05'),
('staff19', 'Staff19', 'staff', 5, 'manager05'),
('staff20', 'Staff20', 'staff', 5, 'manager05')
ON CONFLICT (username) DO UPDATE
SET display_name = EXCLUDED.display_name,
    role = EXCLUDED.role,
    unit_id = EXCLUDED.unit_id,
    manager_username = EXCLUDED.manager_username,
    is_active = TRUE;

-- M07: 固定簽核鏈種子
INSERT INTO approval_steps (step_order, operator_username, role)
VALUES
(1, 'manager01', 'manager'),
(2, 'restaurant01', 'restaurant'),
(3, 'finance01', 'finance')
ON CONFLICT (step_order) DO UPDATE
SET operator_username = EXCLUDED.operator_username,
    role = EXCLUDED.role,
    is_active = TRUE;

-- M08: 回填待簽核人
UPDATE requests
SET next_operator = 'manager01'
WHERE next_operator IS NULL
  AND status IN ('submitted', 'reviewing');
```

### 4.9.1 測試資料規劃（主管-員工比例）

為了驗證資料隔離與主管視角，本規格要求最少準備以下關係：

| 主管 | 帶領員工數 | 員工帳號範圍 |
| --- | --- | --- |
| `manager01` | 2 人 | `staff01` ~ `staff02` |
| `manager02` | 3 人 | `staff03` ~ `staff05` |
| `manager03` | 4 人 | `staff06` ~ `staff09` |
| `manager04` | 5 人 | `staff10` ~ `staff14` |
| `manager05` | 6 人 | `staff15` ~ `staff20` |

用途：驗證不同主管登入後，是否只看到自己轄下員工 + 自己的案件。

### 4.10 驗收條件（Definition of Done）

1. `staff01` 登入只看得到自己的申請。
2. `manager01` 登入看得到自己待簽核案件，不會看到與自己無關案件。
3. `restaurant01`、`finance01` 僅看到各自關卡待簽核資料。
4. 任一角色無法越權核准非自己關卡（`admin` 除外）。
5. 任一終態案件不可再被核准或退回。
6. 每次狀態異動都能在 `audit_logs` 和 `notifications` 查到對應紀錄。

## 5. Agentic Workflow 介入點 (Teaching Pivot)

本章定義 Agent 在教學中的「責任邊界、實作方式、驗收標準」。目標不是取代系統本體，而是示範如何把流程決策抽離為可演進的智慧流程層。

### 5.0 實作決議（Gateway 優先）

為了課堂示範的一致性與可觀測性，本專案採用官方 OpenClaw Gateway（Node.js）作為 Agent 管理與 Control UI 提供者，並將原先 repository 中的 Python worker (`openclaw_agent.py`) 停用或移為備援實作。理由：

- Gateway 提供內建 Control UI（port 18789）、健康檢查、與官方 onboarding/config 工具，便於教學展示。 
- 保持 DB（`requests` / `audit_logs` / `notifications`）為唯一資料契約，Agent 透過 Gateway 與 DB 協作。

短期執行步驟：

1. 在 `docker-compose.yml` 中新增 `openclaw-gateway` 服務（或使用官方 image），並 publish `18789:18789`。
2. 透過 `docker compose run --rm --no-deps --entrypoint node openclaw-gateway dist/index.js onboard --mode local --no-install-daemon` 完成 onboarding。
3. 寫入 gateway config（`gateway.bind=lan`, `gateway.controlUi.allowedOrigins` 等），再以 `docker compose up -d openclaw-gateway` 啟動。 
4. 停用原來的 `openclaw_agent`（Python），以避免 Agent 重複處理相同單據；原 Python 程式可保留為教學示例或備援。 

驗收標準（變更後）：

- Control UI 可在 `http://127.0.0.1:18789/` 開啟並登入。
- Application/Review/Notification Agent 由 Gateway 管理且可觀察到 `audit_logs` 與 `notifications` 的寫入。
- 啟動 Gateway 後，原 Python worker 不再主動處理 `submitted` 單據（或被移入停用狀態）。

### 5.1 介入目標與邊界

* **系統（PHP/Node）負責**：收單、查單、顯示、手動操作入口。
* **Agent（OpenClaw）負責**：根據狀態與規則自動推進流程。
* **共通約束**：不論人工或 Agent，皆必須遵守 Section 4 狀態機、授權規則、交易一致性。

### 5.2 三代理責任矩陣

| Agent 名稱 | 觸發條件 | 核心動作 | 必寫入資料 |
| --- | --- | --- | --- |
| Application Agent | `status='submitted'` 且 `processed_flag=false` | 取得申請內容、建立 workflow context、鎖定單據 | `requests.processed_flag=true`、`audit_logs`（stage=`reviewing`） |
| Review Agent | `status='reviewing'` | 依 `unit_type` + 固定簽核鏈決策，更新下一關或最終結果 | `requests.status`、`requests.next_operator`、`audit_logs` |
| Notification Agent | `status in ('approved','rejected','completed','cancelled')` 且未通知 | 產生角色化通知文案並落地 | `notifications`、`audit_logs`（可選） |

### 5.3 OpenClaw 自動化申請流程（教學計畫）

以下流程可作為課堂實作主線，逐步展示「資料驅動 + Agent 協作」：

1. **建立可觀測資料契約**
   確認 `requests`、`audit_logs`、`notifications` 欄位完整，並先套用 Section 4.9 的 migration（M01–M08）。
2. **建立 Agent 共同輸入格式**
   由 Application Agent 產生標準 JSON context（request_id、unit_type、status、next_operator、order summary），供 Review/Notification 共用。
3. **實作 Application Agent（submitted -> reviewing）**
   用輪詢抓取待處理單，先鎖定再處理，成功後寫入 `reviewing` 與第一筆簽辦紀錄。
4. **實作 Review Agent（reviewing -> reviewing/approved/rejected）**
   依固定簽核鏈（`manager01 -> restaurant01 -> finance01`）推進；若尚有下一關，維持 `reviewing` 並更新 `next_operator`；最後一關核准才改 `approved`。
5. **實作 Notification Agent（結果通知）**
   依事件路由通知對象：待審通知下一關、核准/退回通知申請人、完成通知申請人與財務。
6. **做端到端教學驗收**
   從 `draft -> submitted` 送案後，觀察 Agent 自動推進與 `audit_logs`、`notifications` 的落地結果。

### 5.4 Review Agent 的單位差異規則（教學示範版）

| unit_type | 教學決策重點 | 範例結果 |
| --- | --- | --- |
| `general` | 一般單位走標準簽核鏈 | 正常逐關推進到 `approved` |
| `teaching` | 教學單位可加入「補充確認」提示 | 在 `audit_logs.comment` 記錄教學確認訊息後續簽 |
| `center` | 中心單位可由管理端優先處理 | 由 `manager/admin` 先行審核，再依鏈路收斂 |

> 備註：`unit_type` 影響的是「審核註記與路由策略」，不應破壞 Section 4 的狀態機合法轉移。

### 5.5 OpenClaw 實作規範（安全與穩定）

1. **網路隔離**：Agent 容器不對外暴露 `ports`，僅連內網 `db:5432` 與外部 LLM API。
2. **最小權限**：Agent 使用獨立 DB 帳號，限制 `SELECT/UPDATE/INSERT` 於必要資料表。
3. **交易一致性**：每次狀態變更以 transaction 實作：更新 `requests` + 寫 `audit_logs` + 寫 `notifications`（若事件需通知）。
4. **防重入機制**：使用 `processed_flag`、`status` guard、必要時 `version_no`（樂觀鎖）避免重複處理。
5. **可追溯性**：所有 Agent 需標示 `operator`（例如 `Application_Agent`），不可省略軌跡。

### 5.6 教學操作腳本（建議課堂節奏）

| 階段 | 教學操作 | 預期觀察 |
| --- | --- | --- |
| Step A | 啟動 `db` + `php`，由 `staff01` 送出申請 | `requests.status='submitted'` |
| Step B | 啟動 Application Agent | 案件變為 `reviewing`，`audit_logs` 新增一筆 |
| Step C | 啟動 Review Agent 或模擬輪詢一次 | `next_operator` 流轉，最終到 `approved`/`rejected` |
| Step D | 啟動 Notification Agent | `notifications` 出現對應角色訊息 |
| Step E | 關閉 Agent 改手動審核 | 驗證人工備援仍符合狀態機 |

### 5.7 驗收與評分重點（對應 DoD）

* 至少完成一筆案件由 Agent 自動推進到終態（`approved` 或 `rejected`）。
* 每次狀態改變都可在 `audit_logs` 查到對應 `operator` 與 `comment`。
* 通知內容與對象符合 Section 4.7 路由規則。
* 關閉 Agent 後，人工流程仍可在同一套狀態機下正確運作。

---

## 6. 使用者介面需求與畫面規格 (UI Wireframe)

**設計原則：填表順序遵循「人 → 時間/事件 → 要什麼 → 補充 → 送案」自然流程**

### 6.1 申請頁面 (`create.php`)

**【畫面佈局】**
```text
+---------------------------------------------------------------+
| 同心餐點費用申請單          申請日期: 2026-03-17  單號: S260001 |
+---------------------------------------------------------------+
| Section 1：申請人資訊（我是誰）                                 |
| 申請人姓名: [___________]    | 所屬單位:   [下拉選單▼]          |
| 申請人分機: [___________]    | 預算來源:   [___________]        |
+---------------------------------------------------------------+
| Section 2：用餐事件資訊（什麼時候/什麼事）                      |
| 用餐日期: [日期選擇器]       | 用餐別: [早餐/午餐/下午茶/晚餐▼] |
| 用餐時間: [HH:MM]           | 用餐地點: [___________]          |
| 用餐事由: [_______________________________多行文字___________] |
+---------------------------------------------------------------+
| Section 3：餐點明細（要點什麼）                                 |
| 類別▼    | 品項/單價▼      | 份數 | 付費方式▼ | 小計  | [刪] |
| [便當▼]  | [環保便當 $45▼] | [1]  | [自付▼]   | $45   | [✕] |
| [茶水▼]  | [紅茶  $200▼]  | [2]  | [招待▼]   | $400  | [✕] |
| [+ 新增一列]                                合計：$445         |
+---------------------------------------------------------------+
| Section 4：補充說明（申請配合事項）                             |
| [_______________________________多行文字___________]           |
+---------------------------------------------------------------+
| Section 5：送案設定                                            |
| 下站主辦人: [下拉選單▼]                                        |
|                                                               |
| ▣ 注意事項（唯讀固定提示）：                                   |
|   1. 訂餐請於三日前提出申請                                    |
|   2. 供膳前一日請再次向供膳組確認（分機 6388000）              |
|                                                               |
| [儲存為草稿]                       [確定送案 Submit ►]        |
+---------------------------------------------------------------+
```

**【行為規格】**
* **Section 1 載入時**：預設帶入 Session 登入者姓名（教學模擬）。
* **Section 3 動態行為**：
  * `[類別下拉]` 選定後，AJAX 呼叫 `api_meals.php` 載入對應品項與預設單價。
  * 「其他」類別：顯示單價手動輸入欄（`price` 初始為 0）。
  * 份數或品項變動時，JS 即時計算每列小計及合計總金額。
  * `[+新增一列]`：JS 複製模板行並追加，至少保留 1 列。
* **Section 5 注意事項**：固定唯讀文字，不存入資料庫。
* **送案按鈕**：以 DB Transaction 同時寫入 `requests`（含 `request_no` 自動產生）和 `order_items`，狀態設為 `submitted`。

---

### 6.2 查詢列表頁面 (`list.php`)

**【畫面佈局】**
```text
+---------------------------------------------------------------+
| [首頁] [新增申請]     篩選狀態: [▼]    篩選單位類型: [▼]      |
+---------------------------------------------------------------+
| 單號     | 申請人 | 單位     | 用餐日期   | 合計  | 狀態  | 動作 |
|----------|--------|----------|------------|-------|-------|------|
| S260001  | 林承訓 | 電算中心 | 2026-03-20 | $445  | 申請中| [檢視]|
| S260002  | 王小明 | 醫學系   | 2026-03-21 | $180  | 審核中| [檢視]|
| S260003  | 陳怡君 | 庶務組   | 2026-03-22 | $155  | 已核決| [檢視]|
+---------------------------------------------------------------+
```

**【行為規格】**
* **合計金額**：後端透過 `JOIN order_items, meals` 計算 `SUM(quantity * price)`。
* **狀態篩選**：支援完整 7 態（含 `completed` / `cancelled`）。
* **角色差異**：`manager` 預設顯示 `submitted` 待審列表；`staff` 顯示自己的申請。

---

### 6.3 單據明細與歷程頁面 (`view.php`)

**【畫面佈局】**
```text
+---------------------------------------------------------------+
| 同心餐點費用申請單 #S260001                        [返回列表]  |
+---------------------------------------------------------------+
| 狀態: [已核決]    申請人: 林承訓    分機: 7039                |
| 單位: 電算中心    預算來源: 92213000-03-05                     |
| 用餐日: 2026-03-20  用餐別: 早餐  時間: 08:30  地點: C304     |
| 事由: 電算中心暑期電腦教育訓練課程                            |
+---------------------------------------------------------------+
| 訂單明細                                                      |
| 類別 | 品項   | 單價 | 份數 | 付費方式 | 小計                  |
|------|--------|------|------|----------|-------|                |
| 茶水 | 紅茶   | 200  | 100  | 招待     | $200                  |
|                                          合計: $200           |
+---------------------------------------------------------------+
| 補充說明: 請提供環保杯                                        |
+---------------------------------------------------------------+

             ↓↓↓ 重點教學展示區：Agent 運作軌跡 ↓↓↓

+---------------------------------------------------------------+
| 簽辦歷程 (Audit Logs)                                        |
| # | 日期            | 狀態   | 經辦人            | 意見          |
|---|-----------------|--------|-------------------|---------------|
| 1 | 03/17 10:00     | 申請中 | 林承訓            | 送案給供膳組  |
| 2 | 03/17 10:05     | 審核中 | Application_Agent | 已接收、分析  |
| 3 | 03/17 10:06     | 已核決 | Review_Agent      | 敬表同意[自動]|
| 4 | 03/17 10:07     | 已核決 | Notification_Agent| 通知已發送    |
+---------------------------------------------------------------+
```

**【行為規格】**
* **唯讀顯示**：主檔資料不可編輯。
* **訂單明細**：Table 條列 `order_items` JOIN `meals`，顯示付費方式欄與各列小計。
* **補充說明**：顯示 `requests.notes`。
* **簽辦歷程**：Table 條列 `audit_logs`，按時間正序排列。Agent 經辦人以特殊樣式標示，為課堂核心展示畫面。

---

## 7. 系統帳號與權限規劃 (Permission Planning)

### 7.1 角色定義

教學版採 **PHP Session + Hardcoded 帳號**，不建 `users` 資料庫表，符合「極簡化」原則。

| 角色 | 英文代號 | 測試帳號 / 密碼 | 說明 |
| --- | --- | --- | --- |
| 一般員工 | `staff` | `staff01 / 1234` | 申請者角色 |
| 審核主管 | `manager` | `manager01 / 1234` | 模擬審核端 |
| 餐廳審核 | `restaurant` | `restaurant01 / 1234` | 供膳單位審核 |
| 財務審核 | `finance` | `finance01 / 1234` | 預算/財務最終審核 |
| 系統管理員 | `admin` | `admin / 1234` | 課堂展示用 |

### 7.2 功能權限矩陣

| 功能 | staff | manager | restaurant | finance | admin |
| --- | --- | --- | --- | --- | --- |
| 新增申請 | ✅ | ✅ | ❌ | ❌ | ✅ |
| 查看申請/明細 | ✅ | ✅ | ✅ | ✅ | ✅ |
| 本關核准/退回 | ❌ | ✅（限 next_operator=manager01） | ✅（限 next_operator=restaurant01） | ✅（限 next_operator=finance01） | ✅（可介入） |
| 標記已用膳 `completed` | ❌ | ✅ | ❌ | ❌ | ✅ |
| 撤銷 `draft/submitted` | ✅ | ✅ | ❌ | ❌ | ✅ |
| 管理資料表 CRUD | ❌ | ❌ | ❌ | ❌ | ✅ |
| 查看通知中心 | ✅ | ✅ | ✅ | ✅ | ✅ |

### 7.3 實作說明

* `login.php`：極簡登入頁，比對 hardcoded 帳號，寫入 `$_SESSION['user']` / `$_SESSION['role']`。
* `config.php` 新增三個輔助函式：
  * `requireLogin()` — 未登入時跳轉 `login.php`
  * `getCurrentRole()` — 回傳 `$_SESSION['role']`
  * `getCurrentUser()` — 回傳 `$_SESSION['user']`

---

## 8. 原系統對照說明 (Reference Mapping)

原系統（同心餐點費用申請系統）共有 13 個案件狀態，教學版收斂為 7 個：

| 原系統代碼 | 原系統說明 | 教學版對應 |
| --- | --- | --- |
| 0 | 你目前應辦的案件 | ─（由角色篩選取代）|
| 1 | 申請中 | `submitted` |
| 2 | 審核中 | `reviewing` |
| 3 | 已核決 | `approved` |
| 4 | 用膳狀況 | `completed` |
| 5 | 已用膳，待付款 | `completed`（教學合併）|
| 6 | 待付款 | `completed`（教學合併）|
| X | 已銷案 | `cancelled` |
| A | 無法安排用膳 | `rejected` |
| W | 您辦過但未結案件 | ─（由篩選取代）|
| Y | 本單位所有未結案件 | ─（由角色篩選取代）|
| Z | 所有案件 | ─（admin 預設視圖）|
| ─ | ─（原系統無對應）| `draft` |

> ✅ 教學重點：向學員說明「狀態設計」是系統性決策，不是越多越好。

---

## 9. 已知問題與待修正項目 (Known Issues)

下列為目前仍建議優化的項目：

| 問題 | 影響檔案 | 說明 |
| --- | --- | --- |
| Admin CRUD 風險控管可再強化 | `admin_data.php`, `admin_data_process.php` | 目前為教學用途，建議補充欄位級權限與操作審計 |
| `request_no` 自動編碼規則可再完整化 | `process.php` | 目前流程聚焦狀態機，正式版可補連號與防碰撞策略 |
| Agent 與人工並行時的鎖定策略 | Agent 程式 / `process.php` | 建議補充樂觀鎖或版本欄位，避免競態更新 |

## 10. 後端替換（PHP ↔ Node.js）與教學切換策略

本章節說明如何在教學場景中「以資料契約為核心」進行後端語言替換（例如把 PHP 換成 Node.js），並提供最小操作流程與 Docker 切換策略，方便在課堂上做示範比較。

核心原則：把 **PostgreSQL schema + 狀態機（state machine）** 當成系統的「核心合約（contract）」，把 `PHP / Node / 其他` 視為可替換的 Adapter 層；Agent 也視為可替換的 Worker。

10.1 可行性判斷（結論）

- 可行：只要維持 DB schema 與狀態契約不變，後端語言與 Agent 實作均可互換，教學上是很強的示範效果。

10.2 最小必備條件（MVP Contract）

- A. DB schema 相容或不變（`requests, order_items, audit_logs, notifications` 等核心表）
- B. 狀態機相容（draft/submitted/reviewing/approved/...）
- C. 提交來源一致（新的後端需能把單設為 `submitted`）

10.3 切換 SOP（課堂友善演示）

1. 停掉 AgentA（或暫停其監聽），確保不會與 AgentB 競爭處理相同單據。
2. 確認 DB schema 與狀態值（必要時執行 migration 或資料校正）。
3. 上線新後端（Node.js）提供最小 API：`GET /requests`、`GET /requests/:id`、`POST /requests`。
4. 啟動 AgentB（或相同 OpenClaw Agent 的新實作），觀察 AgentB 對 `submitted` 的處理與 `audit_logs` / `notifications` 的寫入。
5. 驗證：使用 Node API 取得同一筆單，確認狀態與簽辦歷程被 AgentB 正常寫入。

10.4 Docker Compose 切換（建議）

在教學情境下，建議採用 `docker compose` 的 `profiles` 功能維護兩套可切換組態：

- `phpA`：`web`(PHP) + `db` + `agentA`
- `nodeB`: `web_node`(Node) + `db` + `agentB`

示範命令：

```bash
# 啟動 PHP 教學組合
docker compose --profile phpA up -d

# 啟動 Node 教學組合（使用相同 DB volume）
docker compose --profile nodeB up -d
```

選擇資料庫（兩種選項）：

- 使用**同一個 DB volume**：可直接展示「換後端但資料不變」的戲法（教學效果強）。缺點：務必停掉 AgentA，避免重複處理。
- 使用**新 DB（乾淨資料）**：風險低但需同步 seed 資料，適合想隔離環境或做實驗的情境。

10.5 風險與教學觸發點

- 競態（race conditions）：AgentA/AgentB 同時處理相同單據時會產生重複寫入或衝突，教學上可成為引入 `processed_flag` / locking 的好教材。
- 欄位不一致：`notifications` 欄位名稱或 `requests` 欄位若不同步，會導致程式錯誤，需事先比對 DDL。

10.6 建議的教學流程（精簡版）

1. 先完成 PHP + AgentA 的完整跑通 Demo。
2. 暫停 AgentA，保留 PostgreSQL 與已建立的 sample requests。
3. 啟動 Node + AgentB，示範同一筆資料如何被新的後端與 Agent 推進流程。
4. 最後討論：為什麼資料與狀態契約讓這一切成為可能？（規格 > 語言）

