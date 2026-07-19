<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * DTO com os resultados de uma operação OCR.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\OCR;

final class OcrResult
{
    /**
     * @param array<int, array{type: string, value: string, confidence: float}> $candidates
     * @param string $rawText Texto bruto reconhecido
     */
    public function __construct(
        public array $candidates = [],
        public string $rawText = ''
    ) {
    }

    /**
     * Adiciona um candidato tipado.
     */
    public function addCandidate(string $type, string $value, float $confidence): void
    {
        $this->candidates[] = [
            'type'       => $type,
            'value'      => $value,
            'confidence' => $confidence,
        ];
    }

    /**
     * Ordena candidatos por confiança descendente.
     */
    public function sortByConfidence(): void
    {
        usort($this->candidates, static function (array $a, array $b): int {
            return $b['confidence'] <=> $a['confidence'];
        });
    }
}
