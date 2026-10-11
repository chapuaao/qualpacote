# Arquitectura

## Objectivo

O QualPacote funciona como uma central de inteligência de saldos: recebe o orçamento e o resultado que o utilizador quer obter, confronta pacotes e tarifas normais verificadas e calcula a alternativa que entrega mais utilidade nas condições informadas.

## Stack

- PHP 8.2+
- MySQL 8 / MariaDB com InnoDB
- HTML/CSS/JavaScript sem etapa de build
- Apache/Nginx
- GitHub Actions apenas para lint e testes; sem deploy automático

## Componentes

### Site público

`index.php` recebe orçamento, serviços seleccionados, objectivos de maximização ou mínimos concretos, período, horário predominante, destino de chamadas e operadoras disponíveis. Depois do cálculo, a interface desloca-se para a secção de resultados.

### Motor de recomendação

`app/RecommendationEngine.php` é determinístico. Trata primeiro restrições e capacidade útil e só depois ordena as alternativas.

O motor considera:

- orçamento e número de ciclos que cabem nele;
- validade e cobertura do período;
- horários exactos de cada benefício;
- mesma rede, outras redes ou todas as redes;
- dados de aplicações específicas;
- carteiras partilhadas de unidades sem duplicação;
- quotas diárias não acumuláveis;
- FUP quando publicada;
- requisitos prévios, como necessidade de um plano FLEX activo;
- comparação com saldo normal quando existem tarifas suficientes.

Quando o horário escolhido pelo utilizador exclui um benefício, a quantidade é retirada integralmente da capacidade útil e a explicação informa a quantidade exacta afectada. Não se aplicam percentagens arbitrárias para simular a perda.

### Catálogo versionado

`plans` guarda a identidade do plano. `plan_versions` guarda cada alteração de preço, validade, activação, fonte e data de verificação. `benefits` decompõe DATA/VOICE/SMS/SOCIAL por quantidade, rede, horário e aplicação.

`tariffs`, `tariff_versions` e `tariff_rates` representam o saldo normal e permitem calcular quantos minutos, MB ou SMS um valor compra sem pacote.

Ao actualizar um plano ou tarifa pelo admin, uma nova versão é criada e a anterior permanece disponível para auditoria.

### Catálogo verificado

A revisão de 26/09/2026 adiciona 113 opções de planos/aditivos e 248 linhas de benefícios a partir de fontes oficiais. Os ficheiros estão em `database/migrations/catalog_20260926/` e as fontes em `docs/CATALOG_SOURCES_20260926.md`.

### Administração

`/admin/` permite autenticação, visão de frescura do catálogo, gestão de operadoras, planos, benefícios, tarifas normais, fontes e publicação/despublicação. Escritas usam CSRF e queries preparadas.

### Vigilância de fontes

`scripts/check_sources.php` usa `SourceFetcher`, `SourceDocument` e `CatalogRepository`. Conserva fontes, snapshots, propostas, revisões individuais e execuções em `catalog_*`. O hash de texto elimina ruído de scripts/cabeçalhos/rodapés; documentos binários conservam os bytes originais. A alteração bloqueia propostas antigas e cria novos rascunhos. A aprovação bloqueia fonte/plano, verifica a versão esperada e publica na mesma transacção. O worker usa um lock MySQL para impedir execuções sobrepostas e nunca publica.

`plan_version_rules` conserva regras estruturadas por versão: categoria, família, dependência, coexistência, repetição, incompatibilidades, fim da oferta, unidade de cobrança e identidade estável da oferta na fonte. Regras ausentes são tratadas como desconhecidas. `catalog.php` permite procurar ofertas por nome, operadora, categoria e orçamento.

O motor combina somente regras de coexistência confirmadas e conserva as restrições e validades de cada componente. `usage_result_details` guarda identidade canónica e componentes de combinações, preservando as tabelas e votos anteriores. A categoria router não recebe tarifas móveis de saldo normal.

### Estatísticas

`search_events` guarda somente dados agregáveis da procura. Não guarda telefone, nome, email, IP ou identificador pessoal.

## Convenções de inteligência no catálogo

Alguns detalhes que não justificam novas tabelas próprias são codificados de forma explícita no benefício/plano:

- `SHARED:<chave>|...` identifica uma única carteira partilhada por mais de um serviço;
- `DAILY_CAP:<MB>|...` limita a quantidade realmente acumulável no período;
- `FUP:...` regista a política de utilização justa publicada;
- `[REQUIREMENT] ... [/REQUIREMENT]` marca uma condição necessária antes de activar a oferta.

Estas convenções são internas e nunca aparecem como metalinguagem para o utilizador.

## Utilização e opinião

`UsageSession` mantém o identificador anónimo e tokens de consulta emitidos pelo servidor. `UsageRepository` regista visitantes/dias activos e, numa transacção, a consulta em `search_events` e os resultados apresentados (tipo, ID, versão, título e operadora). Um token único de consulta impede duplicação por actualização/repetição; um novo formulário emite outro token. A identidade do resultado separa pacote e saldo normal e preserva a versão original.

`feedback.php` valida método POST, sessão, CSRF, escolha binária e pertença do resultado. Actualiza uma única opinião na linha do resultado. Respostas públicas não contêm agregados; sem JavaScript usa redireccionamento 303 para a consulta. As estatísticas são lidas exclusivamente depois da autenticação em `admin/index.php`, incluindo consultas antigas. As páginas com tokens ou dados administrativos enviam `Cache-Control: no-store`.
