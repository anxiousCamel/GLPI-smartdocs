# 00 — Arquitetura do GLPI

## Objetivo

Entender como o GLPI 10.x funciona por dentro — bootstrap, camadas, fluxo de request, autoload e pontos de extensão — para que qualquer decisão de plugin seja tomada com conhecimento do terreno, não por imitação de código alheio.

## Conceitos

- **Monólito PHP orientado a itemtypes.** O GLPI não é um framework MVC clássico. O átomo do sistema é o *itemtype*: uma classe PHP cujo nome identifica um tipo de objeto de negócio (`Computer`, `Ticket`, `PluginMeupluginCoisa` ou `GlpiPlugin\Meuplugin\Coisa`). Quase tudo — CRUD, busca, permissões, notificações, ações em massa, abas — é resolvido por convenção a partir do nome do itemtype.
- **Convenção sobre configuração.** Nome de classe → nome de tabela (`Computer` → `glpi_computers`; `GlpiPlugin\Meuplugin\Categoria` → `glpi_plugin_meuplugin_categorias`); campo `id` como PK; FKs no formato `<tabela_singular>_id` (`computers_id`). O core deduz relações inteiras a partir desses nomes via `getTableForItemType()`, `getForeignKeyFieldForTable()` e afins.
- **Camadas reais do 10.x:**
  1. **Entry points legados** — scripts em `/front` e `/ajax` (do core e dos plugins) que recebem a request diretamente.
  2. **Bootstrap** — `inc/includes.php`: autoload, configuração, `$DB`, sessão, i18n, carga dos plugins ativos.
  3. **Domínio** — classes em `src/` do core: `CommonDBTM` e família (persistência), `Search` (motor de busca/listas), `Session` (estado+direitos), `Migration`, `CronTask`, `NotificationEvent`...
  4. **View** — transição em andamento no 10: HTML legado ecoado por PHP convive com templates **Twig** (`templates/`) renderizados por `TemplateRenderer`, sobre UI **Tabler** + jQuery + componentes **Vue** pontuais.
  5. **Dados** — MySQL/MariaDB acessado só por `$DB` (`DBmysql`) e pelo query builder `DBMysqlIterator`.
- **Plugins são cidadãos de primeira classe.** O core chama os plugins por *hooks* (tabela global `$PLUGIN_HOOKS` + funções mágicas `plugin_<nome>_<hook>`); os plugins chamam o core pelas mesmas APIs públicas que o core usa internamente. Não existe sandbox: plugin roda no mesmo processo, com o mesmo `$DB` e a mesma sessão — poder total, responsabilidade total.

## Funcionamento interno

### Bootstrap (10.x)

Toda request a um script legado executa, via `inc/includes.php`:

