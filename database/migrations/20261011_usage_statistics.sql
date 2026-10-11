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
