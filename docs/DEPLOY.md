# Deploy manual

Domínio: `https://qualpacote.poligest.ao/`

## Requisitos

- PHP 8.2+
- extensões `pdo`, `pdo_mysql`, `mbstring`
- `curl` para vigilância automática das fontes
- MySQL 8+ ou MariaDB equivalente
- Apache com `mod_rewrite` recomendado

## Primeira instalação

1. Criar base e utilizador MySQL do QualPacote.
2. Copiar o repositório para o DocumentRoot.
3. Copiar `.env.example` para `.env` e configurar `APP_URL`, `APP_HTTPS`, `DB_*`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.
4. Importar `database/schema.sql`.
5. Importar `database/seed.sql`.
6. Importar `database/migrations/20260925_balance_intelligence.sql` para criar e alimentar as tarifas base verificadas.
7. Executar `php scripts/setup_admin.php`.
8. Validar `/admin/` e `/`.

A migração de 25/09 é idempotente para os registos iniciais e também pode ser aplicada numa instalação já existente.

## Actualização de uma instalação anterior

Depois de actualizar o código da `main`, executar obrigatoriamente:

```bash
mysql -u UTILIZADOR -p BASE < database/migrations/20260925_balance_intelligence.sql
```

Em cPanel sem terminal, importe o ficheiro pelo phpMyAdmin: seleccione a base do QualPacote → **Importar** → escolha `database/migrations/20260925_balance_intelligence.sql` → executar.

Depois validar:

1. `/admin/?page=tariffs` mostra as tarifas base;
2. uma pesquisa de 500 Kz para chamadas inclui saldo normal quando a tarifa está publicada;
3. uma pesquisa “máximo de internet” ordena pelo volume útil e não por um perfil pré-definido;
4. planos com equivalência completa mostram o valor estimado em saldo normal.

## Deploy

O deploy continua manual:

1. fazer merge apenas de PR validada;
2. actualizar o código no servidor;
3. não substituir `.env`;
4. aplicar migrações SQL pendentes;
5. executar `php tests/RecommendationEngineTest.php` quando houver CLI disponível;
6. testar página pública, login admin, planos, tarifas e publicação/despublicação.

## HTTPS

Com certificado activo:

```env
APP_URL=https://qualpacote.poligest.ao
APP_HTTPS=true
```

## Verificação diária de fontes

O script verifica fontes de planos e tarifas e não publica alterações sozinho:

```bash
php scripts/check_sources.php
```

Exemplo de cron diário:

```cron
30 6 * * * cd /caminho/qualpacote && php scripts/check_sources.php >> source_check.log 2>&1
```

A pasta `/scripts` é bloqueada pelo `.htaccess`, portanto a rotina não fica exposta como endpoint público.
