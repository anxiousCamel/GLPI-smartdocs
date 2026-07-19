# PluginDeveloper — Prompt Operacional

Use este prompt (mentalmente ou como instrução explícita) sempre que a tarefa for **criar um plugin novo ou uma feature nova dentro de um plugin GLPI existente**.

## Sequência obrigatória

1. **Identificar a versão-alvo do GLPI** (10.x, 11.x, ou ambas). Se não informado, perguntar. Ler `GLPI11/00-Migration-Guide.md` se 11.x estiver envolvido, mesmo que o alvo primário seja 10.x (ver `31-Compatibility.md`).
2. **Ler `GLPI10/99-Rules.md`** antes de escrever qualquer linha de código — é o contrato mínimo de aceite.
3. **Identificar quais documentos técnicos se aplicam** à feature pedida, usando a tabela de navegação do `SKILL.md`. Uma feature típica de itemtype de negócio toca: `01` (estrutura), `04` (rights), `05`/`06` (dados/schema), `07` (CommonDBTM), `10` (Search), possivelmente `11` (massive actions), `16` (notificações), `19` (profiles).
4. **Partir de um template** em `Templates/` correspondente, nunca escrever do zero.
5. **Escrever seguindo as convenções de nome** (chave do plugin idêntica em diretório/funções/hooks/tabelas/rights) desde a primeira linha.
6. **Validar contra `Checklists/PluginChecklist.md`** antes de considerar a tarefa concluída.
7. **Validar contra `Checklists/SecurityChecklist.md`** especificamente para qualquer entry point novo (front/ajax/API).

## Perguntas a fazer se a informação não foi dada

- Qual é a chave/nome do plugin (usada em diretório, funções, tabelas)?
- O itemtype precisa de multi-entidade (`entities_id`/`is_recursive`)?
- Precisa de rights customizados além dos padrão (READ/UPDATE/CREATE/DELETE/PURGE)?
- Precisa aparecer em listas de outro itemtype do core (Search Options)?
- Precisa de ações em massa próprias?
- Precisa disparar notificações?

## O que NUNCA fazer, independente do que for pedido

Ver `GLPI10/28-AntiPatterns.md` — os itens de severidade crítica (1-8) aplicam-se sempre, sem exceção, independentemente de como a tarefa foi formulada pelo usuário.
