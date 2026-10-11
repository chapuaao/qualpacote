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

-- Tarifas de consumo directo do saldo. São separadas dos pacotes porque o mesmo
-- saldo pode ser gasto em voz, dados ou SMS e não pode ser duplicado entre métricas.
CREATE TABLE IF NOT EXISTS tariffs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_tariffs_operator FOREIGN KEY (operator_id) REFERENCES operators(id),
    INDEX idx_tariffs_operator (operator_id),
    INDEX idx_tariffs_public (active, published),
    INDEX idx_tariffs_default (operator_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tariff_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tariff_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    balance_validity_days SMALLINT UNSIGNED NULL,
    source_url VARCHAR(600) NOT NULL,
    source_checked_at DATE NOT NULL,
    notes TEXT NULL,
    active_from DATETIME NOT NULL,
    active_to DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tariff_versions_tariff FOREIGN KEY (tariff_id) REFERENCES tariffs(id),
    CONSTRAINT fk_tariff_versions_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    UNIQUE KEY uq_tariff_version (tariff_id, version_no),
    INDEX idx_tariff_versions_current (tariff_id, version_no),
    INDEX idx_tariff_versions_checked (source_checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tariff_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tariff_version_id INT UNSIGNED NOT NULL,
    service_type ENUM('DATA','VOICE','SMS') NOT NULL,
    unit ENUM('MB','MIN','SMS') NOT NULL,
    price_kz_per_unit DECIMAL(14,4) NOT NULL,
    network_scope ENUM('ALL','ONNET','OFFNET') NOT NULL DEFAULT 'ALL',
    start_time TIME NULL,
    end_time TIME NULL,
    label VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tariff_rates_version FOREIGN KEY (tariff_version_id) REFERENCES tariff_versions(id) ON DELETE CASCADE,
    INDEX idx_tariff_rates_version (tariff_version_id),
    INDEX idx_tariff_rates_service (service_type, network_scope)
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

-- Idempotente: importar antes de activar as estatísticas e a votação.
CREATE TABLE IF NOT EXISTS usage_visitors (
    visitor_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    first_seen_at DATETIME NOT NULL,
    INDEX idx_usage_first_seen (first_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_visit_days (
    visitor_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    visit_date DATE NOT NULL,
    PRIMARY KEY (visitor_key, visit_date),
    INDEX idx_usage_visit_date (visit_date),
    CONSTRAINT fk_usage_visit_visitor FOREIGN KEY (visitor_key) REFERENCES usage_visitors(visitor_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_consultations (
    search_event_id BIGINT UNSIGNED PRIMARY KEY,
    visitor_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    return_query TEXT NOT NULL,
    CONSTRAINT fk_usage_consultation_search FOREIGN KEY (search_event_id) REFERENCES search_events(id),
    CONSTRAINT fk_usage_consultation_visitor FOREIGN KEY (visitor_key) REFERENCES usage_visitors(visitor_key),
    INDEX idx_usage_consultation_visitor (visitor_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    search_event_id BIGINT UNSIGNED NOT NULL,
    vote_token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    kind ENUM('plan','balance') NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NOT NULL,
    operator_name VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    result_position TINYINT UNSIGNED NOT NULL,
    vote TINYINT NULL,
    voted_at DATETIME NULL,
    CONSTRAINT fk_usage_result_consultation FOREIGN KEY (search_event_id) REFERENCES usage_consultations(search_event_id),
    CONSTRAINT chk_usage_vote CHECK (vote IS NULL OR vote IN (-1, 1)),
    UNIQUE KEY uq_usage_result_position (search_event_id, result_position),
    INDEX idx_usage_result_item (kind, item_id, version_id),
    INDEX idx_usage_result_voted (voted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET NAMES utf8mb4;
SET time_zone = '+01:00';

CREATE TABLE IF NOT EXISTS plan_version_rules (
    plan_version_id INT UNSIGNED PRIMARY KEY,
    rules_json TEXT NOT NULL,
    CONSTRAINT fk_plan_rules_version FOREIGN KEY (plan_version_id) REFERENCES plan_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id INT UNSIGNED NOT NULL,
    url VARCHAR(600) NOT NULL,
    url_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    source_kind ENUM('discovery','offer','document') NOT NULL DEFAULT 'offer',
    adapter VARCHAR(60) NOT NULL DEFAULT 'manual',
    status ENUM('discovered','verified','ignored') NOT NULL DEFAULT 'discovered',
    enabled TINYINT NOT NULL DEFAULT 1,
    last_attempt_at DATETIME NULL,
    last_success_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_catalog_source_operator FOREIGN KEY (operator_id) REFERENCES operators(id),
    INDEX idx_catalog_source_status (status, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT UNSIGNED NOT NULL,
    content_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    text_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    evidence_text MEDIUMTEXT NOT NULL,
    evidence_blob MEDIUMBLOB NULL,
    evidence_type VARCHAR(60) NULL,
    fetched_at DATETIME NOT NULL,
    http_status SMALLINT NOT NULL,
    CONSTRAINT fk_catalog_snapshot_source FOREIGN KEY (source_id) REFERENCES catalog_sources(id),
    INDEX idx_catalog_snapshot_source (source_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id INT UNSIGNED NOT NULL,
    snapshot_id BIGINT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NULL,
    expected_version_id INT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    payload_json MEDIUMTEXT NOT NULL,
    warnings_json TEXT NOT NULL,
    signature CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
    state ENUM('pending','approved','dismissed','blocked') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_catalog_proposal_source FOREIGN KEY (source_id) REFERENCES catalog_sources(id),
    CONSTRAINT fk_catalog_proposal_snapshot FOREIGN KEY (snapshot_id) REFERENCES catalog_snapshots(id),
    CONSTRAINT fk_catalog_proposal_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT fk_catalog_proposal_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id),
    INDEX idx_catalog_proposal_state (state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_reviews (
    snapshot_id BIGINT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    reviewed_at DATETIME NOT NULL,
    PRIMARY KEY (snapshot_id, plan_id, version_id),
    CONSTRAINT fk_catalog_review_snapshot FOREIGN KEY (snapshot_id) REFERENCES catalog_snapshots(id),
    CONSTRAINT fk_catalog_review_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT fk_catalog_review_version FOREIGN KEY (version_id) REFERENCES plan_versions(id),
    CONSTRAINT fk_catalog_review_admin FOREIGN KEY (admin_id) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    checked_count INT NOT NULL DEFAULT 0,
    error_count INT NOT NULL DEFAULT 0,
    discovered_count INT NOT NULL DEFAULT 0,
    state ENUM('running','ok','error') NOT NULL DEFAULT 'running',
    error_message TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_result_details (
    result_id BIGINT UNSIGNED PRIMARY KEY,
    result_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    components_json MEDIUMTEXT NOT NULL,
    CONSTRAINT fk_usage_details_result FOREIGN KEY (result_id) REFERENCES usage_results(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
