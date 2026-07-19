<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Resolve vereditos comparando resultado OCR com dados existentes no GLPI.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Equipment;

use GlpiPlugin\SmartDocs\OCR\OcrResult;

final class VerdictResolver
{
    private GlpiAssetSearch $search;

    public function __construct()
    {
        $this->search = new GlpiAssetSearch();
    }

    /**
     * Compara candidatos OCR com ativos existentes e retorna vereditos.
     *
     * @param OcrResult $ocrResult Resultado do OCR
     * @param string $itemType Tipo de ativo esperado
     *
     * @return array<int, array{type: string, value: string, confidence: float, verdict: string, matches: array}>
     */
    public function resolve(OcrResult $ocrResult, string $itemType = ''): array
    {
        $verdicts = [];

        foreach ($ocrResult->candidates as $candidate) {
            $matches = $this->search->search($candidate['value'], $itemType !== '' ? [$itemType] : []);

            $verdict = 'NEW'; // Padrão: novo ativo
            if (count($matches) > 0) {
                // Se encontrou match exato no mesmo campo → DUPLICADO
                $exactMatch = false;
                foreach ($matches as $m) {
                    if ($m['match_type'] === 'SERIAL' || $m['match_type'] === 'PATRIMONY') {
                        $exactMatch = true;
                        break;
                    }
                }
                $verdict = $exactMatch ? 'DUPLICATE' : 'SIMILAR';
            }

            $verdicts[] = [
                'type'       => $candidate['type'],
                'value'      => $candidate['value'],
                'confidence' => $candidate['confidence'],
                'verdict'    => $verdict,
                'matches'    => array_slice($matches, 0, 3),
            ];
        }

        return $verdicts;
    }
}
