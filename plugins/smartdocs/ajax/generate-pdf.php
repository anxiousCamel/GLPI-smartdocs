<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Enfileira a geração do PDF de um documento.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
);

$input = json_decode(file_get_contents('php://input'), true);

$documentId = (int) ($input['document_id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'ID do documento não informado.']);
    exit;
}

try {
    $service = new GlpiPlugin\SmartDocs\Documents\DocumentService();
    $jobId = $service->requestGeneration($documentId);

    echo json_encode([
        'success' => true,
        'job_id'  => $jobId,
    ]);
} catch (\RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_STATUS', 'message' => $e->getMessage()]);
}
