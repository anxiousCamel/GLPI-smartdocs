# 99 — Regras Obrigatórias

## Objetivo

Este documento é o contrato final e inegociável para TODO código gerado por esta skill. Diferente dos demais documentos (que ensinam e explicam), este lista requisitos — cada item aqui é uma condição de aceite, não uma sugestão.

## Regras (do briefing original da skill)

Todo código gerado pela Skill deve:

1. **Nunca modificar arquivos do core do GLPI.** Nenhuma exceção, nenhum "só um patch pequeno".
2. **Utilizar exclusivamente APIs públicas** sempre que existirem. Se a necessidade genuinamente exige algo não exposto publicamente, isso deve ser sinalizado explicitamente como risco/limitação, nunca silenciosamente contornado via acesso a internals.
3. **Utilizar Composer** com `composer.json` próprio do plugin (`23-Composer.md`).
4. **Utilizar PSR-4** para autoload de todas as classes (`23-Composer.md`, `24-Namespaces.md`).
5. **Utilizar namespaces** no padrão `GlpiPlugin\<Nome>\` — nunca o estilo legado em código novo (`24-Namespaces.md`).
6. **Utilizar tipagem** — `declare(strict_types=1);` no topo de todo arquivo, tipos em parâmetros/retornos sempre que a linguagem permitir.
7. **Utilizar internacionalização (`__()`/`_n()`)** em toda string visível ao usuário, com o domínio do plugin (`__('Texto', 'meuplugin')`).
8. **Implementar instalação** (`plugin_<chave>_install()`, idempotente, via `Migration` — `02-Lifecycle.md`, `06-Migrations.md`).
9. **Implementar atualização** — a MESMA função de instalação, sensível a estado, cobrindo upgrade a partir de qualquer versão anterior (`02-Lifecycle.md`).
10. **Implementar desinstalação limpa** — remove tabelas, rights (`ProfileRight::deleteProfileRights`), crons (`CronTask`), notificações/templates, display preferences e logs próprios (`02`, `15`, `16`, `19`).
11. **Implementar migrações** via a classe `Migration` do core para todo delta de schema (`06-Migrations.md`).
12. **Implementar tratamento de erros** — validação em `prepareInputFor*`, mensagens claras via `Session::addMessageAfterRedirect`, exceções HTTP em vez de `exit()` (`02`, `07`, `GLPI11/00-Migration-Guide.md`).
13. **Implementar logs quando necessário** — via `Toolbox::logInFile()`, nunca `error_log`/`var_dump` cru (`26-Debugging.md`).
14. **Implementar permissões** — todo itemtype de negócio declara `$rightname`; toda ação sensível checa o right específico, não apenas o de visualização (`04-Rights.md`, `11-MassiveActions.md`).
15. **Respeitar o sistema de Rights do GLPI** — nunca um mecanismo de autorização paralelo; sempre `Session::haveRight`/`canViewItem`/`canUpdateItem` etc.
16. **Aplicar SOLID** — uma responsabilidade por classe/método; `prepareInputFor*` valida, `post_*Item` executa efeito colateral; entry points finos delegando a classes de `src/`.
17. **Evitar duplicação** — reaproveitar `CommonDBTM`/`CommonDropdown`/`Search`/`Migration`/`NotificationTarget` do core em vez de reimplementar o que já existe.
18. **Priorizar extensibilidade** — hooks e pontos de extensão do próprio plugin quando fizer sentido para o domínio, sem superengenharia.
19. **Priorizar compatibilidade com GLPI 10.x** como alvo primário.
20. **Facilitar futura compatibilidade com GLPI 11.x** — todo código novo já nasce seguindo os padrões documentados em `GLPI11/00-Migration-Guide.md`/`31-Compatibility.md`, mesmo quando o alvo imediato é 10.x.

## Regras adicionais de segurança (não-negociáveis, consolidadas de `30-Security.md`)

21. Todo entry point (`front/`, `ajax/`, endpoint próprio de API) valida right ANTES de qualquer efeito colateral ou exibição de dado.
22. `csrf_compliant` sempre declarado; nenhum POST contorna a checagem de CSRF.
23. Query builder sempre; **zero SQL raw**, em qualquer contexto (incluindo hooks de Search).
24. Toda saída HTML passa por escape (Twig auto-escape, ou `htmlescape()`/`jsescape()` fora de Twig) — **zero `|raw` sobre dado de usuário não sanitizado**.
25. Multi-entidade respeitada em todo itemtype de negócio e em toda consulta customizada.

## Como este documento se relaciona com os demais

- `27-BestPractices.md` explica o **porquê** e o **como** de cada prática, com exemplos.
- `28-AntiPatterns.md` lista o que **nunca fazer**, com severidade.
- `99-Rules.md` (este documento) é a **lista de aceite mínima e obrigatória** — todo código produzido por esta skill deve satisfazer TODOS os itens acima antes de ser considerado completo.

## Checklist de conformidade final

Antes de entregar qualquer código gerado por esta skill, confirme:

- [ ] Regras 1–20 (briefing original) — todas atendidas
- [ ] Regras 21–25 (segurança) — todas atendidas
- [ ] Nenhum item da lista de `28-AntiPatterns.md` presente
- [ ] Compatibilidade 10.x/11.x conforme `31-Compatibility.md`

## Referências

Este documento não introduz conceitos novos — é a consolidação executável de `01` a `31`. Consulte o documento específico linkado em cada regra para exemplos completos e justificativa.
