SET NAMES utf8mb4;

INSERT INTO operators (name, slug, website, display_order, active, created_at, updated_at)
VALUES
    ('UNITEL', 'unitel', 'https://unitel.ao/', 10, 1, NOW(), NOW()),
    ('Africell', 'africell', 'https://www.africell.ao/', 20, 1, NOW(), NOW()),
    ('Movicel', 'movicel', 'https://www.movicel.ao/', 30, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name), website = VALUES(website), display_order = VALUES(display_order), active = VALUES(active), updated_at = NOW();

-- UNITEL: Plano Base, fonte oficial consultada em 25/09/2026.
INSERT INTO plans (operator_id, name, active, published, created_at, updated_at)
SELECT id, 'Plano Base', 1, 1, NOW(), NOW()
FROM operators WHERE slug = 'unitel'
AND NOT EXISTS (SELECT 1 FROM plans p WHERE p.operator_id = operators.id AND p.name = 'Plano Base');

SET @unitel_base_plan = (SELECT p.id FROM plans p INNER JOIN operators o ON o.id = p.operator_id WHERE o.slug = 'unitel' AND p.name = 'Plano Base' LIMIT 1);

INSERT INTO plan_versions
(plan_id, version_no, price_kz, validity_days, activation_code, source_url, source_checked_at, notes, active_from, created_at)
SELECT @unitel_base_plan, 1, 2000, 30, NULL,
'https://unitel.ao/wp-content/uploads/share_upload/2026/02/Unitel_MaisProximaFev-Mar2026_DIGITAL.pdf',
'2026-09-25', 'Fonte oficial UNITEL. Confirmar no canal de activação antes de carregar.', NOW(), NOW()
WHERE @unitel_base_plan IS NOT NULL AND NOT EXISTS (SELECT 1 FROM plan_versions WHERE plan_id = @unitel_base_plan);

SET @unitel_base_version = (SELECT id FROM plan_versions WHERE plan_id = @unitel_base_plan ORDER BY version_no DESC LIMIT 1);
INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT @unitel_base_version, 'VOICE', 70, 'MIN', 'ALL', NOW()
WHERE @unitel_base_version IS NOT NULL AND NOT EXISTS (SELECT 1 FROM benefits WHERE plan_version_id = @unitel_base_version);
INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT @unitel_base_version, 'SMS', 60, 'SMS', 'ALL', NOW()
WHERE @unitel_base_version IS NOT NULL AND (SELECT COUNT(*) FROM benefits WHERE plan_version_id = @unitel_base_version) < 2;
INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT @unitel_base_version, 'DATA', 500, 'MB', 'ALL', NOW()
WHERE @unitel_base_version IS NOT NULL AND (SELECT COUNT(*) FROM benefits WHERE plan_version_id = @unitel_base_version) < 3;

-- UNITEL NET mensais — fonte oficial consultada em 25/09/2026.
INSERT INTO plans (operator_id, name, active, published, created_at, updated_at)
SELECT o.id, x.name, 1, 1, NOW(), NOW()
FROM operators o
CROSS JOIN (
    SELECT 'NET Mensal 1,5 GB' AS name UNION ALL SELECT 'NET Mensal 2 GB' UNION ALL SELECT 'NET Mensal 3,5 GB' UNION ALL SELECT 'NET Mensal 6 GB' UNION ALL SELECT 'NET Mensal 13 GB'
) x
WHERE o.slug = 'unitel' AND NOT EXISTS (SELECT 1 FROM plans p WHERE p.operator_id = o.id AND p.name = x.name);

INSERT INTO plan_versions (plan_id, version_no, price_kz, validity_days, source_url, source_checked_at, notes, active_from, created_at)
SELECT p.id, 1,
    CASE p.name WHEN 'NET Mensal 1,5 GB' THEN 1500 WHEN 'NET Mensal 2 GB' THEN 2000 WHEN 'NET Mensal 3,5 GB' THEN 3000 WHEN 'NET Mensal 6 GB' THEN 5000 WHEN 'NET Mensal 13 GB' THEN 10000 END,
    31,
    'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf',
    '2026-09-25', 'Fonte oficial UNITEL consultada em 25/09/2026.', NOW(), NOW()
FROM plans p INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'unitel'
AND p.name IN ('NET Mensal 1,5 GB','NET Mensal 2 GB','NET Mensal 3,5 GB','NET Mensal 6 GB','NET Mensal 13 GB')
AND NOT EXISTS (SELECT 1 FROM plan_versions pv WHERE pv.plan_id = p.id);

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT pv.id, 'DATA',
    CASE p.name WHEN 'NET Mensal 1,5 GB' THEN 1536 WHEN 'NET Mensal 2 GB' THEN 2048 WHEN 'NET Mensal 3,5 GB' THEN 3584 WHEN 'NET Mensal 6 GB' THEN 6144 WHEN 'NET Mensal 13 GB' THEN 13312 END,
    'MB', 'ALL', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'unitel'
