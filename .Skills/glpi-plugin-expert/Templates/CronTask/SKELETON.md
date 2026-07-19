# Template: CronTask

Tarefa agendada. Ver `GLPI10/15-Cron.md`.

## Registro (no install)

```php
<?php

use CronTask;
use GlpiPlugin\Meuplugin\Coisa;

function registrarCronDoPlugin(): void
{
    CronTask::register(
        Coisa::class,
        'sincronizar',
        3600, // frequência em segundos
        [
            'state'         => CronTask::STATE_WAITING,
            'mode'          => CronTask::MODE_EXTERNAL,
            'allowmode'     => CronTask::MODE_EXTERNAL | CronTask::MODE_INTERNAL,
            'logs_lifetime' => 30,
            'comment'       => __('Sincroniza Coisas com o serviço externo.', 'meuplugin'),
        ]
    );
}
```

Chame `registrarCronDoPlugin()` dentro de `plugin_meuplugin_install()`.

## Implementação (na classe do itemtype ou classe dedicada)

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

use CronTask;

/**
 * Descreve a tarefa para a UI de Ações Automáticas.
 */
public static function cronInfo(string $name): array
{
    switch ($name) {
        case 'sincronizar':
            return ['description' => __('Sincroniza Coisas com o serviço externo.', 'meuplugin')];
    }
    return [];
}

/**
 * Executa a sincronização. 1 = processou algo; 0 = nada a fazer; negativo = erro.
 */
public static function cronSincronizar(CronTask $task): int
{
    $pendentes = self::buscarPendentesDeSincronizacao();

    if (empty($pendentes)) {
        return 0;
    }

    $processados = 0;
    foreach ($pendentes as $coisa) {
        if (self::sincronizarUma($coisa)) {
            $processados++;
        }
    }

    $task->addVolume($processados);

    return $processados > 0 ? 1 : 0;
}

private static function buscarPendentesDeSincronizacao(): array
{
    // ... query via $DB->request(...), com LIMIT para não monopolizar o cron
    return [];
}

private static function sincronizarUma(array $coisa): bool
{
    // ... lógica real
    return true;
}
```

## Checklist pós-cópia

- [ ] `CronTask::register()` chamado no install com `allowmode` permitindo `MODE_EXTERNAL`
- [ ] `cron<Nome>()` retorna 1/0/negativo consistente com o trabalho real
- [ ] Processamento por lote com `LIMIT`, nunca ilimitado
- [ ] `$task->addVolume()` chamado quando aplicável
