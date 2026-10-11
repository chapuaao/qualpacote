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

## Estatísticas privadas e votação — 11/10/2026

Nesta alteração, importar pelo phpMyAdmin ou pelo terminal:

```bash
mysql -u UTILIZADOR -p BASE < database/migrations/20261011_usage_statistics.sql
```

A migração é idempotente, acrescenta quatro tabelas e preserva `search_events` e o catálogo. Instalações novas já recebem as tabelas em `database/schema.sql`. Se a migração estiver pendente, a comparação continua disponível e as consultas continuam no registo antigo; o admin mostra a indicação para importar a migração.

Depois de copiar o código (incluindo `feedback.php` e os novos ficheiros em `app/`), validar:

1. abrir a página pública e fazer uma consulta válida;
2. cada resultado apresenta **Gostei / Não gostei**;
3. votar, repetir o mesmo voto e mudar de opinião: fica apenas um voto actual por resultado;
4. actualizar a URL de resultados não cria outra consulta; clicar novamente no formulário cria;
5. entrar no admin → **Estatísticas** e confirmar consultas, visitantes e votos;
6. filtrar datas e confirmar a evolução diária, a opinião por recomendação e os históricos;
7. sem autenticação administrativa, `/admin/?page=statistics` apresenta apenas o login;
8. repetir a votação com JavaScript desligado: regressa à página de resultados.

O cookie `qualpacote_visitor` é aleatório, HttpOnly e SameSite=Lax, com validade de um ano; a base guarda apenas o seu hash. Não são recolhidos IPs, nomes ou impressão digital do navegador. A contagem representa navegadores aproximados, incluindo possíveis bots, e começa na activação desta versão. Consultas antigas continuam contadas, sem identificação retroactiva.

A votação exige sessão e CSRF, aceita apenas resultados registados para esse visitante e mantém uma única opinião actual por resultado de cada consulta. Outro clique de consulta pode gerar uma nova avaliação do mesmo pacote; não há promessa de impedir abuso por criação de múltiplas identidades anónimas.

Datas seguem `APP_TIMEZONE` (Africa/Luanda por omissão). O período usa datas inclusivas até 366 dias. Visitantes do período são navegadores activos nesse intervalo; visitantes que consultaram são distintos entre consultas registadas. Votos usam a data da última alteração da opinião. A taxa de aprovação é Gostei / (Gostei + Não gostei), excluindo resultados sem voto. Não há histórico separado de todas as mudanças do mesmo voto.

Sem substituição de `.env` e sem deploy automático.