AND p.name IN ('NET Mensal 1,5 GB','NET Mensal 2 GB','NET Mensal 3,5 GB','NET Mensal 6 GB','NET Mensal 13 GB')
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id);

-- Africell Tudo e Todos — fonte oficial consultada em 25/09/2026.
INSERT INTO plans (operator_id, name, active, published, created_at, updated_at)
SELECT o.id, x.name, 1, 1, NOW(), NOW()
FROM operators o
CROSS JOIN (
    SELECT 'Tudo e Todos 30D 2.000' AS name UNION ALL SELECT 'Tudo e Todos 30D 5.000' UNION ALL SELECT 'Tudo e Todos 30D 10.000' UNION ALL SELECT 'Tudo e Todos 30D 18.000' UNION ALL SELECT 'Tudo e Todos 30D 30.000' UNION ALL SELECT 'Tudo e Todos 30D 40.000'
) x
WHERE o.slug = 'africell' AND NOT EXISTS (SELECT 1 FROM plans p WHERE p.operator_id = o.id AND p.name = x.name);

INSERT INTO plan_versions
(plan_id, version_no, price_kz, validity_days, activation_code, source_url, source_checked_at, notes, active_from, created_at)
SELECT p.id, 1,
    CASE p.name WHEN 'Tudo e Todos 30D 2.000' THEN 2000 WHEN 'Tudo e Todos 30D 5.000' THEN 5000 WHEN 'Tudo e Todos 30D 10.000' THEN 10000 WHEN 'Tudo e Todos 30D 18.000' THEN 18000 WHEN 'Tudo e Todos 30D 30.000' THEN 30000 WHEN 'Tudo e Todos 30D 40.000' THEN 40000 END,
    30,
    CASE p.name WHEN 'Tudo e Todos 30D 2.000' THEN '*123*6*5*1#' WHEN 'Tudo e Todos 30D 5.000' THEN '*123*6*5*2#' WHEN 'Tudo e Todos 30D 10.000' THEN '*123*6*5*3#' WHEN 'Tudo e Todos 30D 18.000' THEN '*123*6*5*4#' WHEN 'Tudo e Todos 30D 30.000' THEN '*123*6*5*5#' WHEN 'Tudo e Todos 30D 40.000' THEN '*123*6*5*6#' END,
    'https://www.africell.ao/tudoetodos/', '2026-09-25', 'Fonte oficial Africell. Benefícios válidos 24 horas nos planos de 30 dias listados.', NOW(), NOW()
FROM plans p INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name LIKE 'Tudo e Todos 30D%'
AND NOT EXISTS (SELECT 1 FROM plan_versions pv WHERE pv.plan_id = p.id);

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT pv.id, 'DATA',
    CASE p.name WHEN 'Tudo e Todos 30D 2.000' THEN 500 WHEN 'Tudo e Todos 30D 5.000' THEN 6656 WHEN 'Tudo e Todos 30D 10.000' THEN 13312 WHEN 'Tudo e Todos 30D 18.000' THEN 22528 WHEN 'Tudo e Todos 30D 30.000' THEN 35840 WHEN 'Tudo e Todos 30D 40.000' THEN 51200 END,
    'MB', 'ALL', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name LIKE 'Tudo e Todos 30D%'
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'DATA');

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT pv.id, 'VOICE',
    CASE p.name WHEN 'Tudo e Todos 30D 2.000' THEN 70 WHEN 'Tudo e Todos 30D 5.000' THEN 300 WHEN 'Tudo e Todos 30D 10.000' THEN 700 WHEN 'Tudo e Todos 30D 18.000' THEN 1500 WHEN 'Tudo e Todos 30D 30.000' THEN 2300 WHEN 'Tudo e Todos 30D 40.000' THEN 3500 END,
    'MIN', 'ALL', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name LIKE 'Tudo e Todos 30D%'
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'VOICE');

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT pv.id, 'SMS', 60, 'SMS', 'ALL', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name = 'Tudo e Todos 30D 2.000'
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'SMS');

-- Movicel fica disponível no catálogo, mas sem seed de planos:
-- as páginas públicas encontradas não têm actualização recente suficiente para publicação automática.

