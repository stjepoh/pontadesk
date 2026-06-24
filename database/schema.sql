CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL UNIQUE,
  full_name VARCHAR(191) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE clients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  contact_person VARCHAR(191) NULL,
  email VARCHAR(191) NULL,
  secondary_emails JSON NULL,
  phone VARCHAR(50) NULL,
  secondary_phones JSON NULL,
  address VARCHAR(191) NULL,
  city VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  country VARCHAR(100) NULL,
  vat_id VARCHAR(50) NULL,
  website VARCHAR(191) NULL,
  category ENUM('vip','regular','potential','inactive') NOT NULL DEFAULT 'regular',
  sales_stage ENUM('lead','qualified','proposal','negotiation','closed_won','closed_lost') NOT NULL DEFAULT 'lead',
  industry VARCHAR(100) NULL,
  company_size VARCHAR(20) NULL,
  hourly_rate DECIMAL(10,2) NULL,
  track_work TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE contracts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  contract_name VARCHAR(191) NOT NULL,
  contract_number VARCHAR(100) NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('active','expired','terminated') NOT NULL DEFAULT 'active',
  contract_file_url VARCHAR(255) NULL,
  contract_file_name VARCHAR(255) NULL,
  value DECIMAL(12,2) NULL,
  maintenance_billing_period ENUM('none','monthly','quarterly','annual') NOT NULL DEFAULT 'none',
  maintenance_amount DECIMAL(12,2) NULL,
  auto_renewal TINYINT(1) NOT NULL DEFAULT 0,
  reminder_days INT UNSIGNED NOT NULL DEFAULT 14,
  notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_contracts_client_id (client_id),
  CONSTRAINT fk_contracts_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE work_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  work_date DATE NOT NULL,
  duration_minutes INT UNSIGNED NOT NULL,
  description TEXT NOT NULL,
  billed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_work_logs_client_id (client_id),
  INDEX idx_work_logs_work_date (work_date),
  CONSTRAINT fk_work_logs_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(191) NOT NULL,
  description TEXT NULL,
  deadline DATE NULL,
  status ENUM('planning','in_progress','completed','on_hold') NOT NULL DEFAULT 'planning',
  reminder TINYINT(1) NOT NULL DEFAULT 0,
  reminder_days INT UNSIGNED NOT NULL DEFAULT 3,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_projects_client_id (client_id),
  CONSTRAINT fk_projects_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE project_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  description TEXT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  due_date DATE NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_project_tasks_project_id (project_id),
  CONSTRAINT fk_project_tasks_project_id FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE client_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  content TEXT NULL,
  category ENUM('technical','billing','communication','access','legal','other') NOT NULL DEFAULT 'other',
  importance ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  attachments JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_notes_client_id (client_id),
  CONSTRAINT fk_client_notes_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE client_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(191) NOT NULL,
  notes TEXT NULL,
  done TINYINT(1) NOT NULL DEFAULT 0,
  done_date DATE NULL,
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_client_tasks_client_id (client_id),
  CONSTRAINT fk_client_tasks_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
