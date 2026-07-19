# 03 — Hooks

## Objetivo

Entender o mecanismo de hooks — o único canal pelo qual o core chama o plugin — e catalogar os hooks do GLPI 10.x por categoria, com assinatura, retorno esperado e armadilhas.

## Conceitos

- **Hook = ponto nomeado onde o core delega.** O plugin se inscreve populando `$PLUGIN_HOOKS['<hook>']['<chave_do_plugin>']` no `plugin_init`. O valor varia por hook: booleano, string (asset), array (config) ou callable.
- **Duas formas de callback:**
  1. **Registrado**: `$PLUGIN_HOOKS[Hooks::ITEM_ADD]['meuplugin'] = ['Ticket' => 'callbackOuMetodo']`.
  2. **Mágico por convenção**: o core procura `plugin_meuplugin_<hook>()` em `hook.php` (ex.: `plugin_meuplugin_getAddSearchOptions`, `plugin_meuplugin_MassiveActions`). Não aparece em `$PLUGIN_HOOKS`; existir com o nome certo basta.
- **Use as constantes de `Glpi\Plugin\Hooks`** em vez de strings soltas — erro de digitação em string vira hook silenciosamente morto; constante inexistente estoura no lint.
- Hooks de item recebem o **objeto por referência**: alterar `$item->input` altera o que será gravado; `$item->input = false` veta a operação.

## Funcionamento interno

Quando o core alcança um ponto de extensão, chama `Plugin::doHook()` / `Plugin::doHookFunction()` / `Plugin::doOneHook()`, que percorre `$PLUGIN_HOOKS[<hook>]` na ordem de carga dos plugins e invoca cada inscrito. Para hooks "de filtro" (ex.: `pre_item_add`), a saída de um plugin é a entrada do próximo — plugins podem, portanto, interferir uns nos outros; nunca assuma que seu plugin é o único inscrito.

## Fluxograma — hook de item

```
$item->add($input)
   │
   ▼
prepareInputForAdd (classe do itemtype)
   │
   ▼
Plugin::doHook(PRE_ITEM_ADD)
   ├── pluginA: ajusta $item->input
   ├── meuplugin: valida; se inválido → $item->input = false
   └── pluginB: ...
   │  (se input === false → aborta)
   ▼
INSERT
   │
   ▼
post_addItem (classe)
   │
   ▼
Plugin::doHook(ITEM_ADD)   ← item persistido, id disponível, efeitos colaterais aqui
```

## Catálogo por categoria (GLPI 10.x)

### Obrigatório

| Hook | Valor | Nota |
|---|---|---|
| `Hooks::CSRF_COMPLIANT` | `true` | Sem isso, POSTs do plugin são tratados como legado inseguro |

### Ciclo de vida de itens (Standard Events)

`PRE_ITEM_ADD`, `ITEM_ADD`, `PRE_ITEM_UPDATE`, `ITEM_UPDATE`, `PRE_ITEM_DELETE`, `ITEM_DELETE`, `PRE_ITEM_PURGE`, `ITEM_PURGE`, `PRE_ITEM_RESTORE`, `ITEM_RESTORE`.

Formato de registro: array `['Itemtype' => callable]`. O callback recebe o `CommonDBTM` da operação.

```php
$PLUGIN_HOOKS[Hooks::ITEM_ADD]['meuplugin'] = [
    Ticket::class => [Auditoria::class, 'aoAdicionarTicket'],
];
```

### Interface / assets

| Hook | Valor |
|---|---|
| `ADD_CSS` / `ADD_JAVASCRIPT` | path relativo (ou array) de asset do plugin |
| `ADD_JAVASCRIPT_MODULE` | módulos ES |
| `menu_toadd` | `['setor_do_menu' => Classe::class]` |
| `redefine_menus` | callable que recebe e retorna o array completo de menus |
| `POST_SHOW_ITEM` / `PRE_SHOW_ITEM`, `POST_SHOW_TAB` / `PRE_SHOW_TAB` | injetar conteúdo em forms/abas do core |
| `POST_ITEM_FORM` / `PRE_ITEM_FORM` | injetar campos dentro do form do core |
| `DASHBOARD_CARDS` | callable que retorna cards de dashboard custom |

### Busca e dados

| Hook (função mágica) | Papel |
|---|---|
| `plugin_<k>_getAddSearchOptions[New]($itemtype)` | adicionar search options a itemtypes do core |
| `plugin_<k>_addDefaultSelect / addDefaultJoin / addDefaultWhere` | interferir na query do Search |
| `plugin_<k>_forceGroupBy` | forçar GROUP BY |
| `plugin_<k>_giveItem` | customizar render de célula na lista |

### Massive Actions

| Hook | Papel |
|---|---|
| `Hooks::USE_MASSIVE_ACTION` = `true` | habilita |
| `plugin_<k>_MassiveActions($itemtype)` | declara ações extras (`'Classe:acao' => 'Rótulo'`) |

### Dropdowns / regras / diversos