1. Carrega configuração (`config_db.php`), define constantes de path (`GLPI_ROOT`, `GLPI_PLUGIN_DOC_DIR`...).
2. Registra autoloaders: PSR-4 do core (`src/` → raiz de namespace `Glpi\` e classes globais), autoloader de plugins (resolve `PluginMeupluginCoisa` e `GlpiPlugin\Meuplugin\Coisa` para `plugins/meuplugin/src|inc/`), e o autoload do Composer (`vendor/`).
3. Instancia `$DB` e abre a sessão (`Session`).
4. Carrega idioma do usuário e inicializa i18n (`__()`).
5. **Inicializa plugins ativos**: para cada plugin com estado "ativado", inclui `setup.php` e executa `plugin_init_<nome>()`, que popula `$PLUGIN_HOOKS`.
6. Devolve o controle ao script `front/ajax`, que valida direitos e despacha.

Consequência prática: **tudo que seu `plugin_init` faz roda em TODA request**. Custo ali é custo global (ver Performance).

### Fluxo de uma request típica (form de um itemtype)

```
Browser
  │  GET /plugins/meuplugin/front/coisa.form.php?id=42
  ▼
front/coisa.form.php
  │  include inc/includes.php  ──► bootstrap + init de todos os plugins
  │  Session::checkRight('plugin_meuplugin_coisa', READ)
  ▼
Coisa (CommonDBTM)
  │  getFromDB(42) ──► $DB->request(['FROM' => 'glpi_plugin_meuplugin_coisas', ...])
  │  display() / displayFullPageForItem()
  ▼
CommonGLPI::showTabsContent  ──► abas via getTabNameForItem/displayTabContentForItem
  ▼
TemplateRenderer (Twig) ou HTML legado
  ▼
Resposta
```

Em POST, o padrão é: script lê `$_POST`, checa CSRF (`Session::checkCSRF` — automático via `csrf_compliant`), checa right, chama `$item->add()/update()/delete()`, e `Html::back()`/redireciona. `add/update/delete` disparam a cadeia de hooks (`pre_item_add` → regras → `item_add`...) — detalhada em `02-Lifecycle.md` e `07-CommonDBTM.md`.

### Autoload e nomes de classe de plugin

Duas convenções coexistem no 10.x:

| Estilo | Classe | Arquivo |
|---|---|---|
| Legado | `PluginMeupluginCoisa` | `plugins/meuplugin/inc/coisa.class.php` |
| Moderno (use este) | `GlpiPlugin\Meuplugin\Coisa` | `plugins/meuplugin/src/Coisa.php` (PSR-4) |

O core converte itemtype↔tabela para ambos. Todo código novo desta KB usa exclusivamente o estilo moderno (ver `24-Namespaces.md`).

## Fluxograma — visão macro

```
┌─────────────────────────── Request ───────────────────────────┐
│                                                               │
│  front/*.php  ajax/*.php  api (REST)  cron (CLI/web)          │
└──────────────┬────────────────────────────────────────────────┘
               ▼
        inc/includes.php (bootstrap)
               │
               ├── $DB (DBmysql) ──────────────► MySQL/MariaDB
               ├── Session (auth, rights, msgs)
               ├── i18n (__())
               └── Plugins ativos: setup.php → plugin_init_* → $PLUGIN_HOOKS
               ▼
        Domínio: CommonDBTM / Search / Migration / CronTask / Notification...
               │         ▲
               │         │ hooks (o core chama o plugin)
               │         │ APIs públicas (o plugin chama o core)
               ▼         │
        View: Twig (TemplateRenderer) + Tabler + jQuery/Vue
```

## Exemplos corretos

Descobrir a tabela e a FK de um itemtype sem hardcode:

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use Computer;

final class Relacionamento
{
    /**
     * Retorna a tabela e a FK do itemtype alvo usando as convenções do core.
     *
     * @return array{table: string, fkey: string}
     */
    public static function resolverAlvo(): array
    {
        return [
            'table' => Computer::getTable(),        // "glpi_computers"
            'fkey'  => Computer::getForeignKeyField(), // "computers_id"
        ];
    }
}
```

## Exemplos incorretos

```php
// ERRADO: hardcode de tabela/FK — quebra se a convenção evoluir e
// impede o core de rastrear a relação.
$DB->query("SELECT * FROM glpi_computers WHERE id = " . $_GET['id']);
// Três pecados numa linha: SQL raw, sem query builder, input direto na query.
```

```php
// ERRADO: "resolver" uma limitação editando o core.
// Qualquer edição em src/ do GLPI é perdida no próximo update e
// invalida o suporte. Se o core não expõe o ponto de extensão,
// a resposta é hook + API pública, ou PR para o projeto GLPI.
```

## Boas práticas

- Trate o nome do itemtype como contrato: dele derivam tabela, FK, rights, search options e URLs. Nunca lute contra a convenção.
- Use os getters do core (`getTable()`, `getForeignKeyField()`, `getType()`, `getSearchURL()`, `getFormURL()`) em vez de strings literais.
- Views novas sempre em Twig — é o caminho do core e elimina classe inteira de bugs de escape (ver `20-Twig.md`).
- Leia o código do core quando a doc for omissa, mas dependa apenas do que é API pública documentada; internals mudam sem aviso entre minors.

## Anti-patterns

- Modificar qualquer arquivo do core (incluindo "só um if" em `src/`). Sem exceções.
- Consultas diretas às tabelas do core ignorando as classes de itemtype (perde hooks, cache, regras e histórico).
- Lógica pesada em `plugin_init_` (roda em toda request).
- Depender de jQuery global ou de markup interno do Tabler como se fossem API.

## Checklist

- [ ] Sei em qual camada minha feature vive (front, domínio, view, cron, API)?
- [ ] Todos os acessos a dados passam por itemtypes/`$DB->request` (zero SQL raw)?
- [ ] Nenhuma referência hardcoded a tabela/FK onde existe getter?
- [ ] Nenhum arquivo do core tocado?
- [ ] `plugin_init` contém apenas registro de hooks (barato)?

## Dicas de performance

- O init de plugin roda em toda request: adie qualquer trabalho para o hook/ação que realmente precisa dele.
- `Search` já pagina e faz joins pelas search options — não reimplemente listagens com queries manuais.
- Cache do GLPI (`GLPI_CACHE`) está disponível para dados caros e estáveis (ver `29-Performance.md`).

## Dicas de segurança

- A mesma sessão e o mesmo `$DB` do core estão nas suas mãos: um plugin inseguro compromete a instância inteira.
- Todo entry point (`front/`, `ajax/`) valida right ANTES de qualquer efeito colateral (ver `04-Rights.md`, `30-Security.md`).
- No 10.x existe auto-sanitize de superglobais; **não confie nele** — escreva como se não existisse (ele não existe no 11; ver `GLPI11/00-Migration-Guide.md`).

## Referências

- Developer docs — visão geral e Developer API: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/index.html
- Código do core: https://github.com/glpi-project/glpi (branch `10.0/bugfixes`)
- Documentos relacionados: `01-Plugin-Structure.md`, `02-Lifecycle.md`, `07-CommonDBTM.md`, `24-Namespaces.md`
