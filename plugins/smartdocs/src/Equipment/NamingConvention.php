<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Nomenclatura V5.0 — gera nomes padronizados para ativos.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Equipment;

final class NamingConvention
{
    /**
     * Gera o nome do ativo conforme a convenção V5.0.
     *
     * Padrão: {TIPO_ABREV}-{LOCAL}-{SEQUENCIAL}
     * Exemplos: PC-SPO-0042, MON-RJ-0103, IMP-MG-0007
     *
     * @param string $itemType Tipo do ativo (Computer, Printer, etc.)
     * @param string $location Local/filial (ex: São Paulo → SPO)
     * @param int $sequence Número sequencial
     *
     * @return string
     */
    public static function generate(string $itemType, string $location, int $sequence): string
    {
        $typeAbbr = self::abbreviateType($itemType);
        $locAbbr = self::abbreviateLocation($location);
        $seq = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        return sprintf('%s-%s-%s', $typeAbbr, $locAbbr, $seq);
    }

    /**
     * Abrevia o tipo de ativo.
     */
    public static function abbreviateType(string $itemType): string
    {
        $map = [
            'Computer'         => 'PC',
            'Monitor'          => 'MON',
            'Printer'          => 'IMP',
            'Peripheral'       => 'PER',
            'NetworkEquipment' => 'NET',
            'Phone'            => 'TEL',
        ];

        return $map[$itemType] ?? strtoupper(substr($itemType, 0, 3));
    }

    /**
     * Abrevia o local para 3 caracteres.
     */
    public static function abbreviateLocation(string $location): string
    {
        $clean = preg_replace('/[^A-Za-z]/', '', $location);
        $abbr = strtoupper(substr($clean, 0, 3));

        return $abbr !== '' ? $abbr : 'XXX';
    }

    /**
     * Extrai o próximo número sequencial para um tipo+local.
     *
     * @param string $itemType
     * @param string $location
     *
     * @return int
     */
    public static function nextSequence(string $itemType, string $location): int
    {
        global $DB;

        $prefix = self::abbreviateType($itemType) . '-' . self::abbreviateLocation($location) . '-';
        $table = \getTableForItemType($itemType);

        if (!$DB->tableExists($table)) {
            return 1;
        }

        $iterator = $DB->request([
            'SELECT' => ['name'],
            'FROM'   => $table,
            'WHERE'  => [
                'name' => ['LIKE', $prefix . '%'],
            ],
        ]);

        $max = 0;
        foreach ($iterator as $row) {
            $suffix = substr((string) $row['name'], strlen($prefix));
            $num = (int) $suffix;
            if ($num > $max) {
                $max = $num;
            }
        }

        return $max + 1;
    }
}
