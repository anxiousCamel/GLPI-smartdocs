# PluginChecklist — Antes de Considerar o Plugin Pronto

Uso: rodar do início ao fim antes de qualquer release ou entrega de plugin novo/atualizado.

## Estrutura e Arquitetura
- [ ] Chave do plugin idêntica em: diretório, funções (`plugin_<chave>_*`), hooks, tabelas (`glpi_plugin_<chave>_*`), rights (`plugin_<chave>_*`)
- [ ] Namespace moderno `GlpiPlugin\<Nome>\` em `src/`, PSR-4 correto, nenhuma classe no estilo legado
- [ ] `declare(strict_types=1);` em todo arquivo PHP novo
- [ ] `setup.php` só declara (barato); `hook.php`/classes executam
- [ ] Nenhum arquivo do core do GLPI modificado

## Ciclo de Vida
- [ ] `install()` idempotente, testado a partir de 2+ versões anteriores
- [ ] `uninstall()` remove: tabelas, rights (`ProfileRight::deleteProfileRights`), crons, notificações/templates, display preferences, logs
- [ ] Toda alteração de schema via `Migration`, guardada por `tableExists`/`fieldExists`
- [ ] `prepareInputFor*` valida; `post_*Item` executa efeito colateral

## Segurança (ver também SecurityChecklist.md)
- [ ] `csrf_compliant` declarado
- [ ] Todo entry point (`front/`, `ajax/`, API própria) valida right antes de qualquer efeito
- [ ] Zero SQL raw — sempre query builder
- [ ] Toda saída HTML escapada (Twig ou `htmlescape()`)
- [ ] Multi-entidade respeitada onde aplicável

## UI e Integração
- [ ] Rights customizados expostos na matriz de Perfis
- [ ] Forms em Twig, estendendo `generic_show_form.html.twig`
- [ ] Search Options em faixa de ID reservada e documentada
- [ ] Massive actions (se houver) chamam `parent::` fora do próprio case
- [ ] CSS próprio mínimo, usando classes/variáveis Tabler existentes

## Compatibilidade
- [ ] Nenhum item da lista de quebras do GLPI 11 presente, mesmo visando 10.x
- [ ] Faixa de versão GLPI/PHP no `setup.php` reflete o que foi testado de fato

## Qualidade
- [ ] Testes cobrindo ciclo de vida do(s) itemtype(s) principal(is)
- [ ] Nenhum `var_dump`/`print_r`/`error_log` cru; logging via `Toolbox::logInFile()`
- [ ] README documenta: requisitos, rights criados, faixa de search option IDs, endpoints próprios de API (se houver)
- [ ] `composer.json` com autoload correto; `vendor/` gerado para o pacote de release (`--no-dev --optimize-autoloader`)

## Referências
Cada item remete a um documento específico da KB — ver `GLPI10/27-BestPractices.md` para o detalhamento e `GLPI10/99-Rules.md` para o contrato completo.
