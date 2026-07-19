<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Seleciona um ativo GLPI para um item do documento e retorna
 * os campos com binding keys preenchidos automaticamente.
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
$itemIndex  = (int) ($input['item_index'] ?? 0);
$itemtype   = $input['itemtype'] ?? '';
$itemsId    = (int) ($input['items_id'] ?? 0);

if ($documentId <= 0 || $itemsId <= 0 || empty($itemtype)) {
    http_response_code(422);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'Dados incompletos.']);
    exit;
}

if (!class_exists($itemtype) || !is_subclass_of($itemtype, 'CommonDBTM')) {
    http_response_code(422);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'Tipo de ativo inválido.']);
    exit;
}

try {
    $service = new GlpiPlugin\SmartDocs\Documents\DocumentService();
    $filled = $service->selectAsset($documentId, $itemIndex, $itemtype, $itemsId);

    echo json_encode([
        'success' => true,
        'filled'  => $filled,
    ]);
} catch (\RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_STATUS', 'message' => $e->getMessage()]);
}
