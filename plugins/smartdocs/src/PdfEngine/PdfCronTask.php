<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Integração do processamento de fila PDF com o sistema CronTask do GLPI.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\PdfEngine;

use CommonGLPI;
use CronTask;

final class PdfCronTask extends CommonGLPI
{
    public static function getTypeName($nb = 0): string
    {
        return __('Fila de Geração de PDF', 'smartdocs');
    }

    /**
     * Registra informações do cron no GLPI.
     *
     * @return array<string, mixed>
     */
    public static function cronInfo(): array
    {
        return [
            'description' => __('Processa jobs pendentes de geração de PDF', 'smartdocs'),
            'parameter'   => __('Número máximo de jobs por execução', 'smartdocs'),
        ];
    }

    /**
     * Executa o processamento da fila.
     *
     * @param CronTask $task Instância do CronTask do GLPI
     *
     * @return int Número de jobs processados
     */
    public static function cronProcessPdfQueue(CronTask $task): int
    {
        $maxJobs = (int) ($task->fields['param'] ?? 5);
        $queue = new PdfQueue();
        $processed = 0;

        for ($i = 0; $i < $maxJobs; $i++) {
            try {
                $queue->processNext();
                $processed++;
            } catch (\Throwable $e) {
                // Loga o erro e continua com o próximo job
                \Toolbox::logInFile(
                    'smartdocs-cron',
                    sprintf("Erro no job %d: %s\n", $i, $e->getMessage())
                );
            }
        }

        return $processed;
    }
}
