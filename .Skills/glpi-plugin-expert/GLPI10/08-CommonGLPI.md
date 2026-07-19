# 08 — CommonGLPI

## Objetivo

Entender `CommonGLPI`, a classe-base de tudo que aparece na UI do GLPI: mecanismo de abas (tabs), integração com menus, e como um plugin adiciona uma aba própria a um itemtype do core (ou expõe abas de outros itemtypes na sua classe).

## Conceitos

- **`CommonGLPI` é a raiz.** `CommonDBTM` (persistência) e `CommonDropdown` (`09`) herdam dela. Toda classe que aparece como aba, menu ou item navegável no GLPI passa, direta ou indiretamente, por esses três métodos:
  - `defineTabs(array $options = [])` — **instância**, declara quais classes fornecem abas para *este* itemtype (inclui a própria aba principal via `addDefaultFormTab`, e abas de outras classes via `addStandardTab`).
  - `getTabNameForItem(CommonGLPI $item, $withtemplate = 0)` — **instância**, roda na classe *dona da aba*, decide o rótulo (ou array de rótulos, se a classe fornece múltiplas abas) exibido quando `$item` é do tipo esperado.
  - `displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)` — **estático**, roda na classe *dona da aba*, renderiza o conteúdo.
- **Duas direções de integração:**
  1. **Seu itemtype ganha abas de outras classes** (inclusive do core): implemente `defineTabs()` na sua classe.
  2. **Sua classe vira aba de um itemtype do core** (ex.: aba "Meu Plugin" dentro do form de `Computer`): implemente `getTabNameForItem`/`displayTabContentForItem` na sua classe e registre via `Plugin::registerClass(SuaClasse::class, ['addtabon' => ['Computer']])` no `plugin_init`.
- **`createTabEntry()`** é o helper padrão para montar o rótulo da aba já com contagem (`self::createTabEntry(self::getTypeName($nb), $nb)`), usado quando `$_SESSION['glpishow_count_on_tabs']` está ativo.

## Funcionamento interno

Ao exibir um form (`showTabsContent`), o core monta a lista de abas chamando `defineTabs()` do próprio item e, para cada classe registrada via `addStandardTab`/`Plugin::registerClass(..., 'addtabon')`, chama `getTabNameForItem($item)` estaticamente/por instância para obter o rótulo. Ao clicar numa aba (AJAX), o core chama `displayTabContentForItem($item, $tabnum)` na classe dona daquela aba, que decide o que renderizar — tipicamente delegando para `showForm()`/`showForItem()` de uma instância.

`getTabNameForItem` sempre faz `switch ($item->getType())` (ou `$item::getType()`/`instanceof`) porque a MESMA classe de aba pode ser registrada em vários itemtypes diferentes — o método precisa responder de forma diferente conforme quem está pedindo.

## Fluxograma

```
showTabsContent(item)
      │
      ▼
item->defineTabs($options)             ← abas do PRÓPRIO itemtype
      │  + Plugin::registerClass(..., 'addtabon' => [ItemType])
      ▼
para cada classe registrada:
      ClasseAba::getTabNameForItem(item)  ← rótulo (com contagem, via createTabEntry)
      ▼
[usuário clica na aba N]
      ▼
ClasseAba::displayTabContentForItem(item, N)  ← renderiza conteúdo (geralmente Twig)
```

## Exemplos corretos

### Sua classe ganha uma aba dentro de `Computer` (itemtype do core)

```php
<?php
// setup.php
use GlpiPlugin\Meuplugin\ComputerExtra;

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;
    // ...
    Plugin::registerClass(ComputerExtra::class, ['addtabon' => ['Computer']]);
}
```

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;
use CommonGLPI;
use Computer;
use Session;
use Glpi\Application\View\TemplateRenderer;

/**
 * Aba "Informações extras" exibida dentro do form de Computer.
 */
class ComputerExtra extends CommonDBTM
{
    public static $rightname = 'plugin_meuplugin_computerextra';

