<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Clonagem de campos: replica campos do template para cada slot de item.
 *
 * Porta 1:1 do TypeScript do RegCheck.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\PdfEngine;

final class FieldCloner
{
    /**
     * Clona campos base para cada item, ajustando posições.
     *
     * @param array $baseFields   Campos do template (com position JSON)
     * @param int   $totalItems   Quantidade total de itens
     * @param array $config       Configuração da repetição (passada para RepetitionEngine)
     *
     * @return array<int, array> Campos clonados com computedPageIndex e posições ajustadas
     */
    public function cloneForItems(array $baseFields, int $totalItems, array $config = []): array
    {
        if ($totalItems <= 1) {
            return $baseFields;
        }

        $engine = new RepetitionEngine();
        $layout = $engine->computeLayout($totalItems, $config);

        $clonedFields = [];

        foreach ($layout['pageItems'] as $slot) {
            $itemIndex = $slot['itemIndex'];
            $pageIndex = $slot['pageIndex'];
            $offsetX   = $slot['offsetX'];
            $offsetY   = $slot['offsetY'];

            foreach ($baseFields as $field) {
                $position = json_decode($field['position'] ?? '{}', true);
                if (!is_array($position)) {
                    $position = [];
                }

                $newField = $field;
                $newField['item_index']       = $itemIndex;
                $newField['computedPageIndex'] = $pageIndex + (int) ($field['page_index'] ?? 0);
                $newField['computedPosition'] = [
                    'x'      => ($position['x'] ?? 0) + $offsetX,
                    'y'      => ($position['y'] ?? 0) + $offsetY,
                    'width'  => $position['width'] ?? 0,
                    'height' => $position['height'] ?? 0,
                ];

                $clonedFields[] = $newField;
            }
        }

        return $clonedFields;
    }
}
