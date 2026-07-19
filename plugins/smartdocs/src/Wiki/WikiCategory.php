<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * WikiCategory: categorização hierárquica dos artigos da wiki.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Wiki;

use CommonDropdown;

final class WikiCategory extends CommonDropdown
{
    public static $rightname = 'plugin_smartdocs_wiki';

    public static function getTypeName($nb = 0): string
    {
        return _n('Categoria Wiki', 'Categorias Wiki', $nb, 'smartdocs');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_wiki_categories';
    }
}
