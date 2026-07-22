<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Clonagem de campos: replica campos do template para cada slot de item
 * usando a paginação por slot_index definida no template.
 *
 * Campos globais aparecem uma vez por página gerada.
 * Campos de item aparecem apenas no slot correspondente.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\PdfEngine;

use GlpiPlugin\SmartDocs\Templates\TemplatePaginator;

final class FieldCloner
{
    /**
     * Clona campos base para cada item usando slot_index do template.
     *
     * @param array $baseFields Campos do template (com position JSON, scope, slot_index)
     * @param int   $totalItems Quantidade total de itens
     *
     * @return array<int, array> Campos clonados com computedPageIndex e posições
     */
    public function cloneForItems(array $baseFields, int $totalItems): array
    {
        if ($totalItems <= 0) {
            return $baseFields;
        }

        $itemsPerPage = TemplatePaginator::itemsPerPage($baseFields);

        // Sem slots de item: comportamento legado (1 item = baseFields)
        if ($itemsPerPage === 0) {
            if ($totalItems <= 1) {
                return $baseFields;
            }
            throw new \RuntimeException(
                __('Template sem slots de equipamento configurados.', 'smartdocs')
            );
        }

        $paginator = TemplatePaginator::compute($baseFields, $totalItems);
        $clonedFields = [];

        foreach ($paginator['assignments'] as $assignment) {
            $pageOrdinal = $assignment['pageOrdinal'];
            $slotIndex   = $assignment['slotIndex'];
            $itemIndex   = $assignment['itemIndex'];

            foreach ($baseFields as $field) {
                $scope = $field['scope'] ?? 'global';

                // Campos globais: uma vez por página gerada
                if ($scope === 'global') {
                    // Só inclui o campo global na primeira assignment de cada página
                    // para evitar duplicatas dentro da mesma página
                    $firstSlotOfPage = $slotIndex === 0;
                    if (!$firstSlotOfPage) {
                        continue;
                    }

                    $newField = $field;
                    $newField['item_index']        = 0;
                    $newField['computedPageIndex'] = $pageOrdinal + (int) ($field['page_index'] ?? 0);
                    $newField['computedPosition']  = $this->decodePosition($field['position']);
                    $clonedFields[] = $newField;
                    continue;
                }

                // Campos de item: só entram se o slot_index bate
                $fieldSlot = $field['slot_index'] ?? null;
                if ($fieldSlot === null || (int) $fieldSlot !== $slotIndex) {
                    continue;
                }

                $newField = $field;
                $newField['item_index']        = $itemIndex;
                $newField['computedPageIndex'] = $pageOrdinal + (int) ($field['page_index'] ?? 0);
                $newField['computedPosition']  = $this->decodePosition($field['position']);
                $clonedFields[] = $newField;
            }
        }

        return $clonedFields;
    }

    /**
     * Decodifica a posição JSON do campo para array.
     *
     * @return array{x:float,y:float,width:float,height:float}
     */
    private function decodePosition(mixed $position): array
    {
        $decoded = [];
        if (is_string($position)) {
            $decoded = json_decode($position, true);
        } elseif (is_array($position)) {
            $decoded = $position;
        }

        if (!is_array($decoded)) {
            $decoded = [];
        }

        return [
            'x'      => (float) ($decoded['x'] ?? 0),
            'y'      => (float) ($decoded['y'] ?? 0),
            'width'  => (float) ($decoded['width'] ?? 0),
            'height' => (float) ($decoded['height'] ?? 0),
        ];
    }
}
