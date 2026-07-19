<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Repositório de acesso ao banco para Templates e seus campos.
 *
 * Regra de arquitetura: todo acesso ao banco passa por aqui.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

final class TemplateRepository
{
    /**
     * Busca um template pelo ID com todos os seus dados.
     */
    public function findById(int $id): ?array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'   => PdfTemplate::getTable(),
            'WHERE'  => ['id' => $id],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return $row;
        }

        return null;
    }

    /**
     * Lista templates publicados de uma entidade.
     *
     * @return array<int, array>
     */
    public function findPublished(int $entityId = 0): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $templates = [];
        $iterator = $DB->request([
            'FROM'   => PdfTemplate::getTable(),
            'WHERE'  => [
                'status'     => PdfTemplate::STATUS_PUBLISHED,
                'entities_id' => $entityId,
            ],
            'ORDER'  => 'name ASC',
        ]);

        foreach ($iterator as $row) {
            $templates[] = $row;
        }

        return $templates;
    }

    /**
     * Retorna todos os campos de um template ordenados por página e posição.
     *
     * @return array<int, array>
     */
    public function getFields(int $templateId): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $fields = [];
        $iterator = $DB->request([
            'FROM'   => TemplateField::getTable(),
            'WHERE'  => ['pdf_templates_id' => $templateId],
            'ORDER'  => 'page_index ASC, id ASC',
        ]);

        foreach ($iterator as $row) {
            $fields[] = $row;
        }

        return $fields;
    }

    /**
     * Salva (substitui) todos os campos de um template.
     *
     * Remove campos existentes e insere os novos — usado pelo autosave
     * do editor visual.
     */
    public function saveFields(int $templateId, array $fields): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->delete(TemplateField::getTable(), ['pdf_templates_id' => $templateId]);

        foreach ($fields as $field) {
            $DB->insert(TemplateField::getTable(), [
                'pdf_templates_id' => $templateId,
                'type'             => $field['type'] ?? 'text',
                'page_index'       => $field['page_index'] ?? 0,
                'position'         => json_encode($field['position'] ?? []),
                'config'           => isset($field['config']) ? json_encode($field['config']) : null,
                'scope'            => $field['scope'] ?? 'global',
                'slot_index'       => $field['slot_index'] ?? null,
                'binding_key'      => $field['binding_key'] ?? null,
            ]);
        }
    }

    /**
     * Duplica todos os campos de um template para outro.
     */
    public function duplicateFields(int $sourceTemplateId, int $targetTemplateId): void
    {
        $fields = $this->getFields($sourceTemplateId);

        foreach ($fields as $field) {
            unset($field['id'], $field['date_creation'], $field['date_mod']);
            $field['pdf_templates_id'] = $targetTemplateId;

            $newField = new TemplateField();
            $newField->add($field);
        }
    }
}
