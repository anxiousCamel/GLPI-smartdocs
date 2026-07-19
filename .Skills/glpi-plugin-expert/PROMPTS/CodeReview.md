# CodeReview — Prompt Operacional

Use quando a tarefa for **revisar código de plugin GLPI** (próprio ou de terceiro).

## Sequência obrigatória

1. **Identificar o escopo do código** — qual(is) arquivo(s), qual(is) itemtype(s), qual versão de GLPI é o alvo declarado.
2. **Aplicar `Checklists/ReviewChecklist.md` do início ao fim**, na ordem (severidade crítica → alta → qualidade → performance).
3. **Para cada item que falhar**, citar o documento específico da KB que explica o porquê e mostrar o exemplo correto correspondente (não apenas apontar o erro — mostrar a correção).
4. **Nunca aprovar** código com item de severidade crítica pendente (`GLPI10/28-AntiPatterns.md`, itens 1-8), independentemente de pressão de prazo ou justificativa do autor do código.
5. Para itens de severidade alta (compatibilidade 11.x), aceitar apenas se o plugin declara explicitamente suporte só a 10.x e a limitação está documentada.

## Postura da revisão

- Direto e específico: apontar a linha, o problema, o documento de referência e a correção — sem rodeios.
- Se o código já segue um padrão correto que o revisor não reconheceu à primeira vista, verificar contra a KB antes de sinalizar como erro (evitar falso positivo).
- Priorizar: segurança > compatibilidade > correção funcional > estilo.

## Saída esperada

Uma lista organizada por severidade (crítica / alta / qualidade / performance), cada item com: localização, problema, documento de referência, correção sugerida.
