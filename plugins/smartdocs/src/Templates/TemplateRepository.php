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
        $where = [
            'status' => PdfTemplate::STATUS_PUBLISHED,
        ];

        // Aplica restrição de entidade respeitando is_recursive
        $entityCriteria = getEntitiesRestrictCriteria(PdfTemplate::getTable(), '', $entityId, true);
        if (!empty($entityCriteria)) {
            $where = array_merge($where, $entityCriteria);
        }

        $iterator = $DB->request([
            'FROM'   => PdfTemplate::getTable(),
            'WHERE'  => $where,
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

        return self::resolveSlots($fields);
    }

    /**
     * Deriva `scope` e `slot_index` a partir do `group_label` de cada campo.
     *
     * No editor visual o usuário organiza os campos em "grupos" nomeados
     * (ex.: "Grupo 1", "Grupo 2"), onde cada grupo representa uma posição de
     * equipamento na MESMA página. O resto do sistema (paginação, wizard de
     * preenchimento, geração de PDF) trabalha com o modelo do RegCheck:
     * `scope='item'` + `slot_index` (0..N-1) para campos por equipamento e
     * `scope='global'` + `slot_index=null` para campos do documento inteiro.
     *
     * Esta função é a ponte entre os dois: cada `group_label` distinto vira um
     * slot (na ordem alfabética, batendo com a numeração G1/G2... do editor),
     * e campos sem grupo viram globais. Centralizar aqui garante que editor,
     * wizard e gerador de PDF enxerguem exatamente o mesmo layout.
     *
     * @param array<int,array> $fields
     * @return array<int,array>
     */
    public static function resolveSlots(array $fields): array
    {
        // Coleta os rótulos de grupo distintos e os ordena de forma estável
        // (SORT_STRING = mesma ordenação lexicográfica do `.sort()` do editor,
        // para que o slot 0 aqui seja o mesmo "G1" mostrado no canvas).
        $groups = [];
        foreach ($fields as $field) {
            $label = trim((string) ($field['group_label'] ?? ''));
            if ($label !== '') {
                $groups[$label] = true;
            }
        }
        $groupNames = array_keys($groups);
        sort($groupNames, SORT_STRING);
        $slotByGroup = array_flip($groupNames); // group_label => índice 0..N-1

        foreach ($fields as &$field) {
            $label = trim((string) ($field['group_label'] ?? ''));
            if ($label !== '' && isset($slotByGroup[$label])) {
                $field['scope']      = 'item';
                $field['slot_index'] = $slotByGroup[$label];
            } else {
                $field['scope']      = 'global';
                $field['slot_index'] = null;
            }
        }
        unset($field);

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
            // `position`/`config` podem chegar do JS já como string JSON ou
            // como objeto/array — normaliza para não codificar duas vezes
            // (o dobro de json_encode gera JSON inválido e a coluna JSON
            // nativa rejeita o insert).
            $position = $field['position'] ?? [];
            $config   = $field['config'] ?? null;

            $result = $DB->insert(TemplateField::getTable(), [
                'pdf_templates_id' => $templateId,
                'type'             => $field['type'] ?? 'text',
                'page_index'       => $field['page_index'] ?? 0,
                'position'         => is_string($position) ? $position : json_encode($position),
                'config'           => $config === null ? null : (is_string($config) ? $config : json_encode($config)),
                'scope'            => $field['scope'] ?? 'global',
                'slot_index'       => $field['slot_index'] ?? null,
                'binding_key'      => $field['binding_key'] ?? null,
                'label'            => $field['label'] ?? null,
                'group_label'      => $field['group_label'] ?? null,
            ]);

            if ($result === false) {
                throw new \RuntimeException('Falha ao salvar campo do template: ' . $DB->error());
            }
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
