<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Lista de Templates PDF.
 *
 * Thin: delega ao sistema de Search do GLPI para exibição paginada.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_TEMPLATE_READ
);

Html::header(
    __('Templates PDF', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'templates'
);

Search::show('GlpiPlugin\\SmartDocs\\Templates\\PdfTemplate');

Html::footer();
