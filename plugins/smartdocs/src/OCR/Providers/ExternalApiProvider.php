<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Provedor OCR usando API REST externa configurável.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\OCR\Providers;

use GlpiPlugin\SmartDocs\OCR\Contracts\OcrProviderInterface;
use GlpiPlugin\SmartDocs\OCR\OcrResult;

final class ExternalApiProvider implements OcrProviderInterface
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function supports(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf';
    }

    public function process(string $filePath): OcrResult
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Arquivo não encontrado: ' . $filePath);
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileName = basename($filePath);

        $postData = [
            'file' => new \CURLFile($filePath, $mimeType, $fileName),
        ];

        $ch = curl_init($this->apiUrl . '/ocr');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \RuntimeException('Erro na requisição OCR: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('API OCR retornou HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Resposta inválida da API OCR');
        }

        $result = new OcrResult(
            candidates: $data['candidates'] ?? [],
            rawText: $data['raw_text'] ?? '',
        );
        $result->sortByConfidence();

        return $result;
    }
}
