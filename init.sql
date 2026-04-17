-- init.sql for 同心餐點費用申請系統 (sys_spec v2)
-- Usage (PowerShell, recommended):
--   $env:PGPASSWORD='1234'; psql -h 127.0.0.1 -U postgres -f .\init.sql
-- Or via helper:
--   python create_data.py --sql .\init.sql --host 127.0.0.1 --port 5432 --user postgres

-- ── Drop & recreate database ─────────────────────────────────────────────────
DROP DATABASE IF EXISTS tongxin_meal;
DROP ROLE IF EXISTS tongxin_admin;
CREATE ROLE tongxin_admin WITH LOGIN PASSWORD '1234';

-- Create database owned by the app role
CREATE DATABASE tongxin_meal OWNER tongxin_admin;

-- Note: psycopg2 cannot run \c / \connect.  create_data.py handles the DB switch.
-- When running via psql the \c below switches to the new database automatically.
\c tongxin_meal

-- ── Extensions & settings ────────────────────────────────────────────────────
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
ALTER DATABASE tongxin_meal SET client_encoding = 'UTF8';
ALTER DATABASE tongxin_meal SET timezone = 'Asia/Taipei';

-- Create master data tables
-- ── Sequence for request_no (format S260001) ────────────────────────────────
DROP SEQUENCE IF EXISTS request_no_seq;
CREATE SEQUENCE request_no_seq START 1;

-- ── 單位主檔 ─────────────────────────────────────────────────────────────────
CREATE TABLE units (
    id        SERIAL PRIMARY KEY,
    unit_name VARCHAR(100) NOT NULL,
    unit_type VARCHAR(50)  NOT NULL   -- general / teaching / center
);

-- ── 餐點主檔 ─────────────────────────────────────────────────────────────────
CREATE TABLE meals (
    id       SERIAL PRIMARY KEY,
    category VARCHAR(50)  NOT NULL,
    name     VARCHAR(100) NOT NULL,
    price    INTEGER      NOT NULL DEFAULT 0
);

