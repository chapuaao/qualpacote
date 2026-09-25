SET NAMES utf8mb4;
SET time_zone = '+01:00';

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

-- UNITEL: Fala Mais é identificado pela fonte oficial como tarifário de entrada.
INSERT INTO tariffs (operator_id, name, is_default, active, published, created_at, updated_at)
SELECT o.id, 'Fala Mais — horário normal', 1, 1, 1, NOW(), NOW()
FROM operators o
WHERE o.slug='unitel'
  AND NOT EXISTS (SELECT 1 FROM tariffs t WHERE t.operator_id=o.id AND t.name='Fala Mais — horário normal');
SET @unitel_fala = (SELECT t.id FROM tariffs t INNER JOIN operators o ON o.id=t.operator_id WHERE o.slug='unitel' AND t.name='Fala Mais — horário normal' LIMIT 1);
INSERT INTO tariff_versions (tariff_id, version_no, balance_validity_days, source_url, source_checked_at, notes, active_from, created_at)
SELECT @unitel_fala, 1, NULL,
'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf',
'2026-09-25', 'Tarifário de entrada UNITEL. Valores de voz convertidos de Kz/seg para Kz/min. Registada apenas a tarifa de horário normal porque a fonte pública consultada não explicita nesta tabela o intervalo horário do económico.', NOW(), NOW()
WHERE @unitel_fala IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_versions WHERE tariff_id=@unitel_fala);
SET @unitel_fala_v = (SELECT id FROM tariff_versions WHERE tariff_id=@unitel_fala ORDER BY version_no DESC LIMIT 1);
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @unitel_fala_v,'VOICE','MIN',40.5000,'ONNET','Horário normal: 0,675 Kz/seg × 60',NOW()
WHERE @unitel_fala_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@unitel_fala_v AND service_type='VOICE' AND network_scope='ONNET');
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @unitel_fala_v,'VOICE','MIN',48.4800,'OFFNET','Horário normal: 0,808 Kz/seg × 60',NOW()
WHERE @unitel_fala_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@unitel_fala_v AND service_type='VOICE' AND network_scope='OFFNET');

-- UNITEL: consumo pontual de internet debitado directamente da conta principal.
INSERT INTO tariffs (operator_id, name, is_default, active, published, created_at, updated_at)
SELECT o.id, 'Net Pontual', 0, 1, 1, NOW(), NOW()
FROM operators o
WHERE o.slug='unitel'
  AND NOT EXISTS (SELECT 1 FROM tariffs t WHERE t.operator_id=o.id AND t.name='Net Pontual');
SET @unitel_net = (SELECT t.id FROM tariffs t INNER JOIN operators o ON o.id=t.operator_id WHERE o.slug='unitel' AND t.name='Net Pontual' LIMIT 1);
INSERT INTO tariff_versions (tariff_id, version_no, balance_validity_days, source_url, source_checked_at, notes, active_from, created_at)
SELECT @unitel_net, 1, NULL,
'https://unitel.ao/wp-content/uploads/share_upload/2025/04/Final_-TERMOS-DE-UTILIZAC%CC%A7A%CC%83O-PLANOS-NET-AO-DIA-31.03.2025.pdf',
'2026-09-25', 'Netlight / utilização pontual: débito directo na conta principal quando não existe outro plano de internet activo.', NOW(), NOW()
WHERE @unitel_net IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_versions WHERE tariff_id=@unitel_net);
SET @unitel_net_v = (SELECT id FROM tariff_versions WHERE tariff_id=@unitel_net ORDER BY version_no DESC LIMIT 1);
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @unitel_net_v,'DATA','MB',1.4000,'ALL','Internet avulsa / Net Pontual',NOW()
WHERE @unitel_net_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@unitel_net_v AND service_type='DATA');

-- Africell: plano pré-pago oficial. Chamadas são taxadas ao segundo a partir do 5.º segundo.
INSERT INTO tariffs (operator_id, name, is_default, active, published, created_at, updated_at)
SELECT o.id, 'Pré-pago', 1, 1, 1, NOW(), NOW()
FROM operators o
WHERE o.slug='africell'
  AND NOT EXISTS (SELECT 1 FROM tariffs t WHERE t.operator_id=o.id AND t.name='Pré-pago');
SET @africell_pre = (SELECT t.id FROM tariffs t INNER JOIN operators o ON o.id=t.operator_id WHERE o.slug='africell' AND t.name='Pré-pago' LIMIT 1);
INSERT INTO tariff_versions (tariff_id, version_no, balance_validity_days, source_url, source_checked_at, notes, active_from, created_at)
SELECT @africell_pre, 1, NULL,
'https://www.africell.ao/prepago/',
'2026-09-25', 'Tarifas públicas do pré-pago Africell. A página não publica tarifa de dados avulsos para o SIM móvel comum; por isso não foi inventada.', NOW(), NOW()
WHERE @africell_pre IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_versions WHERE tariff_id=@africell_pre);
SET @africell_pre_v = (SELECT id FROM tariff_versions WHERE tariff_id=@africell_pre ORDER BY version_no DESC LIMIT 1);
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,start_time,end_time,label,created_at)
SELECT @africell_pre_v,'VOICE','MIN',19.0000,'ONNET','07:00:00','23:59:00','Africell → Africell',NOW()
WHERE @africell_pre_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@africell_pre_v AND service_type='VOICE' AND network_scope='ONNET' AND start_time='07:00:00');
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,start_time,end_time,label,created_at)
SELECT @africell_pre_v,'VOICE','MIN',5.0000,'ONNET','00:00:00','06:59:00','Africell → Africell (madrugada)',NOW()
WHERE @africell_pre_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@africell_pre_v AND service_type='VOICE' AND network_scope='ONNET' AND start_time='00:00:00');
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @africell_pre_v,'VOICE','MIN',19.0000,'OFFNET','Outras redes',NOW()
WHERE @africell_pre_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@africell_pre_v AND service_type='VOICE' AND network_scope='OFFNET');
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @africell_pre_v,'SMS','SMS',6.0000,'ALL','SMS nacional',NOW()
WHERE @africell_pre_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@africell_pre_v AND service_type='SMS');
