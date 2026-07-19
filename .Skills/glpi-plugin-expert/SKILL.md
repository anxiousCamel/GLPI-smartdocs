---
name: glpi-plugin-expert
description: Base de conhecimento completa para desenvolvimento profissional de plugins GLPI 10.x e 11.x. USE ESTA SKILL SEMPRE que o usuário mencionar plugin GLPI, hooks, CommonDBTM, CommonGLPI, CommonDropdown, Search Options, Massive Actions, Migration, rights/profiles GLPI, cron GLPI, notificações GLPI, Twig no GLPI, marketplace GLPI, ou pedir para criar, revisar, refatorar, corrigir, migrar (10→11), otimizar ou auditar segurança de qualquer código relacionado a plugins GLPI — mesmo sem usar a palavra "plugin". Também use para dúvidas sobre arquitetura interna do GLPI, banco de dados do GLPI, API REST do GLPI ou compatibilidade entre versões.
---

# GLPI Plugin Expert

Base de conhecimento (KB) técnica, independente de modelo, para desenvolvimento de plugins profissionais para GLPI 10.x, com caminho de compatibilidade para GLPI 11.x.

## Como usar esta skill

1. **Identifique a tarefa** do usuário e leia o documento correspondente na tabela abaixo. Não responda de memória: os padrões do GLPI mudam entre versões e esta KB é a fonte de verdade.
2. **Identifique a versão-alvo.** Se o usuário não disser, pergunte. GLPI 10.x → `GLPI10/`. GLPI 11.x → leia `GLPI10/` (a base conceitual é a mesma) **e** `GLPI11/00-Migration-Guide.md` (quebras e novos padrões). Código novo deve nascer compatível com 11.
3. **Antes de gerar código**, leia `GLPI10/99-Rules.md` (regras obrigatórias) e o template correspondente em `Templates/`.
4. **Antes de entregar código**, valide contra `Checklists/PluginChecklist.md` e `Checklists/SecurityChecklist.md`.
5. Para tarefas específicas (review, refactor, bugfix, migração, segurança), use o prompt operacional em `PROMPTS/`.

## Mapa de navegação — GLPI10/

| Tarefa / dúvida | Documento |
|---|---|
| Entender como o GLPI funciona por dentro (bootstrap, camadas, fluxo de request) | `GLPI10/00-Architecture.md` |
| Criar plugin do zero, estrutura de pastas, setup.php, hook.php | `GLPI10/01-Plugin-Structure.md` |
| Install / update / uninstall / enable / disable | `GLPI10/02-Lifecycle.md` |
| Reagir a eventos do core (item_add, menu, css/js, config...) | `GLPI10/03-Hooks.md` |
| Permissões, ACL, `Session::haveRight`, direitos custom | `GLPI10/04-Rights.md` |
| Query builder, `$DB->request`, tabelas, convenções de nomes | `GLPI10/05-Database.md` |
| Criar/alterar tabelas com a classe `Migration` | `GLPI10/06-Migrations.md` |
| Itemtypes persistentes (CRUD, forms, tabs) | `GLPI10/07-CommonDBTM.md` |
| Base de tudo que aparece na UI (tabs, menus) | `GLPI10/08-CommonGLPI.md` |
| Listas de seleção / dicionários | `GLPI10/09-CommonDropdown.md` |
| Search Options, listas, filtros, colunas | `GLPI10/10-Search.md` |
| Ações em massa | `GLPI10/11-MassiveActions.md` |
| Endpoints AJAX de plugin | `GLPI10/12-AJAX.md` |
| front/, ajax/, URLs, roteamento legado | `GLPI10/13-Routing.md` |
| API REST do GLPI e como plugins a estendem | `GLPI10/14-REST-API.md` |
| Tarefas agendadas (CronTask) | `GLPI10/15-Cron.md` |
| Notificações (templates, targets, eventos) | `GLPI10/16-Notifications.md` |
| Inventário nativo / agentes / regras | `GLPI10/17-Inventory.md` |
| Multi-entidade e recursividade | `GLPI10/18-Entities.md` |
| Perfis e integração de direitos na UI | `GLPI10/19-Profiles.md` |
| Templates Twig no GLPI | `GLPI10/20-Twig.md` |
| Vue.js no GLPI | `GLPI10/21-Vue.md` |
| Tabler (UI framework) e componentes | `GLPI10/22-Tabler.md` |
| Composer no plugin | `GLPI10/23-Composer.md` |
| PSR-4 e namespaces `GlpiPlugin\` | `GLPI10/24-Namespaces.md` |
| Testes (PHPUnit, atoum, CI) | `GLPI10/25-Testing.md` |
| Debug, logs, profiling | `GLPI10/26-Debugging.md` |
| Boas práticas consolidadas | `GLPI10/27-BestPractices.md` |
| O que NUNCA fazer | `GLPI10/28-AntiPatterns.md` |
| Performance | `GLPI10/29-Performance.md` |
| Segurança (XSS, SQLi, CSRF, upload, rights) | `GLPI10/30-Security.md` |
| Suportar 10.x e preparar 11.x no mesmo código | `GLPI10/31-Compatibility.md` |
| Exemplos completos comentados | `GLPI10/32-Examples.md` |
| **Regras obrigatórias de todo código gerado** | `GLPI10/99-Rules.md` |

## GLPI11/

| Tarefa | Documento |
|---|---|
| Migrar plugin 10.x → 11.x; escrever código já compatível com 11 | `GLPI11/00-Migration-Guide.md` |

## Diretórios de apoio

- `PROMPTS/` — prompts operacionais por tarefa (desenvolvimento, review, refactoring, bugfix, performance, migração, segurança). Leia o relevante antes de executar a tarefa correspondente.
- `Templates/` — esqueletos de código prontos (plugin completo, CommonDBTM, dropdown, massive action, AJAX, cron, notificação, search provider, migration, REST). Sempre parta de um template, nunca do zero.
- `Examples/` — análise de plugins oficiais reais (o que estudar em cada um e por quê).
- `Checklists/` — validação antes de entregar (plugin, release, review, segurança, compatibilidade).
- `References/` — fontes oficiais consolidadas: URL, versão, data de consulta e quando usar cada uma. Consulte quando esta KB não cobrir o caso ou quando precisar confirmar comportamento de versão específica.

## Regras inegociáveis (resumo — versão completa em GLPI10/99-Rules.md)

Todo código gerado por esta skill:

1. **Nunca modifica arquivos do core do GLPI.**
2. Usa APIs públicas sempre que existirem; nunca depende de internals não documentados sem marcar o risco.
3. Usa Composer, PSR-4, namespace `GlpiPlugin\<Nome>\`, tipagem estrita e `__()` para toda string visível.
4. Implementa install, update (com `Migration`), uninstall limpo (drop de tabelas, rights, crons, notificações e display preferences próprios).
5. Declara `csrf_compliant` e valida CSRF em todo POST.
6. Respeita o sistema de Rights: todo front/ e ajax/ checa `Session::checkRight`/`canView`/`canCreate` etc.
7. Usa exclusivamente o query builder (`$DB->request([...])`) — SQL raw é proibido.
8. Escapa toda saída (Twig auto-escape; `htmlescape()` fora de Twig no 11).
9. Aplica SOLID, evita duplicação, prioriza extensibilidade.
10. Compatível com GLPI 10.x e escrito para minimizar o custo de migração para 11.x (ver `GLPI10/31-Compatibility.md`).
