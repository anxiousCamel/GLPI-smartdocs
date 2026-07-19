# 15 — Cron (CronTask)

## Objetivo

Registrar e implementar tarefas agendadas (automatic actions) de plugin usando `CronTask`, entendendo os dois modos de execução (GLPI/interno vs CLI/externo) e como reportar progresso corretamente.

## Conceitos

- **`CronTask` é o mecanismo unificado de agendamento** do GLPI para core e plugins — a tela *Configurar > Ações automáticas* lista TODAS as tarefas, nativas e de plugin, de forma idêntica.
- **Dois modos de disparo (`allowmode`), não mutuamente exclusivos:**
  - **GLPI/interno** (`CronTask::MODE_INTERNAL = 1`): disparado como efeito colateral de qualquer request HTML — o core injeta uma imagem invisível que, ao ser carregada, aciona `front/cron.php` em processo separado. Deriva de atividade de usuários logados; para em fins de semana/madrugada sem acesso.
  - **CLI/externo** (`CronTask::MODE_EXTERNAL = 2`): disparado por um cron do sistema operacional rodando `php front/cron.php` periodicamente. **Recomendado para produção** — independe de tráfego de usuários.
  - `3` permite ambos os modos simultaneamente (o admin escolhe na UI).
- **Registro acontece no install**, via `CronTask::register($itemtype, $nomeAcao, $frequenciaSegundos, $opcoes)`. O callback correspondente é um método **estático** `cron<NomeAcao>(CronTask $task)` na classe informada, que retorna um inteiro (`1` = executou algo, `0` = nada a fazer, negativo = erro) e pode chamar `$task->addVolume(N)` para registrar quantos itens processou (aparece na coluna "Volume" dos logs).
- **`cronInfo($name)`** (estático) descreve a tarefa na UI (nome amigável, descrição do parâmetro, se aplicável).

## Funcionamento interno

`front/cron.php` (executado tanto pelo modo interno quanto pelo CLI) obtém um lock de banco, seleciona as tarefas elegíveis (frequência vencida, dentro do horário de execução configurado, respeitando o modo), e para cada uma invoca dinamicamente `<Itemtype>::cron<NomeAcao>($cronTaskInstance)`. Erros de execução, duração e volume processado ficam registrados em `glpi_crontasklogs`, visíveis na aba de logs da tarefa.

## Fluxograma

```
plugin_meuplugin_install()
      │
      ▼
CronTask::register(Coisa::class, 'sincronizar', 3600, [
    'state'     => CronTask::STATE_WAITING,
    'allowmode' => CronTask::MODE_EXTERNAL,
    'comment'   => '...',
])
      │
      ▼
(periodicamente) front/cron.php roda via CLI (cron do SO)
      │
      ▼
verifica tarefas elegíveis por frequência/modo/horário
      │
      ▼
Coisa::cronSincronizar($task)
      │  processa; $task->addVolume($n); return 1|0|-1
      ▼
grava log de execução (duração, volume, sucesso/erro)
```

## Exemplos corretos

### Registro no install

```php
<?php
// hook.php ou classe de schema, chamado por plugin_meuplugin_install()

use CronTask;
use GlpiPlugin\Meuplugin\Coisa;

function registrarCronDoPlugin(): void
{
    CronTask::register(
        Coisa::class,
        'sincronizar',
        3600, // frequência em segundos (aqui, a cada hora)
        [
            'state'     => CronTask::STATE_WAITING,
            'mode'      => CronTask::MODE_EXTERNAL, // sugestão inicial; admin pode mudar na UI
            'allowmode' => CronTask::MODE_EXTERNAL | CronTask::MODE_INTERNAL,
            'logs_lifetime' => 30, // dias de retenção do log
            'comment'   => __('Sincroniza Coisas com o serviço externo.', 'meuplugin'),
        ]
    );
}
```

### Implementação do callback

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;
use CronTask;

