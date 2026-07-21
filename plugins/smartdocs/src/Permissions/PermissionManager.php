<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Gerenciador de permissões do plugin.
 *
 * Integra-se ao sistema de perfis do GLPI (ProfileRight) expondo uma
 * aba "SmartDocs" na tela de perfis e fornecendo verificações de
 * direito para todas as camadas do plugin.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Permissions;

use CommonGLPI;
use Html;
use Profile;
use ProfileRight;
use Session;

final class PermissionManager extends CommonGLPI
{
    /** Ver templates. */
    public const SMARTDOCS_TEMPLATE_READ = 1;

    /** Criar/editar templates. */
    public const SMARTDOCS_TEMPLATE_WRITE = 2;

    /** Ver documentos. */
    public const SMARTDOCS_DOCUMENT_READ = 4;

    /** Criar/preencher documentos. */
    public const SMARTDOCS_DOCUMENT_WRITE = 8;

    /** Usar scanner OCR. */
    public const SMARTDOCS_OCR_USE = 16;

    /** Configurar o plugin. */
    public const SMARTDOCS_ADMIN = 32;

    /** Todos os direitos somados (bitmask). */
    public const SMARTDOCS_ALL_RIGHTS =
        self::SMARTDOCS_TEMPLATE_READ
        | self::SMARTDOCS_TEMPLATE_WRITE
        | self::SMARTDOCS_DOCUMENT_READ
        | self::SMARTDOCS_DOCUMENT_WRITE
        | self::SMARTDOCS_OCR_USE
        | self::SMARTDOCS_ADMIN;

    /** Nome do direito registrado em glpi_profilerights. */
    public const RIGHT_NAME = 'plugin_smartdocs';

    /** Perfil padrão de super-administrador em instalações GLPI. */
    private const SUPER_ADMIN_PROFILE_ID = 4;

    // -----------------------------------------------------------------
    // Instalação / desinstalação
    // -----------------------------------------------------------------

    /**
     * Registra o direito do plugin e concede acesso total aos perfis
     * administrativos existentes. Idempotente.
     */
    public static function installDefaultRights(): void
    {
        ProfileRight::addProfileRights([self::RIGHT_NAME]);

        foreach (self::findAdminProfileIds() as $profiles_id) {
            ProfileRight::updateProfileRights($profiles_id, [
                self::RIGHT_NAME => self::SMARTDOCS_ALL_RIGHTS,
            ]);
        }
    }

    /**
     * Remove o direito do plugin de todos os perfis.
     */
    public static function uninstallRights(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->delete('glpi_profilerights', ['name' => self::RIGHT_NAME]);
    }

    /**
     * Perfis com direito de alterar perfis recebem acesso total por padrão;
     * na ausência deles, o perfil Super-Admin (id 4).
     *
     * @return int[]
     */
    private static function findAdminProfileIds(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $ids = [];
        $iterator = $DB->request([
            'SELECT' => 'profiles_id',
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'name'   => 'profile',
                ['rights' => ['&', UPDATE]],
            ],
        ]);

        foreach ($iterator as $row) {
            $ids[] = (int) $row['profiles_id'];
        }

        if ($ids === []) {
            $ids[] = self::SUPER_ADMIN_PROFILE_ID;
        }

        return $ids;
    }

    // -----------------------------------------------------------------
    // Verificações de direito (camadas do plugin)
    // -----------------------------------------------------------------

    public static function canReadTemplates(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_NAME, self::SMARTDOCS_TEMPLATE_READ);
    }

    public static function canWriteTemplates(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_NAME, self::SMARTDOCS_TEMPLATE_WRITE);
    }

    public static function canReadDocuments(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_NAME, self::SMARTDOCS_DOCUMENT_READ);
    }

    public static function canWriteDocuments(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_NAME, self::SMARTDOCS_DOCUMENT_WRITE);
    }

    public static function canUseOcr(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_NAME, self::SMARTDOCS_OCR_USE);
    }

    public static function canAdmin(): bool
    {
        return (bool) Session::haveRight(self::RIGHT_NAME, self::SMARTDOCS_ADMIN);
    }

    /**
     * Interrompe a requisição com erro 403 se o direito não existir.
     */
    public static function checkRight(int $right): void
    {
        Session::checkRight(self::RIGHT_NAME, $right);
    }

    /**
     * Rótulos dos direitos para a matriz de permissões.
     *
     * @return array<int, string>
     */
    public static function getRightsLabels(): array
    {
        return [
            self::SMARTDOCS_TEMPLATE_READ  => __('Ver templates', 'smartdocs'),
            self::SMARTDOCS_TEMPLATE_WRITE => __('Criar/editar templates', 'smartdocs'),
            self::SMARTDOCS_DOCUMENT_READ  => __('Ver documentos', 'smartdocs'),
            self::SMARTDOCS_DOCUMENT_WRITE => __('Criar/preencher documentos', 'smartdocs'),
            self::SMARTDOCS_OCR_USE        => __('Usar scanner OCR', 'smartdocs'),
            self::SMARTDOCS_ADMIN          => __('Configurar o plugin', 'smartdocs'),
        ];
    }

    // -----------------------------------------------------------------
    // Aba na tela de perfis do GLPI
    // -----------------------------------------------------------------

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            return __('SmartDocs', 'smartdocs');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getID() > 0) {
            self::showRightsForm($item);
        }

        return true;
    }

    /**
     * Renderiza a matriz de direitos do plugin dentro do formulário do
     * perfil (o salvamento é feito pelo próprio fluxo do GLPI, que
     * processa os inputs "_plugin_smartdocs").
     */
    private static function showRightsForm(Profile $profile): void
    {
        $canedit = Session::haveRight('profile', UPDATE);

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form method='post' action='" . htmlescape($profile->getFormURL()) . "'>";
        }

        $profile->displayRightsChoiceMatrix(
            [
                [
                    'itemtype' => self::class,
                    'label'    => __('SmartDocs', 'smartdocs'),
                    'field'    => self::RIGHT_NAME,
                    'rights'   => self::getRightsLabels(),
                ],
            ],
            [
                'canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __('Permissões do SmartDocs', 'smartdocs'),
            ]
        );

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profile->getID()]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";
    }
}