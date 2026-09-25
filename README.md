# QualPacote

Central de inteligência de saldos e pacotes móveis para Angola.

Domínio: `https://qualpacote.poligest.ao/`

## O que faz

O utilizador informa quanto pretende carregar e o que quer conseguir com esse dinheiro: internet, chamadas, SMS, redes sociais ou uma combinação. Pode pedir o máximo possível ou indicar mínimos concretos e um período.

O QualPacote compara:

- pacotes publicados;
- saldo normal e tarifas por minuto/MB/SMS;
- validade;
- rede de destino;
- horário;
- benefícios específicos de aplicações;
- custo equivalente em saldo normal quando os dados são completos.

O mesmo saldo nunca é contado duas vezes em serviços diferentes.

## Stack

- PHP 8.2+
- MySQL/MariaDB
- HTML/CSS/JavaScript sem build
- deploy manual

## Instalação

1. Copiar `.env.example` para `.env`.
2. Configurar MySQL e credenciais do admin.
3. Importar `database/schema.sql`.
4. Importar `database/seed.sql`.
5. Importar `database/migrations/20260925_balance_intelligence.sql`.
6. Executar `php scripts/setup_admin.php`.
7. Abrir `/admin/`.

Guia completo: [`docs/DEPLOY.md`](docs/DEPLOY.md).

## Administração

O admin gere operadoras, planos, benefícios, tarifas normais, fontes e versões. A área **Tarifas de saldo** é a base da comparação “pacote vs. saldo normal”.

## Verificação diária

```bash
php scripts/check_sources.php
```

A rotina verifica fontes de planos e tarifas. Mudanças exigem revisão humana antes de publicar nova versão.

## Testes

```bash
php tests/RecommendationEngineTest.php
```

Lint:

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
```

## Documentação

- [Arquitectura](docs/ARCHITECTURE.md)
- [Deploy manual](docs/DEPLOY.md)
- [Manutenção do catálogo](docs/CATALOG_MAINTENANCE.md)
- [Regras do produto](docs/PRODUCT_RULES.md)
