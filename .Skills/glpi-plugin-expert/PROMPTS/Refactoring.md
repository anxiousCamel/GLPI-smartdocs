# Refactoring — Prompt Operacional

Use quando a tarefa for **refatorar código de plugin GLPI existente** sem mudar comportamento externo observável.

## Sequência obrigatória

1. **Entender o comportamento atual antes de tocar em qualquer linha** — se houver testes (`GLPI10/25-Testing.md`), rodá-los primeiro para ter uma linha de base.
2. **Identificar contra qual(is) documento(s) da KB o código diverge** (ex.: SQL raw em vez de query builder, estilo legado de namespace, falta de separação `prepareInputFor*`/`post_*Item`).
3. **Refatorar incrementalmente**, um padrão por vez, mantendo o comportamento externo idêntico a cada passo — nunca misturar refatoração estrutural com mudança de comportamento na mesma alteração.
4. **Priorizar por severidade**: primeiro os itens de `GLPI10/28-AntiPatterns.md` de severidade crítica, depois alta, depois qualidade/estilo.
5. **Rodar testes novamente** após cada incremento (ou testar manualmente o fluxo, se não houver suíte).
6. **Deixar o código estritamente melhor do que foi encontrado** — mas sem escopo além do necessário (não refatorar partes não relacionadas ao pedido "de brinde").

## Casos comuns de refatoração nesta KB

- Migrar classe do estilo legado (`Plugin<Nome><Classe>`) para o namespace moderno (`24-Namespaces.md`) — cuidado com compatibilidade retroativa se outro código externo referenciar a classe antiga.
- Substituir SQL raw por query builder (`05-Database.md`).
- Separar validação (`prepareInputFor*`) de efeito colateral (`post_*Item`) quando ambos estão misturados (`02-Lifecycle.md`, `07-CommonDBTM.md`).
- Adequar código a `GLPI10/31-Compatibility.md` para preparar migração ao GLPI 11.
- Extrair lógica de negócio embutida em `front/*.php`/`ajax/*.php` para classes testáveis em `src/`.

## O que nunca fazer durante refatoração

- Remover checagem de right/CSRF "temporariamente" para simplificar — segurança nunca é um passo intermediário descartável.
- Misturar mudança de comportamento com reestruturação no mesmo commit/PR.
