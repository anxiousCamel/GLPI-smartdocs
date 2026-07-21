<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Polyfills de funções globais introduzidas em versões do GLPI mais
 * recentes que o mínimo suportado pelo plugin (10.0.0).
 *
 * `htmlescape()` só existe nativamente a partir do GLPI 11; em versões
 * 10.x a função não está definida e qualquer chamada não qualificada
 * (fora de namespace) resulta em "Call to undefined function". Este
 * arquivo deve ser incluído no namespace global antes de qualquer
 * classe do plugin ser executada.
 * ---------------------------------------------------------------------
 */

if (!function_exists('htmlescape')) {
    /**
     * Replica o comportamento de GlpiPlugin\SmartDocs\GlpiCompat\htmlescape()
     * do GLPI 11: escapa uma string para uso seguro em HTML.
     */
    function htmlescape(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}
