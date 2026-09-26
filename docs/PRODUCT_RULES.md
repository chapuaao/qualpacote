# Regras do produto

## Pergunta central

O QualPacote responde: **“Tenho X Kz. O que consigo fazer de forma mais útil com este dinheiro para aquilo que preciso?”**

O utilizador não deve ser obrigado a estimar um “perfil de consumo” abstracto. Ele informa orçamento, usos desejados, mínimos concretos quando existirem, prioridade e período necessário.

## Linguagem pública

Usar frases curtas e concretas: “Quanto pretende carregar?”, “Para que quer usar este valor?”, “Quero o máximo de internet possível”, “Preciso de pelo menos 100 minutos”.

Não expor linguagem de arquitectura, scoring, pesos, engine, perfil estimado, modelo ou desenvolvimento.

## Serviços independentes

Internet, chamadas, SMS e redes sociais são selecções independentes. A combinação nasce das caixas marcadas pelo utilizador; não manter combinações fechadas como “Internet + chamadas”.

## Restrições e objectivos

Distinguir:

- **mínimos**: precisam ser cumpridos, por exemplo 100 minutos e 7 dias;
- **objectivos de maximização**: depois dos mínimos, obter o máximo possível do serviço prioritário dentro do orçamento.

Uma opção que não cobre um mínimo ou período solicitado deve ser identificada como tal; não pode parecer equivalente a uma que cumpra tudo.

## Restrições concretas

Quando a fonte permite calcular a perda, não usar frases vagas como “parte do benefício só vale de madrugada”. Informar a quantidade exacta afectada e ligá-la à escolha do utilizador. Exemplo: “500 MB só ficam disponíveis das 00:00 às 06:59. Como indicou uso diurno, estes 500 MB ficam fora do cálculo útil.”

Inferências comportamentais simples podem ser usadas apenas como probabilidade e sempre depois do facto quantitativo. Exemplo: para quem escolheu uso diurno, explicar que é provável que esteja a dormir durante boa parte da madrugada, sem transformar isso numa certeza sobre a pessoa.

A mesma regra aplica-se a benefícios limitados à própria rede, outras redes, aplicações específicas, quotas diárias e políticas de utilização justa.

## Saldo normal

O saldo normal é uma alternativa real, não um pacote fictício. Tarifas de voz, dados e SMS são versionadas em `tariffs`, `tariff_versions` e `tariff_rates`.

O mesmo dinheiro nunca pode ser contado várias vezes. Se 500 Kz podem ser usados em chamadas, dados ou SMS, esses 500 Kz formam uma carteira única. Em uso combinado, o orçamento é repartido; em cenários “se gastar tudo apenas em…”, cada valor é apresentado explicitamente como alternativa, não como benefício simultâneo.

## Comparação económica

Quando todas as tarifas base necessárias forem conhecidas, um pacote pode mostrar o valor aproximado que os seus benefícios custariam em saldo normal. Se faltar uma tarifa necessária, não inventar nem exibir equivalência completa.

## Neutralidade comercial

O QualPacote não promove uma operadora. A ordenação aplica as mesmas regras a todas as redes. A operadora não pode comprar posição no resultado; eventual conteúdo patrocinado deve ficar separado da recomendação.

## Explicabilidade

Toda recomendação deve mostrar, quando aplicável:

- custo real para o período;
- quantidade útil de internet, minutos e/ou SMS;
- se cobre os mínimos e o período pedidos;
- validade;
- quantidade exacta perdida por horário/rede/aplicação quando calculável;
- condições prévias, como necessidade de um plano base activo;
- forma/canais de activação quando verificados;
- comparação com saldo normal quando completa;
- fonte.

## Canais de activação

Código USSD, SMS, aplicação, loja/agente, Multicaixa/Multicaixa Express, mobile money ou outra forma de adesão só devem ser apresentados quando a operadora os publicar. Não deduzir que um canal de uma família de planos serve para outra família sem evidência específica.

## Cobertura do catálogo

Manter o máximo de ofertas activas que consigam ser sustentadas por fonte oficial. A amplitude nunca substitui a verificabilidade. Preço, validade, benefícios, horários, rede de destino, aplicações, FUP, renovação, quotas diárias, carteiras partilhadas e dependências entre planos devem ser decompostos sempre que a fonte os publicar.

## Dados insuficientes

Quando não houver tarifa ou plano verificado, mostrar ausência de informação. Não preencher lacunas com estimativas inventadas.

## Precisão

Não usar “melhor” de forma absoluta. O primeiro resultado representa a opção que mais atende aos critérios informados dentro dos dados verificados no catálogo.
