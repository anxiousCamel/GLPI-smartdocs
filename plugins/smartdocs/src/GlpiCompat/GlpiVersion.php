<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Detecção de versão do GLPI em runtime.
 *
 * Isola as diferenças de API entre GLPI 10.x e 11.x em um único ponto,
 * permitindo que o restante do plugin permaneça agnóstico de versão.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\GlpiCompat;

final class GlpiVersion
{
    private function __construct()
    {
        // Classe utilitária — não instanciável.
    }

    /**
     * Versão atual do GLPI (ex: "10.0.15").
     */
    public static function current(): string
    {
        return defined('GLPI_VERSION') ? (string) GLPI_VERSION : '0.0.0';
    }

    /**
     * GLPI 11.0 ou superior.
     */
    public static function is11OrAbove(): bool
    {
        return version_compare(self::current(), '11.0.0', '>=');
    }

    /**
     * GLPI 10.x (inclusive).
     */
    public static function is10(): bool
    {
        $version = self::current();

        return version_compare($version, '10.0.0', '>=')
            && version_compare($version, '11.0.0', '<');
    }

    /**
     * Verifica se a versão atual está dentro do range suportado pelo plugin.
     */
    public static function isSupported(): bool
    {
        $version = self::current();

        return version_compare($version, PLUGIN_SMARTDOCS_MIN_GLPI, '>=')
            && version_compare($version, PLUGIN_SMARTDOCS_MAX_GLPI, '<=');
    }
}