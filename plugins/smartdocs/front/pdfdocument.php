<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Lista de Documentos PDF.
 *
 * Thin: delega ao sistema de Search do GLPI.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_READ
);

Html::header(
    __('Documentos PDF', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'documents'
);

Search::show('GlpiPlugin\\SmartDocs\\Documents\\PdfDocument');

Html::footer();
