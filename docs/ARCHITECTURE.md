# Arquitectura

## Objectivo

O QualPacote é uma central de inteligência de saldos para Angola. Recebe o orçamento e o resultado que o utilizador pretende obter, cruza pacotes, tarifas normais e restrições conhecidas, e produz opções práticas e explicáveis.

## Stack

- PHP 8.2+
- MySQL 8 / MariaDB com InnoDB
- HTML/CSS/JavaScript sem etapa de build
- GitHub Actions apenas para lint e testes
- deploy manual

## Site público

`index.php` começa pelo orçamento e por quatro usos independentes: internet, chamadas, SMS e redes sociais.

Para cada uso seleccionado, o utilizador pode:

- maximizar o que recebe pelo dinheiro; ou
- informar um mínimo concreto.

Chamadas também podem ser descritas por número aproximado de chamadas e duração média. Quando vários usos são escolhidos, pode ser indicada uma prioridade. O período pode ser fechado (1, 3, 7, 15, 30 ou 60 dias) ou “usar até acabar”. Horário, rede de destino e cartões disponíveis ficam como afinação.

## Motor de recomendação

`app/RecommendationEngine.php` é determinístico.

O motor:

1. elimina alternativas fora do orçamento;
2. calcula renovações apenas quando necessárias para cobrir o período;
3. reduz benefícios que não servem ao horário ou à rede informados;
4. verifica mínimos explícitos antes de premiar volume adicional;
5. maximiza os serviços escolhidos dentro do orçamento;
6. mantém o saldo normal como candidato real;
7. impede que a mesma carteira de saldo seja duplicada entre voz, dados e SMS;
8. calcula equivalência económica de pacotes contra tarifas normais quando os dados são completos;
9. ordena primeiro as opções que cumprem os requisitos e, depois, pelo valor útil para o objectivo indicado.

O score existe apenas internamente para ordenação. Não é apresentado ao consumidor.

## Catálogo de pacotes

`plans` guarda a identidade do pacote. `plan_versions` guarda preço, validade, código, fonte e data de verificação. `benefits` decompõe DATA/MB, VOICE/MIN, SMS/SMS e SOCIAL/MB, incluindo rede, horário e aplicações.

Cada alteração cria nova versão; o histórico não é sobrescrito.

## Tarifas de saldo normal

`tariffs` representa um tarifário normal/pre-pago de uma operadora. Uma operadora pode ter mais de um; `is_default` identifica a referência principal.

`tariff_versions` guarda fonte, verificação, validade eventual do saldo e histórico.

`tariff_rates` guarda o preço por unidade:

- VOICE em Kz/min, com mesma rede/outras redes e horário quando necessário;
- DATA em Kz/MB;
- SMS em Kz/SMS.

Tarifas publicadas entram directamente na recomendação e também permitem calcular o custo equivalente em saldo normal de um pacote.

## Administração

`/admin/` permite gerir:

- operadoras;
- planos e versões;
- tarifas de saldo e versões;
- preços por minuto/MB/SMS;
- fontes e datas de verificação;
- publicação e despublicação.

O dashboard sinaliza operadoras sem tarifa padrão, itens sem revisão recente e fontes alteradas.

## Vigilância de fontes

`scripts/check_sources.php` verifica fontes de planos e tarifas publicados, calcula hash e regista alterações em `source_checks`. A rotina nunca altera automaticamente preços ou benefícios.

## Privacidade

`search_events` guarda apenas dados agregáveis da procura. Não guarda nome, telefone, email, IP ou identificador pessoal.

## Próximas evoluções

1. Representação explícita de carteiras partilhadas de pacote, como “MIN/SMS” no mesmo saldo.
2. Colectores específicos por operadora com fila de revisão humana.
3. Qualidade/cobertura de rede por zona quando houver dados confiáveis.
4. Combinações de dois ou mais pacotes quando a activação conjunta for oficialmente permitida.
