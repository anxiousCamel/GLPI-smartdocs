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
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

use Computer;
use Entity;
use Location;
use NetworkName;
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
            default  => null,
        };
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

        return match ($field) {
            'serie'       => $item->fields['serial'] ?? null,
            'patrimonio'  => $item->fields['otherserial'] ?? null,
            'modelo'      => self::getModelName($itemtype, (int) ($item->fields[$itemtype::getForeignKeyField() . '_models_id'] ?? 0)),
            'numero'      => $item->fields['name'] ?? null,
            'ip'          => self::getIpAddress($itemtype, $itemsId),
            'localizacao' => self::getLocationName((int) ($item->fields['locations_id'] ?? 0)),
            default       => null,
        };
    }

    private static function resolveTicket(string $field, int $itemsId): ?string
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($itemsId)) {
            return null;
        }

        return match ($field) {
            'id'     => (string) $ticket->fields['id'],
            'titulo' => $ticket->fields['name'] ?? null,
            default  => null,
        };
    }

    private static function resolveUser(string $field, int $itemsId): ?string
    {
        $user = new User();
        if (!$user->getFromDB($itemsId)) {
            return null;
        }

        return match ($field) {
            'nome'  => trim(($user->fields['firstname'] ?? '') . ' ' . ($user->fields['realname'] ?? '')),
            'email' => $user->getDefaultEmail(),
            default => null,
        };
    }

    private static function resolveEntity(string $field, int $itemsId): ?string
    {
        $entity = new Entity();
        if (!$entity->getFromDB($itemsId)) {
            return null;
        }

        return match ($field) {
            'nome' => $entity->fields['name'] ?? null,
            default => null,
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
     * Busca o nome da localização.
     */
    private static function getLocationName(int $locationId): ?string
    {
        if ($locationId <= 0) {
            return null;
        }

        $location = new Location();
        if (!$location->getFromDB($locationId)) {
            return null;
        }

        return $location->fields['name'] ?? null;
    }
}
