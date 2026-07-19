# CompatibilityChecklist — GLPI 10.x / 11.x

Uso: aplicar antes de declarar suporte a uma ou ambas as versões. Ver `GLPI10/31-Compatibility.md` e `GLPI11/00-Migration-Guide.md` para explicação completa de cada item.

## Sanitização e SQL
- [ ] Nenhum `addslashes()`/`Toolbox::addslashes_deep()` sobre input (quebra no 11, desnecessário no 10)
- [ ] Query builder sempre com sintaxe de array único (`['FROM' => ..., 'WHERE' => ...]`)
- [ ] Zero SQL raw em qualquer contexto, incluindo hooks de Search

## Views e Escape
- [ ] 100% das views em Twig
- [ ] Nenhum `|verbatim_value` (removido no 11)
- [ ] Todo `echo` de HTML fora de Twig usa `htmlescape()`
- [ ] JS emitido via PHP usa `jsescape()` além de `htmlescape()`

## Roteamento e URLs
- [ ] Nenhuma referência a `Plugin::getWebDir()`, `GLPI_PLUGINS_PATH` (JS), `get_plugin_web_dir` (Twig)
- [ ] URLs construídas via `getSearchURL()`/`getFormURL()`/`getFormURLWithID()`, nunca concatenação manual
- [ ] Assets do plugin organizados em `public/` (obrigatório no 11, recomendado já no 10)

## Controle de fluxo
- [ ] Nenhum `exit()`/`die()` para interromper script — usar exceção HTTP ou `return`
- [ ] Nenhum `http_response_code()` seguido de `exit()` para erro — usar `throw new \Glpi\Exception\Http\NotFoundHttpException()` (ou equivalente)

## Recursos exclusivos do 11 (feature-detect)
- [ ] Uso de `Firewall::addPluginStrategyForLegacyScripts` protegido por `class_exists(\Glpi\Http\Firewall::class)`
- [ ] Uso de `SessionManager::registerPluginStatelessPath` protegido por `class_exists(...)` equivalente

## Plugins absorvidos pelo core no 11
- [ ] Verificado se a funcionalidade do plugin não foi absorvida pelo core do GLPI 11 (ex.: Forms nativo substitui FormCreator, Custom Assets substitui Generic Object)

## Versionamento
- [ ] `requirements.glpi.min/max` no `setup.php` reflete exatamente o que foi testado
- [ ] Suíte de testes rodada contra cada versão declarada como suportada

## Conclusão
Se todos os itens acima estão marcados, o código deveria funcionar de forma idêntica em GLPI 10.x e 11.x sem branches condicionais — que é o resultado esperado na esmagadora maioria dos casos.
