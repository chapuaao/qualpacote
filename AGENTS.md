# AGENTS.md — QualPacote

## Regras permanentes

- Stack: PHP 8.2+ e MySQL/MariaDB. Não introduzir framework ou etapa de build sem necessidade comprovada.
- Deploy é manual. GitHub Actions valida; nunca publica.
- Não versionar `.env`, credenciais, dumps de produção, logs, `vendor/` ou artefactos gerados.
- A interface pública usa linguagem simples e de negócio; não expõe termos de arquitectura, scoring ou desenvolvimento.
- Recomendações são neutras entre operadoras e derivadas de regras testáveis.
- Nunca publicar tarifário sem fonte e data de verificação.
- Alteração de preço, validade ou benefícios cria nova `plan_version`; não sobrescrever histórico.
- Benefícios com restrições de horário, rede ou aplicação devem ser decompostos, não agregados pelo número de marketing.
- Carteiras partilhadas não podem ser duplicadas entre métricas.
- O verificador automático de fontes apenas sinaliza mudanças; publicação exige revisão.
- Qualquer mudança no motor deve manter ou ampliar `tests/RecommendationEngineTest.php`.
- Antes de PR: executar lint PHP e testes.
- Preservar compatibilidade com alojamento PHP/MySQL comum.

## Prioridades do produto

1. Correctude dos tarifários.
2. Explicação clara da recomendação.
3. Rapidez no fluxo público.
4. Administração simples e auditável.
5. Evolução sem quebrar deploy manual.
