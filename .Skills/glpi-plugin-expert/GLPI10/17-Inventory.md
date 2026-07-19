# 17 — Inventário

## Objetivo

Entender a arquitetura de inventário do GLPI 10.x (nativo vs plugin GLPI Inventory vs GLPI Agent), o pipeline de importação (agente → regras → itemtype), e os pontos onde um plugin pode se integrar sem duplicar o que já existe.

## Conceitos

- **Três camadas, não uma:**
  1. **Inventário nativo** (desde o GLPI 10): recebe arquivos de inventário (JSON/XML) de um agente e cria/atualiza itemtypes do core (`Computer`, `NetworkEquipment`, `Monitor`...) via **Rules Engine**. É o "core" — sempre presente.
  2. **Plugin GLPI Inventory**: extensão oficial (marketplace) que adiciona tarefas avançadas — descoberta de rede, inventário SNMP de equipamentos remotos, deploy de pacotes, coleta de dados customizada — coisas que o nativo não cobre.
  3. **GLPI Agent** (software instalado no endpoint, ou "toolbox" sem agente): é quem efetivamente coleta os dados e envia ao servidor GLPI (nativo) ou ao plugin (para tarefas avançadas).
- **Rules Engine de importação/vinculação** é o portão de entrada: todo dado de inventário passa por regras configuráveis ("se X, então importa/recusa/vincula a item Y") antes de virar um registro em `Computer`/etc. Hardware recusado fica em **"Ignored hardware"** (quarentena), não é descartado silenciosamente.
- **Um plugin de negócio comum RARAMENTE precisa reimplementar coleta de inventário** — a superfície de integração típica é: (a) reagir a itens já inventariados via hooks normais de `CommonDBTM` (`ITEM_ADD`/`ITEM_UPDATE` em `Computer` etc.), ou (b) adicionar campos/regras à importação existente, não construir um pipeline de coleta paralelo.

## Funcionamento interno

O agente (GLPI Agent) envia um payload de inventário (via HTTP, ou pelo endpoint stateless dedicado) para o servidor. O core processa esse payload através do motor de regras de "Asset Import and Linking Rules": cada regra pode aceitar, recusar (→ ignored hardware) ou definir como o item deve ser vinculado a um registro já existente (evitando duplicar o mesmo computador a cada inventário). Uma vez aceito, o resultado é um `add()`/`update()` normal em itemtypes do core — que dispara os hooks padrão (`ITEM_ADD`/`ITEM_UPDATE`) como qualquer outra operação de `CommonDBTM`.

O plugin GLPI Inventory adiciona uma camada de orquestração de tarefas (descoberta de rede, SNMP, deploy) que também termina, no fim das contas, alimentando os mesmos itemtypes e o mesmo motor de regras.

## Fluxograma

```
GLPI Agent (endpoint)
      │  envia payload de inventário
      ▼
Inventário nativo (core) ── ou ── Plugin GLPI Inventory (tarefas avançadas)
      │
      ▼
Rules Engine (Asset Import and Linking Rules)
      │
      ├── aceito → vincula a item existente OU cria novo
      │                │
      │                ▼
      │           Computer::add()/update()  ← hooks padrão disparam aqui
      │
      └── recusado → Ignored hardware (quarentena)
```

## Exemplos corretos

### Reagir a um computador recém-inventariado (via hook padrão, não via pipeline de inventário)

```php
<?php
// setup.php
use Glpi\Plugin\Hooks;
use Computer;
use GlpiPlugin\Meuplugin\ComputerObserver;

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['meuplugin'] = [
        Computer::class => [ComputerObserver::class, 'aoInventariar'],
    ];
}
```

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use Computer;

final class ComputerObserver
{
    /**
     * Roda tanto para computadores criados manualmente quanto para os
     * criados pelo pipeline de inventário — ambos passam por add().
     */
    public static function aoInventariar(Computer $computer): void
    {
        // ex.: sincronizar com um sistema externo, registrar auditoria...
    }
}
```

### Adicionando um campo próprio à regra de importação (avançado)

```php
// Regras de importação/vinculação são configuráveis via UI (Regras >
// Regras de importação e vínculo de equipamentos). Um plugin pode
// expor CRITÉRIOS ou AÇÕES customizados para essas regras através
// do motor de regras do core (RuleImportAsset), análogo a qualquer
// outra extensão de RuleCollection — ver documentação do motor de
// regras do core para o contrato exato de criteria/actions customizados.
```

## Exemplos incorretos

```php
// ERRADO: escrever um endpoint próprio para "receber inventário" e
// processar payload de agente manualmente, duplicando o pipeline
// nativo (parsing, regras, vinculação) que já existe e é mantido
// pelo core. Reinventa uma superfície de ataque nova sem necessidade.
```

```php
// ERRADO: tentar interceptar o inventário ANTES do Rules Engine para
// aplicar lógica de negócio — a integração correta é DEPOIS, reagindo
// ao item já persistido via ITEM_ADD/ITEM_UPDATE, respeitando o que
// as regras de importação decidiram.
```

## Boas práticas

- Trate "inventário" como um detalhe de como `Computer`/`NetworkEquipment`/etc. chegam a existir — sua integração de plugin deve reagir ao itemtype resultante (hooks padrão), não ao protocolo de inventário em si.
- Para necessidades de importação/vinculação customizadas, estenda o motor de regras nativo (critérios/ações customizados) em vez de duplicar a lógica de aceitar/recusar/vincular.
- Se o plugin precisa mesmo de coleta de dados fora do padrão (ex.: um sensor proprietário), modele como uma integração separada que ALIMENTA um itemtype via API/CRUD normal — não como um "inventário paralelo".

## Anti-patterns

- Parsing manual de payload de agente GLPI Agent dentro de um plugin de negócio comum.
- Ignorar a quarentena ("Ignored hardware") e assumir que todo dado enviado pelo agente vira um registro.
- Duplicar lógica de deduplicação/vinculação que o Rules Engine de importação já resolve.

## Checklist

- [ ] Integração feita via hooks padrão de itemtype (`ITEM_ADD`/`ITEM_UPDATE`), não via interceptação do pipeline de inventário
- [ ] Nenhum parser de payload de agente duplicado dentro do plugin
- [ ] Extensões de regra de importação (se existirem) usam o motor de regras nativo
- [ ] Nenhuma suposição de que todo dado do agente vira item automaticamente (quarentena existe)

## Dicas de performance

- Hooks reagindo a inventário em massa (importações grandes, centenas de máquinas) rodam uma vez por item — mantenha o callback leve; processamento pesado vai para cron assíncrono.

## Dicas de segurança

- Se o plugin expõe endpoint que recebe dado de agente (caso avançado, raro), trate como entrada não confiável: valide e nunca confie em campos de identificação (serial, hostname) sem checagem de duplicidade.
- O pipeline nativo já lida com autenticação de agente; não reimplemente isso — reaproveite o mecanismo existente sempre que possível.

## Referências

- Diferenças nativo vs plugin vs agent: https://www.glpi-project.org/en/glpi-inventory-whats-the-difference-between-the-native-the-plugin-and-the-glpi-agent-toolbox/
- Repositório do plugin oficial: https://github.com/glpi-project/glpi-inventory-plugin
- Guia de inventário de computador: https://help.glpi-project.org/tutorials/inventory/computer_inventory
- Ignored hardware / regras de importação: https://help.glpi-project.org/doc-plugins/plugins-glpi/glpi-inventory
- Documentos relacionados: `03-Hooks.md`, `02-Lifecycle.md`