class Coisa extends CommonDBTM
{
    /**
     * Descreve a tarefa para a UI de Ações Automáticas.
     *
     * @return array{description: string}
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
     * Executa a sincronização. Retorna 1 se processou algo, 0 se nada
     * a fazer, negativo em caso de erro.
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
        // ... query via $DB->request(...)
        return [];
    }

    private static function sincronizarUma(array $coisa): bool
    {
        // ... lógica real
        return true;
    }
}
```

## Exemplos incorretos

```php
// ERRADO: lógica de negócio pesada rodando via modo INTERNAL como
// único modo permitido — em instância com pouco tráfego de usuários
// logados, a tarefa praticamente nunca dispara.
CronTask::register(Coisa::class, 'sincronizar', 3600, [
    'allowmode' => CronTask::MODE_INTERNAL, // sem permitir EXTERNAL
]);
```

```php
// ERRADO: callback que nunca retorna volume nem status coerente —
// a UI de logs fica sem informação útil para o administrador
// diagnosticar se a tarefa está realmente fazendo algo.
public static function cronSincronizar(CronTask $task): int
{
    self::sincronizarTudo();
    return 1; // sempre "1", mesmo quando não havia nada a fazer
}
```

```php
// ERRADO: processar volume ilimitado numa única execução de cron
// sem paginação/limite — trava a tarefa (e o lock de banco) por
// tempo indefinido, atrasando as demais ações automáticas.
```

## Boas práticas

- Sempre permita `MODE_EXTERNAL` (o modo recomendado pela própria doc oficial) — não force só `MODE_INTERNAL`.
- Retorne `0` quando não havia nada a processar; `1` só quando processou algo de fato; negativo para erro real.
- Use `$task->addVolume()` sempre que a tarefa processa uma quantidade variável de itens — é a métrica que aparece nos logs para auditoria.
- Limite o volume processado por execução (ex.: `LIMIT` na query de pendentes) para não monopolizar o cron; deixe a próxima execução continuar de onde parou.
- Documente a tarefa (`cronInfo`) com uma descrição clara — é o que o administrador vê ao decidir a frequência.

## Anti-patterns

- Tarefa que nunca retorna `0` mesmo sem trabalho a fazer (dificulta saber se está "realmente" fazendo algo útil).
- Volume de processamento sem limite, monopolizando o slot de execução do cron.
- Lógica de cron duplicando o que já poderia ser um hook de item (ex.: reagir a um evento como se fosse polling periódico, quando `ITEM_ADD` resolveria em tempo real).
- Ignorar `logs_lifetime`, deixando `glpi_crontasklogs` crescer sem limite.

## Checklist

- [ ] `CronTask::register()` chamado no install com frequência e `allowmode` adequados
- [ ] `cron<Nome>($task)` retorna 1/0/negativo de forma consistente com o trabalho real realizado
- [ ] `$task->addVolume()` chamado quando aplicável
- [ ] Processamento por lote com limite, não "tudo de uma vez"
- [ ] `cronInfo()` implementado com descrição útil

## Dicas de performance

- Cron compete por um lock de banco com as demais tarefas — mantenha a execução de cada rodada curta (segundos, não minutos) processando em lotes pequenos e recorrentes.
- Prefira consultas indexadas para localizar "pendentes" (ex.: coluna de status/flag) a varrer a tabela inteira a cada execução.

## Dicas de segurança

- O código de cron roda com privilégios plenos e sem contexto de usuário logado — nunca assuma `Session::haveRight` disponível da forma usual; trate a tarefa como processo de sistema, validando dados como se viessem de fonte não confiável quando envolvem I/O externo.
- Se a tarefa faz chamadas externas (rede), trate timeouts e falhas de forma que uma falha externa não deixe o lock preso indefinidamente.

## Referências

- Automatic actions (CronTask) oficial: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/crontasks.html
- Automatic actions — guia do usuário: https://help.glpi-project.org/documentation/modules/configuration/crontasks
- Documentos relacionados: `02-Lifecycle.md`, `29-Performance.md`
