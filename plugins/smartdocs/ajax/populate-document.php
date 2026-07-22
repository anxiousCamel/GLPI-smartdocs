<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Endpoint AJAX: popula um documento automaticamente com ativos do GLPI.
 *
 * POST {document_id, itemtype, locations_id?}
 * → {success, total_items, total_pages, assignments}
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();

use GlpiPlugin\SmartDocs\Documents\DocumentService;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

PermissionManager::checkRight(PermissionManager::SMARTDOCS_DOCUMENT_WRITE);

$input = json_decode(file_get_contents('php://input'), true);

$documentId = (int) ($input['document_id'] ?? 0);
$itemtype   = trim($input['itemtype'] ?? '');
$locationsId = $input['locations_id'] ?? null;
if ($locationsId !== null && $locationsId !== '') {
    $locationsId = (int) $locationsId;
} else {
    $locationsId = null;
}

if ($documentId <= 0 || empty($itemtype)) {
    http_response_code(422);
    echo json_encode([
        'error'   => 'VALIDATION_ERROR',
        'message' => __('Dados incompletos. Informe o documento e o tipo de ativo.', 'smartdocs'),
    ]);
    exit;
}

try {
    $service = new DocumentService();
    $result = $service->populate($documentId, [
        'itemtype'     => $itemtype,
        'locations_id' => $locationsId,
    ]);

    echo json_encode([
        'success'     => true,
        'total_items' => $result['total_items'],
        'total_pages' => $result['total_pages'],
        'assignments' => $result['assignments'],
    ]);
} catch (\RuntimeException $e) {
    http_response_code(400);
    echo json_encode([
        'error'   => 'POPULATE_ERROR',
        'message' => $e->getMessage(),
    ]);
}
