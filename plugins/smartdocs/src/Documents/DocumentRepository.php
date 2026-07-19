<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Repositório de acesso ao banco para Documentos e campos preenchidos.
 *
 * Regra de arquitetura: todo acesso ao banco passa por aqui.
 * -----------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

final class DocumentRepository
{
    /**
     * Busca um documento pelo ID.
     */
    public function findById(int $id): ?array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'  => PdfDocument::getTable(),
            'WHERE' => ['id' => $id],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Lista documentos de uma entidade com filtros opcionais.
     *
     * @return array<int, array>
     */
    public function findByEntity(int $entityId, array $filters = []): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $where = ['entities_id' => $entityId];

        if (!empty($filters['status'])) {
            $where['status'] = $filters['status'];
        }

        if (!empty($filters['pdf_templates_id'])) {
            $where['pdf_templates_id'] = (int) $filters['pdf_templates_id'];
        }

        $documents = [];
        $iterator = $DB->request([
            'FROM'   => PdfDocument::getTable(),
            'WHERE'  => $where,
            'ORDER'  => 'date_mod DESC',
        ]);

        foreach ($iterator as $row) {
            $documents[] = $row;
        }

        return $documents;
    }

    /**
     * Retorna todos os campos preenchidos de um documento.
     *
     * @return array<int, array>
     */
    public function getFilledFields(int $documentId): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $fields = [];
        $iterator = $DB->request([
            'FROM'   => FilledField::getTable(),
            'WHERE'  => ['pdf_documents_id' => $documentId],
            'ORDER'  => 'item_index ASC, id ASC',
        ]);

        foreach ($iterator as $row) {
            $fields[] = $row;
        }

        return $fields;
    }

    /**
     * Retorna um campo preenchido específico.
     */
    public function getFilledField(int $documentId, int $templateFieldId, int $itemIndex): ?array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'   => FilledField::getTable(),
            'WHERE'  => [
                'pdf_documents_id'       => $documentId,
                'pdf_template_fields_id' => $templateFieldId,
                'item_index'             => $itemIndex,
            ],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Salva (insere ou atualiza) um campo preenchido.
     */
    public function saveFilledField(int $documentId, int $templateFieldId, int $itemIndex, ?string $value, ?int $fileDocumentId = null): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $existing = $this->getFilledField($documentId, $templateFieldId, $itemIndex);

        if ($existing !== null) {
            $update = ['value' => $value];
            if ($fileDocumentId !== null) {
                $update['file_documents_id'] = $fileDocumentId;
            }

            $DB->update(FilledField::getTable(), $update, ['id' => $existing['id']]);
        } else {
            $DB->insert(FilledField::getTable(), [
                'pdf_documents_id'       => $documentId,
                'pdf_template_fields_id' => $templateFieldId,
                'item_index'             => $itemIndex,
                'value'                  => $value,
                'file_documents_id'      => $fileDocumentId,
                'date_creation'          => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
                'date_mod'               => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Atualiza o status de um documento.
     */
    public function updateStatus(int $documentId, string $status): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->update(PdfDocument::getTable(), [
            'status'   => $status,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ], ['id' => $documentId]);
    }

    /**
     * Atualiza o ID do PDF gerado em um documento.
     */
    public function updateGeneratedPdf(int $documentId, int $documentFileId): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->update(PdfDocument::getTable(), [
            'generated_pdf_documents_id' => $documentFileId,
            'date_mod'                   => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ], ['id' => $documentId]);
    }
}
