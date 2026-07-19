# ReviewChecklist — Revisão de Código (PR)

Uso: aplicar a QUALQUER código de plugin GLPI antes de aprovar merge — seja código próprio ou de terceiro sendo avaliado.

## Passo 1 — Severidade crítica (bloqueia merge se presente)
- [ ] Nenhum arquivo do core do GLPI modificado
- [ ] Nenhum SQL raw / string interpolada em query
- [ ] Todo entry point novo/alterado tem `Session::checkRight()`
- [ ] `csrf_compliant` presente; nenhum POST contorna CSRF
- [ ] `install()` não é destrutivo (sem DROP/TRUNCATE fora do uninstall)
- [ ] Nenhuma saída HTML sem escape (`|raw` sobre dado de usuário, `echo` cru)
- [ ] Nenhuma comparação de igualdade simples em bitmask de right
- [ ] Multi-entidade respeitada onde o itemtype é multi-tenant

Ver lista completa em `GLPI10/28-AntiPatterns.md` (itens 1-8).

## Passo 2 — Severidade alta (bloqueia release para 11.x / migração)
- [ ] Nenhum `exit()`/`die()`/`http_response_code()` cru
- [ ] Nenhuma construção manual de path de plugin (`Plugin::getWebDir()`, concatenação)
- [ ] Query builder só com sintaxe de array único
- [ ] `uninstall()` completo (tabelas, rights, crons, notificações, display preferences)
- [ ] Nenhum ID de search option reutilizado/renumerado
- [ ] `ALTER TABLE` sempre via `Migration`, guardado por `tableExists`/`fieldExists`

Ver lista completa em `GLPI10/28-AntiPatterns.md` (itens 9-14).

## Passo 3 — Qualidade e manutenção
- [ ] Nomes descritivos e consistentes
- [ ] Uma responsabilidade por função/método
- [ ] Docstrings em métodos/classes públicas
- [ ] Namespace moderno `GlpiPlugin\<Nome>\`, não estilo legado
- [ ] `prepareInputFor*` só valida; `post_*Item` só efeito colateral
- [ ] Testes cobrindo o que mudou (não só caminho feliz)
- [ ] Nenhum `var_dump`/`print_r`/`@` de supressão de erro

## Passo 4 — Performance
- [ ] `plugin_init` livre de I/O pesado
- [ ] Hooks de item mantidos O(1)
- [ ] Índices presentes em colunas de filtro frequente
- [ ] Processamento em lote (cron/massive action) com limite

## Decisão final
- **Qualquer item do Passo 1 falhando → solicitar correção antes de prosseguir a revisão.**
- Itens do Passo 2 falhando → aceitável apenas se o plugin declara suporte só a 10.x E a limitação está documentada explicitamente.
- Passos 3-4 → comentários de melhoria, não bloqueantes por si só, mas acumulam como débito se ignorados repetidamente.
