<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Interface para provedores de OCR.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\OCR\Contracts;

use GlpiPlugin\SmartDocs\OCR\OcrResult;

interface OcrProviderInterface
{
    /**
     * Processa um arquivo de imagem/PDF e retorna os candidatos extraídos.
     *
     * @param string $filePath Caminho físico do arquivo
     *
     * @return OcrResult
     */
    public function process(string $filePath): OcrResult;

    /**
     * Verifica se o provedor suporta o mime type informado.
     */
    public function supports(string $mimeType): bool;
}
