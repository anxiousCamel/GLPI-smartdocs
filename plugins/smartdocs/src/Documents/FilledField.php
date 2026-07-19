<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Modelo de campo preenchido em um Documento PDF.
 *
 * Cada registro representa o valor preenchido para um campo de
 * template específico, podendo ser por item (item_index) ou global.
 * -----------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

use CommonDBTM;

final class FilledField extends CommonDBTM
{
    public static function getTypeName($nb = 0): string
    {
        return _n('Campo preenchido', 'Campos preenchidos', $nb, 'smartdocs');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_pdf_filled_fields';
    }

    /**
     * Valida o input antes da adição.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        if (empty($input['pdf_documents_id']) || empty($input['pdf_template_fields_id'])) {
            \Session::addMessageAfterRedirect(
                __('Documento e campo do template são obrigatórios.', 'smartdocs'),
                false,
                ERROR
            );
            return false;
        }

        $input['item_index'] = isset($input['item_index']) ? (int) $input['item_index'] : 0;

        return $input;
    }
}
