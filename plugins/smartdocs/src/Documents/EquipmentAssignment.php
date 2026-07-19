<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * EquipmentAssignment: vincula ativos GLPI a um PdfDocument com suporte
 * a soft delete, optimistic locking e preservação de non-binding fields.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

use CommonDBTM;

final class EquipmentAssignment extends CommonDBTM
{
    public static $rightname = 'plugin_smartdocs_use';

    public static function getTypeName($nb = 0): string
    {
        return _n('Vínculo de Equipamento', 'Vínculos de Equipamentos', $nb, 'smartdocs');
    }

    /**
     * Adiciona um ativo ao documento.
     */
    public function addAssignment(int $pdfDocumentId, string $itemType, int $itemId, int $itemIndex, array $nonBindingData = []): int
    {
        $input = [
            'pdf_documents_id' => $pdfDocumentId,
            'itemtype'         => $itemType,
            'items_id'         => $itemId,
            'item_index'       => $itemIndex,
            'non_binding_data' => json_encode($nonBindingData),
            'removed_at'       => null,
            'date_mod'         => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ];

        return $this->add($input);
    }

    /**
     * Remove logicamente um assignment (soft delete).
     */
    public function softDelete(int $assignmentId): bool
    {
        return $this->update([
            'id'         => $assignmentId,
            'removed_at' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Reativa um assignment previamente removido.
     */
    public function reactivate(int $assignmentId): bool
    {
        return $this->update([
            'id'         => $assignmentId,
            'removed_at' => null,
        ]);
    }

    /**
     * Lista assignments ativos de um documento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveAssignments(int $pdfDocumentId): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['*'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'pdf_documents_id' => $pdfDocumentId,
                'removed_at'       => null,
            ],
            'ORDER'  => 'item_index ASC',
        ]);

        $results = [];
        foreach ($iterator as $row) {
            $row['non_binding_data'] = json_decode($row['non_binding_data'] ?? '[]', true);
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Valida optimistic locking.
     */
    public function validateLock(int $pdfDocumentId, string $expectedUpdatedAt): bool
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['date_mod'],
            'FROM'   => 'glpi_plugin_smartdocs_pdf_documents',
            'WHERE'  => ['id' => $pdfDocumentId],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return $row['date_mod'] === $expectedUpdatedAt;
        }

        return false;
    }

    /**
     * Retorna o próximo item_index disponível para um documento.
     */
    public function nextItemIndex(int $pdfDocumentId): int
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['MAX' => 'item_index AS max_idx'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['pdf_documents_id' => $pdfDocumentId],
        ]);

        foreach ($iterator as $row) {
            return ((int) ($row['max_idx'] ?? 0)) + 1;
        }

        return 1;
    }
}
