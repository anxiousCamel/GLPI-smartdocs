# Template: MassiveAction

Ações em massa para itemtype próprio ou do core. Ver `GLPI10/11-MassiveActions.md`.

## Para itemtype PRÓPRIO do plugin (métodos dentro da própria classe)

```php
<?php

// Dentro de GlpiPlugin\Meuplugin\Coisa (adicionar aos métodos existentes)

use CommonDBTM;
use Html;
use MassiveAction;
use Session;

/**
 * Declara ações extras para o itemtype.
 */
public static function getSpecificMassiveActions($checkitem = null): array
{
    $actions = parent::getSpecificMassiveActions($checkitem);

    if (Session::haveRight(self::$rightname, self::APPROVE)) {
        $key = self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'aprovar';
        $actions[$key] = __('Aprovar selecionados', 'meuplugin');
    }

    return $actions;
}

/**
 * Sub-formulário do modal de confirmação.
 */
public static function showMassiveActionsSubForm(MassiveAction $ma)
{
    switch ($ma->getAction()) {
        case 'aprovar':
            echo __('Comentário (opcional):', 'meuplugin');
            echo Html::input('comentario');
            echo Html::submit(__('Aplicar', 'meuplugin'), ['name' => 'massiveaction']);
            return true;
    }

    return parent::showMassiveActionsSubForm($ma);
}

/**
 * Processa a ação para cada item selecionado.
 */
public static function processMassiveActionsForOneItemtype(
    MassiveAction $ma,
    CommonDBTM $item,
    array $ids
): void {
    switch ($ma->getAction()) {
        case 'aprovar':
            $input = $ma->getInput();

            foreach ($ids as $id) {
                if ($item->getFromDB($id)
                    && $item->update([
                        'id'     => $id,
                        'status' => 'aprovado',
                        'comentario_aprovacao' => $input['comentario'] ?? '',
                    ])
                ) {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                } else {
                    $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    $ma->addMessage(__('Falha ao aprovar o item.', 'meuplugin'));
                }
            }
            return;
    }

    parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
}
```

## Para itemtype do CORE (ex.: Computer) — via hook.php

```php
<?php
// hook.php

use GlpiPlugin\Meuplugin\ComputerExtra;
use MassiveAction;
use Computer;

function plugin_meuplugin_MassiveActions(string $itemtype): array
{
    $actions = [];

    if ($itemtype === Computer::class) {
        $key = ComputerExtra::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'sincronizar';
        $actions[$key] = __('Sincronizar com Meuplugin', 'meuplugin');
    }

    return $actions;
}
```

Os métodos `showMassiveActionsSubForm`/`processMassiveActionsForOneItemtype` ficam na classe declarada (`ComputerExtra`), seguindo o mesmo padrão do exemplo acima.

## setup.php necessário

```php
$PLUGIN_HOOKS[\Glpi\Plugin\Hooks::USE_MASSIVE_ACTION]['meuplugin'] = true;
```

## Checklist pós-cópia

- [ ] `USE_MASSIVE_ACTION` registrado no `plugin_init`
- [ ] Right específico da ação checado (não confundir com right de visualização)
- [ ] `parent::` chamado no `default`/fora do próprio `case` em ambos os métodos
- [ ] `$ma->itemDone()` chamado para cada id, refletindo o resultado real
