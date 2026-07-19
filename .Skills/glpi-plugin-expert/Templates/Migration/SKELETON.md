# Template: Migration

Padrão de schema incremental via `Migration`. Ver `GLPI10/06-Migrations.md`.

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use Migration;

final class CoisaSchema
{
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $table = Coisa::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Instalando $table");

            $query = "CREATE TABLE `$table` (
                `id`            int unsigned NOT NULL AUTO_INCREMENT,
                `name`          varchar(255) NOT NULL,
                `entities_id`   int unsigned NOT NULL DEFAULT '0',
                `is_recursive`  tinyint NOT NULL DEFAULT '0',
                `is_deleted`    tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod`      timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `entities_id` (`entities_id`),
                KEY `is_deleted` (`is_deleted`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $DB->doQuery($query);
        }

        // ---- Delta v1.1.0 ----
        if (!$DB->fieldExists($table, 'status')) {
            $migration->addField($table, 'status', 'integer', ['value' => 0]);
            $migration->addKey($table, 'status');
        }

        // ---- Delta v1.2.0 ----
        if (!$DB->fieldExists($table, 'users_id_responsavel')) {
            $migration->addField(
                $table,
                'users_id_responsavel',
                'integer',
                ['null' => true, 'after' => 'status']
            );
            $migration->addKey($table, 'users_id_responsavel');
        }

        $migration->executeMigration();
    }

    public static function uninstall(Migration $migration): void
    {
        $migration->dropTable(Coisa::getTable());
    }
}
```

## Checklist pós-cópia

- [ ] Cada delta guardado por `tableExists`/`fieldExists`
- [ ] `executeMigration()` chamado ao final
- [ ] Comentário indicando a versão em que cada delta foi introduzido
- [ ] Testado a partir de pelo menos duas versões anteriores diferentes
- [ ] Nenhuma operação destrutiva (DROP/TRUNCATE) fora do `uninstall()`
