# QualPacote

Comparador personalizado de pacotes móveis para Angola.

Domínio previsto: `http://qualpacote.poligest.ao/`

## O que faz

O utilizador informa orçamento, principal necessidade, intensidade de utilização, período, horário de uso, padrão de chamadas e operadoras disponíveis. O sistema compara os planos publicados e considera preço, validade e restrições de horário/rede antes de apresentar as opções que melhor encaixam no perfil.

## Stack

- PHP 8.2+
- MySQL/MariaDB
- HTML/CSS/JavaScript sem build
- Deploy manual

## Instalação

1. Copiar `.env.example` para `.env`.
2. Configurar a ligação MySQL e as credenciais iniciais do admin.
3. Importar `database/schema.sql`.
4. Importar `database/seed.sql`.
5. Executar `php scripts/setup_admin.php`.
6. Abrir `/admin/`.

Guia completo: [`docs/DEPLOY.md`](docs/DEPLOY.md)

## Dados iniciais

O seed inclui ofertas publicadas a partir de fontes oficiais consultadas em 25/09/2026 para UNITEL e Africell.

A Movicel é criada como operadora, mas sem planos iniciais publicados porque as páginas públicas encontradas não apresentavam actualização recente suficiente para assumir os tarifários como actuais.

## Verificação diária de fontes

```bash
php scripts/check_sources.php
```

O script detecta alterações nas fontes cadastradas. A publicação continua sob revisão administrativa.

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

Implementação inicial: issue #1.
