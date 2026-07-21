<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Acesso à tabela de configurações do plugin (glpi_plugin_smartdocs_configs).
 *
 * Chave/valor simples, seedada em install.php e consultada pelo
 * checklist de setup. Único ponto de leitura/escrita — outras camadas
 * (ex: OcrService) devem usar esta classe em vez de acessar a tabela
 * diretamente ou usar GLPI\Config (que é um namespace de configuração
 * distinto e não persiste aqui).
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Services;

final class PluginConfigService
{
    private const TABLE = 'glpi_plugin_smartdocs_configs';

    public static function get(string $name, string $default = ''): string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $row = $DB->request([
            'FROM'  => self::TABLE,
            'WHERE' => ['name' => $name],
        ])->current();

        return $row ? (string) $row['value'] : $default;
    }

    public static function set(string $name, string $value): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->updateOrInsert(
            self::TABLE,
            [
                'value'    => $value,
                'date_mod' => date('Y-m-d H:i:s'),
            ],
            ['name' => $name]
        );
    }
}
