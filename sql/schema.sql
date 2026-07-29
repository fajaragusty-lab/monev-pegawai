CREATE DATABASE IF NOT EXISTS monev_pegawai;
USE monev_pegawai;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS taxpayers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    petugas_id INT NOT NULL,
    taxpayer_id INT NOT NULL,
    target_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    deadline_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assign_petugas FOREIGN KEY (petugas_id) REFERENCES users(id),
    CONSTRAINT fk_assign_taxpayer FOREIGN KEY (taxpayer_id) REFERENCES taxpayers(id)
);

CREATE TABLE IF NOT EXISTS field_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    realisasi_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    penagihan_berhasil DECIMAL(14,2) NOT NULL DEFAULT 0,
    verval_complete TINYINT(1) NOT NULL DEFAULT 0,
    gps_valid TINYINT(1) NOT NULL DEFAULT 0,
    on_time TINYINT(1) NOT NULL DEFAULT 0,
    document_completeness DECIMAL(5,2) NOT NULL DEFAULT 0,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy DECIMAL(8,2) DEFAULT NULL,
    captured_at DATETIME NOT NULL,
    submitted_at DATETIME NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_field_result_assignment (assignment_id),
    CONSTRAINT fk_result_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id)
);

CREATE TABLE IF NOT EXISTS kpi_weights (
    component_key VARCHAR(50) PRIMARY KEY,
    weight DECIMAL(5,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS kpi_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    target_achievement DECIMAL(6,2) NOT NULL,
    collection_success DECIMAL(6,2) NOT NULL,
    verification_rate DECIMAL(6,2) NOT NULL,
    gps_validity DECIMAL(6,2) NOT NULL,
    timeliness DECIMAL(6,2) NOT NULL,
    document_completeness DECIMAL(6,2) NOT NULL,
    final_score DECIMAL(6,2) NOT NULL,
    category VARCHAR(40) NOT NULL,
    calculated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_kpi_assignment (assignment_id),
    CONSTRAINT fk_kpi_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id)
);
