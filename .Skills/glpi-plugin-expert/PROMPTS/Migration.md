# Migration — Prompt Operacional

Use quando a tarefa for **migrar um plugin de GLPI 10.x para 11.x**, ou escrever um plugin novo com suporte às duas versões.

## Sequência obrigatória

1. **Ler `GLPI11/00-Migration-Guide.md` por completo antes de tocar em qualquer código** — é a fonte única de verdade para todas as quebras conhecidas.
2. **Rodar `Checklists/CompatibilityChecklist.md`** contra o código existente, item por item.
3. **Corrigir na ordem do guia de migração**: sanitização/SQL → views/escape → roteamento/URLs → controle de fluxo → recursos exclusivos do 11.
4. **Para cada correção, preferir a forma que já funciona igual nas duas versões** (ver tabela de decisão em `GLPI10/31-Compatibility.md`) em vez de criar branches condicionais desnecessários.
5. **Usar `class_exists()` apenas para as poucas APIs genuinamente exclusivas do 11** (`Firewall`, `SessionManager::registerPluginStatelessPath`).
6. **Verificar se alguma funcionalidade do plugin foi absorvida pelo core do 11** (Forms nativo, Custom Assets) — nesse caso, a "migração" pode ser remover código em vez de adaptá-lo.
7. **Testar contra as duas versões** (ou só a versão-alvo final, se o suporte a 10.x for descontinuado) antes de considerar concluído.
8. **Atualizar `requirements.glpi.min/max`** no `setup.php` para refletir a nova faixa testada.

## Perguntas a fazer se a informação não foi dada

- O plugin precisa suportar 10.x E 11.x simultaneamente, ou está descontinuando 10.x?
- Alguma funcionalidade do plugin sobrepõe algo que virou nativo no 11 (Forms, Custom Assets)?

## O que nunca fazer

- Migrar mecanicamente sem entender por que cada mudança é necessária (ver a explicação completa de cada quebra no guia).
- Deixar `addslashes`/sintaxe antiga do query builder "porque ainda funciona no 10" — são bugs latentes mesmo antes da migração.
