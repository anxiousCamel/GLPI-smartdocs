<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Construção do menu lateral do GLPI para o plugin.
 *
 * O GLPI 10/11 resolve os menus a partir das classes registradas em
 * $PLUGIN_HOOKS['menu_toadd'], invocando estaticamente getMenuContent()
 * e getIcon(). Cada entrada respeita as permissões do perfil ativo.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\GlpiCompat;

use GlpiPlugin\SmartDocs\Documents\PdfDocument;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;
use GlpiPlugin\SmartDocs\Templates\PdfTemplate;
use GlpiPlugin\SmartDocs\Wiki\WikiDocument;

final class MenuHelper
{
    public const MENU_KEY = 'smartdocs';

    private function __construct()
    {
        // Classe utilitária — não instanciável.
    }

    /**
     * Ícone do menu (Tabler Icons, já embutidos no GLPI 10/11).
     */
    public static function getIcon(): string
    {
        return 'ti ti-file-description';
    }

    /**
     * Estrutura de entradas do menu SmartDocs.
     *
     * Retorna entradas multi-nível conforme as permissões do usuário
     * corrente; módulos indisponíveis são omitidos.
     *
     * @return array<string, mixed>
     */
    public static function getMenuContent(): array
    {
        $menu = [];

        $menu['home'] = [
            'title' => __('Página Inicial', 'smartdocs'),
            'page'  => self::frontUrl('smartdocs.php'),
            'icon'  => 'ti ti-home',
        ];

        if (PermissionManager::canReadTemplates() && class_exists(PdfTemplate::class)) {
            $menu['templates'] = [
                'title' => __('Templates PDF', 'smartdocs'),
                'page'  => self::frontUrl('pdftemplate.php'),
                'icon'  => 'ti ti-layout-collage',
                'links' => self::filteredLinks([
                    'search' => self::frontUrl('pdftemplate.php'),
                    'add'    => PermissionManager::canWriteTemplates()
                        ? self::frontUrl('pdftemplate.form.php')
                        : null,
                ]),
            ];
        }

        if (PermissionManager::canReadDocuments() && class_exists(PdfDocument::class)) {
            $menu['documents'] = [
                'title' => __('Documentos', 'smartdocs'),
                'page'  => self::frontUrl('pdfdocument.php'),
                'icon'  => 'ti ti-files',
                'links' => [
                    'search' => self::frontUrl('pdfdocument.php'),
                ],
            ];
        }

        if (class_exists(WikiDocument::class)) {
            $menu['wiki'] = [
                'title' => __('Wiki', 'smartdocs'),
                'page'  => self::frontUrl('wikidocument.php'),
                'icon'  => 'ti ti-book',
            ];
        }

        if (PermissionManager::canAdmin()) {
            $menu['config'] = [
                'title' => __('Configurações', 'smartdocs'),
                'page'  => self::frontUrl('config.form.php'),
                'icon'  => 'ti ti-settings',
            ];
        }

        if ($menu === []) {
            return [];
        }

        $menu['title']            = __('SmartDocs', 'smartdocs');
        $menu['is_multi_entries'] = true;

        return $menu;
    }

    /**
     * URL relativa à raiz web do GLPI para uma página do plugin.
     */
    public static function frontUrl(string $page): string
    {
        return '/plugins/smartdocs/front/' . $page;
    }

    /**
     * Remove links nulos (sem permissão) preservando os demais.
     *
     * @param array<string, string|null> $links
     * @return array<string, string>
     */
    private static function filteredLinks(array $links): array
    {
        /** @var array<string, string> $filtered */
        $filtered = array_filter($links, static fn (?string $url): bool => $url !== null);

        return $filtered;
    }
}