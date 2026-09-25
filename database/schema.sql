SET NAMES utf8mb4;
SET time_zone = '+01:00';

CREATE TABLE IF NOT EXISTS operators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    website VARCHAR(255) NULL,
    display_order INT NOT NULL DEFAULT 10,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_plans_operator FOREIGN KEY (operator_id) REFERENCES operators(id),
    INDEX idx_plans_operator (operator_id),
    INDEX idx_plans_public (active, published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    price_kz DECIMAL(12,2) NOT NULL,
    validity_days SMALLINT UNSIGNED NOT NULL,
    activation_code VARCHAR(120) NULL,
    source_url VARCHAR(600) NOT NULL,
    source_checked_at DATE NOT NULL,
    notes TEXT NULL,
    active_from DATETIME NOT NULL,
    active_to DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_versions_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT fk_versions_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    UNIQUE KEY uq_plan_version (plan_id, version_no),
    INDEX idx_versions_current (plan_id, version_no),
    INDEX idx_versions_checked (source_checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS benefits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_version_id INT UNSIGNED NOT NULL,
    type ENUM('DATA','VOICE','SMS','SOCIAL') NOT NULL,
    quantity DECIMAL(14,2) NOT NULL,
    unit ENUM('MB','MIN','SMS') NOT NULL,
    network_scope ENUM('ALL','ONNET','OFFNET') NOT NULL DEFAULT 'ALL',
    start_time TIME NULL,
    end_time TIME NULL,
    app_scope VARCHAR(500) NULL,
    label VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_benefits_version FOREIGN KEY (plan_version_id) REFERENCES plan_versions(id) ON DELETE CASCADE,
    INDEX idx_benefits_version (plan_version_id),
    INDEX idx_benefits_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    budget_kz DECIMAL(12,2) NOT NULL,
    goal VARCHAR(30) NOT NULL,
    usage_level VARCHAR(20) NOT NULL,
    duration_days SMALLINT UNSIGNED NOT NULL,
    schedule_profile VARCHAR(20) NOT NULL,
    call_scope VARCHAR(20) NOT NULL,
    result_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_search_events_created (created_at),
    INDEX idx_search_events_goal (goal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_url VARCHAR(600) NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    content_hash CHAR(64) NULL,
    changed TINYINT(1) NOT NULL DEFAULT 0,
    error_message VARCHAR(500) NULL,
    checked_at DATETIME NOT NULL,
    INDEX idx_source_checks_url (source_url(190), id),
    INDEX idx_source_checks_changed (changed, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT UNSIGNED NULL,
    payload_json JSON NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
