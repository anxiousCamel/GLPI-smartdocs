<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Endpoint AJAX: adiciona um novo item/equipamento ao documento (modo Único).
 * ---------------------------------------------------------------------
 */

ob_start();

include('../../../inc/includes.php');

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$documentId = (int) ($input['document_id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'ID do documento não informado.']);
    exit;
}

$doc = new GlpiPlugin\SmartDocs\Documents\PdfDocument();
if (!$doc->getFromDB($documentId)) {
    http_response_code(404);
    echo json_encode(['error' => 'NOT_FOUND', 'message' => 'Documento não encontrado.']);
    exit;
}

$newTotal = ((int) $doc->fields['total_items']) + 1;
$doc->update([
    'id'          => $documentId,
    'total_items' => $newTotal,
]);

echo json_encode([
    'success'     => true,
    'total_items' => $newTotal,
]);
