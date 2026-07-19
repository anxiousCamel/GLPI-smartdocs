# Template: CommonDropdown

Dicionário/lista de seleção simples. Ver `GLPI10/09-CommonDropdown.md` para explicação.

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDropdown;

/**
 * Dicionário simples usado como referência (FK) em outro itemtype do plugin.
 */
class CategoriaCoisa extends CommonDropdown
{
    public static $rightname = 'plugin_meuplugin_coisa';

    public static function getTypeName($nb = 0): string
    {
        return _n('Categoria de Coisa', 'Categorias de Coisa', $nb, 'meuplugin');
    }

    /**
     * Campos extras desenhados pelo form genérico do core,
     * além dos padrões name/comment.
     *
     * @return array<int, array{name: string, label: string, type: string}>
     */
    public function getAdditionalFields(): array
    {
        return [
            [
                'name'  => 'is_active',
                'label' => __('Ativo', 'meuplugin'),
                'type'  => 'bool',
            ],
        ];
    }
}
```

## Variante hierárquica (CommonTreeDropdown)

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonTreeDropdown;

/**
 * Dicionário hierárquico (tem pai/filhos).
 */
class GrupoCoisa extends CommonTreeDropdown
{
    public static $rightname = 'plugin_meuplugin_coisa';

    public static function getTypeName($nb = 0): string
    {
        return _n('Grupo de Coisa', 'Grupos de Coisa', $nb, 'meuplugin');
    }
}
```

## Instalação (adicionar ao mesmo Migration do itemtype principal)

```php
public static function install(Migration $migration): void
{
    global $DB;
    $table = self::getTable();

    if (!$DB->tableExists($table)) {
        $DB->doQuery("CREATE TABLE `$table` (
            `id`         int unsigned NOT NULL AUTO_INCREMENT,
            `name`       varchar(255) DEFAULT NULL,
            `comment`    text,
            `is_active`  tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
```

## Checklist pós-cópia

- [ ] Escolher `CommonDropdown` (achatado) vs `CommonTreeDropdown` (hierárquico) conforme a natureza do dado
- [ ] Adicionar FK correspondente na tabela que referencia este dropdown (convenção `<tabela_singular>_id`)
- [ ] Expor via `Dropdown::show(CategoriaCoisa::class, [...])` no form do itemtype que referencia
