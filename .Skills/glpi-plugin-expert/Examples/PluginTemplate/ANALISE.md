# PluginTemplate — O que estudar

**Repositório:** https://github.com/glpi-project/plugin-template

## O que é

Esqueleto oficial mantido pela equipe GLPI, pensado como ponto de partida de um plugin novo — ao contrário do plugin `example` (ver nota abaixo), é adequado como base real de projeto.

## O que estudar

- **Layout de diretórios de referência**: confirmar contra `GLPI10/01-Plugin-Structure.md` se o padrão adotado nesta KB está alinhado com o oficial mais recente.
- **`setup.php`/`hook.php` mínimos**: comparar a estrutura de metadados (`plugin_version_*`) com o template desta KB (`Templates/PluginSkeleton/SKELETON.md`).
- **Arquivos de build/release**: como o template organiza scripts de empacotamento (relevante para `Checklists/ReleaseChecklist.md`).
- **Convenções de nomenclatura de arquivo** em `tools/`/CI, se presentes.

## Nota importante

O plugin `example`/`pluginsGLPI/example` é **diferente** deste — é um plugin de demonstração de hooks (ver `Examples/` mais abaixo não incluído por não estar na lista original, mas referenciado em vários documentos técnicos como `GLPI10/03-Hooks.md`). A comunidade oficial recomenda explicitamente **não usar `example` como base de projeto novo** (partes estão desatualizadas) — usar `plugin-template` para isso.

## Quando consultar

Ao iniciar um plugin novo do zero, antes de aplicar `Templates/PluginSkeleton/SKELETON.md` desta KB — para confirmar que a estrutura desta KB continua alinhada com a convenção oficial mais recente.
