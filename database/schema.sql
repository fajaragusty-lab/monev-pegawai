CREATE DATABASE IF NOT EXISTS monev_pegawai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE monev_pegawai;

CREATE TABLE IF NOT EXISTS regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    center_latitude DECIMAL(10,7) NULL,
    center_longitude DECIMAL(10,7) NULL,
    radius_meters INT NOT NULL DEFAULT 500,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    position VARCHAR(150) NOT NULL,
    region_id INT NULL,
    phone VARCHAR(50) NULL,
    role ENUM('admin', 'petugas') NOT NULL DEFAULT 'petugas',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS taxpayers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_number VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    region_id INT NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_taxpayers_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS job_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    default_target DECIMAL(12,2) NOT NULL DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kpi_weights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_key VARCHAR(100) NOT NULL UNIQUE,
    metric_name VARCHAR(150) NOT NULL,
    weight_percent DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS incentive_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    minimum_kpi DECIMAL(10,2) NOT NULL DEFAULT 0,
    bonus_multiplier DECIMAL(10,2) NOT NULL DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL,
    employee_id INT NOT NULL,
    taxpayer_id INT NOT NULL,
    job_type_id INT NOT NULL,
    assigned_by INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    target_value DECIMAL(12,2) NOT NULL DEFAULT 1,
    deadline_date DATE NOT NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    notes TEXT NULL,
    status ENUM('assigned', 'accepted', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'assigned',
    accepted_at DATETIME NULL,
    submitted_at DATETIME NULL,
    verified_at DATETIME NULL,
    verified_by INT NULL,
    verification_notes TEXT NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    distance_meters DECIMAL(12,2) NULL,
    gps_valid TINYINT(1) NOT NULL DEFAULT 0,
    location_photo_path VARCHAR(255) NULL,
    evidence_file_path VARCHAR(255) NULL,
    result_notes TEXT NULL,
    actual_value DECIMAL(12,2) NULL,
    amount_collected DECIMAL(14,2) NOT NULL DEFAULT 0,
    document_complete TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_taxpayer FOREIGN KEY (taxpayer_id) REFERENCES taxpayers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_job_type FOREIGN KEY (job_type_id) REFERENCES job_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_assigned_by FOREIGN KEY (assigned_by) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT fk_assignments_verified_by FOREIGN KEY (verified_by) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS kpi_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL,
    employee_id INT NOT NULL,
    target_count INT NOT NULL DEFAULT 0,
    realization_count INT NOT NULL DEFAULT 0,
    collection_success_count INT NOT NULL DEFAULT 0,
    verification_count INT NOT NULL DEFAULT 0,
    gps_valid_count INT NOT NULL DEFAULT 0,
    on_time_count INT NOT NULL DEFAULT 0,
    document_complete_count INT NOT NULL DEFAULT 0,
    target_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    realization_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    collection_success_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    verification_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    gps_valid_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    on_time_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    document_complete_ratio DECIMAL(8,2) NOT NULL DEFAULT 0,
    score_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    calculated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_kpi_period_employee (period_id, employee_id),
    CONSTRAINT fk_kpi_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_kpi_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS incentive_funds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL UNIQUE,
    weekly_fund DECIMAL(14,2) NOT NULL DEFAULT 0,
    monthly_fund DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_incentive_fund_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS incentive_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_id INT NOT NULL,
    employee_id INT NOT NULL,
    kpi_score DECIMAL(10,2) NOT NULL DEFAULT 0,
    proportion DECIMAL(12,6) NOT NULL DEFAULT 0,
    weekly_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    monthly_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    calculated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_incentive_period_employee (period_id, employee_id),
    CONSTRAINT fk_incentive_result_period FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE,
    CONSTRAINT fk_incentive_result_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
