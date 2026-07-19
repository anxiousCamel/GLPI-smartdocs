# 28 — Anti-Patterns (O Que Nunca Fazer)

## Objetivo

Lista consolidada e priorizada dos erros mais graves e mais comuns em plugins GLPI, para checagem rápida durante code review. Cada item referencia o documento onde a explicação completa e os exemplos vivem.

## Objetivo de uso

Este documento é um **detector de bandeiras vermelhas**. Se qualquer item abaixo for encontrado em código (próprio ou de terceiros sendo revisado), pare e trate antes de prosseguir — não são "estilo", são defeitos que comprometem segurança, dados do cliente ou compatibilidade futura.

## Severidade crítica (compromete segurança ou integridade de dados)

1. **Modificar qualquer arquivo do core do GLPI.** Sem exceções, mesmo "só um if". → `00-Architecture.md`
2. **SQL raw / string interpolada em query.** Sempre query builder. → `05-Database.md`
3. **Entry point (`front/`, `ajax/`, endpoint de API) sem `Session::checkRight()`.** → `04-Rights.md`, `12-AJAX.md`, `14-REST-API.md`
4. **Ausência de `csrf_compliant` ou POST sem checagem de CSRF.** → `03-Hooks.md`, `01-Plugin-Structure.md`
5. **`install()` destrutivo (DROP/TRUNCATE) que roda também no update, apagando dado do cliente.** → `02-Lifecycle.md`, `06-Migrations.md`
6. **HTML de saída sem escape (`echo` cru de dado de usuário, `|raw` em Twig sem sanitização prévia).** → `20-Twig.md`, `30-Security.md`
7. **Comparação de igualdade simples em bitmask de right (`===` em vez de `Session::haveRight`).** → `04-Rights.md`
8. **Ignorar multi-entidade num itemtype que deveria respeitá-la**, vazando dados entre clientes/departamentos. → `18-Entities.md`

## Severidade alta (quebra em produção ou na migração)

9. **`exit()`/`die()`/`http_response_code()` em vez de exceção HTTP/`return`.** Quebra no GLPI 11. → `GLPI11/00-Migration-Guide.md`
10. **`Plugin::getWebDir()`/`GLPI_PLUGINS_PATH`/`get_plugin_web_dir` / concatenação manual de path de plugin.** Quebra no 11. → `13-Routing.md`, `GLPI11/00-Migration-Guide.md`
11. **Sintaxe antiga do query builder (`$DB->request('tabela', [...])`) ou SQL raw dentro de hooks de Search.** Proibido no 11. → `05-Database.md`, `10-Search.md`
12. **`uninstall()` incompleto** — deixa tabelas, rights, crons ou notificações órfãs. → `02-Lifecycle.md`, `19-Profiles.md`
13. **Renumerar/reutilizar IDs de search option já publicados.** Quebra buscas salvas e dashboards de clientes em produção. → `10-Search.md`
14. **`ALTER TABLE` manual fora da classe `Migration`, sem checagem de existência.** Quebra em updates repetidos. → `06-Migrations.md`

## Severidade média (dívida técnica, funciona mas é frágil)

15. **Classes no estilo legado (`Plugin<Nome><Classe>`) em código novo**, em vez do namespace moderno. → `24-Namespaces.md`
16. **Reimplementar showForm()/listagem manualmente** quando `CommonDBTM`/`CommonDropdown`/`Search` já resolvem. → `07`, `09`, `10`
17. **Lógica de negócio pesada em `plugin_init`** — roda em toda request do GLPI inteiro. → `01-Plugin-Structure.md`
18. **Efeito colateral (email, chamada externa) em `prepareInputFor*`** em vez de `post_*Item` — a operação ainda pode ser vetada. → `02-Lifecycle.md`, `07-CommonDBTM.md`
19. **Right insuficiente checado só na UI (esconder botão/menu)**, sem espelho no servidor. → `04-Rights.md`, `08-CommonGLPI.md`
20. **`var_dump`/`print_r`/`error_log` cru em produção; supressão de erro com `@`.** → `26-Debugging.md`
21. **Envio de e-mail direto (`mail()`) em vez do pipeline de `NotificationTarget`/`NotificationEvent`.** → `16-Notifications.md`
22. **Reimplementar coleta/parsing de inventário de agente** em vez de reagir a hooks padrão de itemtype. → `17-Inventory.md`

## Uso em code review

Ao revisar código (próprio ou de terceiro) contra esta KB:

1. Rode primeiro os itens de severidade crítica — qualquer ocorrência bloqueia o merge/release.
2. Itens de severidade alta bloqueiam release para GLPI 11 ou instância que possa migrar.
3. Itens de severidade média viram débito documentado se não corrigidos imediatamente — nunca silenciosos.

## Referências

Cada item aponta para o documento com explicação completa, exemplo correto e exemplo incorreto lado a lado. Ver também `27-BestPractices.md` para a lista simétrica do que fazer, e `Checklists/SecurityChecklist.md`/`ReviewChecklist.md` para checklists operacionais de uso direto em PR.
