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
