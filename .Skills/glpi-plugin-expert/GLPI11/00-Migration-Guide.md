# GLPI 11 — Guia de Migração de Plugins (10.x → 11.x)

> **Status deste documento:** consolidado a partir do guia oficial de upgrade (consultado em 2026-07-19). GLPI 11.0.x está em produção desde o fim de 2025. A estrutura equivalente completa (espelho do GLPI10/) será expandida conforme a doc de 11 amadurecer; este documento cobre todas as quebras conhecidas e é **leitura obrigatória** antes de escrever código para 11 ou migrar plugin existente.

## Objetivo

Listar toda mudança do GLPI 11 que quebra ou deprecia padrões do GLPI 10, com o diff exato de código, e definir como escrever plugin que rode em 11 (e, quando possível, em 10 e 11 simultaneamente).

## Visão geral das quebras

| Área | GLPI 10 | GLPI 11 |
|---|---|---|
| Sanitização de input | `$_GET/$_POST/$_REQUEST` auto-sanitizados (slashes + entities) | **Removida.** Dados sempre crus |
| Proteção SQLi | Parcial via auto-sanitize + query builder | Automática **apenas** via query builder |
| Proteção XSS | Entities automáticas + `|verbatim_value` no Twig | Escape manual: Twig auto-escape ou `htmlescape()`/`jsescape()` |
| Bootstrap de scripts | `include("../../../inc/includes.php")` | **Removido.** Entry point único `/public/index.php` |
| Acesso a scripts PHP | Livre por padrão | Autenticado por padrão; exceções via `Firewall` |
| Assets estáticos | Servidos de qualquer pasta do plugin | Devem estar em `/public` do plugin (URL sem o `/public`) |
| Endpoints stateless (API) | Manual | `SessionManager::registerPluginStatelessPath()` no boot |
| Saída antecipada | `exit()`/`die()` + `http_response_code()` | Exceções HTTP (`NotFoundHttpException` etc.) ou `return` |
| URLs de plugin | `Plugin::getWebDir()`, `GLPI_PLUGINS_PATH` (JS), `get_plugin_web_dir` (Twig) | **Depreciados.** Caminho fixo `/plugins/<nome>/...` |
| Query builder | 3 sintaxes aceitas | Só sintaxe de array única; SQL raw **proibido** |

## 1. Fim do auto-sanitize de input

