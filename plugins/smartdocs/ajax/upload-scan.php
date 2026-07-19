<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Endpoint AJAX para upload de imagem/PDF e execução de OCR.
 *
 * POST multipart/form-data:
 *   - scan: arquivo (imagem ou PDF)
 *   - type_hint: (opcional) 'serial' | 'patrimonio' | 'modelo'
 *
 * Resposta JSON:
 *   {
 *     "success": true,
 *     "candidates": [...],
 *     "raw_text": "..."
 *   }
 * ---------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\OCR\OcrService;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

include_once dirname(__DIR__) . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight(PermissionManager::RIGHT_NAME, PermissionManager::SMARTDOCS_OCR_USE);

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_FILES['scan']) || $_FILES['scan']['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException(
            'Nenhum arquivo enviado ou erro no upload: ' .
            ($_FILES['scan']['error'] ?? 'desconhecido')
        );
    }

    $upload = $_FILES['scan'];
    $tmpPath = $upload['tmp_name'];
    $origName = $upload['name'];

    // Validação básica de mime type
    $allowedMimes = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/tiff',
        'image/bmp',
        'application/pdf',
    ];

    $mimeType = mime_content_type($tmpPath) ?: $upload['type'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new \RuntimeException('Tipo de arquivo não permitido: ' . $mimeType);
    }

    // Move para pasta temporária do plugin com nome seguro
    $tmpDir = GLPI_PLUGIN_DOC_DIR . '/smartdocs/tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }

    $safeName = sprintf(
        '%s_%s_%s',
        Session::getLoginUserID(),
        time(),
        preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName)
    );
    $targetPath = $tmpDir . '/' . $safeName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new \RuntimeException('Falha ao mover arquivo temporário.');
    }

    // Executa OCR
    $service = new OcrService();
    $result = $service->process($targetPath);

    // Remove arquivo temporário
    @unlink($targetPath);

    // Filtra por type_hint se informado
    $candidates = $result->candidates;
    $typeHint = $_POST['type_hint'] ?? '';
    if ($typeHint !== '' && in_array($typeHint, ['serial', 'patrimonio', 'modelo'], true)) {
        $candidates = array_values(array_filter(
            $candidates,
            static fn(array $c): bool => $c['type'] === $typeHint
        ));
    }

    echo json_encode([
        'success'    => true,
        'candidates' => $candidates,
        'raw_text'   => $result->rawText,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
