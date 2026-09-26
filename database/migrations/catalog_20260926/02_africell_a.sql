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
('africell','Afrinet 1D 400 MB',300,1,'*123*1*1*2# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 1D 800 MB',500,1,'*123*1*1*3# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 1D 1 GB',600,1,'*123*1*1*4# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 1D 1,5 GB',800,1,'*123*1*1*5# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 3D 500 MB',500,3,'*123*1*2*1# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 3D 1,4 GB',800,3,'*123*1*2*2# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 7D 750 MB + YouTube noite',500,7,'*123*1*3*1# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 7D 1,5 GB',1000,7,'*123*1*3*2# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 7D 4 GB',2000,7,'*123*1*3*3# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 30D 1,5 GB',1500,30,'*123*1*4*1# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 30D 2,5 GB',2000,30,'*123*1*4*2# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 30D 4 GB',3000,30,'*123*1*4*3# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 30D 8 GB',5000,30,'*123*1*4*4# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 30D 18 GB',10000,30,'*123*1*4*5# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 60D 25 GB',15000,60,'*123*1*5*1# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 60D 50 GB',25000,60,'*123*1*5*2# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 60D 150 GB',60000,60,'*123*1*5# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet 60D 200 GB',100000,60,'*123*1*5# | MyAfricell | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/afrinet/','[CATALOG:20260926]\nDados Afrinet conforme página oficial.'),
('africell','Afrinet Nocturno',300,1,'*123*601# / *123*0*7# | MyAfricell | Afrimoney *777*3*1# | ATM/MCX Express | loja/agente','https://www.africell.ao/afrinet-nocturno/','[CATALOG:20260926]\nAcesso ilimitado entre 01h00 e 06h00; FUP de 20 GB por activação e, após essa quota, velocidade reduzida para 2 Mbps. Sem renovação automática.'),
('africell','Socializa 1D 500 MB',200,1,'*123*3*1*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/socializa/','[CATALOG:20260926]\nExclusivo para sete redes sociais; renovação automática por defeito se houver saldo.'),
('africell','Socializa 3D 1 GB',400,3,'*123*3*2*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/socializa/','[CATALOG:20260926]\nExclusivo para sete redes sociais; renovação automática por defeito se houver saldo.'),
('africell','Socializa 7D 1,5 GB',500,7,'*123*3*3*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/socializa/','[CATALOG:20260926]\nExclusivo para sete redes sociais; renovação automática por defeito se houver saldo.'),
('africell','Socializa 7D 2 GB',750,7,'*123*3*3*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/socializa/','[CATALOG:20260926]\nExclusivo para sete redes sociais; renovação automática por defeito se houver saldo.'),
('africell','Socializa 30D 4 GB',1500,30,'*123*3*4*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/socializa/','[CATALOG:20260926]\nExclusivo para sete redes sociais; renovação automática por defeito se houver saldo.'),
('africell','Fala Com Todos 1D 200',200,1,'*123*4*1*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 1D 300',300,1,'*123*4*1*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 3D 300',300,3,'*123*4*2*1# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 3D 400',400,3,'*123*4*2*2# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 3D 500',500,3,'*123*4*2*3# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.'),
('africell','Fala Com Todos 3D 700',700,3,'*123*4*2*4# | lojas/agentes | Afrimoney | ATM/MCX Express | apps bancárias | Telopay/PagáSó/5Linhas/PayPay','https://www.africell.ao/falacomtodos/','[CATALOG:20260926]\nVoz e SMS para todas as redes; renovação automática por defeito.');

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
('africell','Afrinet 1D 400 MB','DATA',400,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 1D 800 MB','DATA',800,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 1D 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 1D 1,5 GB','DATA',1536,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 3D 500 MB','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 3D 1,4 GB','DATA',1433.6,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 7D 750 MB + YouTube noite','DATA',750,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 7D 750 MB + YouTube noite','SOCIAL',750,'MB','ALL','23:00:00','07:00:00','YouTube',NULL),
('africell','Afrinet 7D 1,5 GB','DATA',1536,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 7D 4 GB','DATA',4096,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 30D 1,5 GB','DATA',1536,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 30D 2,5 GB','DATA',2560,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 30D 4 GB','DATA',4096,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 30D 8 GB','DATA',8192,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 30D 18 GB','DATA',18432,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 60D 25 GB','DATA',25600,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 60D 50 GB','DATA',51200,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 60D 150 GB','DATA',153600,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet 60D 200 GB','DATA',204800,'MB','ALL',NULL,NULL,NULL,NULL),
('africell','Afrinet Nocturno','DATA',20480,'MB','ALL','01:00:00','06:00:00',NULL,'FUP:20GB; após a quota a velocidade é reduzida para 2 Mbps'),
('africell','Socializa 1D 500 MB','SOCIAL',500,'MB','ALL',NULL,NULL,'TikTok, YouTube, Facebook, WhatsApp, Instagram, X e Snapchat',NULL),
('africell','Socializa 3D 1 GB','SOCIAL',1024,'MB','ALL',NULL,NULL,'TikTok, YouTube, Facebook, WhatsApp, Instagram, X e Snapchat',NULL),
('africell','Socializa 7D 1,5 GB','SOCIAL',1536,'MB','ALL',NULL,NULL,'TikTok, YouTube, Facebook, WhatsApp, Instagram, X e Snapchat',NULL),
('africell','Socializa 7D 2 GB','SOCIAL',2048,'MB','ALL',NULL,NULL,'TikTok, YouTube, Facebook, WhatsApp, Instagram, X e Snapchat',NULL),
('africell','Socializa 30D 4 GB','SOCIAL',4096,'MB','ALL',NULL,NULL,'TikTok, YouTube, Facebook, WhatsApp, Instagram, X e Snapchat',NULL),
('africell','Fala Com Todos 1D 200','VOICE',20,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 1D 200','VOICE',20,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 1D 200','SMS',40,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 1D 300','VOICE',35,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 1D 300','VOICE',35,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 1D 300','SMS',50,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 3D 300','VOICE',30,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 300','VOICE',30,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 300','SMS',60,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 3D 400','VOICE',45,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 400','VOICE',45,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 400','SMS',90,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 3D 500','VOICE',60,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 500','VOICE',60,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 500','SMS',120,'SMS','ALL',NULL,NULL,NULL,NULL),
('africell','Fala Com Todos 3D 700','VOICE',80,'MIN','ALL','07:00:00','23:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 700','VOICE',80,'MIN','ALL','00:00:00','06:59:00',NULL,NULL),
('africell','Fala Com Todos 3D 700','SMS',160,'SMS','ALL',NULL,NULL,NULL,NULL);

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
