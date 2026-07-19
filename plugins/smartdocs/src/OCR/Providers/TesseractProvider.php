<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Provedor OCR usando Tesseract instalado no servidor.
 * Usa thiagoalessio/tesseract_ocr como wrapper PHP.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\OCR\Providers;

use GlpiPlugin\SmartDocs\OCR\Contracts\OcrProviderInterface;
use GlpiPlugin\SmartDocs\OCR\OcrResult;
use thiagoalessio\TesseractOCR\TesseractOCR;

final class TesseractProvider implements OcrProviderInterface
{
    private string $lang;

    public function __construct(string $lang = 'eng+por')
    {
        $this->lang = $lang;
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/tiff',
            'image/bmp',
            'application/pdf',
        ], true);
    }

    public function process(string $filePath): OcrResult
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Arquivo não encontrado: ' . $filePath);
        }

        $ocr = new TesseractOCR($filePath);
        $ocr->lang(...explode('+', $this->lang));

        $rawText = $ocr->run();

        $result = new OcrResult(rawText: $rawText);
        $this->parseCandidates($rawText, $result);
        $result->sortByConfidence();

        return $result;
    }

    /**
     * Extrai candidatos tipados do texto bruto.
     */
    private function parseCandidates(string $rawText, OcrResult $result): void
    {
        $lines = explode("\n", $rawText);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Serial: padrões como ABC123456, 5CD1234XYZ
            if (preg_match('/[A-Z0-9]{6,20}/i', $line, $matches)) {
                $result->addCandidate('serial', $matches[0], 0.75);
            }

            // Patrimônio: números com 4-8 dígitos, possivelmente com zeros à esquerda
            if (preg_match('/\b0*\d{4,8}\b/', $line, $matches)) {
                $result->addCandidate('patrimonio', ltrim($matches[0], '0') ?: $matches[0], 0.80);
            }

            // Modelo: linhas que parecem nomes de produto (contêm letras e números)
            if (preg_match('/[A-Z]+\s*\d+[A-Z]*/i', $line, $matches) && strlen($line) > 3) {
                $result->addCandidate('modelo', $line, 0.60);
            }
        }

        // Deduplica candidatos por type:value (mantém maior confiança)
        $seen = [];
        $deduped = [];
        foreach ($result->candidates as $c) {
            $key = $c['type'] . ':' . $c['value'];
            if (!isset($seen[$key]) || $seen[$key] < $c['confidence']) {
                $seen[$key] = $c['confidence'];
                $deduped[$key] = $c;
            }
        }
        $result->candidates = array_values($deduped);
    }
}
