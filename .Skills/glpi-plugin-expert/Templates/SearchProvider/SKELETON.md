# Template: SearchProvider

Adicionar colunas do plugin a um itemtype do CORE (ex.: Computer). Ver `GLPI10/10-Search.md`.

## hook.php

```php
<?php

use Search;
use Computer;

/**
 * Expõe colunas do plugin na lista de Computer.
 * IDs em faixa reservada (ex.: 8000-8099) documentada no README do plugin.
 */
function plugin_meuplugin_getAddSearchOptionsNew(string $itemtype): array
{
    $tab = [];

    if ($itemtype === Computer::class) {
        $tab[] = [
            'id'         => 8001,
            'table'      => 'glpi_plugin_meuplugin_coisas',
            'field'      => 'name',
            'name'       => __('Coisa vinculada', 'meuplugin'),
            'datatype'   => 'dropdown',
            'joinparams' => ['jointype' => 'itemtype_item'],
        ];
    }

    return $tab;
}

/**
 * JOIN necessário para a coluna acima funcionar — sempre via
 * Search::addLeftJoin, nunca SQL livre.
 */
function plugin_meuplugin_addDefaultJoin(string $itemtype, string $ref_table, array &$already_link_tables): string
{
    if ($itemtype === Computer::class) {
        return Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            'glpi_plugin_meuplugin_coisas',
            'computers_id'
        );
    }

    return '';
}

/**
 * Formatação especial de célula (quando datatype => 'specific').
 */
function plugin_meuplugin_giveItem(string $type, int $ID, array $data, int $num): string
{
    $searchopt = &Search::getOptions($type);
    $table = $searchopt[$ID]['table'];
    $field = $searchopt[$ID]['field'];

    switch ($table . '.' . $field) {
        case 'glpi_plugin_meuplugin_coisas.status':
            return '<span class="badge">' . htmlescape($data["ITEM_$num"]) . '</span>';
    }

    return '';
}
```

## Search options do próprio itemtype (dentro da classe)

```php
public function getSearchOptions(): array
{
    $tab = parent::getSearchOptions();

    $tab[] = [
        'id'       => 10,
        'table'    => self::getTable(),
        'field'    => 'status',
        'name'     => __('Status', 'meuplugin'),
        'datatype' => 'specific',
    ];

    return $tab;
}
```

## Checklist pós-cópia

- [ ] IDs em faixa reservada e documentada no README, nunca renumerados após publicação
- [ ] `Search::addLeftJoin` usado, nunca SQL livre
- [ ] `datatype => 'specific'` + `giveItem` para renderização customizada
- [ ] Testado com export CSV/PDF e display preferences
