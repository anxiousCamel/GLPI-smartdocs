<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Busca multi-fallback em ativos GLPI (Computer, Printer, etc.)
 * por nome, serial ou patrimônio.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_READ
);

$query    = $_GET['q'] ?? '';
$itemtype = $_GET['itemtype'] ?? 'Computer';

if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

if (!class_exists($itemtype) || !is_subclass_of($itemtype, 'CommonDBTM')) {
    http_response_code(422);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'Tipo de ativo inválido.']);
    exit;
}

$results = [];
$searchTerm = '%' . $query . '%';

// Busca por nome, serial e otherserial
$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'serial', 'otherserial'],
    'FROM'   => $itemtype::getTable(),
    'WHERE'  => [
        'is_deleted' => 0,
        'OR' => [
            ['name'        => ['LIKE', $searchTerm]],
            ['serial'      => ['LIKE', $searchTerm]],
            ['otherserial' => ['LIKE', $searchTerm]],
        ],
    ],
    'ORDER'  => 'name ASC',
    'LIMIT'  => 20,
]);

foreach ($iterator as $row) {
    $results[] = [
        'id'          => (int) $row['id'],
        'itemtype'    => $itemtype,
        'name'        => $row['name'],
        'serial'      => $row['serial'] ?? null,
        'otherserial' => $row['otherserial'] ?? null,
    ];
}

echo json_encode(['results' => $results]);
