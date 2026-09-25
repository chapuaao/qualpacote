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
- restrições relevantes de horário, rede ou aplicação;
- comparação com saldo normal quando completa;
- fonte.

## Dados insuficientes

Quando não houver tarifa ou plano verificado, mostrar ausência de informação. Não preencher lacunas com estimativas inventadas.

## Precisão

Não usar “melhor” de forma absoluta. O primeiro resultado representa a opção que mais atende aos critérios informados dentro dos dados verificados no catálogo.
