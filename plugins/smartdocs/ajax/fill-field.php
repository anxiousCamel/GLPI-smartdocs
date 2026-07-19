<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Salva o valor preenchido de um campo do documento.
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
$fieldId    = (int) ($input['field_id'] ?? 0);
$itemIndex  = (int) ($input['item_index'] ?? 0);
$value      = $input['value'] ?? null;

if ($documentId <= 0 || $fieldId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'IDs obrigatórios não informados.']);
    exit;
}

$doc = new GlpiPlugin\SmartDocs\Documents\PdfDocument();
if (!$doc->getFromDB($documentId)) {
    http_response_code(404);
    echo json_encode(['error' => 'NOT_FOUND', 'message' => 'Documento não encontrado.']);
    exit;
}

// Só permite preenchimento em DRAFT ou IN_PROGRESS
$allowedStatuses = [
    GlpiPlugin\SmartDocs\Documents\PdfDocument::STATUS_DRAFT,
    GlpiPlugin\SmartDocs\Documents\PdfDocument::STATUS_IN_PROGRESS,
];

if (!in_array($doc->fields['status'], $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        'error'   => 'INVALID_STATUS',
        'message' => __('Documento não pode ser editado no status atual.', 'smartdocs'),
    ]);
    exit;
}

$service = new GlpiPlugin\SmartDocs\Documents\DocumentService();
$service->fillField($documentId, $fieldId, $itemIndex, $value);

echo json_encode(['success' => true, 'saved_at' => date('c')]);
