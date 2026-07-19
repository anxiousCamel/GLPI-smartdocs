# Referências — Documentação Oficial

Consolidação das fontes primárias. Nada aqui é cópia; são ponteiros com contexto de uso.

---

## GLPI Developer Documentation

**URL:** https://glpi-developer-documentation.readthedocs.io/en/master/
**Versão:** cobre GLPI 10.x e 11.x (branch `master`; use `/en/latest/` para a versão estável mais recente)
**Data da consulta:** 2026-07-19

**Conteúdo:**
- Plugins (requirements, tutorial, hooks, guidelines)
- Developer API (database/query builder, CommonDBTM, Search, Massive Actions, Rights, Cron, Notificações, Extra tools)
- Coding standards
- Checklists (release de versão)
- Upgrade guides (inclui o guia oficial 10→11)
- Packaging

**Quando consultar:**
Sempre que existir dúvida sobre APIs públicas, assinatura de hooks, ou comportamento oficial documentado. É a fonte de verdade número 1.

**Páginas-chave:**
- Requirements de plugin: `/plugins/requirements.html`
- Tutorial de plugin: `/plugins/tutorial.html`
- Hooks: `/plugins/hooks.html`
- Query builder (DBMysqlIterator): `/devapi/database/dbiterator.html`
- Upgrade 11.0: `/upgradeguides/glpi-11.0.html`

---

## GLPI Help Center (Teclib')

**URL:** https://help.glpi-project.org/
**Versão:** GLPI 10.x / 11.x
**Data da consulta:** 2026-07-19

**Conteúdo:**
- Tutoriais de administração e migração de instâncias
- Procedimentos de migração de plugins específicos 10→11 (Fields, GenericObject, FormCreator → core)

**Quando consultar:**
Procedimentos operacionais de upgrade de instância e migração de dados de plugins descontinuados para o core do GLPI 11.

---

## Repositório do core GLPI

**URL:** https://github.com/glpi-project/glpi
**Versão:** branches `10.0/bugfixes`, `11.0/bugfixes`, `main`
**Data da consulta:** 2026-07-19

**Conteúdo:**
- Código-fonte completo (fonte definitiva do comportamento real)
- `src/` — classes do core (CommonDBTM, CommonGLPI, Migration, Search...)
- `CHANGELOG.md` e issues/PRs

**Quando consultar:**
Quando a documentação for omissa ou ambígua. O comportamento real é o do código. Ler o core NÃO autoriza depender de internals não públicos — apenas entender o contrato.

---

## Repositório da documentação de desenvolvedor (docdev)

**URL:** https://github.com/glpi-project/docdev
**Versão:** master
**Data da consulta:** 2026-07-19

**Quando consultar:**
Para ver histórico/PRs da doc e hooks ainda sem documentação escrita (vários hooks estão marcados como "todo" na doc renderizada; o fonte `.rst` às vezes tem mais contexto).

---

## Plugin Template (esqueleto oficial)

**URL:** https://github.com/glpi-project/plugin-template
**Versão:** compatível com GLPI 10.x
**Data da consulta:** 2026-07-19

**Quando consultar:**
Estrutura inicial de novos plugins: layout de pastas, setup.php, hook.php, arquivos de build, tools de release.

---

## Plugin Example (pluginsGLPI/example)

**URL:** https://github.com/pluginsGLPI/example
**Versão:** acompanha o core; partes antigas podem estar defasadas
**Data da consulta:** 2026-07-19

**Conteúdo:**
Implementação de referência da maioria dos hooks, com valores esperados de retorno.

**Quando consultar:**
Para ver a assinatura prática de um hook. **Atenção:** a comunidade oficial alerta que trechos são antigos e não valem como ponto de partida de plugin — use apenas como referência pontual de hooks, nunca como template.

---

## FormCreator

**URL:** https://github.com/pluginsGLPI/formcreator
**Status:** descontinuado no GLPI 11 (funcionalidade migrada para o core via `bin/console migration:formcreator_plugin_to_core`)
**Data da consulta:** 2026-07-19

**Quando consultar:**
Exemplos maduros de formulários, rights complexos, interface rica e organização de plugin grande em GLPI 10.x. No GLPI 11, estude o módulo de Forms do core.

---

## Fields

**URL:** https://github.com/pluginsGLPI/fields
**Data da consulta:** 2026-07-19

**Quando consultar:**
Campos personalizados: criação dinâmica de tabelas, containers, injeção de campos em forms de itemtypes existentes.

---

## Gantt

**URL:** https://github.com/pluginsGLPI/gantt
**Data da consulta:** 2026-07-19

**Quando consultar:**
Integração de interface: JS externo, componente visual acoplado a itemtypes do core (Project).

---

## GLPI Inventory Plugin

**URL:** https://github.com/glpi-project/glpi-inventory-plugin
**Data da consulta:** 2026-07-19

**Quando consultar:**
Inventário e ativos: tasks de deploy/inventário, comunicação com glpi-agent, extensão do inventário nativo do 10.x.

---

## Generic Object

**URL:** https://github.com/pluginsGLPI/genericobject
**Status:** migrado para o core no GLPI 11 (Custom Assets)
**Data da consulta:** 2026-07-19

**Quando consultar:**
Criação de novos objetos/itemtypes dinâmicos em 10.x. No 11, estude Custom Assets do core.

---

## Behaviors

**URL:** https://github.com/yllen/behaviors
**Data da consulta:** 2026-07-19

**Quando consultar:**
Uso extensivo de hooks de negócio (pre_item_add/update) para alterar comportamento do core sem tocar no core.

---

## PDF

**URL:** https://github.com/pluginsGLPI/pdf
**Data da consulta:** 2026-07-19

**Quando consultar:**
Geração de documentos PDF a partir de itemtypes; exemplo de plugin que expõe pontos de extensão para OUTROS plugins.

---

## Catálogo de plugins

**URL:** https://plugins.glpi-project.org/
**Data da consulta:** 2026-07-19

**Quando consultar:**
Descobrir se já existe plugin para uma necessidade antes de desenvolver; encontrar plugin de referência que faça algo similar ao objetivo.

---

## Fórum oficial

**URL:** https://forum.glpi-project.org/
**Data da consulta:** 2026-07-19

**Quando consultar:**
Dúvidas de borda não documentadas; respostas de desenvolvedores do core (ex.: cconard96) sobre práticas recomendadas.
