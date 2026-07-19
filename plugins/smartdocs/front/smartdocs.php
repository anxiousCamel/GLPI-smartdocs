<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Página inicial do plugin (painel de status e atalhos dos módulos).
 * Thin: apenas valida a sessão e delega ao DashboardController.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

Html::header(
    __('SmartDocs', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs'
);

(new \GlpiPlugin\SmartDocs\Controllers\DashboardController())->show();

Html::footer();