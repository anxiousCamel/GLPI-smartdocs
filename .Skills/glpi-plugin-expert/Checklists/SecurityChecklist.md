# SecurityChecklist — Auditoria de Segurança

Uso: checklist dedicado de segurança, aplicado camada por camada (ver `GLPI10/30-Security.md` para explicação completa de cada item).

## Camada 1 — Right (autorização)
- [ ] Todo `front/*.php` chama `Session::checkRight()` antes de exibir/processar
- [ ] Todo `ajax/*.php` chama `Session::checkRight()` antes de processar
- [ ] Todo endpoint de API próprio checa right antes de processar
- [ ] Ações de massive action checam o right ESPECÍFICO da ação, não apenas o de visualização
- [ ] Rights customizados usam faixa > 1024, nunca reaproveitando valores padrão (1/2/4/8/16)

## Camada 2 — Entidade (multi-tenant)
- [ ] Todo itemtype de negócio tem `entities_id`/`is_recursive` quando aplicável
- [ ] Checagem de acesso usa `canViewItem()`/`Session::haveAccessToEntity()`, nunca comparação manual de `==`
- [ ] Toda consulta customizada (fora do `Search` nativo) filtra por `Session::getActiveEntities()`

## Camada 3 — CSRF
- [ ] `csrf_compliant` declarado no `plugin_init`
- [ ] Nenhum form/POST do plugin contorna o token CSRF automático
- [ ] Endpoints de API próprios reaproveitam `Session`, não autenticação paralela

## Camada 4 — Validação de entrada
- [ ] Todo `$_GET`/`$_POST`/body de API convertido para o tipo esperado antes de uso
- [ ] `prepareInputForAdd`/`prepareInputForUpdate` validam formato/obrigatoriedade
- [ ] Zero SQL raw — sempre query builder, em qualquer contexto (incluindo hooks de Search)
- [ ] Upload de arquivo (se houver) usa as APIs nativas de `Document`, não move arquivo manualmente

## Camada 5 — Escape de saída
- [ ] Views em Twig (auto-escape ativo)
- [ ] `htmlescape()`/`jsescape()` usado em qualquer `echo` HTML fora de Twig
- [ ] Zero uso de `|raw` sobre dado de usuário não sanitizado
- [ ] Resposta JSON usa `json_encode`, nunca concatenação manual de string JSON

## Vetores específicos a checar
- [ ] Nenhum parâmetro GET/POST refletido diretamente em HTML de resposta (XSS refletido)
- [ ] Nenhum campo de texto livre exibido fora do form padrão sem escape (XSS armazenado)
- [ ] Nenhuma tabela multi-tenant consultada sem filtro de entidade
- [ ] Nenhum e-mail enviado via `mail()` direto (deve usar `NotificationEvent`)

## Logging
- [ ] Nenhum dado sensível (senha, token, chave) logado, mesmo em modo debug
- [ ] Logging via `Toolbox::logInFile()`, não `error_log`/`var_dump`

## Conclusão
Qualquer item não marcado nesta lista é um bloqueador de release — não uma sugestão de melhoria futura.
