<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Smart Repopulate — algoritmo de diff entre assignments ativos do
 * documento e a lista atual de ativos do GLPI.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

final class SmartRepopulate
{
    /**
     * Calcula diff entre assignments ativos e ativos GLPI.
     *
     * @param array<int, array<string, mixed>> $assignments Lista de assignments ativos
     * @param array<int, array<string, mixed>> $glpiAssets Lista de ativos do GLPI
     *
     * @return array{
     *   keptUnchanged: array,
     *   keptWithChanges: array,
     *   added: array,
     *   removed: array,
     * }
     */
    public function computeDiff(array $assignments, array $glpiAssets): array
    {
        $keptUnchanged = [];
        $keptWithChanges = [];
        $added = [];
        $removed = [];

        $assetMap = [];
        foreach ($glpiAssets as $asset) {
            $key = $asset['itemtype'] . ':' . $asset['id'];
            $assetMap[$key] = $asset;
        }

        $assignmentMap = [];
        foreach ($assignments as $assignment) {
            $key = $assignment['itemtype'] . ':' . $assignment['items_id'];
            $assignmentMap[$key] = $assignment;
        }

        // Processa ativos GLPI
        foreach ($glpiAssets as $asset) {
            $key = $asset['itemtype'] . ':' . $asset['id'];

            if (isset($assignmentMap[$key])) {
                $assignment = $assignmentMap[$key];
                $changes = $this->compareFields($assignment, $asset);

                if (empty($changes)) {
                    $keptUnchanged[] = [
                        'assignment_id' => $assignment['id'],
                        'itemtype'      => $asset['itemtype'],
                        'items_id'      => $asset['id'],
                        'item_index'    => $assignment['item_index'],
                    ];
                } else {
                    $keptWithChanges[] = [
                        'assignment_id' => $assignment['id'],
                        'itemtype'      => $asset['itemtype'],
                        'items_id'      => $asset['id'],
                        'item_index'    => $assignment['item_index'],
                        'changes'       => $changes,
                    ];
                }
            } else {
                $added[] = [
                    'itemtype'       => $asset['itemtype'],
                    'items_id'       => $asset['id'],
                    'is_reactivation' => isset($asset['removed_assignment_id']),
                    'original_index'  => $asset['removed_assignment_id'] ?? null,
                ];
            }
        }

        // Processa assignments sem correspondência no GLPI → removidos
        foreach ($assignments as $assignment) {
            $key = $assignment['itemtype'] . ':' . $assignment['items_id'];
            if (!isset($assetMap[$key])) {
                $removed[] = [
                    'assignment_id' => $assignment['id'],
                    'itemtype'      => $assignment['itemtype'],
                    'items_id'      => $assignment['items_id'],
                    'item_index'    => $assignment['item_index'],
                ];
            }
        }

        return [
            'keptUnchanged'   => $keptUnchanged,
            'keptWithChanges' => $keptWithChanges,
            'added'           => $added,
            'removed'         => $removed,
        ];
    }

    /**
     * Compara campos binding entre assignment e ativo GLPI.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    private function compareFields(array $assignment, array $asset): array
    {
        $changes = [];
        $bindingFields = ['serial', 'otherserial', 'name', 'model'];

        foreach ($bindingFields as $field) {
            $old = $assignment[$field] ?? null;
            $new = $asset[$field] ?? null;
            if ($old !== $new) {
                $changes[] = [
                    'field' => $field,
                    'old'   => $old,
                    'new'   => $new,
                ];
            }
        }

        return $changes;
    }
}
