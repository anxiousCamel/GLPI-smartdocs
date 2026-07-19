<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * TechnicalFile: arquivo técnico (manual, POP, contrato, garantia)
 * vinculado a objetos do GLPI.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Library;

use CommonDBTM;

final class TechnicalFile extends CommonDBTM
{
    public static $rightname = 'plugin_smartdocs_library';

    public static function getTypeName($nb = 0): string
    {
        return _n('Arquivo Técnico', 'Arquivos Técnicos', $nb, 'smartdocs');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_technical_files';
    }

    public function prepareInputForAdd($input): array
    {
        $input = parent::prepareInputForAdd($input);
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        return $input;
    }
}
