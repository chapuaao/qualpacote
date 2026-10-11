# Manutenção do catálogo

## Publicação e evidência

Só publicar informação confirmada em fonte oficial: preço, validade, quantidades, rede, horários, aplicações, carteiras partilhadas, requisitos e incompatibilidades. Uma alteração cria nova versão; nunca substituir o histórico. Benefícios incompatíveis com a unidade/rede ou horários incompletos são recusados.

A fonte descoberta é um endereço a investigar, não uma oferta vendável. A ausência de informação ou uma falha de acesso não prova retirada de uma oferta. Se a retirada for confirmada, despublicar o plano no admin, conservando o histórico.

## Rotina assistida

1. Executar `php scripts/check_sources.php` diariamente por cron.
2. Abrir **Fontes e revisão** no admin. O painel mostra última execução, falhas, fontes novas e propostas.
3. Consultar a diferença entre informação publicada e proposta, a página oficial e a evidência recolhida. PDFs/imagens originais ficam acessíveis apenas a administradores autenticados.
4. Completar os rascunhos manuais e confirmar todos os campos, incluindo coexistência e códigos. Seleccionar a identidade do plano ou nova oferta; uma mesma página pode alimentar vários planos. Tarifas de saldo continuam a ser editadas na área própria depois de rever a fonte.
5. Aprovar individualmente cada oferta. A aprovação cria versão, revisão e auditoria na mesma transacção. Uma proposta baseada numa fonte ou versão já alterada não pode ser publicada.
6. Arquivar propostas irrelevantes; classificar/ignorar fontes que não forneçam ofertas úteis. Nunca marcar tudo como confirmado apenas por partilhar o mesmo endereço.
7. Controlar comparações para 500, 1.000, 2.000, 5.000 e 10.000 Kz e os serviços/redes realmente publicados.

O worker semeia fontes do catálogo e índices oficiais; descobre até 50 ligações por página e visita no máximo 120 fontes por execução, começando pelas menos recentemente consultadas. As novas ligações entram na execução seguinte. Redireccionamentos são limitados e validados. Só são aceites os domínios oficiais explicitamente permitidos, correspondentes à operadora; cada documento pode ter até 8 MB. Não há acesso a aplicações autenticadas, menus USSD ou ofertas pessoais.

As imagens de benefícios Kuia/FLEX são vigiadas como fontes separadas: um HTML inalterado não confirma automaticamente uma imagem nele incluída. Ligar a revisão à evidência que realmente sustenta o plano e verificar os termos complementares.

Afrimix, Movinet e Konekta 4G têm extracção determinística. Um formato desconhecido gera falha para revisão; não inventa dados. As demais famílias e documentos exigem preparação humana. Não existe OCR nem extracção genérica por IA neste upgrade.

## Carteiras e condições

- Internet e redes sociais, quando seleccionadas juntas, representam consumos adicionais. Os dados gerais são repartidos uma única vez.
- Dados exclusivos de aplicações só entram quando todas as aplicações escolhidas estão cobertas. Sem escolha, só os dados gerais contam.
- MIN/SMS partilhados formam uma carteira única. Na cobrança por minuto iniciado, chamadas curtas gastam unidades completas; o cálculo usa a duração média declarada.
- Mesma rede e outras redes conservam saldos separados. Sem repartição do consumo, o motor usa a capacidade conservadora disponível para qualquer destino e explica a escolha.
- Horários usam referências: dia às 12h, noite às 21h e madrugada às 02h. Uso misto admite os benefícios em todos os horários; não estima uma distribuição diária nem garante que o utilizador os consumirá.
- `repeat_policy=unknown` permite uma compra; `sequential` confirma renovação sucessiva; `parallel` confirma subscrições independentes simultâneas. Não atribuir estas regras por semelhança de nome.
- `combination_group` igual significa coexistência confirmada, na mesma operadora. `requires_family` exige uma base comprada na combinação ou declarada activa durante todo o período. Os benefícios de planos já activos não são somados.
- `allows_balance` só deve ser marcado se a tarifa base publicada for aplicável ao saldo extra naquela oferta.
- Router exige SIM e equipamento já disponíveis. O custo de hardware não foi incorporado.

A pesquisa combina até dois tipos de pacote, ou uma base com dois aditivos que a exigem; também considera saldo extra confirmado. A enumeração do primeiro pacote limita-se a 60 compras e usa quantidades relevantes para mínimos/orçamento no segundo. Não é uma procura exaustiva de todas as combinações possíveis. Pacotes da mesma família só coexistem quando ambos confirmam subscrições paralelas.

## Frescura

A data humana `source_checked_at` nunca é renovada apenas porque um URL respondeu. Uma consulta recente sem mudança de texto pode sustentar a versão apenas quando existe revisão humana daquela versão ligada à mesma evidência, a fonte está confirmada e sem falha. A página mostra separadamente a data humana e a última consulta sem alterações. Uma mudança da fonte invalida essa ligação.

Após 7 dias sem confirmação/observação válida, há aviso. Após 30 dias, a recomendação torna-se condicional e não pode garantir os mínimos. Estes prazos são uma política do produto, não uma prova de que o preço mudou. Não existe uma data global que apresente todo o catálogo como revisto.

## Lacunas de cobertura

Consultar `docs/CATALOG_SOURCES_20261011.md`. A rotina reduz visitas manuais repetidas, mas a equipa continua responsável por resolver contradições, rever documentos, confirmar elegibilidade, descobrir canais não indexáveis e manter o cron a funcionar. Não há notificações externas neste upgrade; o estado fica no admin e o worker devolve código de erro.
