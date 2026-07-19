<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Snapshot de versão de um Template PDF.
 *
 * Criado automaticamente no momento da publicação; preserva o estado
 * exato dos campos para que documentos gerados posteriormente possam
 * referenciar a versão usada.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

use CommonDBTM;

final class PdfTemplateVersion extends CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return _n('Versão do template', 'Versões do template', $nb, 'smartdocs');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_pdf_template_versions';
    }

    /**
     * Busca uma versão específica de um template.
     */
    public function findByTemplateAndVersion(int $templateId, int $version): ?array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'pdf_templates_id' => $templateId,
                'version'          => $version,
            ],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Decodifica o snapshot de campos.
     */
    public function getFieldsSnapshot(): array
    {
        $raw = $this->fields['fields_snapshot'] ?? '[]';
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
