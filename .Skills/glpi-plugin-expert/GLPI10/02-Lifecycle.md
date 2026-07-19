# 02 — Ciclo de Vida

## Objetivo

Dominar os dois ciclos de vida que regem um plugin: o do **plugin** (instalar → ativar → atualizar → desativar → desinstalar) e o de um **item** (add → update → delete → restore → purge), incluindo em que ordem o core dispara cada etapa e cada hook.

## Conceitos

### Estados do plugin

| Estado | Significado | O que roda |
|---|---|---|
| Não instalado | Diretório presente, tabelas ausentes | Só `setup.php` na tela de plugins |
| Instalado, inativo | `plugin_<k>_install()` já rodou | Idem |
| **Ativo** | Instalado + ativado | `setup.php` + `plugin_init_<k>()` em toda request |
| A atualizar | Versão do diretório > versão registrada | Core pede nova execução de `install()` |

**Ponto crítico:** o GLPI usa a MESMA função `plugin_<chave>_install()` para instalar E atualizar. Ela precisa ser idempotente e sensível a estado: olhar o que existe no banco e aplicar apenas o delta (é exatamente para isso que a classe `Migration` existe — `06-Migrations.md`).

### Ciclo de vida de um item (CommonDBTM)

Para `$item->add($input)`:

```
input do form
  ▼
prepareInputForAdd($input)          ← sua classe pode vetar (return false) ou ajustar
  ▼
hook pre_item_add                   ← outros plugins podem alterar $item->input
  ▼
regras de negócio do core (rules engine, se aplicável)
  ▼
INSERT na tabela
  ▼
post_addItem()                      ← sua classe: efeitos colaterais pós-insert
  ▼
hook item_add                       ← outros plugins reagem (item já tem id)
  ▼
histórico (glpi_logs) + notificações (se configuradas)
```

`update()` segue o espelho (`prepareInputForUpdate` → `pre_item_update` → UPDATE → `post_updateItem` → `item_update`), com `$item->oldvalues` disponível. `delete()` tem duas fases: **delete** (lixeira, `is_deleted=1`, hooks `pre_item_delete`/`item_delete`) e **purge** (remoção física, `pre_item_purge`/`item_purge`), mais **restore** (`pre_item_restore`/`item_restore`).

Regra de ouro: `prepareInputFor*` retorna o `$input` (modificado) ou `false` para abortar; os hooks `pre_item_*` recebem o objeto por referência e vetam esvaziando `$item->input = false`.

## Funcionamento interno

- Estado do plugin fica em `glpi_plugins` (`directory`, `version`, `state`). Ao detectar diretório com versão ≠ registrada, o core marca "A atualizar" e bloqueia o uso até rodar o update.
- Ativar ≠ instalar: ativar só liga o `plugin_init`. Desativar não remove nada. Desinstalar chama `plugin_<k>_uninstall()` — e é SUA responsabilidade dropar tabelas, rights (`glpi_profilerights`), crons (`glpi_crontasks`), notificações, templates, display preferences (`glpi_displaypreferences`) e logs próprios.
- No item, `$item->fields` = estado persistido; `$item->input` = o que veio do form; `$item->oldvalues` = valores anteriores dos campos alterados (no update). Confundir `fields` com `input` é fonte clássica de bug.

## Fluxograma — plugin

```
[diretório em plugins/]
      │ (tela Plugins encontra setup.php)
      ▼
  NÃO INSTALADO ──install()──► INSTALADO/INATIVO ──ativar──► ATIVO
      ▲                              │    ▲                    │
      │                              │    └──desativar─────────┘
      └────────uninstall()───────────┘
                                     │ nova versão no diretório
                                     ▼
                               A ATUALIZAR ──install() de novo──► ATIVO
```

## Exemplos corretos

### install() idempotente orientado a estado

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

/**
 * Cria/atualiza a tabela do itemtype. Chamado por plugin_meuplugin_install()
 * tanto na instalação quanto em TODO update de versão.
 */
