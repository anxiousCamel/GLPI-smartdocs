<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Modelo de campo posicionado em um Template PDF.
 *
 * Cada registro representa um campo (texto, imagem, assinatura,
 * checkbox) posicionado sobre uma página do PDF base.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

use CommonDBTM;

final class TemplateField extends CommonDBTM
{
    public const TYPE_TEXT      = 'text';
    public const TYPE_IMAGE     = 'image';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_CHECKBOX  = 'checkbox';

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_ITEM   = 'item';

    public static function getTypeName($nb = 0): string
    {
        return _n('Campo do template', 'Campos do template', $nb, 'smartdocs');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_pdf_template_fields';
    }

    /**
     * Tipos de campo disponíveis para o dropdown.
     *
     * @return array<string, string>
     */
    public static function getFieldTypes(): array
    {
        return [
            self::TYPE_TEXT      => __('Texto', 'smartdocs'),
            self::TYPE_IMAGE     => __('Imagem', 'smartdocs'),
            self::TYPE_SIGNATURE => __('Assinatura', 'smartdocs'),
            self::TYPE_CHECKBOX  => __('Checkbox', 'smartdocs'),
        ];
    }

    /**
     * Escopos disponíveis para o dropdown.
     *
     * @return array<string, string>
     */
    public static function getScopes(): array
    {
        return [
            self::SCOPE_GLOBAL => __('Global (aparece uma vez)', 'smartdocs'),
            self::SCOPE_ITEM   => __('Por item (repete por equipamento)', 'smartdocs'),
        ];
    }

    /**
     * Decodifica o JSON de posição em array tipado.
     *
     * @return array{x: float, y: float, width: float, height: float}
     */
    public function getPosition(): array
    {
        $raw = $this->fields['position'] ?? '{}';
        $decoded = json_decode((string) $raw, true);

        return [
            'x'      => (float) ($decoded['x'] ?? 0),
            'y'      => (float) ($decoded['y'] ?? 0),
            'width'  => (float) ($decoded['width'] ?? 0),
            'height' => (float) ($decoded['height'] ?? 0),
        ];
    }

    /**
     * Decodifica o JSON de configuração em array.
     */
    public function getConfig(): array
    {
        $raw = $this->fields['config'] ?? '{}';
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
