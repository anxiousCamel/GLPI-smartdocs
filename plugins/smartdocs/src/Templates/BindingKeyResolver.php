<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Traduz uma binding key + identificador de objeto GLPI em valor
 * concreto (string).
 *
 * Binding keys seguem o padrão: <dominio>.<campo>
 *   - eq.serie       → serial do ativo
 *   - eq.patrimonio  → otherserial do ativo
 *   - eq.modelo      → nome do modelo associado
 *   - eq.numero      → name do ativo
 *   - eq.ip          → IP associado (NetworkName)
 *   - eq.localizacao → nome da localização
 *   - ticket.id      → id do chamado
 *   - ticket.titulo  → name do chamado
 *   - user.nome      → firstname + realname do usuário
 *   - user.email     → email principal do usuário
 *   - entity.nome    → name da entidade
 *   - system.data    → data atual do sistema
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

use Computer;
use Entity;
use Group;
use Location;
use Manufacturer;
use NetworkName;
use NetworkPort;
use Search;
use State;
use Ticket;
use User;

final class BindingKeyResolver
{
    /**
     * Resolve uma binding key para um valor concreto.
     *
     * @param string $bindingKey  ex: 'eq.serie'
     * @param string $itemtype    ex: 'Computer'
     * @param int    $itemsId     ex: 42
     */
    public static function resolve(string $bindingKey, string $itemtype, int $itemsId): ?string
    {
        if ($itemsId <= 0) {
            return null;
        }

        [$domain, $field] = self::parseKey($bindingKey);

        return match ($domain) {
            'eq'     => self::resolveAsset($field, $itemtype, $itemsId),
            'ticket' => self::resolveTicket($field, $itemsId),
            'user'   => self::resolveUser($field, $itemsId),
            'entity' => self::resolveEntity($field, $itemsId),
            'system' => self::resolveSystem($field),
            default  => null,
        };
    }

    /**
     * Retorna todas as binding keys disponíveis, organizadas por categoria.
     * As keys são descobertas dinamicamente inspecionando os itemtypes do GLPI.
     *
     * @param string|null $itemtype  Itemtype de referência para o domínio 'eq' (ex: 'Computer')
     */
    public static function getAvailableKeys(?string $itemtype = null): array
    {
        $categories = [];

        $categories[] = self::discoverAssetKeys($itemtype ?? Computer::class);
        $categories[] = self::discoverTicketKeys();
        $categories[] = self::discoverUserKeys();
        $categories[] = self::discoverEntityKeys();
        $categories[] = self::discoverSystemKeys();

        return array_values(array_filter($categories));
    }

    /**
     * Descobre campos disponíveis para um itemtype de ativo (eq.*).
     */
    private static function discoverAssetKeys(string $itemtype): array
    {
        $keys = [];
        $labels = self::getFieldLabels($itemtype);

        $item = self::getItemInstance($itemtype);
        if ($item === null) {
            return [];
        }

        $item->getEmpty();
        $fields = array_keys($item->fields);

        // Campos principais primeiro (ordem preferida)
        $priority = ['name', 'serial', 'otherserial', 'states_id', 'locations_id',
                     'manufacturers_id', 'networks_id', 'computertypes_id', 'monitortypes_id',
                     'peripheraltypes_id', 'phonetypes_id', 'printertypes_id',
                     'networkequipmenttypes_id', 'users_id', 'users_id_tech',
                     'groups_id', 'groups_id_tech', 'contact', 'contact_num',
                     'comment', 'date_creation', 'date_mod'];

        $seen = [];
        foreach ($priority as $field) {
            if (in_array($field, $fields, true) && !isset($seen[$field])) {
                $label = $labels[$field] ?? self::humanize($field);
                $keys[] = ['value' => 'eq.' . $field, 'label' => $label];
                $seen[$field] = true;
            }
        }

        // Demais campos do itemtype
        foreach ($fields as $field) {
            if (isset($seen[$field])) {
                continue;
            }
            if ($field === 'id') {
                continue; // já coberto implicitamente
            }
            $label = $labels[$field] ?? self::humanize($field);
            $keys[] = ['value' => 'eq.' . $field, 'label' => $label];
            $seen[$field] = true;
        }

        // Campos de rede (não estão em $item->fields diretamente)
        $keys[] = ['value' => 'eq.ip', 'label' => __('Endereço IP', 'smartdocs')];
        $keys[] = ['value' => 'eq.mac', 'label' => __('Endereço MAC', 'smartdocs')];

        return [
            'label'   => __('Equipamento / Ativo', 'smartdocs'),
            'domain'  => 'eq',
            'keys'    => $keys,
        ];
    }