-- ── 使用者主檔 (M02) ─────────────────────────────────────────────────────────
CREATE TABLE users (
    id               SERIAL PRIMARY KEY,
    username         VARCHAR(50)  UNIQUE NOT NULL,
    display_name     VARCHAR(100) NOT NULL,
    role             VARCHAR(20)  NOT NULL,  -- staff/manager/restaurant/finance/admin
    unit_id          INTEGER REFERENCES units(id),
    manager_username VARCHAR(50),
    is_active        BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 申請單主檔 ───────────────────────────────────────────────────────────────
CREATE TABLE requests (
    id             SERIAL PRIMARY KEY,
    request_no     VARCHAR(20),
    status         VARCHAR(20)  NOT NULL DEFAULT 'draft',
    applicant_id   INTEGER,     -- loosely references users.id
    applicant_name VARCHAR(100) NOT NULL,
    extension      VARCHAR(20),
    unit_id        INTEGER REFERENCES units(id),
    budget_source  VARCHAR(150),
    meal_date      DATE,
    meal_type      VARCHAR(20),  -- breakfast/lunch/afternoon_tea/dinner/other
    meal_time      TIME,
    meal_location  VARCHAR(200),
    meal_reason    TEXT,
    notes          TEXT,
    next_operator  VARCHAR(100),  -- username of current required approver
    processed_flag BOOLEAN DEFAULT FALSE,
    version_no     INTEGER DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 訂單明細 ─────────────────────────────────────────────────────────────────
CREATE TABLE order_items (
    id             SERIAL PRIMARY KEY,
    request_id     INTEGER REFERENCES requests(id) ON DELETE CASCADE,
    meal_id        INTEGER REFERENCES meals(id),
    quantity       INTEGER NOT NULL DEFAULT 1,
    payment_method VARCHAR(10) DEFAULT '自付',  -- 自付 / 招待
    custom_price   INTEGER  -- NULL = use meals.price; set when category=其他
);

-- ── 簽辦歷程 (audit_logs) ────────────────────────────────────────────────────
CREATE TABLE audit_logs (
    id          SERIAL PRIMARY KEY,
    request_id  INTEGER REFERENCES requests(id),
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stage       VARCHAR(50),   -- 狀態中文標籤
    operator    VARCHAR(100),  -- username or Agent name
    comment     TEXT
);

-- ── 通知紀錄 ─────────────────────────────────────────────────────────────────
CREATE TABLE notifications (
    id          SERIAL PRIMARY KEY,
    request_id  INTEGER REFERENCES requests(id),
    message     TEXT,
    target_role VARCHAR(50) DEFAULT 'staff',
    read_flag   BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 簽核鏈設定 (M03) ─────────────────────────────────────────────────────────
CREATE TABLE approval_steps (
    id                SERIAL PRIMARY KEY,
    step_order        INTEGER     NOT NULL,
    operator_username VARCHAR(50) NOT NULL,
    role              VARCHAR(20) NOT NULL,
    is_active         BOOLEAN DEFAULT TRUE,
    UNIQUE(step_order)
);

-- ── 狀態轉移審計 (M04) ───────────────────────────────────────────────────────
CREATE TABLE request_transitions (
    id          SERIAL PRIMARY KEY,
    request_id  INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    from_status VARCHAR(50),
    to_status   VARCHAR(50) NOT NULL,
    operator    VARCHAR(100) NOT NULL,
    comment     TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Indexes (M05) ────────────────────────────────────────────────────────────
CREATE INDEX idx_requests_status        ON requests(status);
CREATE INDEX idx_requests_next_operator ON requests(next_operator);
CREATE INDEX idx_requests_unit_id       ON requests(unit_id);
CREATE INDEX idx_audit_logs_request_id  ON audit_logs(request_id);
CREATE INDEX idx_notifications_request  ON notifications(request_id);
CREATE INDEX idx_users_manager          ON users(manager_username);

-- ── Privileges ───────────────────────────────────────────────────────────────
GRANT ALL PRIVILEGES ON DATABASE tongxin_meal TO tongxin_admin;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO tongxin_admin;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO tongxin_admin;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tongxin_admin;

-- ── Seed: units ──────────────────────────────────────────────────────────────
INSERT INTO units (id, unit_name, unit_type) VALUES
(1, '業務部',   'general'),
(2, '教學部',   'teaching'),
(3, '電算中心', 'center'),
(4, '行政部',   'general'),
(5, '供膳組',   'center'),
(6, '財務部',   'general');

-- ── Seed: meals (9 categories per spec 3.2) ──────────────────────────────────
INSERT INTO meals (category, name, price) VALUES
('便當',       '環保便當',      45),
('茶水',       '紅茶飲料',     200),
('中式快餐',   '中式快餐',     100),
('點心',       '綜合點心',      30),
('歐式自助餐', '歐式自助餐',   100),
('早餐',       '早餐',          25),
('午餐',       '午餐（打菜）',  45),
('晚餐',       '晚餐（打菜）',  45),
('其他',       '其他（自訂）',   0);

-- ── Seed: users (M06) ────────────────────────────────────────────────────────
INSERT INTO users (username, display_name, role, unit_id, manager_username) VALUES
('admin',        '系統管理員', 'admin',       NULL, NULL),
('restaurant01', '供膳審核員', 'restaurant',     5, NULL),
('finance01',    '財務審核員', 'finance',        6, NULL),
('manager01',    '業務主管',   'manager',        1, NULL),
('manager02',    '教學主管',   'manager',        2, NULL),
('manager03',    '電算主管',   'manager',        3, NULL),
('manager04',    '行政主管',   'manager',        4, NULL),
('manager05',    '供膳主管',   'manager',        5, NULL),
('staff01',  '員工01', 'staff', 1, 'manager01'),
('staff02',  '員工02', 'staff', 1, 'manager01'),
('staff03',  '員工03', 'staff', 2, 'manager02'),
('staff04',  '員工04', 'staff', 2, 'manager02'),
('staff05',  '員工05', 'staff', 2, 'manager02'),
('staff06',  '員工06', 'staff', 3, 'manager03'),
('staff07',  '員工07', 'staff', 3, 'manager03'),
('staff08',  '員工08', 'staff', 3, 'manager03'),
('staff09',  '員工09', 'staff', 3, 'manager03'),
('staff10',  '員工10', 'staff', 4, 'manager04'),
('staff11',  '員工11', 'staff', 4, 'manager04'),
('staff12',  '員工12', 'staff', 4, 'manager04'),
('staff13',  '員工13', 'staff', 4, 'manager04'),
('staff14',  '員工14', 'staff', 4, 'manager04'),
('staff15',  '員工15', 'staff', 5, 'manager05'),
('staff16',  '員工16', 'staff', 5, 'manager05'),
('staff17',  '員工17', 'staff', 5, 'manager05'),
('staff18',  '員工18', 'staff', 5, 'manager05'),
('staff19',  '員工19', 'staff', 5, 'manager05'),
('staff20',  '員工20', 'staff', 5, 'manager05')
ON CONFLICT (username) DO UPDATE
SET display_name     = EXCLUDED.display_name,
    role             = EXCLUDED.role,
    unit_id          = EXCLUDED.unit_id,
    manager_username = EXCLUDED.manager_username,
    is_active        = TRUE;

-- ── Seed: approval_steps (M07) ───────────────────────────────────────────────
INSERT INTO approval_steps (step_order, operator_username, role) VALUES
(1, 'manager01',    'manager'),
(2, 'restaurant01', 'restaurant'),
(3, 'finance01',    'finance')
ON CONFLICT (step_order) DO UPDATE
SET operator_username = EXCLUDED.operator_username,
    role              = EXCLUDED.role,
    is_active         = TRUE;

-- ── Demo request (for classroom display) ─────────────────────────────────────
INSERT INTO requests (request_no, status, applicant_id, applicant_name, extension,
                      unit_id, budget_source, meal_date, meal_type, meal_time,
                      meal_location, meal_reason, notes, next_operator)
VALUES ('S260001', 'approved', 9, '員工01', '7039',
        3, '電算中心計畫經費-2026', '2026-04-20', 'breakfast', '08:30:00',
        'C304電腦教室', '電算中心暑期電腦教育訓練課程', '請提供環保杯', NULL);

INSERT INTO order_items (request_id, meal_id, quantity, payment_method)
SELECT id, 2, 100, '招待' FROM requests WHERE request_no = 'S260001';

INSERT INTO audit_logs (request_id, stage, operator, comment)
SELECT id, '已送案', '員工01',       '送案給主管審核'   FROM requests WHERE request_no = 'S260001'
UNION ALL
SELECT id, '審核中', 'manager01',    '敬表同意'         FROM requests WHERE request_no = 'S260001'
UNION ALL
SELECT id, '審核中', 'restaurant01', '確認供膳'         FROM requests WHERE request_no = 'S260001'
UNION ALL
SELECT id, '已核決', 'finance01',    '預算確認，核准'   FROM requests WHERE request_no = 'S260001';

-- End of init.sql
