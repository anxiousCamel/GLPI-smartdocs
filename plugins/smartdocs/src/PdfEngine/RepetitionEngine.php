<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Motor de repetição: calcula layout de grade para N itens em páginas.
 *
 * Porta 1:1 do TypeScript do RegCheck.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\PdfEngine;

final class RepetitionEngine
{
    /**
     * Calcula o layout de grade para distribuir N itens em páginas.
     *
     * @param int   $totalItems Quantidade total de equipamentos/itens
     * @param array $config     Configuração da grade:
     *                          - rows: int (padrão 1)
     *                          - columns: int (padrão 1)
     *                          - itemsPerPage: int (padrão 1)
     *                          - offsetX: float (padrão 0.0)
     *                          - offsetY: float (padrão 0.0)
     *                          - startX: float (padrão 0.0)
     *                          - startY: float (padrão 0.0)
     *
     * @return array{
     *     totalPages: int,
     *     pageItems: array<int, array{
     *         itemIndex: int,
     *         pageIndex: int,
     *         offsetX: float,
     *         offsetY: float
     *     }>
     * }
     */
    public function computeLayout(int $totalItems, array $config = []): array
    {
        $rows          = (int) ($config['rows'] ?? 1);
        $columns       = (int) ($config['columns'] ?? 1);
        $itemsPerPage  = (int) ($config['itemsPerPage'] ?? ($rows * $columns));
        $offsetX       = (float) ($config['offsetX'] ?? 0.0);
        $offsetY       = (float) ($config['offsetY'] ?? 0.0);
        $startX        = (float) ($config['startX'] ?? 0.0);
        $startY        = (float) ($config['startY'] ?? 0.0);

        $totalPages = (int) ceil($totalItems / max(1, $itemsPerPage));
        $pageItems  = [];

        for ($itemIndex = 0; $itemIndex < $totalItems; $itemIndex++) {
            $pageIndex = (int) floor($itemIndex / max(1, $itemsPerPage));
            $slotIndex = $itemIndex % max(1, $itemsPerPage);
            $rowIndex  = (int) floor($slotIndex / max(1, $columns));
            $colIndex  = $slotIndex % max(1, $columns);

            $pageItems[] = [
                'itemIndex' => $itemIndex,
                'pageIndex' => $pageIndex,
                'offsetX'   => $startX + ($colIndex * $offsetX),
                'offsetY'   => $startY + ($rowIndex * $offsetY),
            ];
        }

        return [
            'totalPages' => $totalPages,
            'pageItems'  => $pageItems,
        ];
    }
}