    /**
     * Descobre campos disponíveis para Ticket (ticket.*).
     */
    private static function discoverTicketKeys(): array
    {
        $keys = [];
        $labels = self::getFieldLabels(Ticket::class);

        $ticket = new Ticket();
        $ticket->getEmpty();
        $fields = array_keys($ticket->fields);

        $priority = ['id', 'name', 'content', 'status', 'priority', 'urgency',
                     'impact', 'itilcategories_id', 'date', 'closedate',
                     'solvedate', 'time_to_resolve', 'time_to_own', 'users_id_recipient',
                     'users_id_lastupdater'];

        $seen = [];
        foreach ($priority as $field) {
            if (in_array($field, $fields, true) && !isset($seen[$field])) {
                $label = $labels[$field] ?? self::humanize($field);
                $keys[] = ['value' => 'ticket.' . $field, 'label' => $label];
                $seen[$field] = true;
            }
        }

        foreach ($fields as $field) {
            if (isset($seen[$field]) || $field === 'id') {
                continue;
            }
            $label = $labels[$field] ?? self::humanize($field);
            $keys[] = ['value' => 'ticket.' . $field, 'label' => $label];
            $seen[$field] = true;
        }

        return [
            'label'   => __('Chamado / Ticket', 'smartdocs'),
            'domain'  => 'ticket',
            'keys'    => $keys,
        ];
    }

    /**
     * Descobre campos disponíveis para User (user.*).
     */
    private static function discoverUserKeys(): array
    {
        $keys = [];
        $labels = self::getFieldLabels(User::class);

        $user = new User();
        $user->getEmpty();
        $fields = array_keys($user->fields);

        $priority = ['id', 'name', 'firstname', 'realname', 'phone', 'phone2',
                     'mobile', 'usercategories_id', 'usertitles_id',
                     'locations_id', 'comment', 'date_creation', 'date_mod'];

        $seen = [];
        foreach ($priority as $field) {
            if (in_array($field, $fields, true) && !isset($seen[$field])) {
                $label = $labels[$field] ?? self::humanize($field);
                $keys[] = ['value' => 'user.' . $field, 'label' => $label];
                $seen[$field] = true;
            }
        }

        foreach ($fields as $field) {
            if (isset($seen[$field]) || $field === 'id') {
                continue;
            }
            $label = $labels[$field] ?? self::humanize($field);
            $keys[] = ['value' => 'user.' . $field, 'label' => $label];
            $seen[$field] = true;
        }

        // Email não está em $user->fields como coluna direta
        $keys[] = ['value' => 'user.email', 'label' => __('Email principal', 'smartdocs')];

        return [
            'label'   => __('Usuário / Técnico', 'smartdocs'),
            'domain'  => 'user',
            'keys'    => $keys,
        ];
    }

    /**
     * Descobre campos disponíveis para Entity (entity.*).
     */
    private static function discoverEntityKeys(): array
    {
        $keys = [];
        $labels = self::getFieldLabels(Entity::class);

        $entity = new Entity();
        $entity->getEmpty();
        $fields = array_keys($entity->fields);

        $priority = ['id', 'name', 'completename', 'phone', 'fax', 'website',
                     'email', 'address', 'postcode', 'town', 'state', 'country',
                     'comment'];

        $seen = [];
        foreach ($priority as $field) {
            if (in_array($field, $fields, true) && !isset($seen[$field])) {
                $label = $labels[$field] ?? self::humanize($field);
                $keys[] = ['value' => 'entity.' . $field, 'label' => $label];
                $seen[$field] = true;
            }
        }

        foreach ($fields as $field) {
            if (isset($seen[$field]) || $field === 'id') {
                continue;
            }
            $label = $labels[$field] ?? self::humanize($field);
            $keys[] = ['value' => 'entity.' . $field, 'label' => $label];
            $seen[$field] = true;
        }

        return [
            'label'   => __('Entidade', 'smartdocs'),
            'domain'  => 'entity',
            'keys'    => $keys,
        ];
    }

