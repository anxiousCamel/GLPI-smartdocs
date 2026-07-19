<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Fachada OCR: seleciona o provedor ativo conforme configuração do plugin
 * e expõe uma API única para digitalização de ativos.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\OCR;

use GlpiPlugin\SmartDocs\OCR\Contracts\OcrProviderInterface;
use GlpiPlugin\SmartDocs\OCR\Providers\ExternalApiProvider;
use GlpiPlugin\SmartDocs\OCR\Providers\TesseractProvider;

final class OcrService
{
    private OcrProviderInterface $provider;

    public function __construct()
    {
        $this->provider = self::buildProvider();
    }

    /**
     * Processa um arquivo de imagem/PDF e retorna os candidatos OCR.
     *
     * @param string $filePath Caminho físico do arquivo
     *
     * @return OcrResult
     */
    public function process(string $filePath): OcrResult
    {
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        if (!$this->provider->supports($mimeType)) {
            throw new \RuntimeException(
                sprintf('Tipo de arquivo não suportado pelo OCR: %s', $mimeType)
            );
        }

        return $this->provider->process($filePath);
    }

    /**
     * Constrói o provedor ativo com base na configuração do plugin.
     */
    public static function buildProvider(): OcrProviderInterface
    {
        $provider = \Config::getOption('smartdocs_ocr_provider', 'tesseract');

        if ($provider === 'external_api') {
            $apiUrl = \Config::getOption('smartdocs_ocr_api_url', '');
            $apiKey = \Config::getOption('smartdocs_ocr_api_key', '');

            if ($apiUrl === '' || $apiKey === '') {
                throw new \RuntimeException(
                    'Configuração de API OCR externa incompleta (URL ou chave vazia).'
                );
            }

            return new ExternalApiProvider($apiUrl, $apiKey);
        }

        // Padrão: Tesseract local
        $lang = \Config::getOption('smartdocs_ocr_lang', 'eng+por');

        return new TesseractProvider($lang);
    }
}
