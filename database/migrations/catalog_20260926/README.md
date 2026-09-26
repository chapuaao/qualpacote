# Catálogo verificado — 26/09/2026

Este conjunto amplia o catálogo sem substituir histórico. Cada importação é idempotente e cria no máximo uma versão marcada `[CATALOG:20260926]` por plano.

## Pré-requisito

A estrutura de inteligência de saldo deve existir. Em instalações que ainda não a receberam, importar primeiro:

`database/migrations/20260925_balance_intelligence.sql`

## Importação no phpMyAdmin

Importar os ficheiros desta pasta por ordem:

1. `01_unitel.sql`
2. `02_africell_a.sql`
3. `03_africell_b.sql`
4. `04_africell_c.sql`
5. `05_movicel.sql`

Os ficheiros são independentes entre si e podem ser repetidos sem criar uma segunda versão do mesmo catálogo.

## Cobertura desta revisão

- UNITEL: 30 planos/ofertas.
- Africell: 60 planos/ofertas.
- Movicel: 23 planos/aditivos + tarifário base de saldo normal.
- Total: 113 opções de planos/aditivos, com 248 linhas de benefícios decompostos por serviço, rede, horário ou aplicação quando aplicável.

## Critério de publicação

Somente foram incluídos dados sustentados por fontes oficiais acessíveis. Quando um canal de activação não estava explícito na fonte usada, o campo não foi inferido. Ofertas dependentes de outro plano são marcadas como condicionais para não serem apresentadas como opções autónomas equivalentes.