    /**
     * Rótulo da aba, com contagem de registros relacionados.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Computer) || !Session::haveRight(self::$rightname, READ)) {
            return '';
        }

        $nb = $_SESSION['glpishow_count_on_tabs']
            ? countElementsInTable(self::getTable(), ['computers_id' => $item->getID()])
            : 0;

        return self::createTabEntry(self::getTypeName($nb), $nb, $item::class);
    }

    /**
     * Conteúdo da aba.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Computer)) {
            return false;
        }

        TemplateRenderer::getInstance()->display('@meuplugin/computer_extra.html.twig', [
            'computer' => $item,
        ]);

        return true;
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Informação extra', 'Informações extras', $nb, 'meuplugin');
    }
}
```

### Seu próprio itemtype recebe abas de outras classes (incluindo o Log nativo)

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;
use Log;

class Coisa extends CommonDBTM
{
    // ...

    /**
     * Declara as abas exibidas no form desta classe:
     * a própria (via addDefaultFormTab) + o histórico nativo do core.
     */
    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(Log::class, $tabs, $options);
        return $tabs;
    }
}
```

## Exemplos incorretos

```php
// ERRADO: getTabNameForItem sem checar right — expõe a aba (e a
// contagem, que já é dado) a quem não deveria nem saber que existe.
public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
{
    return self::createTabEntry(self::getTypeName());
}
```

```php
// ERRADO: displayTabContentForItem sem checar o tipo do $item recebido.
// A mesma classe pode estar registrada em múltiplos itemtypes; sem o
// switch/instanceof, conteúdo errado pode vazar para outro itemtype.
public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
{
    self::showForItem($item); // assume Computer, quebra se registrado em outro lugar
    return true;
}
```

```php
// ERRADO: HTML manual em displayTabContentForItem em vez de Twig —
// perde consistência visual e reabre risco de escape manual incorreto.
```

## Boas práticas

- Toda `getTabNameForItem` que expõe dado sensível (contagem, rótulo condicional) checa right antes.
- Use `instanceof`/`switch ($item->getType())` mesmo quando "só" existe um registro hoje — é o contrato do método, e evita quebra silenciosa se a classe for reaproveitada depois.
- Delegue a renderização real para `showForm()`/métodos próprios em vez de amontoar tudo dentro de `displayTabContentForItem`.
- Registre `Log::class` como aba padrão em itemtypes que têm histórico relevante — é de graça e o usuário espera essa aba.

## Anti-patterns

- Retornar rótulo de aba sem checagem de right (a aba aparece; o conteúdo pode até ser bloqueado depois, mas a visibilidade da aba já vazou informação).
- Uma classe de aba tentando servir N itemtypes sem discriminar por tipo, causando conteúdo cruzado.
- `defineTabs()` chamando lógica pesada (query grande) só para montar a lista de nomes de abas — isso deve estar em `displayTabContentForItem`, chamado só quando a aba é de fato aberta.

## Checklist

- [ ] `getTabNameForItem` checa `instanceof`/tipo e right antes de retornar rótulo
- [ ] `displayTabContentForItem` valida o tipo do `$item` recebido
- [ ] Contagem em aba só é calculada quando `$_SESSION['glpishow_count_on_tabs']` está ativo
- [ ] Conteúdo da aba renderizado via Twig/`TemplateRenderer`
- [ ] Registro feito via `Plugin::registerClass(..., ['addtabon' => [...]])` no `plugin_init`

## Dicas de performance

- `getTabNameForItem` roda para TODA aba possível ao abrir o form — mantenha-o barato (idealmente `COUNT` indexado, nunca um `SELECT *`).
- Adie qualquer processamento pesado para `displayTabContentForItem`, que só executa quando a aba é efetivamente aberta (via AJAX).

## Dicas de segurança

- Right insuficiente deve resultar em string vazia no rótulo (aba nem aparece) — não apenas em bloqueio no conteúdo.
- Nunca confie no `$tabnum` recebido sem mapear para uma ação conhecida — trate como um índice de um switch fechado, não como entrada livre.

## Referências

- Tips & tricks (defineTabs/getTabNameForItem/displayTabContentForItem): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tips.html
- Tutorial oficial (abas em itemtype de plugin): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Main framework objects: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/mainobjects.html
- Documentos relacionados: `07-CommonDBTM.md`, `04-Rights.md`, `20-Twig.md`
