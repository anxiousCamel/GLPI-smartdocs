# Template: CommonDBTM

Itemtype persistente completo, combinando ciclo de vida, rights, Search, showForm em Twig e schema via Migration. Ver `GLPI10/07-CommonDBTM.md`, `04-Rights.md`, `06-Migrations.md`, `10-Search.md` para explicação detalhada de cada parte.

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;
use Migration;
use Session;
use Glpi\Application\View\TemplateRenderer;

/**
 * Itemtype "Coisa" do plugin Meuplugin.
 * Tabela: glpi_plugin_meuplugin_coisas (resolvida por convenção de nome).
 */
class Coisa extends CommonDBTM
{
    public static $rightname = 'plugin_meuplugin_coisa';

    /** Nível de direito customizado, acima da faixa padrão do core */
    public const APPROVE = 1024;

    public static function getTypeName($nb = 0): string
    {
        return _n('Coisa', 'Coisas', $nb, 'meuplugin');
    }

    /**
     * Estende os rights padrão do core com o nível customizado.
     */
    public function getRights($interface = 'central'): array
    {
        $rights = parent::getRights();
        $rights[self::APPROVE] = __('Aprovar', 'meuplugin');
        return $rights;
    }

    /**
     * Valida/normaliza antes do INSERT. false aborta a operação.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        if (empty($input['name'])) {
            Session::addMessageAfterRedirect(
                __('Nome é obrigatório.', 'meuplugin'),
                false,
                ERROR
            );
            return false;
        }

        $input['name'] = trim($input['name']);

        if (!isset($input['entities_id'])) {
            $input['entities_id'] = Session::getActiveEntity();
        }

        return $input;
    }

    /**
     * Efeito colateral pós-insert (item já existe, tem id).
     */
    public function post_addItem(): void
    {
        // ex.: auditoria, evento, etc.
    }

    /**
     * Search Options — IDs de plugin em faixa reservada (>= 8000),
     * nunca renumerados após publicação.
     */
    public function getSearchOptions(): array
    {
        $tab = parent::getSearchOptions();

        $tab[] = [
            'id'       => 8001,
            'table'    => self::getTable(),
            'field'    => 'status',
            'name'     => __('Status', 'meuplugin'),
            'datatype' => 'specific',
        ];

        return $tab;
    }

    /**
     * Renderiza o form via Twig, estendendo o template genérico do core.
     */
    public function showForm($ID, $options = []): bool
    {
        $this->initForm($ID, $options);

        TemplateRenderer::getInstance()->display('@meuplugin/coisa.form.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);

        return true;
    }

    /**
     * Cria/atualiza a tabela. Chamado por plugin_meuplugin_install()
     * tanto na instalação quanto em todo update de versão.
     */
    public static function install(Migration $migration): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Criando $table");

            $query = "CREATE TABLE `$table` (
                `id`            int unsigned NOT NULL AUTO_INCREMENT,
                `name`          varchar(255) DEFAULT NULL,
                `status`        int NOT NULL DEFAULT '0',
                `entities_id`   int unsigned NOT NULL DEFAULT '0',
                `is_recursive`  tinyint NOT NULL DEFAULT '0',
                `is_deleted`    tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod`      timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `entities_id` (`entities_id`),
                KEY `status` (`status`),
                KEY `is_deleted` (`is_deleted`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $DB->doQuery($query);
        }

        // Exemplo de delta incremental de versão futura:
        // if (!$DB->fieldExists($table, 'novo_campo')) {
        //     $migration->addField($table, 'novo_campo', 'varchar(255)');
        // }
    }

    /**
     * Remove a tabela e tudo que o itemtype registrou.
     */
    public static function uninstall(Migration $migration): void
    {
        $migration->dropTable(self::getTable());
    }
}
```

## Checklist pós-cópia

- [ ] Ajustar nome da classe, `$rightname`, tabela (via `getTable()` automático pelo nome da classe)
- [ ] Ajustar campos de `getSearchOptions()` (IDs em faixa reservada documentada no README)
- [ ] Criar `templates/coisa.form.html.twig` correspondente (ver `Templates/PluginSkeleton/SKELETON.md`)
- [ ] Adicionar `getSpecificMassiveActions`/`showMassiveActionsSubForm`/`processMassiveActionsForOneItemtype` se o itemtype precisar de ações em massa próprias (ver `Templates/MassiveAction/SKELETON.md`)
