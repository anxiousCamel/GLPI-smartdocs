# 20 — Twig

## Objetivo

Usar `TemplateRenderer` e templates Twig corretamente em views de plugin: namespace do plugin, extensão de templates genéricos do core, macros de formulário, e por que isso é o caminho oficial (não uma opção estética).

## Conceitos

- **`Glpi\Application\View\TemplateRenderer`** é o singleton que renderiza templates Twig no GLPI. Ao construir, ele varre todos os plugins ativos e registra, para cada um, um **namespace Twig próprio** derivado do nome do diretório do plugin: a pasta física `plugins/meuplugin/templates/` vira o namespace `@meuplugin` — sem incluir a palavra `templates` no caminho usado nos includes.
- **`TemplateRenderer::getInstance()->display('@meuplugin/arquivo.html.twig', $vars)`** — imprime direto; `render()` retorna a string (útil quando o resultado ainda vai ser embutido em outro contexto).
- **Estender, não reescrever**: para forms de itemtype, o padrão oficial é `{% extends "generic_show_form.html.twig" %}` — herda cabeçalho, botões, estrutura e comportamento padrão do form; o plugin só sobrescreve o bloco de campos extras.
- **Macros de campo prontas**: `{% import "components/form/fields_macros.html.twig" as fields %}` expõe helpers (`fields.textField`, `fields.dropdownField`, `fields.largeTextField` etc.) que já cuidam de rótulo, escaping, layout e integração com o restante do form — usar isso em vez de `<input>` cru.
- **Auto-escape é o padrão** do Twig no GLPI: qualquer `{{ variavel }}` é escapada automaticamente para o contexto HTML. É a razão pela qual Twig elimina de saída a classe inteira de bugs de XSS que `echo` manual introduz.

## Funcionamento interno

`TemplateRenderer` registra um `FilesystemLoader` do Twig com um path por plugin ativo, associando `templates/` de cada plugin ao respectivo `@namespace`. Ao chamar `display('@meuplugin/x.html.twig', [...])`, o loader resolve o caminho físico automaticamente — o código do plugin nunca precisa (nem deve) construir esse caminho manualmente com `../../../`, o que é frágil e já gerou bugs reportados no próprio core quando tentado.

## Fluxograma

```
showForm($ID, $options)
      │
      ▼
$this->initForm($ID, $options)     ← prepara dados padrão do CommonDBTM
      │
      ▼
TemplateRenderer::getInstance()->display('@meuplugin/coisa.form.html.twig', [
    'item' => $this, 'params' => $options
])
      │
      ▼
Twig resolve @meuplugin → plugins/meuplugin/templates/
      │
      ▼
coisa.form.html.twig { % extends "generic_show_form.html.twig" % }
      │  bloco more_fields sobrescrito com campos do plugin
      ▼
HTML final (auto-escapado)
```

## Exemplos corretos

### showForm delegando a um template que estende o genérico

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

use Glpi\Application\View\TemplateRenderer;

