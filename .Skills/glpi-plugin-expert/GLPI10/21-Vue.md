# 21 — Vue no GLPI

## Objetivo

Entender como e quando um plugin usa Vue.js dentro do GLPI: quando faz sentido (interatividade rica que Twig+jQuery não resolve bem), como evitar duplicar a instância do Vue já carregada pelo core, e o esqueleto mínimo de build (webpack) necessário.

## Conceitos

- **Vue é usado seletivamente no core**, não como framework de toda a UI — a maior parte da interface ainda é Twig + jQuery + Tabler. Um plugin só deveria trazer Vue para um componente genuinamente interativo (formulário dinâmico complexo, dashboard com muito estado no cliente) — não para substituir uma tela CRUD comum, que Twig já resolve melhor e mais barato.
- **O core já carrega uma instância global do Vue** (exposta como `window._vue`) e expõe `window.Vue.components` para registro. Um plugin que precisa de componentes Vue **não deve empacotar sua própria cópia do Vue** — deve compilar seus componentes com Vue como `external`, apontando para `window._vue`, evitando duplicar o bundle e garantir que os componentes rodem na mesma instância/reatividade do core.
- **Cada plugin com Vue mantém seu próprio pipeline de build (webpack)** — não existe integração automática de Single File Components (`.vue`) sem esse passo; o core não compila `.vue` de plugin por você.

## Funcionamento interno

O bundle principal do GLPI inicializa o Vue e o disponibiliza globalmente antes de qualquer plugin carregar seu JS. O `externals` do webpack do plugin mapeia `import ... from 'vue'` para essa instância global (`window._vue`) em vez de embutir outra cópia — reduzindo drasticamente o tamanho do bundle do plugin e evitando dois runtimes de Vue coexistindo (o que quebraria reatividade entre componentes). O ponto de entrada do plugin registra os componentes compilados em `window.Vue.components`, de onde templates Twig do plugin podem instanciá-los via tags customizadas/mount points.

## Fluxograma

```
Core GLPI carrega Vue → window._vue disponível globalmente
      │
      ▼
plugin webpack.config.js:
   externals: { vue: 'window _vue' }
      │
      ▼
build gera plugins/meuplugin/public/build/vue/app.js
      │
      ▼
ADD_JAVASCRIPT (hook) carrega esse app.js na página relevante
      │
      ▼
componentes registrados em window.Vue.components
      │
      ▼
template Twig do plugin monta um elemento onde o componente é ativado
```

## Exemplos corretos

### webpack.config.js mínimo (derivado do padrão do core)

```javascript
const webpack = require('webpack');
const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
    entry: {
        vue: './js/src/vue/app.js',
    },
    externals: {
        // evita duplicar o Vue — reaproveita a instância global do core
        vue: 'window _vue',
    },
    output: {
        filename: 'app.js',
        chunkFilename: '[name].js',
        chunkFormat: 'module',
        path: path.resolve(__dirname, 'public/build/vue'),
        publicPath: '/public/build/vue/',
        asyncChunks: true,
        clean: true,
    },
    module: {
        rules: [
            { test: /\.vue$/, loader: 'vue-loader' },
            { test: /\.css$/, use: ['style-loader', 'css-loader'] },
        ],
    },
    plugins: [
        new VueLoaderPlugin(),
        new webpack.DefinePlugin({
            __VUE_OPTIONS_API__: false, // Composition API apenas
            __VUE_PROD_DEVTOOLS__: false,
        }),
    ],
};
```

### Registrando o asset compilado no plugin

```php
<?php
// setup.php

use Glpi\Plugin\Hooks;

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT_MODULE]['meuplugin'] = 'public/build/vue/app.js';
}
```

## Exemplos incorretos

```javascript
// ERRADO: importar Vue diretamente sem marcar como external — empacota
// uma segunda cópia do Vue, infla o bundle e pode criar dois runtimes
// de reatividade coexistindo na mesma página.
import { createApp } from 'vue'; // sem externals configurado
```

```php
// ERRADO: reescrever em Vue uma tela CRUD simples que o padrão
// Twig + CommonDBTM já resolve. Vue adiciona complexidade de build,
// estado no cliente e superfície de manutenção sem ganho real quando
// o caso de uso é só listar/editar campos simples.
```

## Boas práticas

- Reserve Vue para interatividade genuinamente complexa (estado compartilhado entre vários elementos, atualizações em tempo real, formulários com lógica condicional rica).
- Sempre configure `externals` para reaproveitar `window._vue` do core.
- Componentes Vue de plugin vivem ao lado do restante do JS do plugin, versionados e buildados como parte do pipeline de release — nunca comitar apenas o `.vue` fonte sem o `app.js` compilado (ou documentar claramente o passo de build no README).
- Prefira Composition API (alinhado ao `__VUE_OPTIONS_API__: false` usado no core) para consistência com o padrão do projeto.

## Anti-patterns

- Duplicar a instância do Vue em vez de usar `window._vue`.
- Introduzir Vue "porque é moderno" em telas que Twig resolveria com menos complexidade.
- Build de plugin sem processo reprodutível (webpack config ausente do repositório, artefato compilado sem origem rastreável).

## Checklist

- [ ] `externals` mapeando `vue` para `window._vue`
- [ ] Uso de Vue restrito a componentes com interatividade que justifique a complexidade
- [ ] Asset compilado registrado via `ADD_JAVASCRIPT_MODULE`
- [ ] Pipeline de build (webpack.config.js) versionado no repositório do plugin

## Dicas de performance

- Reaproveitar `window._vue` não é só elegância — reduz payload transferido ao browser, especialmente relevante se vários plugins usarem Vue na mesma instância.
- Componentes assíncronos (`asyncChunks: true`) evitam carregar JS de um componente Vue em páginas onde ele não é usado.

## Dicas de segurança

- Dados passados para componentes Vue a partir de PHP/Twig devem ser serializados com cuidado (JSON, não interpolação de string) para evitar quebra de contexto/XSS na injeção inicial de props.
- Componentes Vue ainda operam sobre a mesma sessão/rights do GLPI — chamadas AJAX feitas a partir deles seguem as mesmas regras de `04-Rights.md`/`12-AJAX.md`.

## Referências

- Javascript (Vue em plugins) oficial: https://glpi-developer-documentation.readthedocs.io/en/develop/plugins/javascript.html
- Documentos relacionados: `12-AJAX.md`, `20-Twig.md`, `22-Tabler.md`
