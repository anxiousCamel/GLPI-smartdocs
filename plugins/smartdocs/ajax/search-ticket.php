<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Endpoint AJAX para busca de chamados (Ticket) por número ou título.
 *
 * GET ?q={termo}
 *
 * Resposta JSON: { "success": true, "tickets": [...] }
 * ----------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

include_once dirname(__DIR__) . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight(PermissionManager::RIGHT_NAME, PermissionManager::SMARTDOCS_DOCUMENT_READ);

header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';
if ($q === '') {
    echo json_encode(['success' => true, 'tickets' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

$tickets = [];

// Busca por ID exato se numérico
if (ctype_digit($q)) {
    $ticket = new Ticket();
    if ($ticket->getFromDB((int) $q)) {
        $tickets[] = [
            'id'        => (int) $ticket->fields['id'],
            'title'     => $ticket->fields['name'],
            'status'    => $ticket->getStatus($ticket->fields['status']),
            'entity'    => \Dropdown::getDropdownName('glpi_entities', $ticket->fields['entities_id']),
        ];
    }
}

// Busca por LIKE no título
$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'status', 'entities_id'],
    'FROM'   => 'glpi_tickets',
    'WHERE'  => [
        'is_deleted' => 0,
        'name'       => ['LIKE', '%' . $q . '%'],
    ],
    'LIMIT'  => 10,
]);

$seen = [];
foreach ($tickets as $t) {
    $seen[$t['id']] = true;
}

foreach ($iterator as $row) {
    $id = (int) $row['id'];
    if (isset($seen[$id])) {
        continue;
    }
    $seen[$id] = true;

    $ticketObj = new Ticket();
    $ticketObj->getFromDB($id);

    $tickets[] = [
        'id'        => $id,
        'title'     => $row['name'],
        'status'    => $ticketObj->getStatus($row['status']),
        'entity'    => \Dropdown::getDropdownName('glpi_entities', $row['entities_id']),
    ];
}

echo json_encode([
    'success' => true,
    'tickets' => $tickets,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
