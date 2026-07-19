<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Endpoint AJAX para verificação de duplicidade de ativos.
 *
 * POST JSON:
 *   {
 *     "itemtype": "Computer",
 *     "fields": { "serial": "ABC123", "otherserial": "0042", "name": "PC-SPO-0042" },
 *     "exclude_id": 0
 *   }
 *
 * Resposta:
 *   { "is_duplicate": true, "matches": [...] }
 * ----------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\Equipment\DuplicateDetector;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

include_once dirname(__DIR__) . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight(PermissionManager::RIGHT_NAME, PermissionManager::SMARTDOCS_OCR_USE);

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['fields'])) {
        throw new \RuntimeException('Payload inválido: "fields" obrigatório.');
    }

    $fields = array_filter($input['fields'], static fn($v) => $v !== null && $v !== '');
    $itemType = isset($input['itemtype']) ? (string) $input['itemtype'] : '';
    $excludeId = isset($input['exclude_id']) ? (int) $input['exclude_id'] : 0;

    $detector = new DuplicateDetector();
    $result = $detector->check($fields, $itemType, $excludeId);

    echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'is_duplicate' => false,
        'error'        => $e->getMessage(),
        'matches'      => [],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
