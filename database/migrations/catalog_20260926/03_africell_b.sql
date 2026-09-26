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
('africell','Fala Com Todos 7D 850',850,7,'*123*4*3*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 7D 1.000',1000,7,'*123*4*3*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 30D 2.000',2000,30,'*123*4*4*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 30D 3.500',3500,30,'*123*4*4*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Tudo e Todos 1D 300',300,1,'*123*6*1*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 3D 300',300,3,'*123*6*2*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 3D 400',400,3,'*123*6*2*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 3D 500',500,3,'*123*6*2*3# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 3D 600',600,3,'*123*6*2*4# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 3D 700',700,3,'*123*6*2*5# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 500',500,7,'*123*6*3*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 625',625,7,'*123*6*3*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 1.000',1000,7,'*123*6*3*3# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 1.200',1200,7,'*123*6*3*4# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 2.500',2500,7,'*123*6*3*5# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.');

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
('africell','Fala Com Todos 7D 850','VOICE',85,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 7D 850','VOICE',85,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 7D 850','SMS',170,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 7D 1.000','VOICE',100,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 7D 1.000','VOICE',100,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 7D 1.000','SMS',200,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 30D 2.000','VOICE',200,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 30D 2.000','VOICE',200,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 30D 2.000','SMS',400,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 30D 3.500','VOICE',375,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 30D 3.500','VOICE',375,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 30D 3.500','SMS',750,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 1D 300','DATA',300,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 1D 300','DATA',300,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 1D 300','VOICE',20,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 1D 300','VOICE',20,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 1D 300','SMS',40,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 3D 300','DATA',200,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 300','DATA',200,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 300','VOICE',20,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 300','VOICE',20,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 300','SMS',40,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 3D 400','DATA',300,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 400','DATA',300,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 400','VOICE',25,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 400','VOICE',25,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 400','SMS',50,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 3D 500','DATA',450,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 500','DATA',450,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 500','VOICE',30,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 500','VOICE',30,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 500','SMS',60,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 3D 600','DATA',600,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 600','DATA',600,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 600','VOICE',45,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 600','VOICE',45,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 600','SMS',45,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 3D 700','DATA',650,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 700','DATA',650,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 700','VOICE',55,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 700','VOICE',55,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 3D 700','SMS',110,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 7D 500','DATA',500,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 500','DATA',500,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 500','VOICE',25,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 500','VOICE',25,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 500','SMS',25,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 7D 625','DATA',550,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 625','DATA',550,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 625','VOICE',35,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 625','VOICE',35,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 625','SMS',70,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 7D 1.000','DATA',800,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.000','DATA',800,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.000','VOICE',60,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.000','VOICE',60,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.000','SMS',120,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 7D 1.200','DATA',1280,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.200','DATA',1280,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.200','VOICE',65,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.200','VOICE',65,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 1.200','SMS',130,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 7D 2.500','DATA',2560,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 2.500','DATA',2560,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 2.500','VOICE',130,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 2.500','VOICE',130,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 2.500','SMS',130,'SMS','ALL',NULL,NULL,NULL,NULL);

INSERT INTO benefits
(plan_version_id,type,quantity,unit,network_scope,start_time,end_time,app_scope,label,created_at)
SELECT pv.id,b.benefit_type,b.quantity,b.unit,b.network_scope,b.start_time,b.end_time,b.app_scope,b.label,NOW()
FROM qp_benefit_import b
INNER JOIN operators o ON o.slug=b.operator_slug
INNER JOIN plans p ON p.operator_id=o.id AND p.name=b.plan_name
INNER JOIN plan_versions pv ON pv.plan_id=p.id AND pv.notes LIKE '%[CATALOG:20260926]%'
WHERE NOT EXISTS (SELECT 1 FROM benefits x WHERE x.plan_version_id=pv.id);

DROP TEMPORARY TABLE IF EXISTS qp_benefit_import;
DROP TEMPORARY TABLE IF EXISTS qp_plan_import;
