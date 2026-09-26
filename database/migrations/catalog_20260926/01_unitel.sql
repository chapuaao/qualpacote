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
('unitel','Plano Base',2000,30,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Plano Base UNITEL; benefícios 24h conforme revista oficial.'),
('unitel','Redes Sociais 500 MB',300,1,'*157*300# | SMS 19157 | MyUnitel / Unitel Money | lojas/agentes | ATM/MCX Express','https://unitel.ao/wp-content/uploads/share_upload/2026/02/Termos-de-Utilizacao-do-Servico_Redes-Sociais_18.12.2025.pdf','[CATALOG:20260926]
Exclusivo para redes sociais listadas pela UNITEL.'),
('unitel','Redes Sociais 800 MB',400,3,'*157*400# | SMS 19157 | MyUnitel / Unitel Money | lojas/agentes | ATM/MCX Express','https://unitel.ao/wp-content/uploads/share_upload/2026/02/Termos-de-Utilizacao-do-Servico_Redes-Sociais_18.12.2025.pdf','[CATALOG:20260926]
Exclusivo para redes sociais listadas pela UNITEL.'),
('unitel','Redes Sociais 900 MB',500,3,'*157*500# | SMS 19157 | MyUnitel / Unitel Money | lojas/agentes | ATM/MCX Express','https://unitel.ao/wp-content/uploads/share_upload/2026/02/Termos-de-Utilizacao-do-Servico_Redes-Sociais_18.12.2025.pdf','[CATALOG:20260926]
Exclusivo para redes sociais listadas pela UNITEL.'),
('unitel','Redes Sociais 1,5 GB',750,7,'*157*750# | SMS 19157 | MyUnitel / Unitel Money | lojas/agentes | ATM/MCX Express','https://unitel.ao/wp-content/uploads/share_upload/2026/02/Termos-de-Utilizacao-do-Servico_Redes-Sociais_18.12.2025.pdf','[CATALOG:20260926]
Exclusivo para redes sociais listadas pela UNITEL.'),
('unitel','Redes Sociais 3 GB',1500,30,'*157*1500# | SMS 19157 | MyUnitel / Unitel Money | lojas/agentes | ATM/MCX Express','https://unitel.ao/wp-content/uploads/share_upload/2026/02/Termos-de-Utilizacao-do-Servico_Redes-Sociais_18.12.2025.pdf','[CATALOG:20260926]
Exclusivo para redes sociais listadas pela UNITEL.'),
('unitel','NET ao Dia 300 MB',200,1,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET ao Dia 400 MB',300,1,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET ao Dia 800 MB',500,1,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Semanal 500 MB',500,7,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Semanal 1 GB',1000,7,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Mensal 1,5 GB',1500,31,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Mensal 2 GB',2000,31,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Mensal 3,5 GB',3000,31,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Mensal 6 GB',5000,31,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET Mensal 13 GB',10000,31,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET 2 Meses 20 GB',15000,60,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET 2 Meses 40 GB',25000,60,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET 6 Meses 100 GB',60000,180,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','NET 12 Meses 200 GB',100000,365,NULL,'https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Pacote de dados UNITEL conforme revista oficial; canal de activação não inferido quando a fonte não o explicita.'),
('unitel','MAIS Leve 7D',625,7,'*145*625#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma carteira partilhada de unidades. Oferta do mesmo volume das 22h às 07h.'),
('unitel','MAIS Vivo 7D',1250,7,'*145*1250#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma carteira partilhada de unidades. Oferta do mesmo volume das 22h às 07h.'),
('unitel','MAIS Ágil 7D',2500,7,'*145*2500#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma carteira partilhada de unidades. Oferta do mesmo volume das 22h às 07h.'),
('unitel','MAIS Social 7D',6250,7,'*145*6250#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma carteira partilhada de unidades. Oferta do mesmo volume das 22h às 07h.'),
('unitel','MAIS Livre 7D',12500,7,'*145*12500#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma carteira partilhada de unidades. Oferta do mesmo volume das 22h às 07h.'),
('unitel','MAIS 30D 5.000',5000,30,'*145*5000#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma única carteira partilhada de unidades.'),
('unitel','MAIS 30D 10.000',10000,30,'*145*10000#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma única carteira partilhada de unidades.'),
('unitel','MAIS 30D 18.000',18000,30,'*145*18000#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma única carteira partilhada de unidades.'),
('unitel','MAIS 30D 30.000',30000,30,'*145*30000#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma única carteira partilhada de unidades.'),
('unitel','MAIS 30D 50.000',50000,30,'*145*50000#','https://unitel.ao/wp-content/uploads/share_upload/2026/04/UNITEL_RevistaMaisProximaAbrMai2026DIGITAL.pdf','[CATALOG:20260926]
Min/SMS tratados como uma única carteira partilhada de unidades.');

INSERT INTO plans (operator_id,name,active,published,created_at,updated_at)
SELECT o.id,i.plan_name,1,1,NOW(),NOW()
FROM qp_plan_import i
INNER JOIN operators o ON o.slug=i.operator_slug
WHERE NOT EXISTS (
    SELECT 1 FROM plans p WHERE p.operator_id=o.id AND p.name=i.plan_name
);

UPDATE plan_versions pv
INNER JOIN plans p ON p.id=pv.plan_id
INNER JOIN operators o ON o.id=p.operator_id
INNER JOIN qp_plan_import i ON i.operator_slug=o.slug AND i.plan_name=p.name
SET pv.active_to=NOW()
WHERE pv.active_to IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM plan_versions x
      WHERE x.plan_id=p.id AND x.notes LIKE '%[CATALOG:20260926]%'
  );

INSERT INTO plan_versions
(plan_id,version_no,price_kz,validity_days,activation_code,source_url,source_checked_at,notes,active_from,created_by,created_at)
SELECT
    p.id,
    (SELECT COALESCE(MAX(v.version_no),0)+1 FROM plan_versions v WHERE v.plan_id=p.id),
    i.price_kz,i.validity_days,i.activation_code,i.source_url,'2026-09-26',i.notes,NOW(),NULL,NOW()
FROM qp_plan_import i
INNER JOIN operators o ON o.slug=i.operator_slug
INNER JOIN plans p ON p.operator_id=o.id AND p.name=i.plan_name
WHERE NOT EXISTS (
    SELECT 1 FROM plan_versions x
    WHERE x.plan_id=p.id AND x.notes LIKE '%[CATALOG:20260926]%'
);

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
('unitel','Plano Base','VOICE',70,'MIN','ALL',NULL,NULL,NULL,NULL),
('unitel','Plano Base','SMS',60,'SMS','ALL',NULL,NULL,NULL,NULL),
('unitel','Plano Base','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','Redes Sociais 500 MB','SOCIAL',500,'MB','ALL',NULL,NULL,'Facebook, WhatsApp, YouTube, TikTok, Instagram, X e Snapchat',NULL),
('unitel','Redes Sociais 800 MB','SOCIAL',800,'MB','ALL',NULL,NULL,'Facebook, WhatsApp, YouTube, TikTok, Instagram, X e Snapchat',NULL),
('unitel','Redes Sociais 900 MB','SOCIAL',900,'MB','ALL',NULL,NULL,'Facebook, WhatsApp, YouTube, TikTok, Instagram, X e Snapchat',NULL),
('unitel','Redes Sociais 1,5 GB','SOCIAL',1536,'MB','ALL',NULL,NULL,'Facebook, WhatsApp, YouTube, TikTok, Instagram, X e Snapchat',NULL),
('unitel','Redes Sociais 3 GB','SOCIAL',3072,'MB','ALL',NULL,NULL,'Facebook, WhatsApp, YouTube, TikTok, Instagram, X e Snapchat',NULL),
('unitel','NET ao Dia 300 MB','DATA',300,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET ao Dia 400 MB','DATA',400,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET ao Dia 800 MB','DATA',800,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Semanal 500 MB','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Semanal 1 GB','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Mensal 1,5 GB','DATA',1536,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Mensal 2 GB','DATA',2048,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Mensal 3,5 GB','DATA',3584,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Mensal 6 GB','DATA',6144,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET Mensal 13 GB','DATA',13312,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET 2 Meses 20 GB','DATA',20480,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET 2 Meses 40 GB','DATA',40960,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET 6 Meses 100 GB','DATA',102400,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','NET 12 Meses 200 GB','DATA',204800,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS 30D 5.000','VOICE',250,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 5.000','SMS',250,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 5.000','DATA',5120,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS 30D 10.000','VOICE',600,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 10.000','SMS',600,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 10.000','DATA',10240,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS 30D 18.000','VOICE',1150,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 18.000','SMS',1150,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 18.000','DATA',18432,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS 30D 30.000','VOICE',2000,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 30.000','SMS',2000,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 30.000','DATA',30720,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS 30D 50.000','VOICE',3500,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 50.000','SMS',3500,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_units|Minutos/SMS partilhados'),
('unitel','MAIS 30D 50.000','DATA',51200,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS Leve 7D','VOICE',30,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Leve 7D','VOICE',30,'MIN','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Leve 7D','SMS',30,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Leve 7D','SMS',30,'SMS','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Leve 7D','DATA',500,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS Leve 7D','DATA',500,'MB','ALL','22:00:00','06:59:00',NULL,'Bónus nocturno: mesmo volume das 22h às 07h'),
('unitel','MAIS Vivo 7D','VOICE',60,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Vivo 7D','VOICE',60,'MIN','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Vivo 7D','SMS',60,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Vivo 7D','SMS',60,'SMS','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Vivo 7D','DATA',1024,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS Vivo 7D','DATA',1024,'MB','ALL','22:00:00','06:59:00',NULL,'Bónus nocturno: mesmo volume das 22h às 07h'),
('unitel','MAIS Ágil 7D','VOICE',120,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Ágil 7D','VOICE',120,'MIN','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Ágil 7D','SMS',120,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Ágil 7D','SMS',120,'SMS','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Ágil 7D','DATA',2048,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS Ágil 7D','DATA',2048,'MB','ALL','22:00:00','06:59:00',NULL,'Bónus nocturno: mesmo volume das 22h às 07h'),
('unitel','MAIS Social 7D','VOICE',300,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Social 7D','VOICE',300,'MIN','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Social 7D','SMS',300,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Social 7D','SMS',300,'SMS','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Social 7D','DATA',5120,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS Social 7D','DATA',5120,'MB','ALL','22:00:00','06:59:00',NULL,'Bónus nocturno: mesmo volume das 22h às 07h'),
('unitel','MAIS Livre 7D','VOICE',750,'MIN','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Livre 7D','VOICE',750,'MIN','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Livre 7D','SMS',750,'SMS','ALL',NULL,NULL,NULL,'SHARED:mais_base|Minutos/SMS partilhados, utilizáveis a qualquer hora'),
('unitel','MAIS Livre 7D','SMS',750,'SMS','ALL','22:00:00','06:59:00',NULL,'SHARED:mais_bonus_night|Bónus de Minutos/SMS partilhados das 22h às 07h'),
('unitel','MAIS Livre 7D','DATA',10240,'MB','ALL',NULL,NULL,NULL,NULL),
('unitel','MAIS Livre 7D','DATA',10240,'MB','ALL','22:00:00','06:59:00',NULL,'Bónus nocturno: mesmo volume das 22h às 07h');

INSERT INTO benefits
(plan_version_id,type,quantity,unit,network_scope,start_time,end_time,app_scope,label,created_at)
SELECT pv.id,b.benefit_type,b.quantity,b.unit,b.network_scope,b.start_time,b.end_time,b.app_scope,b.label,NOW()
FROM qp_benefit_import b
INNER JOIN operators o ON o.slug=b.operator_slug
INNER JOIN plans p ON p.operator_id=o.id AND p.name=b.plan_name
INNER JOIN plan_versions pv ON pv.plan_id=p.id AND pv.notes LIKE '%[CATALOG:20260926]%'
WHERE NOT EXISTS (
    SELECT 1 FROM benefits x WHERE x.plan_version_id=pv.id
);

DROP TEMPORARY TABLE IF EXISTS qp_benefit_import;
DROP TEMPORARY TABLE IF EXISTS qp_plan_import;
