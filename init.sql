-- init.sql for tongxin-meal-system
-- Usage (PowerShell):
-- $env:PGPASSWORD='1234'; psql -h 127.0.0.1 -U postgres -f .\init.sql

-- Clean up existing objects (if any)
DROP DATABASE IF EXISTS tongxin_meal;
DROP ROLE IF EXISTS tongxin_admin;

-- Create an application role (password provided by user)
CREATE ROLE tongxin_admin WITH LOGIN PASSWORD '1234';

-- Create database owned by the app role
CREATE DATABASE tongxin_meal OWNER tongxin_admin;

-- Note: do NOT use psql meta-commands like "\connect" when running via psycopg2.
-- The helper script will create the database first and then run the following statements
-- against the newly created database.

-- Ensure useful extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Recommended settings
ALTER DATABASE tongxin_meal SET client_encoding = 'UTF8';
ALTER DATABASE tongxin_meal SET timezone = 'Asia/Taipei';

-- Create master data tables
CREATE TABLE departments (
    dept_code VARCHAR(20) PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL
);

CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    dept_code VARCHAR(20) REFERENCES departments(dept_code),
    extension VARCHAR(10),
    role VARCHAR(20) DEFAULT 'applicant'
);

CREATE TABLE meal_items (
    item_id SERIAL PRIMARY KEY,
    item_name VARCHAR(50) NOT NULL,
    description VARCHAR(100),
    standard_price DECIMAL(10,2) DEFAULT 0,
    unit VARCHAR(10) DEFAULT '份'
);

CREATE TABLE budgets (
    budget_code VARCHAR(50) PRIMARY KEY,
    subject_name VARCHAR(100),
    balance DECIMAL(15,2) DEFAULT 0
);

-- Transactional tables
CREATE TABLE order_headers (
    order_no VARCHAR(20) PRIMARY KEY,
    applicant_id INTEGER REFERENCES users(user_id),
    dept_code VARCHAR(20) REFERENCES departments(dept_code),
    apply_date DATE DEFAULT CURRENT_DATE,
    meal_date DATE NOT NULL,
    meal_time TIME NOT NULL,
    meal_type VARCHAR(20),
    location TEXT,
    purpose TEXT,
    budget_code VARCHAR(50) REFERENCES budgets(budget_code),
    total_amount DECIMAL(15,2) DEFAULT 0,
    status_code VARCHAR(2) DEFAULT '1',
    current_handler_id INTEGER REFERENCES users(user_id),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_details (
    detail_id SERIAL PRIMARY KEY,
    order_no VARCHAR(20) REFERENCES order_headers(order_no) ON DELETE CASCADE,
    item_id INTEGER REFERENCES meal_items(item_id),
    quantity INTEGER DEFAULT 0,
    price_per_unit DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(10) DEFAULT '自付',
    subtotal DECIMAL(15,2) NOT NULL
);

CREATE TABLE workflow_logs (
    log_id SERIAL PRIMARY KEY,
    order_no VARCHAR(20) REFERENCES order_headers(order_no),
    sequence_no INTEGER NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status_code VARCHAR(2),
    handler_id INTEGER REFERENCES users(user_id),
    opinion TEXT
);

-- Grant privileges to application role
GRANT ALL PRIVILEGES ON DATABASE tongxin_meal TO tongxin_admin;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO tongxin_admin;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO tongxin_admin;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO tongxin_admin;

-- Insert sample master data and test records
INSERT INTO departments (dept_code, dept_name) VALUES ('213000', '電算中心');

INSERT INTO users (username, full_name, dept_code, extension) VALUES
('yibei.xie', '謝依蓓', '213000', '7039'),
('chengxun.lin', '林承訓', '213000', '7039'),
('yongsen.liu', '劉永森', '213000', '1303'),
('nianlong.wu', '吳年龍', '213000', '1304');

INSERT INTO meal_items (item_name, description, standard_price, unit) VALUES
('便當', '環保便當(45元)', 45, '份'),
('茶水', '紅茶', 200, '人份'),
('中式快餐', '每人份100元', 100, '份'),
('點心', '三樣點心', 30, '份');

INSERT INTO budgets (budget_code, subject_name, balance) VALUES
('92213000-03-05', '電算中心計畫經費', 5000000);

-- Example order matching provided spec (S920005)
INSERT INTO order_headers (order_no, applicant_id, dept_code, meal_date, meal_time, meal_type, location, purpose, budget_code, total_amount, status_code)
VALUES ('S920005', 2, '213000', '2003-08-14', '08:30:00', '早上', 'C304電腦教室', '電算中心暑期電腦教育訓練課程', '92213000-03-05', 200, '6');

INSERT INTO order_details (order_no, item_id, quantity, price_per_unit, payment_method, subtotal)
VALUES ('S920005', 2, 100, 2, '招待', 200);

INSERT INTO workflow_logs (order_no, sequence_no, action_date, status_code, handler_id, opinion) VALUES
('S920005', 1, '2003-08-06 09:00:00', '1', 2, '送案給劉永森'),
('S920005', 2, '2003-08-06 14:00:00', '2', 3, '敬表同意');

-- End of init.sql