-- Africell 7 dias: benefícios separados por período real de utilização.
INSERT INTO plans (operator_id, name, active, published, created_at, updated_at)
SELECT o.id, x.name, 1, 1, NOW(), NOW()
FROM operators o
CROSS JOIN (SELECT 'Tudo e Todos 7D 500' AS name UNION ALL SELECT 'Tudo e Todos 7D 1.000' UNION ALL SELECT 'Tudo e Todos 7D 2.500') x
WHERE o.slug = 'africell' AND NOT EXISTS (SELECT 1 FROM plans p WHERE p.operator_id = o.id AND p.name = x.name);

INSERT INTO plan_versions
(plan_id, version_no, price_kz, validity_days, activation_code, source_url, source_checked_at, notes, active_from, created_at)
SELECT p.id, 1,
    CASE p.name WHEN 'Tudo e Todos 7D 500' THEN 500 WHEN 'Tudo e Todos 7D 1.000' THEN 1000 WHEN 'Tudo e Todos 7D 2.500' THEN 2500 END,
    7,
    CASE p.name WHEN 'Tudo e Todos 7D 500' THEN '*123*6*3*1#' WHEN 'Tudo e Todos 7D 1.000' THEN '*123*6*3*3#' WHEN 'Tudo e Todos 7D 2.500' THEN '*123*6*3*5#' END,
    'https://www.africell.ao/tudoetodos/', '2026-09-25', 'O volume de dados e minutos é separado entre 07:00–23:59 e 00:00–06:59.', NOW(), NOW()
FROM plans p INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name IN ('Tudo e Todos 7D 500','Tudo e Todos 7D 1.000','Tudo e Todos 7D 2.500')
AND NOT EXISTS (SELECT 1 FROM plan_versions pv WHERE pv.plan_id = p.id);

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, start_time, end_time, created_at)
SELECT pv.id, 'DATA', CASE p.name WHEN 'Tudo e Todos 7D 500' THEN 500 WHEN 'Tudo e Todos 7D 1.000' THEN 800 WHEN 'Tudo e Todos 7D 2.500' THEN 2560 END, 'MB', 'ALL', '07:00:00', '23:59:00', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name IN ('Tudo e Todos 7D 500','Tudo e Todos 7D 1.000','Tudo e Todos 7D 2.500')
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'DATA' AND b.start_time = '07:00:00');

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, start_time, end_time, created_at)
SELECT pv.id, 'DATA', CASE p.name WHEN 'Tudo e Todos 7D 500' THEN 500 WHEN 'Tudo e Todos 7D 1.000' THEN 800 WHEN 'Tudo e Todos 7D 2.500' THEN 2560 END, 'MB', 'ALL', '00:00:00', '06:59:00', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name IN ('Tudo e Todos 7D 500','Tudo e Todos 7D 1.000','Tudo e Todos 7D 2.500')
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'DATA' AND b.start_time = '00:00:00');

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, start_time, end_time, created_at)
SELECT pv.id, 'VOICE', CASE p.name WHEN 'Tudo e Todos 7D 500' THEN 25 WHEN 'Tudo e Todos 7D 1.000' THEN 60 WHEN 'Tudo e Todos 7D 2.500' THEN 130 END, 'MIN', 'ALL', '07:00:00', '23:59:00', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name IN ('Tudo e Todos 7D 500','Tudo e Todos 7D 1.000','Tudo e Todos 7D 2.500')
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'VOICE' AND b.start_time = '07:00:00');

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, start_time, end_time, created_at)
SELECT pv.id, 'VOICE', CASE p.name WHEN 'Tudo e Todos 7D 500' THEN 25 WHEN 'Tudo e Todos 7D 1.000' THEN 60 WHEN 'Tudo e Todos 7D 2.500' THEN 130 END, 'MIN', 'ALL', '00:00:00', '06:59:00', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name IN ('Tudo e Todos 7D 500','Tudo e Todos 7D 1.000','Tudo e Todos 7D 2.500')
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'VOICE' AND b.start_time = '00:00:00');

INSERT INTO benefits (plan_version_id, type, quantity, unit, network_scope, created_at)
SELECT pv.id, 'SMS', CASE p.name WHEN 'Tudo e Todos 7D 500' THEN 25 WHEN 'Tudo e Todos 7D 1.000' THEN 120 WHEN 'Tudo e Todos 7D 2.500' THEN 130 END, 'SMS', 'ALL', NOW()
FROM plan_versions pv INNER JOIN plans p ON p.id = pv.plan_id INNER JOIN operators o ON o.id = p.operator_id
WHERE o.slug = 'africell' AND p.name IN ('Tudo e Todos 7D 500','Tudo e Todos 7D 1.000','Tudo e Todos 7D 2.500')
AND NOT EXISTS (SELECT 1 FROM benefits b WHERE b.plan_version_id = pv.id AND b.type = 'SMS');