public function showForm($ID, $options = [])
{
    $this->initForm($ID, $options);

    TemplateRenderer::getInstance()->display('@meuplugin/coisa.form.html.twig', [
        'item'   => $this,
        'params' => $options,
    ]);

    return true;
}
```

```twig
{# plugins/meuplugin/templates/coisa.form.html.twig — namespace @meuplugin #}
{% extends "generic_show_form.html.twig" %}
{% import "components/form/fields_macros.html.twig" as fields %}

{% block more_fields %}
    {{ fields.textField('name', item.fields['name'], __('Nome', 'meuplugin')) }}
    {{ fields.dropdownField(
        'Entity',
        'entities_id',
        item.fields['entities_id'],
        __('Entidade', 'meuplugin')
    ) }}
    {{ fields.largeTextField(
        'comentario',
        item.fields['comentario'],
        __('Comentário', 'meuplugin')
    ) }}
{% endblock %}
```

### Renderizando conteúdo de aba (não é form completo)

```php
<?php
// Em GlpiPlugin\Meuplugin\ComputerExtra::displayTabContentForItem

TemplateRenderer::getInstance()->display('@meuplugin/computer_extra.html.twig', [
    'computer' => $item,
]);
```

```twig
{# plugins/meuplugin/templates/computer_extra.html.twig #}
<div class="card">
    <div class="card-body">
        <strong>{{ __('Informações extras', 'meuplugin') }}</strong>
        <p>{{ computer.fields['name'] }}</p> {# auto-escapado #}
    </div>
</div>
```

## Exemplos incorretos

```php
// ERRADO: caminho relativo manual em vez do namespace @plugin —
// frágil, depende da estrutura de diretórios em runtime, e é
// justamente o tipo de bug já reportado no core (issue #20360).
TemplateRenderer::getInstance()->display(
    '../../plugins/meuplugin/templates/coisa.form.html.twig',
    [...]
);
```

```twig
{# ERRADO: reescrever o form inteiro do zero em vez de estender o
   genérico — perde cabeçalho, botões padrão, e qualquer melhoria
   futura que o core faça no template base. #}
<form>
  <input name="name" value="{{ item.fields['name'] }}">
</form>
```

```twig
{# ERRADO: usar o filtro |raw sem necessidade genuína — reabre
   manualmente o escape automático que o Twig oferece de graça,
   reintroduzindo risco de XSS que a migração para Twig existe
   justamente para eliminar. #}
<p>{{ item.fields['comentario']|raw }}</p>
```

## Boas práticas

- Sempre `@nomedoplugin/arquivo.html.twig`, nunca caminho relativo/absoluto manual.
- Sempre `{% extends "generic_show_form.html.twig" %}` para forms de itemtype; use as macros de campo padrão (`fields_macros.html.twig`) em vez de HTML cru.
- Trate `|raw` como bandeira vermelha: só se justifica quando o conteúdo já foi deliberadamente sanitizado/gerado como HTML de confiança — nunca em dado vindo direto de input de usuário.
- Para fragmentos reutilizáveis dentro do próprio plugin, use `{% include %}`/macros próprias do plugin, mantendo consistência com os componentes do core.

## Anti-patterns

- Caminho de template construído manualmente com `../`.
- HTML de form escrito via `echo`/concatenação em vez de template Twig.
- Uso de `|raw` como atalho para "resolver" um problema de formatação, sem avaliar o risco de XSS.
- Duplicar em Twig a estrutura inteira do form genérico do core em vez de estendê-lo.

## Checklist

- [ ] Todo template do plugin acessado via namespace `@nomedoplugin`
- [ ] Forms de itemtype estendem `generic_show_form.html.twig`
- [ ] Campos usam as macros de `fields_macros.html.twig`, não HTML cru
- [ ] Nenhum uso de `|raw` sobre dado de usuário não sanitizado
- [ ] Pasta física correspondente ao namespace chamada exatamente `templates/`

## Dicas de performance

- Twig compila templates para cache PHP na primeira renderização — evite lógica pesada dentro do template (isso deve estar em PHP, passado já pronto via variáveis).
- Prefira passar dados já processados (arrays simples) ao template, evitando chamar métodos custosos repetidamente dentro de loops Twig.

## Dicas de segurança

- Auto-escape é a razão de existir dessa camada — nunca contorne com `|raw` sobre conteúdo que veio (direta ou indiretamente) de input de usuário.
- Mesmo com auto-escape, dado usado em atributos HTML sensíveis (`href`, `onclick`) merece atenção redobrada — prefira as macros do core, que já tratam esses contextos corretamente.

## Referências

- Confirmação oficial do padrão `@plugin/template.html.twig`: https://forum.glpi-project.org/viewtopic.php?id=289907
- Tutorial oficial (showForm + Twig): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Documentos relacionados: `07-CommonDBTM.md`, `08-CommonGLPI.md`, `22-Tabler.md`, `30-Security.md`
