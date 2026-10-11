SET NAMES utf8mb4;
SET time_zone = '+01:00';
-- Revisão humana das fontes oficiais em 11/10/2026. Histórico preservado.
-- Importe primeiro 20261011_catalog_intelligence.sql. Reimportar não duplica versões.
START TRANSACTION;
UPDATE operators SET website='https://www.movicel.co.ao/' WHERE slug='movicel';
DROP TEMPORARY TABLE IF EXISTS qp_verified_import;
CREATE TEMPORARY TABLE qp_verified_import (
 operator_slug VARCHAR(80),name VARCHAR(180),price_kz DECIMAL(12,2),validity_days SMALLINT,
 activation_code VARCHAR(120),source_url VARCHAR(600),notes TEXT,rules_json TEXT,
 PRIMARY KEY (operator_slug,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO qp_verified_import VALUES
('africell','Afrimix 1D 200',200,1,'*123*7*1#','https://www.africell.ao/afrimix/','[CATALOG:20261011]\nChamadas exclusivamente Africell. Cada subscrição tem saldo e validade próprios; o saldo expira. Renovação opcional. Uma passagem da FAQ refere outras redes, em conflito com a descrição e restantes condições: confirmar em revisão.','{"category":"mobile","repeat_policy":"parallel","family":"afrimix","source_offer_key":"afrimix:option-1","requires_family":"","combination_group":"afrimix","allows_balance":true,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Afrimix 3D 300',300,3,'*123*7*2#','https://www.africell.ao/afrimix/','[CATALOG:20261011]\nChamadas exclusivamente Africell. Cada subscrição tem saldo e validade próprios; o saldo expira. Renovação opcional. Uma passagem da FAQ refere outras redes, em conflito com a descrição e restantes condições: confirmar em revisão.','{"category":"mobile","repeat_policy":"parallel","family":"afrimix","source_offer_key":"afrimix:option-2","requires_family":"","combination_group":"afrimix","allows_balance":true,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Afrimix 3D 500',500,3,'*123*7*3#','https://www.africell.ao/afrimix/','[CATALOG:20261011]\nChamadas exclusivamente Africell. Cada subscrição tem saldo e validade próprios; o saldo expira. Renovação opcional. Uma passagem da FAQ refere outras redes, em conflito com a descrição e restantes condições: confirmar em revisão.','{"category":"mobile","repeat_policy":"parallel","family":"afrimix","source_offer_key":"afrimix:option-3","requires_family":"","combination_group":"afrimix","allows_balance":true,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Afrimix 7D 1.000',1000,7,'*123*7*4#','https://www.africell.ao/afrimix/','[CATALOG:20261011]\nChamadas exclusivamente Africell. Cada subscrição tem saldo e validade próprios; o saldo expira. Renovação opcional. Uma passagem da FAQ refere outras redes, em conflito com a descrição e restantes condições: confirmar em revisão.','{"category":"mobile","repeat_policy":"parallel","family":"afrimix","source_offer_key":"afrimix:option-4","requires_family":"","combination_group":"afrimix","allows_balance":true,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('movicel','Movinet 1D 100 MB',100,1,'*200*100#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 1D 500 MB',400,1,'*200*400#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 1D 1 GB',700,1,'*200*700#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 7D 400 MB',350,7,'*200*350#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 15D 1 GB',1000,15,'*200*1000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 30D 2 GB',2000,30,'*200*2000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 30D 6 GB',5000,30,'*200*5000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 30D 10 GB',8000,30,'*200*8000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 30D 15 GB',10000,30,'*200*10000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 30D 30 GB',18000,30,'*200*18000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('movicel','Movinet 30D 60 GB',32000,30,'*200*32000#','https://www.movicel.co.ao/plano/movinet.html','[CATALOG:20261011]\nPré-pago particular ou empresa. Dados gerais numa carteira única. Incompatível com FLEX: a activação pode apagar benefícios existentes. Renovação automática; acumulação limitada a 3 vezes, não adicionada ao cálculo. Condições: https://www.movicel.co.ao/plano/FAQ/Movinet.html','{"category":"mobile","repeat_policy":"sequential","family":"movinet","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":["flex"]}'),
('africell','Konekta 4G 6 GB',4000,7,'*123*5#','https://www.africell.ao/konekta/plans/Konekta-4G/','[CATALOG:20261011]\nRequer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.','{"category":"router","repeat_policy":"unknown","family":"konekta4g","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Konekta 4G 12 GB',6500,7,'*123*5#','https://www.africell.ao/konekta/plans/Konekta-4G/','[CATALOG:20261011]\nRequer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.','{"category":"router","repeat_policy":"unknown","family":"konekta4g","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Konekta 4G 25 GB',13000,30,'*123*5#','https://www.africell.ao/konekta/plans/Konekta-4G/','[CATALOG:20261011]\nRequer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.','{"category":"router","repeat_policy":"unknown","family":"konekta4g","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Konekta 4G 40 GB',20000,30,'*123*5#','https://www.africell.ao/konekta/plans/Konekta-4G/','[CATALOG:20261011]\nRequer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.','{"category":"router","repeat_policy":"unknown","family":"konekta4g","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Konekta 4G 60 GB',25000,30,'*123*5#','https://www.africell.ao/konekta/plans/Konekta-4G/','[CATALOG:20261011]\nRequer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.','{"category":"router","repeat_policy":"unknown","family":"konekta4g","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('africell','Konekta 4G 100 GB',40000,30,'*123*5#','https://www.africell.ao/konekta/plans/Konekta-4G/','[CATALOG:20261011]\nRequer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.','{"category":"router","repeat_policy":"unknown","family":"konekta4g","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('movicel','Kuia',100,1,'*500*100#','https://www.movicel.co.ao/plano/FAQ/PlanoMix.html','[CATALOG:20261011]\nMinutos/SMS Movicel partilham uma única carteira. Minutos e SMS para outras redes são carteiras distintas. Sem renovação automática; nova subscrição manual quando terminar. Coexistência com outras famílias tem descrições contraditórias nas FAQs: não combinada. Benefícios e preço: tabela oficial PlanoMixPB4.png.','{"category":"mobile","repeat_policy":"unknown","family":"kuia","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('movicel','Kuia+',200,1,'*500*200#','https://www.movicel.co.ao/plano/FAQ/PlanoMix.html','[CATALOG:20261011]\nMinutos/SMS Movicel partilham uma única carteira. Minutos e SMS para outras redes são carteiras distintas. Sem renovação automática; nova subscrição manual quando terminar. Coexistência com outras famílias tem descrições contraditórias nas FAQs: não combinada. Benefícios e preço: tabela oficial PlanoMixPB4.png.','{"category":"mobile","repeat_policy":"unknown","family":"kuia","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}'),
('movicel','Takuiá',500,3,'*500*500#','https://www.movicel.co.ao/plano/FAQ/PlanoMix.html','[CATALOG:20261011]\nMinutos/SMS Movicel partilham uma única carteira. Minutos e SMS para outras redes são carteiras distintas. Sem renovação automática; nova subscrição manual quando terminar. Coexistência com outras famílias tem descrições contraditórias nas FAQs: não combinada. Benefícios e preço: tabela oficial PlanoMixPB4.png.','{"category":"mobile","repeat_policy":"unknown","family":"kuia","requires_family":"","combination_group":"","allows_balance":false,"offer_ends_at":"","voice_step_seconds":0,"incompatible_families":[]}');
INSERT INTO plans (operator_id,name,active,published,created_at,updated_at)
SELECT o.id,i.name,1,1,NOW(),NOW() FROM qp_verified_import i JOIN operators o ON o.slug=i.operator_slug
WHERE NOT EXISTS (SELECT 1 FROM plans p WHERE p.operator_id=o.id AND p.name=i.name);
UPDATE plan_versions pv JOIN plans p ON p.id=pv.plan_id JOIN operators o ON o.id=p.operator_id
JOIN qp_verified_import i ON i.operator_slug=o.slug AND i.name=p.name
SET pv.active_to=NOW() WHERE pv.active_to IS NULL
AND NOT EXISTS (SELECT 1 FROM plan_versions x WHERE x.plan_id=p.id AND x.notes LIKE '%[CATALOG:20261011]%');
INSERT INTO plan_versions (plan_id,version_no,price_kz,validity_days,activation_code,source_url,source_checked_at,notes,active_from,created_by,created_at)
SELECT p.id,(SELECT COALESCE(MAX(version_no),0)+1 FROM plan_versions WHERE plan_id=p.id),i.price_kz,i.validity_days,i.activation_code,i.source_url,'2026-10-11',i.notes,NOW(),NULL,NOW()
FROM qp_verified_import i JOIN operators o ON o.slug=i.operator_slug JOIN plans p ON p.operator_id=o.id AND p.name=i.name
WHERE NOT EXISTS (SELECT 1 FROM plan_versions x WHERE x.plan_id=p.id AND x.notes LIKE '%[CATALOG:20261011]%');
INSERT INTO plan_version_rules (plan_version_id,rules_json)
SELECT pv.id,i.rules_json FROM qp_verified_import i JOIN operators o ON o.slug=i.operator_slug JOIN plans p ON p.operator_id=o.id AND p.name=i.name
JOIN plan_versions pv ON pv.plan_id=p.id AND pv.notes LIKE '%[CATALOG:20261011]%'
WHERE NOT EXISTS (SELECT 1 FROM plan_version_rules r WHERE r.plan_version_id=pv.id);
DROP TEMPORARY TABLE IF EXISTS qp_verified_benefits;
CREATE TEMPORARY TABLE qp_verified_benefits (
 operator_slug VARCHAR(80),name VARCHAR(180),type VARCHAR(10),quantity DECIMAL(14,2),unit VARCHAR(10),network_scope VARCHAR(10),
 start_time TIME NULL,end_time TIME NULL,app_scope VARCHAR(500) NULL,label VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO qp_verified_benefits VALUES
('africell','Afrimix 1D 200','DATA',200,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 1D 200','VOICE',60,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 300','DATA',300,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 300','VOICE',100,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 500','DATA',600,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 500','VOICE',200,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','Afrimix 7D 1.000','DATA',1280,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 7D 1.000','VOICE',220,'MIN','ONNET',NULL,NULL,NULL,NULL),
('movicel','Movinet 1D 100 MB','DATA',100,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 1D 500 MB','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 1D 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 7D 400 MB','DATA',400,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 15D 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 30D 2 GB','DATA',2048,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 30D 6 GB','DATA',6144,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 30D 10 GB','DATA',10240,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 30D 15 GB','DATA',15360,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 30D 30 GB','DATA',30720,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Movinet 30D 60 GB','DATA',61440,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Konekta 4G 6 GB','DATA',6144,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Konekta 4G 12 GB','DATA',12288,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Konekta 4G 25 GB','DATA',25600,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Konekta 4G 40 GB','DATA',40960,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Konekta 4G 60 GB','DATA',61440,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Konekta 4G 100 GB','DATA',102400,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Kuia','VOICE',15,'MIN','ONNET',NULL,NULL,NULL,'SHARED:kuia-onnet|Minutos/SMS Movicel numa carteira única'),
('movicel','Kuia','SMS',15,'SMS','ONNET',NULL,NULL,NULL,'SHARED:kuia-onnet|Minutos/SMS Movicel numa carteira única'),
('movicel','Kuia','VOICE',3,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','Kuia','SMS',2,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','Kuia','DATA',250,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Kuia+','VOICE',35,'MIN','ONNET',NULL,NULL,NULL,'SHARED:kuia-onnet|Minutos/SMS Movicel numa carteira única'),
('movicel','Kuia+','SMS',35,'SMS','ONNET',NULL,NULL,NULL,'SHARED:kuia-onnet|Minutos/SMS Movicel numa carteira única'),
('movicel','Kuia+','VOICE',6,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','Kuia+','SMS',4,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','Kuia+','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('movicel','Takuiá','VOICE',50,'MIN','ONNET',NULL,NULL,NULL,'SHARED:kuia-onnet|Minutos/SMS Movicel numa carteira única'),
('movicel','Takuiá','SMS',50,'SMS','ONNET',NULL,NULL,NULL,'SHARED:kuia-onnet|Minutos/SMS Movicel numa carteira única'),
('movicel','Takuiá','VOICE',10,'MIN','OFFNET',NULL,NULL,NULL,NULL),
('movicel','Takuiá','SMS',5,'SMS','OFFNET',NULL,NULL,NULL,NULL),
('movicel','Takuiá','DATA',400,'MB','ALL',NULL,NULL,NULL,NULL);
INSERT INTO benefits (plan_version_id,type,quantity,unit,network_scope,start_time,end_time,app_scope,label,created_at)
SELECT pv.id,b.type,b.quantity,b.unit,b.network_scope,b.start_time,b.end_time,b.app_scope,b.label,NOW()
FROM qp_verified_benefits b JOIN operators o ON o.slug=b.operator_slug JOIN plans p ON p.operator_id=o.id AND p.name=b.name
JOIN plan_versions pv ON pv.plan_id=p.id AND pv.notes LIKE '%[CATALOG:20261011]%'
WHERE NOT EXISTS (SELECT 1 FROM benefits existing WHERE existing.plan_version_id=pv.id);
DROP TEMPORARY TABLE qp_verified_import;
DROP TEMPORARY TABLE qp_verified_benefits;
COMMIT;

-- Regras FLEX confirmadas na FAQ. Preços e volumes conservam a data de revisão
-- anterior: esta parte NÃO declara uma nova confirmação integral dos tarifários.
START TRANSACTION;
DROP TEMPORARY TABLE IF EXISTS qp_flex_rules;
CREATE TEMPORARY TABLE qp_flex_rules AS
SELECT p.id AS plan_id,pv.id AS old_version_id,pv.version_no,pv.price_kz,pv.validity_days,pv.activation_code,pv.source_url,pv.source_checked_at,pv.notes,
 CASE WHEN p.name LIKE 'FLEX Aditivo Outras Redes%' THEN 'flex-offnet' WHEN p.name LIKE 'FLEX Dados%' THEN 'flex-data' WHEN p.name LIKE 'FLEX Noites%' THEN 'flex-noites' ELSE 'flex' END AS family,
 CASE WHEN pv.notes LIKE '%[REQUIREMENT]%' THEN 'flex' ELSE '' END AS requires_family
FROM plans p JOIN operators o ON o.id=p.operator_id JOIN plan_versions pv ON pv.plan_id=p.id
WHERE o.slug='movicel' AND p.name LIKE 'FLEX %' AND pv.id=(SELECT id FROM plan_versions WHERE plan_id=p.id ORDER BY version_no DESC,id DESC LIMIT 1)
AND NOT EXISTS (SELECT 1 FROM plan_versions x WHERE x.plan_id=p.id AND x.notes LIKE '%[RULES:20261011]%');
UPDATE plan_versions pv JOIN qp_flex_rules f ON f.old_version_id=pv.id SET pv.active_to=NOW();
INSERT INTO plan_versions (plan_id,version_no,price_kz,validity_days,activation_code,source_url,source_checked_at,notes,active_from,created_at)
SELECT plan_id,version_no+1,price_kz,validity_days,activation_code,source_url,source_checked_at,
 CONCAT(COALESCE(notes,''),'\n[RULES:20261011]\nCoexistência FLEX/aditivos confirmada; cada carteira mantém validade própria. Dados diários FLEX acumulam até à expiração. Fonte das regras: https://www.movicel.co.ao/plano/FAQ/NovoFlex.html (11/10/2026). Preços e volumes conservam a data de confirmação anterior.'),NOW(),NOW()
FROM qp_flex_rules;
INSERT INTO benefits (plan_version_id,type,quantity,unit,network_scope,start_time,end_time,app_scope,label,created_at)
SELECT pv.id,b.type,b.quantity,b.unit,b.network_scope,b.start_time,b.end_time,b.app_scope,
 CASE WHEN f.family='flex' AND b.label LIKE 'DAILY_CAP:%' THEN REPLACE(b.label,'DAILY_CAP:','DAILY_RELEASE:') ELSE b.label END,NOW()
FROM qp_flex_rules f JOIN plan_versions pv ON pv.plan_id=f.plan_id AND pv.version_no=f.version_no+1 JOIN benefits b ON b.plan_version_id=f.old_version_id;
INSERT INTO plan_version_rules (plan_version_id,rules_json)
SELECT pv.id,JSON_OBJECT('category','mobile','family',f.family,'requires_family',f.requires_family,'combination_group','flex',
 'repeat_policy',IF(f.family='flex','sequential','unknown'),'allows_balance',true,'voice_step_seconds',IF(f.family='flex',60,0),'incompatible_families',JSON_ARRAY('movinet'))
FROM qp_flex_rules f JOIN plan_versions pv ON pv.plan_id=f.plan_id AND pv.version_no=f.version_no+1;
DROP TEMPORARY TABLE qp_flex_rules;
COMMIT;
