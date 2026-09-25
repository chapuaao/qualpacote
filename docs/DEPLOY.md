# Deploy manual

Domínio previsto: `http://qualpacote.poligest.ao/`

É recomendável activar HTTPS antes da publicação ao público.

## Requisitos

- PHP 8.2 ou superior
- extensões `pdo`, `pdo_mysql`, `mbstring`
- extensão `curl` recomendada para verificar alterações das fontes
- MySQL 8+ ou MariaDB equivalente
- Apache com `mod_headers` e, de preferência, `mod_rewrite`

## Primeira instalação

1. Criar a base de dados e um utilizador MySQL com acesso apenas à base do QualPacote.
2. Copiar o repositório para o DocumentRoot do subdomínio.
3. Copiar `.env.example` para `.env`.
4. Configurar `APP_URL`, `APP_HTTPS`, dados `DB_*`, `ADMIN_EMAIL` e `ADMIN_PASSWORD`.
5. Importar `database/schema.sql` e `database/seed.sql`.
6. Executar `php scripts/setup_admin.php`.
7. Abrir `/admin/` e validar o acesso.
8. Abrir `/` e executar comparações com diferentes orçamentos.

## Actualizações

O deploy é manual:

1. Fazer merge apenas de PR validada.
2. No servidor, actualizar o código a partir da `main`.
3. Não substituir `.env`.
4. Executar eventual SQL de migração.
5. Executar `php tests/RecommendationEngineTest.php`.
6. Testar página pública, recomendação, login admin, edição de plano e publicação/despublicação.

Não existe deploy automático no GitHub Actions e não devem ser versionados artefactos gerados localmente.

## Permissões

O utilizador do servidor web precisa apenas de leitura do código. A aplicação não precisa escrever ficheiros no servidor.

## HTTPS

Quando o certificado estiver activo, alterar `APP_URL=https://qualpacote.poligest.ao` e `APP_HTTPS=true`. Isso activa o cookie de sessão `Secure`.

## Verificação diária de fontes

O catálogo não é alterado automaticamente. O script apenas detecta mudanças nas fontes cadastradas:

```bash
php scripts/check_sources.php
```

Pode ser executado diariamente por cron, por exemplo às 06:30:

```cron
30 6 * * * /usr/bin/php /caminho/qualpacote/scripts/check_sources.php >> /caminho/logs/qualpacote-sources.log 2>&1
```

Quando o dashboard indicar alteração, o administrador deve confirmar a fonte e criar nova versão do plano quando necessário.
