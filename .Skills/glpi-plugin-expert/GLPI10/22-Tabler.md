# 22 — Tabler

## Objetivo

Usar corretamente o Tabler (framework de UI baseado em Bootstrap adotado pelo GLPI desde a reformulação visual do 10.x/11.x) em telas de plugin, aproveitando classes, variáveis de tema e componentes já disponíveis em vez de estilizar do zero.

## Conceitos

- **Tabler é a camada visual do GLPI moderno**: cards, grids, botões, badges, formulários seguem as classes utilitárias e componentes do Tabler (que por sua vez é construído sobre Bootstrap). O GLPI customiza cores/tema via CSS custom properties (variáveis CSS) por cima do Tabler — não via override bruto de classes.
- **Um plugin bem-comportado usa as MESMAS classes do Tabler já carregadas globalmente pelo core** (`.card`, `.card-body`, `.btn`, `.table`, `.badge`, grid `.row`/`.col-*`) em vez de trazer CSS próprio para replicar esses componentes.
- **CSS próprio do plugin deve ser mínimo e aditivo**: apenas o que for genuinamente específico do domínio do plugin, registrado via hook `ADD_CSS`, nunca um framework CSS paralelo completo.
- **Temas**: o GLPI suporta tema claro/escuro via variáveis CSS; qualquer CSS custom do plugin que hardcode cores (`#ffffff`, `#000`) quebra a troca de tema — use as variáveis CSS do tema quando existir equivalente.

## Funcionamento interno

O core carrega o CSS compilado do Tabler (+ customizações GLPI) globalmente em toda página autenticada. Templates Twig do core e de plugins compartilham essas classes automaticamente — nenhuma ação extra é necessária para "ativar" o Tabler num template de plugin, basta usar as classes certas. Hooks `ADD_CSS`/`ADD_JAVASCRIPT` só devem trazer o que for exclusivo do plugin (ex.: um layout de dashboard muito específico).

## Fluxograma

```
Página autenticada carrega
      │
      ▼
CSS core (Tabler + tema GLPI) já presente globalmente
      │
      ▼
Template Twig do plugin usa classes Tabler padrão (.card, .btn, .table...)
      │
      ▼
(opcional) hook ADD_CSS carrega SÓ o CSS específico do domínio do plugin
```

## Exemplos corretos

### Reaproveitando componentes Tabler num template de plugin

```twig
{# plugins/meuplugin/templates/dashboard_coisa.html.twig #}
<div class="row row-cards">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="card-title">{{ __('Total de Coisas', 'meuplugin') }}</div>
                <div class="h1">{{ total }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <span class="badge bg-green-lt">{{ __('Aprovadas', 'meuplugin') }}</span>
                <div class="h1">{{ aprovadas }}</div>
            </div>
        </div>
    </div>
</div>
```

### CSS próprio, mínimo e aditivo (usando variável de tema)

```css
/* plugins/meuplugin/public/css/meuplugin.css */
.meuplugin-status-badge {
    /* usa a variável de cor do tema em vez de hardcode */
    background-color: var(--tblr-success);
    color: var(--tblr-body-bg);
}
```

```php
<?php
// setup.php
use Glpi\Plugin\Hooks;

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['meuplugin'] = 'public/css/meuplugin.css';
}
```

## Exemplos incorretos

```twig
{# ERRADO: markup e classes inteiramente próprias reimplementando o
   que .card/.row/.col-* do Tabler já fazem — quebra a consistência
   visual com o resto do GLPI e dobra o CSS a manter. #}
<div class="meuplugin-box">
    <div class="meuplugin-box-header">Total de Coisas</div>
</div>
```

```css
/* ERRADO: cor hardcoded — quebra ao trocar para tema escuro,
   já que ignora as variáveis de tema do GLPI/Tabler. */
.meuplugin-status-badge {
    background-color: #28a745;
    color: #ffffff;
}
```

```php
// ERRADO: carregar um framework CSS completo próprio (ex.: Bootstrap
// de outra versão) junto ao plugin — conflita com classes do Tabler
// já carregado e infla o payload da página sem necessidade.
```

## Boas práticas

- Antes de escrever qualquer CSS, verifique se uma classe Tabler já existente resolve (cards, badges, grid, tabelas, alerts, botões).
- CSS próprio do plugin usa variáveis CSS do tema (`var(--tblr-*)`) para cores, garantindo compatibilidade com tema escuro/claro.
- Prefixe classes próprias com o nome do plugin (`.meuplugin-*`) para evitar colisão com classes do core ou de outros plugins.
- Componentes de dashboard/cards seguem o mesmo padrão visual (`row row-cards`, `col-md-*`) usado nos dashboards nativos do GLPI.

## Anti-patterns

- Reimplementar componentes visuais que o Tabler já oferece.
- Cores hardcoded ignorando variáveis de tema.
- CSS de plugin sem prefixo, colidindo com classes genéricas do core.
- Trazer outro framework CSS/JS de UI paralelo ao Tabler.

## Checklist

- [ ] Componentes visuais reaproveitam classes Tabler existentes
- [ ] CSS próprio é mínimo, prefixado e usa variáveis de tema
- [ ] Nenhum framework CSS paralelo introduzido
- [ ] Testado visualmente em tema claro E escuro (se a instância suportar)

## Dicas de performance

- CSS adicional carregado via `ADD_CSS` soma ao payload de TODA página onde o hook está ativo — condicione o carregamento a páginas relevantes quando o CSS for grande.
- Evite `!important` para "vencer" estilos do Tabler — geralmente indica que a classe certa não foi usada; normalmente basta a especificidade correta.

## Dicas de segurança

- CSS não é vetor de segurança direto, mas classes/estrutura HTML mal formadas podem interagir mal com componentes JS do core (ex.: modais, tooltips) que esperam uma estrutura DOM específica — siga os exemplos de markup do core ao reaproveitar componentes interativos.

## Referências

- HTML Rendering and UI Components (Tabler no core): https://deepwiki.com/glpi-project/glpi/2.2-html-rendering-and-ui-components
- Documentos relacionados: `20-Twig.md`, `21-Vue.md`
