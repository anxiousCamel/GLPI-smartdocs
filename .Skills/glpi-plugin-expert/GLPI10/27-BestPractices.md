# 27 — Boas Práticas Consolidadas

## Objetivo

Servir como checklist-mestre de boas práticas transversais a todo o desenvolvimento de plugin GLPI, sintetizando o que cada documento anterior já detalhou. Use este documento como revisão final antes de considerar um plugin "pronto" — não como substituto da leitura dos documentos específicos.

## Conceitos

Boas práticas de plugin GLPI se agrupam em cinco eixos: **arquitetura** (respeitar convenções do core), **segurança** (right + CSRF + escape em toda fronteira), **manutenção** (código legível, testável, documentado), **compatibilidade** (10.x hoje, 11.x amanhã) e **performance** (não degradar a instância inteira).

## Arquitetura

- Namespace moderno `GlpiPlugin\<Nome>\` em `src/` (PSR-4) — nunca o estilo legado em código novo (`24-Namespaces.md`).
- Nunca modificar arquivo do core — todo ponto de extensão passa por hook ou API pública (`00-Architecture.md`, `03-Hooks.md`).
- Convenção de nome (tabela, FK, chave do plugin) idêntica em todos os artefatos: diretório, funções, hooks, rights (`01-Plugin-Structure.md`).
- `install()`/`uninstall()` simétricos e idempotentes via `Migration` (`02-Lifecycle.md`, `06-Migrations.md`).
- Um itemtype, uma responsabilidade — `CommonDBTM`/`CommonDropdown`/`CommonTreeDropdown` conforme a natureza real do dado (`07`, `09`).

## Segurança

- Todo entry point (`front/`, `ajax/`, endpoint próprio de API) começa com `Session::checkRight()` (`04-Rights.md`, `12-AJAX.md`, `13-Routing.md`, `14-REST-API.md`).
- `csrf_compliant` declarado; todo POST passa pela checagem de CSRF automática.
- Query builder sempre; SQL raw nunca (`05-Database.md`).
- Toda saída HTML escapada — Twig por padrão, `htmlescape()`/`jsescape()` fora de Twig no 11 (`20-Twig.md`, `GLPI11/00-Migration-Guide.md`).
- Right específico da AÇÃO (não só de visualização) checado em massive actions e efeitos colaterais sensíveis (`11-MassiveActions.md`).
- Multi-entidade tratada como requisito de segurança, não só de organização (`18-Entities.md`).

## Manutenção

- Nomes descritivos e consistentes em classes, métodos, variáveis.
- Uma responsabilidade por função/método — separar validação (`prepareInputFor*`) de efeito colateral (`post_*Item`).
- Docstrings (PHPDoc) em todo módulo público explicando objetivo, parâmetros e retorno.
- Refatorar e simplificar sempre que uma linha problemática for identificada, deixando o código melhor do que foi encontrado.
- Testes cobrindo ciclo de vida real (add válido/inválido, update, delete), não só caminho feliz (`25-Testing.md`).
- Logging via `Toolbox::logInFile()`, nunca `var_dump`/`error_log` cru em produção (`26-Debugging.md`).

## Compatibilidade

- Escrever pensando no GLPI 11 desde o primeiro commit: nada de `exit()`, `Plugin::getWebDir()`, sintaxe antiga do query builder, `include inc/includes.php` fora do necessário (`GLPI11/00-Migration-Guide.md`).
- Views 100% em Twig — resolve escape nas duas versões de uma vez.
- Faixa de versão GLPI declarada em `setup.php` refletindo o que foi de fato testado (`01-Plugin-Structure.md`).

## Performance

- Índices em toda coluna usada em `WHERE`/`JOIN` frequente (`05-Database.md`, `29-Performance.md`).
- `plugin_init` barato — nenhuma query ou I/O pesado nesse ponto (`01-Plugin-Structure.md`).
- Hooks de item mantidos O(1), sem I/O externo síncrono (`03-Hooks.md`).
- Cron processando em lotes limitados, nunca "tudo de uma vez" (`15-Cron.md`).
- Cache para dado caro e estável (`29-Performance.md`).

## Checklist mestre (antes de considerar o plugin pronto para release)

- [ ] Todo entry point valida right e CSRF
- [ ] Zero SQL raw; zero HTML sem escape
- [ ] `install()`/`uninstall()` testados a partir de 2+ versões anteriores
- [ ] Namespace moderno, PSR-4 correto, `declare(strict_types=1)` em todo arquivo
- [ ] Testes cobrindo ciclo de vida do(s) itemtype(s) principal(is)
- [ ] Nenhum padrão da lista de quebras do GLPI 11 presente no código
- [ ] Views em Twig, estendendo templates genéricos do core
- [ ] Rights customizados expostos na matriz de Perfis
- [ ] Uninstall remove tabelas, rights, crons, notificações e display preferences
- [ ] README documenta rights, search options (faixa de IDs), endpoints próprios (se houver) e requisitos

## Referências

- Este documento é uma síntese; cada seção referencia o documento fonte com o detalhamento completo, exemplos corretos/incorretos e justificativa.
- Ver também `28-AntiPatterns.md` para a lista simétrica do que evitar.