No 10.x, o core escapava caracteres SQL (`\`) e codificava `<`, `>`, `&` em entities nos superglobais. Isso gerava ambiguidade: nunca se sabia se uma string estava "limpa" ou dupla-escapada. No 11, todo dado — de form, banco ou API — chega **cru**.

### SQLi

A proteção agora acontece exclusivamente na montagem da query pelo query builder. Todo `addslashes()`/`Toolbox::addslashes_deep()` deve ser removido:

```diff
- $item->add(Toolbox::addslashes_deep($properties));
+ $item->add($properties);
```

### XSS

Entities não são mais codificadas na entrada nem decodificadas mágicamente. Dados existentes no banco são decodificados de forma transparente na leitura. O escape é responsabilidade da **camada de view**:

**Twig** — auto-escape ativo; `|verbatim_value` sai de cena:

```diff
- <p>{{ content|verbatim_value }}</p>
+ <p>{{ content }}</p>
```

**PHP que emite HTML direto** (legado):

```diff
- echo '<p>' . $content . '</p>';
+ echo '<p>' . htmlescape($content) . '</p>';
```

**PHP que emite JS**: escapar em duas camadas — HTML por dentro, JS por fora:

```diff
- $(body).append("<p>' . $content . '</p>");
+ $(body).append("' . jsescape('<p>' . htmlescape($content) . '</p>') . '");
```

> `htmlescape()` e `jsescape()` são pontes de migração: serão depreciadas quando todo HTML/JS do core estiver em Twig/arquivos JS. Estratégia correta de longo prazo: **mover views para Twig**, não espalhar `htmlescape()`.

## 2. Novo pipeline de requests

Todas as requests passam por `/public/index.php`. Consequências:

### 2.1 `inc/includes.php` morreu

```diff
- include("../../../inc/includes.php");
```

O arquivo ainda existe como ponte vazia para não fatalizar plugin antigo, mas não deve ser incluído. Toda inicialização (autoload, sessão, plugins) é automática.

### 2.2 Restrição de recursos acessíveis

- Scripts em `/ajax`, `/front` e `/report` do plugin continuam acessíveis com URLs inalteradas (compatibilidade).
- **Qualquer outro** asset ou script acessível por web deve ir para `/public` do plugin. O `/public` não aparece na URL:
  - `meu_plugin/public/css/styles.css` → `/plugins/meuplugin/css/styles.css`
  - `meu_plugin/public/api.php` → `/plugins/meuplugin/api.php`

### 2.3 Firewall — política de acesso por script

Padrão novo: **todo script PHP exige usuário autenticado**. Exceções são declaradas no `plugin_init_<nome>()`:

```php
<?php

use Glpi\Http\Firewall;

function plugin_init_meuplugin() {
    Firewall::addPluginStrategyForLegacyScripts('meuplugin', '#^/front/faq.php$#', Firewall::STRATEGY_FAQ_ACCESS);
    Firewall::addPluginStrategyForLegacyScripts('meuplugin', '#^/front/dashboard.php$#', Firewall::STRATEGY_CENTRAL_ACCESS);
}
```

Estratégias disponíveis:

| Estratégia | Acesso |
|---|---|
| `STRATEGY_NO_CHECK` | Qualquer um, inclusive não autenticado |
| `STRATEGY_AUTHENTICATED` | Autenticados (padrão de todos os scripts) |
| `STRATEGY_CENTRAL_ACCESS` | Usuários da interface padrão |
| `STRATEGY_HELPDESK_ACCESS` | Usuários da interface simplificada |
| `STRATEGY_FAQ_ACCESS` | Leitores da FAQ (ou público, se FAQ pública) |

> `STRATEGY_NO_CHECK` não elimina a obrigação de validar rights dentro do script. Firewall controla *acesso ao endpoint*; Rights controla *o que o usuário pode fazer*.

### 2.4 Endpoints stateless

Sem isso, o GLPI abre sessão, seta cookie e redireciona não autenticados para o login — comportamento errado para APIs. Registrar no hook `boot` (setup.php):

```php
<?php

use Glpi\Http\SessionManager;

function plugin_meuplugin_boot() {
    SessionManager::registerPluginStatelessPath('meuplugin', '#^/api\.php#');
}
```

### 2.5 Fim de `exit()`/`die()`

`exit()` impede rotinas pós-request e `http_response_code()` tem bug de PHP com resultado dependente do ambiente. Substituições:

Erro HTTP → exceção (capturada pelo error handler central, renderiza página de erro GLPI):

```diff
if ($item->getFromDB($_GET['id']) === false) {
-    http_response_code(404);
-    exit();
+    throw new \Glpi\Exception\Http\NotFoundHttpException();
}
```

Interromper fluxo do script → `return`:

```diff
if ($action === 'foo') {
    echo "foo action executed";
-    exit();
+    return;
}
```

## 3. URLs de plugin — caminho canônico

URL não reflete mais a localização física (manual em `/plugins` vs marketplace). Prefixo `/marketplace` nas URLs ainda funciona, mas está depreciado.

```diff
- $path = Plugin::getWebDir('meuplugin', false) . '/front/myscript.php';
+ $path = '/plugins/meuplugin/front/myscript.php';

- $path = Plugin::getWebDir('meuplugin', true) . '/front/myscript.php';
+ $path = $CFG_GLPI['root_doc'] . '/plugins/meuplugin/front/myscript.php';
```

JS:

```diff
- var url = CFG_GLPI.root_doc + '/' + GLPI_PLUGINS_PATH.meuplugin + '/ajax/script.php';
+ var url = CFG_GLPI.root_doc + '/plugins/meuplugin/ajax/script.php';
```

Twig:

```diff
- <form action="{{ get_plugin_web_dir('meuplugin') }}/front/config.form.php" method="post">
+ <form action="{{ path('/plugins/meuplugin/front/config.form.php') }}" method="post">
```

## 4. Query builder — sintaxe única

Das três sintaxes históricas do `DBMysqlIterator`:

1. Array único (com `FROM`/`WHERE`...) → **a única suportada**
2. Tabela como 1º parâmetro + condição como 2º → **depreciada**
3. SQL raw → **proibida** (segurança)

```diff
- $iterator = $DB->request('mytable', ['field' => 'condition']);
+ $iterator = $DB->request(['FROM' => 'mytable', 'WHERE' => ['field' => 'condition']]);
```

## 5. Plugins absorvidos pelo core no 11

| Plugin 10.x | Destino no 11 |
|---|---|
| FormCreator | Forms nativo (`bin/console migration:formcreator_plugin_to_core`) |
| Generic Object | Custom Assets nativo |
| Fields | Continua como plugin (obrigatório instalar/atualizar antes da migração da instância) |

Implicação para novos plugins: antes de construir algo sobre esses plugins, verifique se o alvo é o core do 11.

## 6. Estratégia de compatibilidade dual (10 + 11)

Para manter um único codebase rodando nas duas major versions:

1. **Nunca** use `exit()`, `addslashes_deep`, `Plugin::getWebDir()`, `GLPI_PLUGINS_PATH`, sintaxe 2/3 do query builder, nem `include inc/includes.php` — tudo isso já funciona no 10 do jeito novo (array único no builder, caminho `/plugins/...`) ou tem substituto neutro.
2. Views 100% em Twig — resolve o problema de escape nas duas versões.
3. Feature-detect quando inevitável: `if (class_exists(\Glpi\Http\Firewall::class))` para registrar estratégias só no 11.
4. Declare no `getMinGlpiVersion`/`getMaxGlpiVersion` do setup a faixa real testada.

## Checklist de migração 10→11

- [ ] Removidos todos os `addslashes`/`addslashes_deep`/`stripslashes` de input
- [ ] Removido todo `|verbatim_value` dos Twig
- [ ] Todo `echo` de HTML fora de Twig usa `htmlescape()` (ou foi movido para Twig)
- [ ] Removido `include inc/includes.php` de todos os front/ajax
- [ ] Assets movidos para `/public` do plugin; URLs conferidas sem o segmento `/public`
- [ ] Estratégias de Firewall declaradas para scripts públicos/FAQ/helpdesk
- [ ] Endpoints de API registrados como stateless no hook boot
- [ ] Zero `exit()`/`die()`/`http_response_code()` — exceções HTTP ou `return`
- [ ] Zero `Plugin::getWebDir()` / `GLPI_PLUGINS_PATH` / `get_plugin_web_dir`
- [ ] Query builder só com sintaxe de array único; zero SQL raw
- [ ] Verificado se a funcionalidade não foi absorvida pelo core do 11
- [ ] Faixa de versão no setup.php atualizada e testada nas duas versões (se dual)

## Referências

- Guia oficial: https://glpi-developer-documentation.readthedocs.io/en/master/upgradeguides/glpi-11.0.html (consultado 2026-07-19)
- Migração de plugins de instância: https://help.glpi-project.org/tutorials/procedures/migrate-of-specific-plugins-from-glpi-10-to-glpi-11