public static function install(Migration $migration): void
{
    /** @var \DBmysql $DB */
    global $DB;

    $table = self::getTable();

    if (!$DB->tableExists($table)) {
        // Instalação limpa
        $migration->displayMessage("Criando $table");

        $query = "CREATE TABLE `$table` (
            `id`           int unsigned NOT NULL AUTO_INCREMENT,
            `name`         varchar(255) DEFAULT NULL,
            `entities_id`  int unsigned NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $DB->doQuery($query);
    } else {
        // Upgrades incrementais — cada if é um delta de versão
        if (!$DB->fieldExists($table, 'date_creation')) {
            $migration->addField($table, 'date_creation', 'timestamp');
            $migration->addKey($table, 'date_creation');
        }
    }
}

/**
 * Remove a tabela e tudo que o itemtype registrou.
 */
public static function uninstall(Migration $migration): void
{
    $migration->dropTable(self::getTable());

    // Limpa preferências de exibição e logs do itemtype
    foreach ([DisplayPreference::class, Log::class] as $itemtype) {
        (new $itemtype())->deleteByCriteria(['itemtype' => self::getType()]);
    }
}
```

### Vetando um add na própria classe

```php
/**
 * Normaliza e valida o input antes do INSERT.
 *
 * @param array $input
 * @return array|false false aborta o add com segurança
 */
public function prepareInputForAdd($input)
{
    if (empty($input['name'])) {
        Session::addMessageAfterRedirect(
            __('Nome é obrigatório', 'meuplugin'),
            false,
            ERROR
        );
        return false;
    }

    $input['name'] = trim($input['name']);
    return $input;
}
```

## Exemplos incorretos

```php
// ERRADO: install() destrutivo — roda também no UPDATE e apaga dados do usuário.
function plugin_meuplugin_install(): bool
{
    global $DB;
    $DB->doQuery("DROP TABLE IF EXISTS glpi_plugin_meuplugin_coisas"); // NUNCA
    $DB->doQuery("CREATE TABLE ...");
    return true;
}
```

```php
// ERRADO: uninstall() "educado" que deixa lixo.
// Tabelas órfãs, rights fantasmas em glpi_profilerights, crons mortos
// em glpi_crontasks. Desinstalação limpa é obrigatória.
function plugin_meuplugin_uninstall(): bool
{
    return true; // não remove nada
}
```

```php
// ERRADO: efeito colateral em prepareInputForAdd (email, insert em outra
// tabela). Nesse ponto o INSERT ainda pode falhar/ser vetado — efeitos
// colaterais vão para post_addItem(), onde o item já existe e tem id.
```

## Boas práticas

- Um bloco de upgrade por mudança de schema, guardado por `fieldExists`/`tableExists` — o update funciona a partir de QUALQUER versão anterior, não só da imediatamente anterior.
- Registre em `post_addItem`/`post_updateItem` os efeitos colaterais; em `prepareInputFor*` apenas validação/normalização.
- Ao criar rights, crons e notificações no install, escreva no MESMO commit o código de remoção no uninstall. Simetria install/uninstall é revisável; assimetria é dívida.
- Teste o ciclo completo em dev: instalar → usar → atualizar de versão antiga → desativar → desinstalar → reinstalar.

## Anti-patterns

- `DROP TABLE` no install; `TRUNCATE` em update.
- Detectar versão lendo constante do próprio plugin em vez do estado real do banco.
- Guardar estado de negócio em arquivo dentro do diretório do plugin (é apagado em updates via marketplace) — use tabela ou `Config`.
- Assumir que desativar limpa hooks residuais de request em andamento.

## Checklist

- [ ] `install()` idempotente, roda N vezes sem efeito destrutivo
- [ ] Upgrades incrementais guardados por checagem de estado
- [ ] `uninstall()` remove: tabelas, rights, crons, notificações, display preferences, logs
- [ ] Validação em `prepareInputFor*`, efeitos colaterais em `post_*Item`
- [ ] Ciclo completo testado, incluindo update de 2+ versões atrás

## Dicas de performance

- `Migration` acumula ALTERs e executa em lote no `executeMigration()` — não execute ALTERs manuais um a um.
- Em updates com milhões de linhas, use `$migration->addPostQuery()` para dados e avalie migração em chunks.

## Dicas de segurança

- Update roda com privilégios plenos: nunca interpole dados de tabela em SQL de migração sem query builder/escape.
- Uninstall que não remove rights deixa entradas fantasma que podem colidir com um right futuro de outro plugin.

## Referências

- Tutorial oficial (partes de install/update): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Documentos relacionados: `06-Migrations.md`, `07-CommonDBTM.md`, `03-Hooks.md`, `04-Rights.md`
