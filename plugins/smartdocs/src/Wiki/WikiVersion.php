<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * WikiVersion: snapshot de conteúdo para versionamento de artigos.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Wiki;

use CommonDBTM;

final class WikiVersion extends CommonDBTM
{
    public static $rightname = 'plugin_smartdocs_wiki';

    public static function getTypeName($nb = 0): string
    {
        return _n('Versão Wiki', 'Versões Wiki', $nb, 'smartdocs');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_wiki_versions';
    }

    public function prepareInputForAdd($input): array
    {
        $input = parent::prepareInputForAdd($input);
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        return $input;
    }
}
