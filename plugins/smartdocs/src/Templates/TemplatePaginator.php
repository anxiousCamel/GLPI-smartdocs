<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Paginator de templates: deriva paginação a partir dos slot_index
 * configurados nos campos do template (porta do template-paginator.ts).
 *
 * Regras de validação aplicadas no publish do template.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

final class TemplatePaginator
{
    /**
     * Conta quantos slots distintos existem entre campos de escopo 'item'.
     * Retorna 0 se não houver campos de item (apenas globais).
     */
    public static function itemsPerPage(array $fields): int
    {
        $slots = [];
        foreach ($fields as $field) {
            if (($field['scope'] ?? 'global') === 'item' && isset($field['slot_index'])) {
                $slots[(int) $field['slot_index']] = true;
            }
        }

        return count($slots);
    }

    /**
     * Empacota assignments sequencialmente.
     *
     * @param array $fields     Campos do template (usados para calcular itemsPerPage)
     * @param int   $totalItems Quantidade total de equipamentos/documentos
     *
     * @return array{itemsPerPage:int,totalPages:int,assignments:array<int,array{pageOrdinal:int,slotIndex:int,itemIndex:int}>}
     *
     * @throws \RuntimeException Se totalItems > 0 mas não há slots configurados
     */
    public static function compute(array $fields, int $totalItems): array
    {
        $itemsPerPage = self::itemsPerPage($fields);

        if ($totalItems > 0 && $itemsPerPage === 0) {
            throw new \RuntimeException(
                __('Template sem slots de equipamento configurados. Adicione campos "Por equipamento".', 'smartdocs')
            );
        }

        if ($totalItems <= 0 || $itemsPerPage === 0) {
            return [
                'itemsPerPage' => $itemsPerPage,
                'totalPages'   => 0,
                'assignments'  => [],
            ];
        }

        $totalPages   = (int) ceil($totalItems / $itemsPerPage);
        $assignments  = [];

        for ($itemIndex = 0; $itemIndex < $totalItems; $itemIndex++) {
            $pageOrdinal = (int) floor($itemIndex / $itemsPerPage);
            $slotIndex   = $itemIndex % $itemsPerPage;

            $assignments[$itemIndex] = [
                'pageOrdinal' => $pageOrdinal,
                'slotIndex'   => $slotIndex,
                'itemIndex'   => $itemIndex,
            ];
        }

        return [
            'itemsPerPage' => $itemsPerPage,
            'totalPages'   => $totalPages,
            'assignments'  => $assignments,
        ];
    }

    /**
     * Valida consistência dos campos do template.
     *
     * @return array<string> Lista de mensagens de erro (vazia = ok)
     */
    public static function validate(array $fields): array
    {
        $errors = [];
        $itemSlots = [];

        foreach ($fields as $index => $field) {
            $scope      = $field['scope'] ?? 'global';
            $slotIndex  = $field['slot_index'] ?? null;
            $label      = $field['label'] ?? "campo #{$index}";

            if ($scope === 'global' && $slotIndex !== null) {
                $errors[] = sprintf(
                    /* TRANS: %1$s = field label */
                    __('Campo "%1$s" é global mas possui slot_index (%2$d). Campos globais não devem ter slot.', 'smartdocs'),
                    $label,
                    (int) $slotIndex
                );
            }

            if ($scope === 'item') {
                if ($slotIndex === null) {
                    $errors[] = sprintf(
                        /* TRANS: %1$s = field label */
                        __('Campo "%1$s" é por equipamento mas não possui slot_index.', 'smartdocs'),
                        $label
                    );
                } else {
                    $itemSlots[] = (int) $slotIndex;
                }
            }
        }

        // Verifica contiguidade 0..N-1
        if ($itemSlots !== []) {
            $uniqueSlots = array_unique($itemSlots);
            sort($uniqueSlots);

            $expected = range(0, count($uniqueSlots) - 1);
            if ($uniqueSlots !== $expected) {
                $errors[] = sprintf(
                    /* TRANS: %1$s = lista esperada, %2$s = lista obtida */
                    __('slot_index de campos por equipamento devem ser contíguos (esperado: %1$s; obtido: %2$s).', 'smartdocs'),
                    implode(', ', $expected),
                    implode(', ', $uniqueSlots)
                );
            }
        }

        return $errors;
    }
}