    /**
     * Campos do domínio system.* (data/hora atual).
     */
    private static function discoverSystemKeys(): array
    {
        return [
            'label'   => __('Sistema / Data', 'smartdocs'),
            'domain'  => 'system',
            'keys'    => [
                ['value' => 'system.data_atual',  'label' => __('Data atual', 'smartdocs')],
                ['value' => 'system.hora_atual',  'label' => __('Hora atual', 'smartdocs')],
                ['value' => 'system.data_hora',   'label' => __('Data e hora', 'smartdocs')],
            ],
        ];
    }

    /**
     * Retorna um mapa campo => label humanizado usando as Search Options do GLPI.
     */
    private static function getFieldLabels(string $itemtype): array
    {
        $labels = [];

        if (!method_exists($itemtype, 'getSearchOptions') && !method_exists($itemtype, 'getSearchOptionsNew')) {
            return $labels;
        }

        $options = Search::getOptions($itemtype);
        foreach ($options as $opt) {
            if (!is_array($opt) || empty($opt['field']) || empty($opt['name'])) {
                continue;
            }
            $field = $opt['field'];
            $name  = $opt['name'];
            // Só guarda se ainda não tem (primeiro wins — opções nativas têm prioridade)
            if (!isset($labels[$field])) {
                $labels[$field] = $name;
            }
        }

        return $labels;
    }

    /**
     * Transforma um nome de campo snake_case em texto legível.
     */
    private static function humanize(string $field): string
    {
        // Remove sufixos _id para FKs
        $display = $field;
        if (str_ends_with($display, '_id')) {
            $display = substr($display, 0, -3);
        }
        return ucwords(str_replace('_', ' ', $display));
    }

