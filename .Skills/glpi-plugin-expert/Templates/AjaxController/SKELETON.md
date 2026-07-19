# Template: AjaxController

Endpoint AJAX seguro. Ver `GLPI10/12-AJAX.md`.

## ajax/getStatusOptions.php (retorno JSON)

```php
<?php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

Session::checkRight(Coisa::$rightname, READ);

$categoriaId = (int) ($_GET['categorias_id'] ?? 0);

echo json_encode(
    Coisa::getStatusOptionsParaCategoria($categoriaId)
);
```

## ajax/coisaDropdown.php (retorno HTML via Dropdown::show)

```php
<?php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

Session::checkRight(Coisa::$rightname, READ);

\Dropdown::show(Coisa::class, [
    'name'   => $_GET['name'] ?? 'plugin_meuplugin_coisas_id',
    'entity' => $_GET['entity_restrict'] ?? -1,
    'value'  => (int) ($_GET['value'] ?? 0),
]);
```

## Método correspondente na classe (lógica real, testável)

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

/**
 * Retorna as opções de status válidas para uma categoria.
 *
 * @return array<int, string>
 */
public static function getStatusOptionsParaCategoria(int $categoriaId): array
{
    if ($categoriaId <= 0) {
        return [];
    }

    // ... lógica real, via query builder

    return [
        1 => __('Aberto', 'meuplugin'),
        2 => __('Em análise', 'meuplugin'),
    ];
}
```

## Checklist pós-cópia

- [ ] `Session::checkRight` antes de qualquer processamento
- [ ] Lógica delegada a método de classe em `src/`, script só orquestra
- [ ] `Content-Type` correto para o formato de resposta
- [ ] Entrada tipada (`(int)`, `(string)`) antes de qualquer uso
