# Inventory — O que estudar

**Repositório:** https://github.com/glpi-project/glpi-inventory-plugin

## O que é

Plugin oficial que estende o inventário nativo do GLPI 10.x/11.x com tarefas avançadas: descoberta de rede, inventário SNMP remoto, deploy de pacotes.

## O que estudar

- **Orquestração de tarefas assíncronas em escala** (agentes reportando de forma distribuída) — vai além do `CronTask` simples de `GLPI10/15-Cron.md`, mostrando um padrão de fila/task management mais sofisticado.
- **Integração com o Rules Engine de importação/vinculação nativo** — como o plugin adiciona dados (SNMP, rede) que alimentam o MESMO pipeline de regras descrito em `GLPI10/17-Inventory.md`, em vez de criar um pipeline paralelo.
- **Uso de PHPUnit como framework de teste** (`README.tests.md` do próprio repositório) — referência atual e oficial para `GLPI10/25-Testing.md`, incluindo o padrão de bootstrap via `bin/console glpi:plugin:install`/`activate`.
- **Gestão de "unknown devices"/quarentena** antes de virar asset real — reforça o conceito central de `17-Inventory.md` de que nem todo dado recebido vira registro automaticamente.

## Quando consultar

Ao integrar com ou estender o pipeline de inventário nativo, e como referência atualizada de estrutura de testes PHPUnit em plugin oficial mantido pela própria equipe GLPI.
