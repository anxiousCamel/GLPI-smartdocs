<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Página de diagnóstico do plugin.
 * Thin: valida sessão e delega ao DiagnosticController.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

Html::header(
    __('SmartDocs — Diagnóstico', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs'
);

(new \GlpiPlugin\SmartDocs\Controllers\DiagnosticController())->show();

Html::footer();