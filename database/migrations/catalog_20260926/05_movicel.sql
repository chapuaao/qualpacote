SET NAMES utf8mb4;
SET time_zone = '+01:00';

-- Catálogo expandido verificado em 26/09/2026.
-- A migração é idempotente: cada plano recebe no máximo uma versão marcada CATALOG:20260926.

DROP TEMPORARY TABLE IF EXISTS qp_plan_import;
CREATE TEMPORARY TABLE qp_plan_import (
    operator_slug VARCHAR(80) NOT NULL,
    plan_name VARCHAR(180) NOT NULL,
    price_kz DECIMAL(12,2) NOT NULL,
    validity_days SMALLINT UNSIGNED NOT NULL,
    activation_code VARCHAR(120) NULL,
    source_url VARCHAR(600) NOT NULL,
    notes TEXT NULL,
    PRIMARY KEY (operator_slug, plan_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO qp_plan_import (operator_slug,plan_name,price_kz,validity_days,activation_code,source_url,notes) VALUES
('movicel','FLEX 25',500,30,'*202*500# | *191# | SMS 19202: 500 | Multicaixa | loja Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\nMinutos e SMS para Movicel em carteiras separadas; renovação automática se houver saldo.'),
('movicel','FLEX 55',1000,30,'*202*1000# | *191# | SMS 19202: 1000 | Multicaixa | loja Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\nMinutos e SMS para Movicel em carteiras separadas; renovação automática se houver saldo.'),
('movicel','FLEX 100',2000,30,'*202*2000# | *191# | SMS 19202: 2000 | Multicaixa | loja Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\nMinutos e SMS para Movicel em carteiras separadas; renovação automática se houver saldo.'),
('movicel','FLEX 150',3000,30,'*202*3000# | *191# | SMS 19202: 3000 | Multicaixa | loja Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\nMinutos e SMS para Movicel em carteiras separadas; renovação automática se houver saldo.'),
('movicel','FLEX 250',4000,30,'*202*4000# | *191# | SMS 19202: 4000 | Multicaixa | loja Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\nMinutos e SMS para Movicel em carteiras separadas; renovação automática se houver saldo.'),
('movicel','FLEX 350',5000,30,'*202*5000# | *191# | SMS 19202: 5000 | Multicaixa | loja Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\nMinutos e SMS para Movicel em carteiras separadas; renovação automática se houver saldo.'),
('movicel','FLEX Aditivo Outras Redes 20',500,30,'*203*500# | SMS 19203 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nMinutos e SMS destinados a outras redes nacionais.'),
('movicel','FLEX Aditivo Outras Redes 40',1000,30,'*203*1000# | SMS 19203 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nMinutos e SMS destinados a outras redes nacionais.'),
('movicel','FLEX Aditivo Outras Redes 80',2000,30,'*203*2000# | SMS 19203 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nMinutos e SMS destinados a outras redes nacionais.'),
('movicel','FLEX Aditivo Outras Redes 120',3000,30,'*203*3000# | SMS 19203 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nMinutos e SMS destinados a outras redes nacionais.'),
('movicel','FLEX Aditivo Outras Redes 160',4000,30,'*203*4000# | SMS 19203 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nMinutos e SMS destinados a outras redes nacionais.'),
('movicel','FLEX Aditivo Outras Redes 200',5000,30,'*203*5000# | SMS 19203 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nMinutos e SMS destinados a outras redes nacionais.'),
('movicel','FLEX Dados 1D 100 MB',100,1,'*204*100# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 1D 500 MB',400,1,'*204*400# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 1D 1 GB',700,1,'*204*700# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 3D 1 GB',850,3,'*204*850# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 30D 1 GB',1000,30,'*204*1000# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 30D 2 GB',2000,30,'*204*2000# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 30D 6 GB',5000,30,'*204*5000# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Dados 30D 10 GB',8000,30,'*204*8000# | SMS 19204 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nAditivo de dados para qualquer hora.'),
('movicel','FLEX Noites Bwé 3 GB',200,15,'*205*200# | SMS 19205 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nDados carregados diariamente; utilizáveis entre 00:00 e 05:00.'),
('movicel','FLEX Noites Bwé 7,3 GB',500,15,'*205*500# | SMS 19205 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nDados carregados diariamente; utilizáveis entre 00:00 e 05:00.'),
('movicel','FLEX Noites Bwé 15 GB',1000,15,'*205*1000# | SMS 19205 | *191# | lojas/agentes Movicel','https://www.movicel.co.ao/Download/ResumoPSite_OfertaFlexMovicel.pdf','[CATALOG:20260926]\n[REQUIREMENT]\nRequer um Plano FLEX activo.\n[/REQUIREMENT]\nDados carregados diariamente; utilizáveis entre 00:00 e 05:00.');

INSERT INTO plans (operator_id,name,active,published,created_at,updated_at)
SELECT o.id,i.plan_name,1,1,NOW(),NOW()
FROM qp_plan_import i
INNER JOIN operators o ON o.slug=i.operator_slug
WHERE NOT EXISTS (SELECT 1 FROM plans p WHERE p.operator_id=o.id AND p.name=i.plan_name);

UPDATE plan_versions pv
INNER JOIN plans p ON p.id=pv.plan_id
INNER JOIN operators o ON o.id=p.operator_id
INNER JOIN qp_plan_import i ON i.operator_slug=o.slug AND i.plan_name=p.name
SET pv.active_to=NOW()
WHERE pv.active_to IS NULL
  AND NOT EXISTS (SELECT 1 FROM plan_versions x WHERE x.plan_id=p.id AND x.notes LIKE '%[CATALOG:20260926]%');

INSERT INTO plan_versions
(plan_id,version_no,price_kz,validity_days,activation_code,source_url,source_checked_at,notes,active_from,created_by,created_at)
SELECT p.id,(SELECT COALESCE(MAX(v.version_no),0)+1 FROM plan_versions v WHERE v.plan_id=p.id),i.price_kz,i.validity_days,i.activation_code,i.source_url,'2026-09-26',i.notes,NOW(),NULL,NOW()
FROM qp_plan_import i
INNER JOIN operators o ON o.slug=i.operator_slug
INNER JOIN plans p ON p.operator_id=o.id AND p.name=i.plan_name
WHERE NOT EXISTS (SELECT 1 FROM plan_versions x WHERE x.plan_id=p.id AND x.notes LIKE '%[CATALOG:20260926]%');

DROP TEMPORARY TABLE IF EXISTS qp_benefit_import;
CREATE TEMPORARY TABLE qp_benefit_import (
    operator_slug VARCHAR(80) NOT NULL,
    plan_name VARCHAR(180) NOT NULL,
    benefit_type ENUM('DATA','VOICE','SMS','SOCIAL') NOT NULL,
    quantity DECIMAL(14,2) NOT NULL,
    unit ENUM('MB','MIN','SMS') NOT NULL,
    network_scope ENUM('ALL','ONNET','OFFNET') NOT NULL DEFAULT 'ALL',
    start_time TIME NULL,
    end_time TIME NULL,
    app_scope VARCHAR(500) NULL,
    label VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO qp_benefit_import (operator_slug,plan_name,benefit_type,quantity,unit,network_scope,start_time,end_time,app_scope,label) VALUES
('movicel','FLEX 25','VOICE',25,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 25','SMS',30,'SMS','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 55','VOICE',55,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 55','SMS',50,'SMS','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 100','VOICE',100,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 100','SMS',80,'SMS','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 150','VOICE',150,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 150','SMS',100,'SMS','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 250','VOICE',250,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 250','SMS',150,'SMS','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 350','VOICE',350,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX 350','SMS',200,'SMS','ONNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 20','VOICE',20,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 20','SMS',10,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 40','VOICE',40,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 40','SMS',20,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 80','VOICE',80,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 80','SMS',40,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 120','VOICE',120,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 120','SMS',40,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 160','VOICE',160,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 160','SMS',60,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 200','VOICE',200,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Aditivo Outras Redes 200','SMS',60,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 1D 100 MB','DATA',100,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 1D 500 MB','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 1D 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 3D 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 30D 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 30D 2 GB','DATA',2048,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 30D 6 GB','DATA',6144,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Dados 30D 10 GB','DATA',10240,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','FLEX Noites Bwé 3 GB','DATA',3072,'MB','ALL','00:00:00','05:00:00',NULL,'DAILY_CAP:200|Recebe 200 MB por noite; o saldo diário não acumula.'),
('movicel','FLEX Noites Bwé 7,3 GB','DATA',7475.2,'MB','ALL','00:00:00','05:00:00',NULL,'DAILY_CAP:500|Recebe 500 MB por noite; o saldo diário não acumula.'),
('movicel','FLEX Noites Bwé 15 GB','DATA',15360,'MB','ALL','00:00:00','05:00:00',NULL,'DAILY_CAP:1024|Recebe 1 GB por noite; o saldo diário não acumula.');

INSERT INTO benefits
(plan_version_id,type,quantity,unit,network_scope,start_time,end_time,app_scope,label,created_at)
SELECT pv.id,b.benefit_type,b.quantity,b.unit,b.network_scope,b.start_time,b.end_time,b.app_scope,b.label,NOW()
FROM qp_benefit_import b
INNER JOIN operators o ON o.slug=b.operator_slug
INNER JOIN plans p ON p.operator_id=o.id AND p.name=b.plan_name
INNER JOIN plan_versions pv ON pv.plan_id=p.id AND pv.notes LIKE '%[CATALOG:20260926]%'
WHERE NOT EXISTS (SELECT 1 FROM benefits x WHERE x.plan_version_id=pv.id);

-- Completa a comparação com saldo normal da Movicel usando o tarifário base oficial ainda publicado.
INSERT INTO tariffs (operator_id,name,is_default,active,published,created_at,updated_at)
SELECT o.id,'Tarifário Base',1,1,1,NOW(),NOW()
FROM operators o
WHERE o.slug='movicel' AND NOT EXISTS (SELECT 1 FROM tariffs t WHERE t.operator_id=o.id AND t.name='Tarifário Base');
SET @mov_base=(SELECT t.id FROM tariffs t INNER JOIN operators o ON o.id=t.operator_id WHERE o.slug='movicel' AND t.name='Tarifário Base' LIMIT 1);
UPDATE tariff_versions SET active_to=NOW()
WHERE tariff_id=@mov_base AND active_to IS NULL
  AND NOT EXISTS (SELECT 1 FROM tariff_versions x WHERE x.tariff_id=@mov_base AND x.notes LIKE '%[CATALOG:20260926]%');
INSERT INTO tariff_versions
(tariff_id,version_no,balance_validity_days,source_url,source_checked_at,notes,active_from,created_by,created_at)
SELECT @mov_base,(SELECT COALESCE(MAX(v.version_no),0)+1 FROM tariff_versions v WHERE v.tariff_id=@mov_base),NULL,
       'https://www.movicel.co.ao/saiba-mais/tarifario-base.html','2026-09-26',
       '[CATALOG:20260926] Tarifário base Movicel publicado no site oficial; chamadas têm 5 segundos iniciais grátis e regra própria de inicialização.',NOW(),NULL,NOW()
WHERE @mov_base IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tariff_versions x WHERE x.tariff_id=@mov_base AND x.notes LIKE '%[CATALOG:20260926]%');
SET @mov_base_v=(SELECT id FROM tariff_versions WHERE tariff_id=@mov_base AND notes LIKE '%[CATALOG:20260926]%' ORDER BY version_no DESC LIMIT 1);
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @mov_base_v,'VOICE','MIN',32.5700,'ONNET','Movicel → Movicel',NOW()
WHERE @mov_base_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@mov_base_v);
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @mov_base_v,'VOICE','MIN',39.0800,'OFFNET','Movicel → outras redes nacionais',NOW()
WHERE @mov_base_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@mov_base_v AND service_type='VOICE' AND network_scope='OFFNET');
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @mov_base_v,'SMS','SMS',13.5700,'ALL','SMS nacional',NOW()
WHERE @mov_base_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@mov_base_v AND service_type='SMS');
INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,label,created_at)
SELECT @mov_base_v,'DATA','MB',34.7200,'ALL','Dados avulsos',NOW()
WHERE @mov_base_v IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tariff_rates WHERE tariff_version_id=@mov_base_v AND service_type='DATA');

DROP TEMPORARY TABLE IF EXISTS qp_benefit_import;
DROP TEMPORARY TABLE IF EXISTS qp_plan_import;
