<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Serviço de consulta de ativos/equipamentos do GLPI para populate
 * automático de documentos.
 *
 * Porta de GlpiService.listAllByCategoryAndEntity do RegCheck.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

final class EquipmentQueryService
{
    /**
     * Busca ativos GLPI por itemtype, entidade e localização opcional.
     *
     * @param string    $itemtype     Itemtype GLPI (Computer, Monitor, NetworkEquipment...)
     * @param int       $entityId     Entidade atual (entities_id)
     * @param int|null  $locationsId  Filtro opcional por localização
     *
     * @return array<int, array{id:int,name:string,serial:?string,otherserial:?string,model:?string,locationName:?string}>
     */
    public function query(string $itemtype, int $entityId, ?int $locationsId = null): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!class_exists($itemtype)) {
            throw new \RuntimeException(
                sprintf(__('Itemtype inválido: %s', 'smartdocs'), $itemtype)
            );
        }

        $table = $itemtype::getTable();

        $where = [
            "$table.is_deleted" => 0,
        ];

        // Restrição de entidade
        $entityCriteria = getEntitiesRestrictCriteria($table, '', $entityId, true);
        if (!empty($entityCriteria)) {
            $where = array_merge($where, $entityCriteria);
        }

        if ($locationsId !== null && $locationsId > 0) {
            $where["$table.locations_id"] = $locationsId;
        }

        $iterator = $DB->request([
            'SELECT'    => [
                "$table.id",
                "$table.name",
                "$table.serial",
                "$table.otherserial",
                "$table.locations_id",
            ],
            'FROM'      => $table,
            'WHERE'     => $where,
            'ORDER'     => ["$table.locations_id ASC", "$table.name ASC"],
        ]);

        $results = [];
        foreach ($iterator as $row) {
            $results[] = [
                'id'           => (int) $row['id'],
                'name'         => $row['name'] ?? '',
                'serial'       => $row['serial'] ?: null,
                'otherserial'  => $row['otherserial'] ?: null,
                'model'        => $this->resolveModelName($itemtype, (int) ($row['id'] ?? 0)),
                'locationName' => $this->resolveLocationName((int) ($row['locations_id'] ?? 0)),
            ];
        }

        // Reordena por locationName depois name (o ORDER BY do SQL já aproxima,
        // mas locations_id ≠ locationName em casos de nomes iguais com IDs diferentes)
        usort($results, static function (array $a, array $b): int {
            $locCmp = strcmp($a['locationName'] ?? '', $b['locationName'] ?? '');
            if ($locCmp !== 0) {
                return $locCmp;
            }
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $results;
    }

    /**
     * Resolve o nome do modelo de um ativo.
     */
    private function resolveModelName(string $itemtype, int $itemsId): ?string
    {
        if ($itemsId <= 0) {
            return null;
        }

        $item = new $itemtype();
        if (!$item->getFromDB($itemsId)) {
            return null;
        }

        $modelFk = $this->getModelFkField($itemtype);
        $modelId = (int) ($item->fields[$modelFk] ?? 0);
        if ($modelId <= 0) {
            return null;
        }

        $modelClass = $itemtype . 'Model';
        if (!class_exists($modelClass)) {
            return null;
        }

        $model = new $modelClass();
        if (!$model->getFromDB($modelId)) {
            return null;
        }

        return $model->fields['name'] ?? null;
    }

    /**
     * Resolve o nome de uma localização pelo ID.
     */
    private function resolveLocationName(int $locationsId): ?string
    {
        if ($locationsId <= 0) {
            return null;
        }

        $location = new \Location();
        if (!$location->getFromDB($locationsId)) {
            return null;
        }

        return $location->fields['completename'] ?? $location->fields['name'] ?? null;
    }

    /**
     * Retorna o nome da coluna FK de modelo para um itemtype.
     */
    private function getModelFkField(string $itemtype): string
    {
        $short = str_replace('glpi_', '', $itemtype::getTable());
        return $short . 'models_id';
    }
}
