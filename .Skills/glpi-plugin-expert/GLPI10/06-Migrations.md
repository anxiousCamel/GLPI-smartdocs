# 06 — Migrations

## Objetivo

Usar a classe `Migration` do core para criar e evoluir schema de forma segura, incremental e idempotente — a peça central que torna `install()` reexecutável em qualquer versão anterior do plugin.

## Conceitos

- **`Migration` é um acumulador de operações de DDL/DML**, não um executor imediato: você chama `addField`, `addKey`, `changeField`, `dropField` etc.; nada acontece até `executeMigration()`. Isso permite ao core (e ao seu plugin) logar mensagens de progresso e agrupar tudo numa transação lógica.
- **Idempotência via checagem de estado**: cada operação deve ser precedida por uma pergunta ("esse campo já existe? essa tabela já existe?") usando `$DB->tableExists()`/`$DB->fieldExists()`. É o padrão que permite rodar `install()` a partir de QUALQUER versão anterior do plugin sem erro.
- **Migration não substitui CREATE TABLE inicial.** Para a criação nova, ainda se usa `$DB->doQuery()` com DDL explícito (dentro do `if (!tableExists)`); `Migration` brilha nos **deltas** entre versões.

## Funcionamento interno

`new Migration($versionAtual)` registra a versão para log. Cada chamada (`addField`, `addKey`, `dropKey`, `changeField`, `renameTable`...) empilha uma operação e, quando aplicável, dispara automaticamente `displayMessage()` no fluxo de update visível ao administrador. `executeMigration()` no fim aplica pendências residuais (como popular um campo novo com valor default em lote) e finaliza.

## Fluxograma

```
plugin_meuplugin_install()
      │
      ▼
new Migration(MEUPLUGIN_VERSION)
      │
      ▼
tableExists('glpi_plugin_meuplugin_coisas')?
   │no                              │yes
   ▼                                ▼
CREATE TABLE (doQuery)      fieldExists('date_creation')? ─no─► addField + addKey
   │                                │yes
   │                                ▼ (nada a fazer nesse campo)
   └──────────────┬─────────────────┘
                  ▼
         (repita por campo/índice novo de cada versão)
                  ▼
         migration->executeMigration()
```

## Exemplos corretos

### Padrão completo de install incremental

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

        $table = Coisa::getTable(); // "glpi_plugin_meuplugin_coisas"

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

        // ---- Delta introduzido na v1.1.0 ----
        if (!$DB->fieldExists($table, 'status')) {
            $migration->addField($table, 'status', 'integer', ['value' => 0]);
            $migration->addKey($table, 'status');
        }

        // ---- Delta introduzido na v1.2.0 ----
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

### Renomear/alterar campo existente

```php
if ($DB->fieldExists($table, 'nome_antigo')) {
    $migration->changeField($table, 'nome_antigo', 'nome_novo', 'varchar(255)');
}
```

## Exemplos incorretos

```php
// ERRADO: ALTER manual fora do Migration, sem checagem de existência —
// quebra em updates repetidos ("Duplicate column name").
$DB->doQuery("ALTER TABLE glpi_plugin_meuplugin_coisas ADD COLUMN status INT");
```

```php
// ERRADO: recriar a tabela em toda instalação/atualização.
// Apaga dados do cliente em produção.
$DB->doQuery("DROP TABLE IF EXISTS glpi_plugin_meuplugin_coisas");
$DB->doQuery("CREATE TABLE glpi_plugin_meuplugin_coisas (...)");
```

```php
// ERRADO: assumir que install() só roda uma vez.
// O core chama a MESMA função em toda atualização de versão —
// código sem checagem de estado quebra no segundo release.
```

## Boas práticas

- Um bloco `if (!fieldExists(...))` por campo introduzido em cada versão — funciona como um changelog executável do schema.
- Comente cada bloco com a versão em que o delta foi introduzido (facilita auditoria e rollback mental).
- Sempre finalize com `executeMigration()`.
- Ao adicionar `NOT NULL` sem default em tabela já populada, defina um valor default explícito (`['value' => ...]`) para não quebrar linhas existentes.

## Anti-patterns

- ALTER TABLE fora da classe `Migration`.
- Migração de dados (grandes volumes) dentro do fluxo síncrono do install sem feedback de progresso — considere `displayMessage` a cada lote e, se muito grande, um cron de backfill.
- Dropar coluna com dado de usuário sem uma versão de "aviso"/backup anterior.
- Ignorar `$migration->displayMessage()` — o administrador fica sem visibilidade do que está acontecendo num update longo.

## Checklist

- [ ] Toda alteração de schema passa por `Migration`, nunca ALTER manual solto
- [ ] Cada delta guardado por `tableExists`/`fieldExists`
- [ ] `executeMigration()` chamado ao final
- [ ] Testado a partir de pelo menos duas versões anteriores diferentes
- [ ] Nenhuma operação destrutiva (DROP/TRUNCATE) fora do uninstall

## Dicas de performance

- Agrupe múltiplos `addField`/`addKey` antes de um único `executeMigration()` em vez de vários migrations pequenos — reduz overhead de lock em tabelas grandes.
- Para popular uma coluna nova em tabela com milhões de linhas, prefira `UPDATE` em lotes via query builder a um único `UPDATE` gigante.

## Dicas de segurança

- Nomes de tabela/coluna usados na `Migration` vêm do seu próprio código (não de input do usuário) — mesmo assim, nunca componha esses nomes a partir de dado externo.
- Migração de dados sensíveis (ex.: tokens) deve considerar os hooks `SECURED_FIELDS`/`SECURED_CONFIGS` (ver `03-Hooks.md`) para participar da rotação de chave do GLPI.

## Referências

- Developer API — Migration: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/migration.html
- Documentos relacionados: `02-Lifecycle.md`, `05-Database.md`, `07-CommonDBTM.md`
