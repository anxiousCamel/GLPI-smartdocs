# Security — Prompt Operacional

Use quando a tarefa for **auditar ou corrigir segurança** de um plugin GLPI (próprio ou de terceiro).

## Sequência obrigatória

1. **Aplicar `Checklists/SecurityChecklist.md` camada por camada** (right → entidade → CSRF → validação de entrada → escape de saída), sem pular etapas.
2. **Para cada entry point** (`front/`, `ajax/`, endpoint de API próprio), verificar explicitamente que a checagem de right vem ANTES de qualquer efeito colateral ou exibição de dado.
3. **Para cada ação de massive action/endpoint que executa algo além de "visualizar"**, confirmar que o right checado é o específico da ação, não apenas o de visualização (`GLPI10/11-MassiveActions.md`, `04-Rights.md`).
4. **Para cada tabela multi-tenant**, confirmar filtro de entidade em toda consulta customizada fora do `Search` nativo (`18-Entities.md`).
5. **Para cada saída HTML**, confirmar que passa por Twig (auto-escape) ou `htmlescape()`/`jsescape()` — nunca `|raw` sobre dado de usuário.
6. **Reportar achados por severidade**, usando a mesma escala de `GLPI10/28-AntiPatterns.md` (crítica/alta/média).

## Vetores a checar especificamente

- XSS refletido em endpoints AJAX que ecoam parâmetro GET.
- XSS armazenado em campo de texto livre exibido fora do form padrão.
- Escalação de privilégio em massive action que só checa right de visualização.
- Vazamento entre entidades em query customizada sem filtro.
- CSRF ausente em plugin sem `csrf_compliant` ou em endpoint de API com autenticação paralela.
- SQL injection em qualquer uso de SQL raw.

## O que nunca fazer

- Aceitar "está protegido pela UI" (menu escondido, botão desabilitado) como suficiente — a checagem real é sempre no servidor.
- Reduzir uma checagem de segurança para "resolver" um bug funcional relacionado — investigar a causa raiz em vez de remover a proteção.
- Considerar um plugin "seguro o bastante" com itens de severidade crítica pendentes, independentemente de contexto ou prazo.
