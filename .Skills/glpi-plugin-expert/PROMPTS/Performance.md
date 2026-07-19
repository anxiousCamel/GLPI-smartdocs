# Performance — Prompt Operacional

Use quando a tarefa for **diagnosticar ou otimizar performance** de um plugin GLPI.

## Sequência obrigatória

1. **Medir antes de otimizar.** Ativar o modo debug do GLPI (`GLPI10/26-Debugging.md`) para ver queries executadas, tempo de cada uma e hooks disparados na página/fluxo afetado. Nunca otimizar especulativamente sem confirmar onde o tempo realmente vai.
2. **Verificar os pontos de maior alavancagem primeiro** (`GLPI10/29-Performance.md`):
   - `plugin_init` fazendo I/O pesado (afeta TODA request do GLPI, não só do plugin)
   - Hooks de item (`ITEM_ADD`/`ITEM_UPDATE`) com custo não-O(1) (afeta toda operação daquele itemtype, incluindo importação em massa)
   - Queries de lista sem índice nas colunas de filtro
   - Cron/massive action processando sem limite de lote
3. **Aplicar a correção mais barata primeiro**: índice de banco > cache (`GLPI_CACHE`) > reestruturação de query > reescrita de lógica.
4. **Medir de novo depois da correção** para confirmar que resolveu e não introduziu regressão funcional.

## Perguntas a fazer se a informação não foi dada

- A lentidão é numa página específica, numa operação em massa, ou global (toda a instância)?
- Desde quando o problema é percebido (correlaciona com alguma mudança recente)?
- Quantos registros/itens estão envolvidos tipicamente?

## O que nunca fazer

- Adicionar cache "por precaução" sem confirmar que o dado é genuinamente caro/estável.
- Reescrever cache próprio (arquivo, variável estática) em vez de usar `GLPI_CACHE`.
- Otimizar prematuramente sem medir — a intuição sobre onde o tempo vai costuma estar errada.
