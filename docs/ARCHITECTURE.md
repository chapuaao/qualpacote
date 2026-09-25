# Arquitectura

## Objectivo

O QualPacote compara ofertas móveis pela capacidade que o utilizador consegue aproveitar dentro do orçamento e do período informado.

## Stack

- PHP 8.2+
- MySQL 8 / MariaDB com InnoDB
- HTML/CSS/JavaScript sem etapa de build
- Apache/Nginx
- GitHub Actions apenas para lint e testes; sem deploy automático

## Componentes

### Site público

`index.php` recebe orçamento, prioridade, intensidade de uso, período necessário, horário predominante, padrão de chamadas, operadoras disponíveis e, opcionalmente, GB/minutos para afinação. A resposta mostra até cinco opções ordenadas.

### Motor de recomendação

`app/RecommendationEngine.php` é determinístico e não depende de IA. O motor converte o perfil em necessidades estimadas para o período escolhido, calcula renovações que cabem no orçamento, aplica validade, reduz o valor de benefícios incompatíveis com o horário/rede do utilizador, mede o atendimento do perfil e ordena por encaixe, cobertura e aproveitamento do orçamento.

### Catálogo versionado

`plans` guarda a identidade do plano. `plan_versions` guarda cada alteração de preço, validade, código, fonte e data de verificação. `benefits` decompõe a oferta em DATA/MB, VOICE/MIN, SMS/SMS e SOCIAL/MB, com horário, rede e aplicações quando aplicável.

Ao actualizar um plano pelo admin, uma nova versão é criada. A versão anterior permanece na base para auditoria.

### Administração

`/admin/` permite autenticação, visão de frescura do catálogo, gestão de operadoras, criação/actualização versionada de planos e publicação/despublicação. Escritas usam CSRF e queries preparadas.

### Vigilância de fontes

`scripts/check_sources.php` consulta as fontes dos planos publicados, calcula hash do conteúdo e regista alterações em `source_checks`. O detector não altera tarifários: apenas sinaliza a necessidade de revisão.

### Estatísticas

`search_events` guarda somente dados agregáveis da procura: orçamento, objectivo, intensidade, período, horário, padrão de chamadas e quantidade de resultados. Não guarda telefone, nome, email, IP ou identificador pessoal.

## Evoluções previstas

1. Carteiras partilhadas, como MIN/SMS no mesmo saldo.
2. Colectores específicos por operadora.
3. Fila de revisão de alterações detectadas.
4. WhatsApp/USSD/API como novos canais.
5. Cobertura e qualidade de rede por zona quando houver dados confiáveis.
6. Comparação com saldo normal e tarifários base.