    /**
     * @return array{string, string}
     */
    private static function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private static function resolveAsset(string $field, string $itemtype, int $itemsId): ?string
    {
        $item = self::getItemInstance($itemtype);
        if ($item === null || !$item->getFromDB($itemsId)) {
            return null;
        }

        // Alias legados (mantêm compatibilidade com templates existentes)
        $legacy = match ($field) {
            'serie'       => $item->fields['serial'] ?? null,
            'patrimonio'  => $item->fields['otherserial'] ?? null,
            'modelo'      => self::getModelName($itemtype, (int) ($item->fields[self::getModelFkField($itemtype)] ?? 0)),
            'numero'      => $item->fields['name'] ?? null,
            'ip'          => self::getIpAddress($itemtype, $itemsId),
            'mac'         => self::getMacAddress($itemtype, $itemsId),
            'localizacao' => self::getDropdownName(Location::class, (int) ($item->fields['locations_id'] ?? 0)),
            'estado'      => self::getDropdownName(State::class, (int) ($item->fields['states_id'] ?? 0)),
            'fabricante'  => self::getDropdownName(Manufacturer::class, (int) ($item->fields['manufacturers_id'] ?? 0)),
            'usuario'     => self::getDropdownName(User::class, (int) ($item->fields['users_id'] ?? 0)),
            'grupo'       => self::getDropdownName(Group::class, (int) ($item->fields['groups_id'] ?? 0)),
            'tipo'        => self::getAssetTypeName($itemtype, $item),
            'contato'     => $item->fields['contact'] ?? null,
            'contato_num' => $item->fields['contact_num'] ?? null,
            'comentario'  => $item->fields['comment'] ?? null,
            'data_criacao'     => $item->fields['date_creation'] ?? null,
            'data_modificacao' => $item->fields['date_mod'] ?? null,
            default       => null,
        };

        if ($legacy !== null) {
            return $legacy;
        }

        // Fallback dinâmico: qualquer campo existente em $item->fields
        if (array_key_exists($field, $item->fields)) {
            $value = $item->fields[$field];
            // Resolve FK automaticamente
            if (is_numeric($value) && str_ends_with($field, '_id') && (int) $value > 0) {
                $dropdown = self::resolveFkClass($field);
                if ($dropdown !== null) {
                    return self::getDropdownName($dropdown, (int) $value);
                }
            }
            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    private static function resolveTicket(string $field, int $itemsId): ?string
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($itemsId)) {
            return null;
        }

        $legacy = match ($field) {
            'id'      => (string) $ticket->fields['id'],
            'titulo'  => $ticket->fields['name'] ?? null,
            'descricao' => $ticket->fields['content'] ?? null,
            'status'  => Ticket::getStatus($ticket->fields['status'] ?? 0),
            'prioridade' => Ticket::getPriorityName($ticket->fields['priority'] ?? 0),
            'urgencia'   => Ticket::getUrgencyName($ticket->fields['urgency'] ?? 0),
            'impacto'    => Ticket::getImpactName($ticket->fields['impact'] ?? 0),
            'categoria'  => self::getDropdownName(\ITILCategory::class, (int) ($ticket->fields['itilcategories_id'] ?? 0)),
            'data_abertura'    => $ticket->fields['date'] ?? null,
            'data_fechamento'  => $ticket->fields['closedate'] ?? null,
            'data_solucao'     => $ticket->fields['solvedate'] ?? null,
            'requerente'       => self::getTicketActorNames($itemsId, \CommonITILActor::REQUESTER),
            'tecnico'          => self::getTicketActorNames($itemsId, \CommonITILActor::ASSIGN),
            'grupo_tecnico'    => self::getTicketGroupNames($itemsId, \CommonITILActor::ASSIGN),
            default            => null,
        };

        if ($legacy !== null) {
            return $legacy;
        }

        // Fallback dinâmico
        if (array_key_exists($field, $ticket->fields)) {
            $value = $ticket->fields[$field];
            if (is_numeric($value) && str_ends_with($field, '_id') && (int) $value > 0) {
                $dropdown = self::resolveFkClass($field);
                if ($dropdown !== null) {
                    return self::getDropdownName($dropdown, (int) $value);
                }
            }
            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    private static function resolveUser(string $field, int $itemsId): ?string
    {
        $user = new User();
        if (!$user->getFromDB($itemsId)) {
            return null;
        }

        $legacy = match ($field) {
            'nome'   => trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? '')),
            'email'  => $user->getDefaultEmail(),
            'titulo' => self::getDropdownName(\UserTitle::class, (int) ($user->fields['usertitles_id'] ?? 0)),
            default  => null,
        };

        if ($legacy !== null) {
            return $legacy;
        }

        // Fallback dinâmico
        if (array_key_exists($field, $user->fields)) {
            $value = $user->fields[$field];
            if (is_numeric($value) && str_ends_with($field, '_id') && (int) $value > 0) {
                $dropdown = self::resolveFkClass($field);
                if ($dropdown !== null) {
                    return self::getDropdownName($dropdown, (int) $value);
                }
            }
            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    private static function resolveEntity(string $field, int $itemsId): ?string
    {
        $entity = new Entity();
        if (!$entity->getFromDB($itemsId)) {
            return null;
        }

        $legacy = match ($field) {
            'nome' => $entity->fields['name'] ?? null,
            default => null,
        };

        if ($legacy !== null) {
            return $legacy;
        }

        // Fallback dinâmico
        if (array_key_exists($field, $entity->fields)) {
            $value = $entity->fields[$field];
            if (is_numeric($value) && str_ends_with($field, '_id') && (int) $value > 0) {
                $dropdown = self::resolveFkClass($field);
                if ($dropdown !== null) {
                    return self::getDropdownName($dropdown, (int) $value);
                }
            }
            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    private static function resolveSystem(string $field): ?string
    {
        return match ($field) {
            'data_atual' => date('d/m/Y'),
            'hora_atual' => date('H:i:s'),
            'data_hora'  => date('d/m/Y H:i:s'),
            default      => null,
        };
    }

    /**
     * Retorna uma instância da classe do ativo GLPI.
     */
    private static function getItemInstance(string $itemtype): ?\CommonDBTM
    {
        if (!class_exists($itemtype)) {
            return null;
        }

        $item = new $itemtype();
        if (!($item instanceof \CommonDBTM)) {
            return null;
        }

        return $item;
    }

    /**
     * Busca o nome do modelo associado ao ativo.
     */
    private static function getModelName(string $itemtype, int $modelId): ?string
    {
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
     * Retorna o nome da coluna FK de modelo para um itemtype.
     */
    private static function getModelFkField(string $itemtype): string
    {
        $short = str_replace('glpi_', '', $itemtype::getTable());
        return $short . 'models_id';
    }

    /**
     * Busca o IP principal associado ao ativo.
     */
    private static function getIpAddress(string $itemtype, int $itemsId): ?string
    {
        $networkName = new NetworkName();
        $iterator = $networkName->find([
            'itemtype' => $itemtype,
            'items_id' => $itemsId,
        ], ['id ASC'], 1);

        foreach ($iterator as $row) {
            return $row['name'] ?? null;
        }

        return null;
    }

    /**
     * Busca o MAC principal associado ao ativo.
     */
    private static function getMacAddress(string $itemtype, int $itemsId): ?string
    {
        $networkPort = new NetworkPort();
        $iterator = $networkPort->find([
            'itemtype' => $itemtype,
            'items_id' => $itemsId,
        ], ['id ASC'], 1);

        foreach ($iterator as $row) {
            return $row['mac'] ?? null;
        }

        return null;
    }

    /**
     * Busca o nome de um dropdown pelo ID.
     */
    private static function getDropdownName(string $class, int $id): ?string
    {
        if ($id <= 0 || !class_exists($class)) {
            return null;
        }

        $item = new $class();
        if (!$item->getFromDB($id)) {
            return null;
        }

        return $item->fields['name'] ?? $item->fields['completename'] ?? null;
    }

    /**
     * Resolve o nome da classe dropdown a partir do nome da coluna FK.
     */
    private static function resolveFkClass(string $field): ?string
    {
        if (!str_ends_with($field, '_id')) {
            return null;
        }

        $base = substr($field, 0, -3);

        // Mapeamentos conhecidos
        $map = [
            'locations'      => Location::class,
            'states'         => State::class,
            'manufacturers'  => Manufacturer::class,
            'users'          => User::class,
            'groups'         => Group::class,
            'entities'       => Entity::class,
            'itilcategories' => \ITILCategory::class,
            'usertitles'     => \UserTitle::class,
            'usercategories' => \UserCategory::class,
        ];

        if (isset($map[$base])) {
            return $map[$base];
        }

        // Tenta inferir: glpi_<base> → classe CamelCase
        $candidates = [
            '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $base))),
            '\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', \Infocom::getItemTypeForTable('glpi_' . $base))))
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Retorna o nome do tipo de ativo (ex: Computador, Monitor).
     */
    private static function getAssetTypeName(string $itemtype, \CommonDBTM $item): ?string
    {
        $typeField = null;
        foreach (array_keys($item->fields) as $key) {
            if (str_ends_with($key, 'types_id')) {
                $typeField = $key;
                break;
            }
        }

        if ($typeField === null || empty($item->fields[$typeField])) {
            return $itemtype::getTypeName(1);
        }

        $typeClass = $itemtype . 'Type';
        if (!class_exists($typeClass)) {
            return $itemtype::getTypeName(1);
        }

        $type = new $typeClass();
        if (!$type->getFromDB((int) $item->fields[$typeField])) {
            return $itemtype::getTypeName(1);
        }

        return $type->fields['name'] ?? $itemtype::getTypeName(1);
    }

    /**
     * Retorna nomes dos atores de um ticket (requesters, technicians).
     */
    private static function getTicketActorNames(int $ticketId, int $actorType): ?string
    {
        $actors = (new \Ticket_User())->getActors($ticketId);
        if (!isset($actors[$actorType])) {
            return null;
        }

        $names = [];
        foreach ($actors[$actorType] as $actor) {
            $user = new User();
            if ($user->getFromDB($actor['users_id'])) {
                $name = trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? ''));
                $names[] = $name ?: ($user->fields['name'] ?? '');
            }
        }

        return empty($names) ? null : implode(', ', $names);
    }

    /**
     * Retorna nomes dos grupos de um ticket.
     */
    private static function getTicketGroupNames(int $ticketId, int $actorType): ?string
    {
        $groupTicket = new \Group_Ticket();
        $iterator = $groupTicket->find([
            'tickets_id' => $ticketId,
            'type'       => $actorType,
        ]);

        $names = [];
        foreach ($iterator as $row) {
            $group = new Group();
            if ($group->getFromDB($row['groups_id'])) {
                $names[] = $group->fields['name'] ?? '';
            }
        }

        return empty($names) ? null : implode(', ', $names);
    }
}
