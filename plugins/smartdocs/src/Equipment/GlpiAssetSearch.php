<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Busca multi-fallback de ativos GLPI com cascata de 8 estratégias.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Equipment;

use DBmysql;

final class GlpiAssetSearch
{
    private DBmysql $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Executa busca multi-fallback por serial, patrimônio ou nome.
     *
     * @param string $query Termo de busca
     * @param array<int, string> $itemTypes Tipos de ativo (default: todos)
     *
     * @return array<int, array{id: int, itemtype: string, name: string, serial: ?string, otherserial: ?string, match_type: string}>
     */
    public function search(string $query, array $itemTypes = []): array
    {
        if ($itemTypes === []) {
            $itemTypes = [
                'Computer',
                'Peripheral',
                'Printer',
                'Monitor',
                'NetworkEquipment',
                'Phone',
            ];
        }

        $normalizedQuery = $this->normalize($query);
        $results = [];
        $seen = [];

        foreach ($itemTypes as $itemType) {
            $table = \getTableForItemType($itemType);
            if (!$this->db->tableExists($table)) {
                continue;
            }

            $strategies = [
                ['field' => 'serial', 'match' => 'SERIAL'],
                ['field' => 'serial', 'match' => 'SERIAL_LIKE', 'like' => true],
                ['field' => 'otherserial', 'match' => 'PATRIMONY', 'patrimony' => true],
                ['field' => 'otherserial', 'match' => 'SERIAL_FALLBACK'],
                ['field' => 'serial', 'match' => 'PATRIMONY_FALLBACK', 'patrimony' => true],
                ['field' => 'name', 'match' => 'NAME'],
                ['field' => 'name', 'match' => 'NAME_FALLBACK'],
            ];

            foreach ($strategies as $strategy) {
                $rows = $this->executeStrategy($table, $itemType, $normalizedQuery, $strategy);
                foreach ($rows as $row) {
                    $key = $row['itemtype'] . ':' . $row['id'];
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $results[] = $row;
                }
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $strategy
     *
     * @return array<int, array<string, mixed>>
     */
    private function executeStrategy(string $table, string $itemType, string $query, array $strategy): array
    {
        $field = $strategy['field'];
        $matchType = $strategy['match'];
        $useLike = $strategy['like'] ?? false;
        $isPatrimony = $strategy['patrimony'] ?? false;

        $criteria = [
            'SELECT' => ['id', 'name', 'serial', 'otherserial'],
            'FROM'   => $table,
            'WHERE'  => [
                'is_deleted' => 0,
            ],
            'LIMIT'  => 20,
        ];

        if ($useLike) {
            $criteria['WHERE'][] = [$field => ['LIKE', '%' . $query . '%']];
        } elseif ($isPatrimony) {
            $variants = $this->patrimonyVariants($query);
            $criteria['WHERE'][] = [$field => $variants];
        } else {
            $criteria['WHERE'][] = [$field => $query];
        }

        $iterator = $this->db->request($criteria);
        $rows = [];

        foreach ($iterator as $data) {
            $rows[] = [
                'id'          => (int) $data['id'],
                'itemtype'    => $itemType,
                'name'        => (string) $data['name'],
                'serial'      => $data['serial'] ?: null,
                'otherserial' => $data['otherserial'] ?: null,
                'match_type'  => $matchType,
            ];
        }

        return $rows;
    }

    /**
     * Gera variantes de busca para patrimônio (com e sem zeros à esquerda).
     *
     * @return array<int, string>
     */
    private function patrimonyVariants(string $query): array
    {
        $variants = [$query];
        $numeric = ltrim($query, '0');
        if ($numeric !== '' && $numeric !== $query) {
            $variants[] = $numeric;
        }
        if (ctype_digit($numeric)) {
            $variants[] = str_pad($numeric, 6, '0', STR_PAD_LEFT);
            $variants[] = str_pad($numeric, 8, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique($variants));
    }

    private function normalize(string $query): string
    {
        return trim($query);
    }
}