| Hook | Papel |
|---|---|
| função `plugin_<k>_getDropdown()` | expõe dropdowns do plugin na tela de dicionários |
| `RULE_MATCHED`, `ruleCollectionPrepareInputDataForProcess`, `preProcessRulePreviewResults` | integração com engine de regras |
| `Hooks::INIT_SESSION` | após login |
| `Hooks::CHANGE_PROFILE` / `CHANGE_ENTITY` | troca de contexto do usuário |
| `Hooks::DISPLAY_LOGIN` / `DISPLAY_CENTRAL` | injetar conteúdo nessas telas |
| `Hooks::POST_INIT` | após init de todos os plugins |
| `Hooks::SECURED_FIELDS` / `SECURED_CONFIGS` (9.4.6+) | declara campos/configs cifrados com a chave GLPI, integrando `glpi:security:changekey` |

### Notificações / cron

- `Hooks::ITEM_GET_EVENTS` / `ITEM_GET_DATAS`: adicionar eventos e dados a notificações de itemtypes do core.
- Cron não é hook: registra-se `CronTask` no install (ver `15-Cron.md`).

> Lista completa e sempre atualizada: página oficial de hooks (referência abaixo). Vários hooks obscuros estão sem doc oficial ("write documentation for this hook") — para esses, a fonte é o código do core e o plugin `example`.

## Exemplos corretos

```php
<?php

// setup.php
use Glpi\Plugin\Hooks;
use GlpiPlugin\Meuplugin\TicketObserver;

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['meuplugin'] = true;

    $PLUGIN_HOOKS[Hooks::PRE_ITEM_UPDATE]['meuplugin'] = [
        Ticket::class => [TicketObserver::class, 'antesDeAtualizar'],
    ];
}
```

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use Ticket;

final class TicketObserver
{
    /**
     * Impede rebaixar prioridade de ticket com SLA estourado.
     * Hook: pre_item_update (Ticket).
     */
    public static function antesDeAtualizar(Ticket $ticket): void
    {
        $novaPrioridade  = $ticket->input['priority'] ?? null;
        $prioridadeAtual = $ticket->fields['priority'] ?? null;

        if ($novaPrioridade !== null
            && $novaPrioridade < $prioridadeAtual
            && self::slaEstourado($ticket)
        ) {
            \Session::addMessageAfterRedirect(
                __('Não é permitido reduzir prioridade com SLA estourado.', 'meuplugin'),
                false,
                ERROR
            );
            $ticket->input = false; // veta o update
        }
    }

    private static function slaEstourado(Ticket $ticket): bool
    {
        // ... consulta via APIs públicas
        return false;
    }
}
```

## Exemplos incorretos

```php
// ERRADO: string mágica com typo — nunca dispara, ninguém avisa.
$PLUGIN_HOOKS['pre_item_updte']['meuplugin'] = [...];
// Use Hooks::PRE_ITEM_UPDATE.
```

```php
// ERRADO: efeito colateral pesado em PRE_* (o insert ainda pode falhar)
// e chamada externa síncrona em hook de item (toda gravação de Ticket
// passa a esperar seu webhook). Efeito colateral → ITEM_ADD/UPDATE;
// coisa lenta → fila própria + cron.
```

```php
// ERRADO: assumir exclusividade.
// Outro plugin pode ter alterado $item->input antes de você.
// Valide sobre o estado atual do input, não sobre o que "deveria" estar lá.
```

## Boas práticas

- Constantes `Hooks::*` sempre; strings nunca.
- Callback = método estático de classe dedicada (`*Observer`, `*Hooks`) em `src/`; `hook.php` fino.
- Um hook, uma responsabilidade por callback; orquestre dentro da classe, não empilhando callbacks anônimos.
- Cheque rights dentro do callback quando a ação depende do usuário — hook não herda contexto de permissão da sua feature.

## Anti-patterns

- Lógica de negócio inteira dentro de `hook.php` global.
- Hooks de exibição (`POST_SHOW_ITEM`) fazendo queries N+1 por item exibido.
- Vetar operação lançando exceção em vez de `input = false` (estoura a request inteira em vez de mensagem amigável).
- Usar hooks para modificar comportamento que o core já oferece por configuração/regras.

## Checklist

- [ ] `CSRF_COMPLIANT` registrado
- [ ] Todos os hooks via constantes `Hooks::*`
- [ ] PRE_* só valida/ajusta input; ITEM_* faz efeitos colaterais
- [ ] Callbacks em classes PSR-4, não closures no setup
- [ ] Comportamento correto testado com outro plugin inscrito no mesmo hook

## Dicas de performance

- Hooks de item rodam em TODA operação daquele itemtype, incluindo importações em massa: mantenha O(1) e sem I/O externo.
- Em `getAddSearchOptions`, monte o array estaticamente (é chamado com frequência); cachê se derivar de banco.

## Dicas de segurança

- `pre_item_*` é sua última linha de validação server-side — nunca confie que o form validou.
- Ao injetar HTML via `POST_SHOW_*`, escape tudo (Twig ou `htmlescape` no 11); é vetor clássico de XSS de plugin.

## Referências

- Hooks oficiais: https://glpi-developer-documentation.readthedocs.io/en/master/plugins/hooks.html
- Implementações de referência: https://github.com/pluginsGLPI/example
- Documentos relacionados: `02-Lifecycle.md`, `10-Search.md`, `11-MassiveActions.md`, `16-Notifications.md`
