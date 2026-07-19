<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Detecta duplicidade de ativos por serial, patrimônio ou nome.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Equipment;

final class DuplicateDetector
{
    private GlpiAssetSearch $search;

    public function __construct()
    {
        $this->search = new GlpiAssetSearch();
    }

    /**
     * Verifica se existe ativo duplicado com base nos dados informados.
     *
     * @param array<string, string|null> $fields Campos do ativo (serial, otherserial, name)
     * @param string $currentItemType Tipo do item sendo cadastrado/editado
     * @param int $excludeId ID a excluir da busca (edição)
     *
     * @return array{is_duplicate: bool, matches: array<int, array<string, mixed>>}
     */
    public function check(array $fields, string $currentItemType = '', int $excludeId = 0): array
    {
        $queries = [];

        if (!empty($fields['serial'])) {
            $queries[] = $fields['serial'];
        }
        if (!empty($fields['otherserial'])) {
            $queries[] = $fields['otherserial'];
        }
        if (!empty($fields['name'])) {
            $queries[] = $fields['name'];
        }

        $queries = array_values(array_unique($queries));
        $allMatches = [];
        $seen = [];

        foreach ($queries as $query) {
            $results = $this->search->search($query, $currentItemType !== '' ? [$currentItemType] : []);
            foreach ($results as $r) {
                if ($excludeId > 0 && $r['id'] === $excludeId) {
                    continue;
                }
                $key = $r['itemtype'] . ':' . $r['id'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $allMatches[] = $r;
            }
        }

        return [
            'is_duplicate' => count($allMatches) > 0,
            'matches'      => $allMatches,
        ];
    }
}
