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
('africell','Tudo e Todos 7D 6.000',6000,7,'*123*6*3*6# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 10.000',10000,7,'*123*6*3*7# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 7D 12.500',12500,7,'*123*6*3*8# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nDados e voz divididos entre horário diurno e madrugada conforme página oficial.'),
('africell','Tudo e Todos 30D 2.000',2000,30,'*123*6*5*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nBenefícios 24 horas.'),
('africell','Tudo e Todos 30D 5.000',5000,30,'*123*6*5*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nBenefícios 24 horas.'),
('africell','Tudo e Todos 30D 10.000',10000,30,'*123*6*5*3# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nBenefícios 24 horas.'),
('africell','Tudo e Todos 30D 18.000',18000,30,'*123*6*5*4# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nBenefícios 24 horas.'),
('africell','Tudo e Todos 30D 30.000',30000,30,'*123*6*5*5# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nBenefícios 24 horas.'),
('africell','Tudo e Todos 30D 40.000',40000,30,'*123*6*5*6# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/tudoetodos/','[CATALOG:20260926]\nBenefícios 24 horas.'),
('africell','Afrimix 1D 200',200,1,'*123*6*4# | lojas | Afrimoney | agentes | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrimix/','[CATALOG:20260926]\nMinutos exclusivos para Africell-Africell.'),
('africell','Afrimix 3D 300',300,3,'*123*6*4# | lojas | Afrimoney | agentes | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrimix/','[CATALOG:20260926]\nMinutos exclusivos para Africell-Africell.'),
('africell','Afrimix 3D 500',500,3,'*123*6*4# | lojas | Afrimoney | agentes | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrimix/','[CATALOG:20260926]\nMinutos exclusivos para Africell-Africell.'),
('africell','Afrimix 7D 1.000',1000,7,'*123*6*4# | lojas | Afrimoney | agentes | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrimix/','[CATALOG:20260926]\nMinutos exclusivos para Africell-Africell.'),
('africell','SMS Semanal 200',1000,7,'*123*4*5#','https://www.africell.ao/bundles/Pacotes-De-SMS/','[CATALOG:20260926]\nPacote de SMS Africell.'),
('africell','SMS Mensal 1000',5000,30,'*123*4*5#','https://www.africell.ao/bundles/Pacotes-De-SMS/','[CATALOG:20260926]\nPacote de SMS Africell.');

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
('africell','Tudo e Todos 7D 6.000','DATA',6144,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 6.000','DATA',6144,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 6.000','VOICE',300,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 6.000','VOICE',300,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 6.000','SMS',150,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 7D 10.000','DATA',10240,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 10.000','DATA',10240,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 10.000','VOICE',700,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 10.000','VOICE',700,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 12.500','DATA',12288,'MB','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 12.500','DATA',12288,'MB','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 12.500','VOICE',800,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Tudo e Todos 7D 12.500','VOICE',800,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Tudo e Todos 30D 2.000','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 2.000','VOICE',70,'MIN','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 2.000','SMS',60,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 5.000','DATA',6656,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 5.000','VOICE',300,'MIN','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 10.000','DATA',13312,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 10.000','VOICE',700,'MIN','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 18.000','DATA',22528,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 18.000','VOICE',1500,'MIN','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 30.000','DATA',35840,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 30.000','VOICE',2300,'MIN','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 40.000','DATA',51200,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Tudo e Todos 30D 40.000','VOICE',3500,'MIN','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 1D 200','DATA',200,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 1D 200','VOICE',60,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 300','DATA',300,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 300','VOICE',100,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 500','DATA',600,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 3D 500','VOICE',200,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','Afrimix 7D 1.000','DATA',1280,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrimix 7D 1.000','VOICE',220,'MIN','ONNET',NULL,NULL,NULL,NULL),
('africell','SMS Semanal 200','SMS',200,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','SMS Mensal 1000','SMS',1000,'SMS','ALL',NULL,NULL,NULL,NULL);

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
