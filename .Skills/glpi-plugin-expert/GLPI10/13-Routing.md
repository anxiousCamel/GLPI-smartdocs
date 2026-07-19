# 13 — Routing

## Objetivo

Entender como uma URL vira uma resposta no GLPI 10.x (roteamento legado por arquivo), como construir URLs corretas de plugin, e como isso vai mudar no GLPI 11 (para já escrever pensando na migração).

## Conceitos

- **Roteamento por arquivo, não por tabela de rotas.** No 10.x, cada `front/*.php`/`ajax/*.php` é literalmente o alvo da URL: `/plugins/meuplugin/front/coisa.form.php?id=5` executa aquele arquivo diretamente. Não existe (ainda) um dispatcher central de rotas para plugins — o "roteador" é o próprio filesystem do servidor web.
- **Padrão de nomenclatura de `front/`:**
  - `<itemtype>.php` — lista (Search) do itemtype.
  - `<itemtype>.form.php` — form de criação/edição/visualização (`?id=N` para editar, sem id para novo).
  - `<coisa>.php` sem ligação a itemtype — página utilitária (configuração, relatório).
- **Construção de URL correta:** sempre via helpers do core (`Plugin::getWebDir()` no 10.x, `Toolbox::getItemTypeFormURL()`, `$item->getFormURL()`/`getSearchURL()`) — nunca montar caminho manual concatenando strings, pois isso quebra quando o plugin é instalado via marketplace (caminho físico diferente) e quebra de vez no GLPI 11 (ver `GLPI11/00-Migration-Guide.md`).

## Funcionamento interno

O servidor web (Apache/Nginx) mapeia `/plugins/<chave>/front/*` e `/plugins/<chave>/ajax/*` para os arquivos físicos correspondentes dentro do diretório do plugin. Cada script inclui o bootstrap (`inc/includes.php` no 10.x) manualmente, então tudo que precede a lógica do script é boilerplate obrigatório. Isso é o que o GLPI 11 substitui por um entry point único (`/public/index.php`) com resolução de URL não vinculada à posição física do arquivo — mas a *convenção de nomenclatura* de front/ajax se mantém por compatibilidade.

## Fluxograma

```
Browser → GET /plugins/meuplugin/front/coisa.form.php?id=5
      │
      ▼
servidor web resolve para o arquivo físico
      │
      ▼
front/coisa.form.php:
   include inc/includes.php   (bootstrap)
   Session::checkRight(...)
   $coisa->getFromDB($_GET['id'])
   $coisa->display([...])     (delega para showForm/Twig)
```

## Exemplos corretos

### Construindo URLs corretamente

```php
<?php

use GlpiPlugin\Meuplugin\Coisa;

// Nunca: '/plugins/meuplugin/front/coisa.form.php?id=' . $id (string manual)

// Sim — usa os helpers do próprio itemtype:
$urlLista = Coisa::getSearchURL();          // .../front/coisa.php
$urlForm  = Coisa::getFormURL();            // .../front/coisa.form.php
$urlEdicao = $coisaInstancia->getLinkURL();  // link+id, já formatado

// Redirecionamento após um POST bem-sucedido:
Html::redirect($urlLista);
```

### front/coisa.php — lista (delegando ao Search)

```php
<?php
// front/coisa.php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

Session::checkRight(Coisa::$rightname, READ);

Html::header(
    Coisa::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'plugins',
    Coisa::class
);

Search::show(Coisa::class);

Html::footer();
```

### front/coisa.form.php — form (delegando ao itemtype)

```php
<?php
// front/coisa.form.php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

$coisa = new Coisa();

if (isset($_POST['add'])) {
    Session::checkRight(Coisa::$rightname, CREATE);
    $coisa->check(-1, CREATE, $_POST);
    $newId = $coisa->add($_POST);
    Html::redirect($coisa->getFormURLWithID($newId));
} elseif (isset($_POST['update'])) {
    Session::checkRight(Coisa::$rightname, UPDATE);
    $coisa->check($_POST['id'], UPDATE);
    $coisa->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight(Coisa::$rightname, DELETE);
    $coisa->check($_POST['id'], DELETE);
    $coisa->delete($_POST);
    Html::redirect(Coisa::getSearchURL());
} else {
    Html::header(Coisa::getTypeName(1), $_SERVER['PHP_SELF'], 'plugins', Coisa::class, 'coisa');
    $id = (int) ($_GET['id'] ?? 0);
    $coisa->display(['id' => $id]);
    Html::footer();
}
```

## Exemplos incorretos

```php
// ERRADO: montar URL concatenando o caminho físico manualmente.
// Quebra se o plugin for instalado via marketplace (path diferente)
// e quebra completamente na migração para GLPI 11.
$url = '/plugins/meuplugin/front/coisa.form.php?id=' . $id;
```

```php
// ERRADO: form.php sem distinguir add/update/delete por verbo de
// intenção claro — um único bloco que tenta adivinhar a ação a
// partir de campos presentes é frágil e difícil de auditar.
```

```php
// ERRADO: redirecionar com header() manual em vez de Html::redirect()/
// Html::back() — perde tratamento de mensagens de sessão e de segurança
// que os helpers do core já cuidam.
```

## Boas práticas

- Sempre construa URLs via `getSearchURL()`/`getFormURL()`/`getFormURLWithID()`/`getLinkURL()` do próprio itemtype.
- Um bloco por ação (`add`/`update`/`delete`) claramente separado por `isset($_POST['<ação>'])`, cada um com sua própria checagem de right.
- `front/*.php` fino: bootstrap → right → delega para o itemtype/classe → resposta. Zero lógica de negócio no arquivo.
- Escreva pensando no GLPI 11 desde já: nada de `Plugin::getWebDir()`/caminho físico hardcoded (ver `GLPI11/00-Migration-Guide.md`).

## Anti-patterns

- Concatenação manual de path de plugin em qualquer lugar do código.
- `front/*.php` que processa múltiplas responsabilidades não relacionadas (ex.: um script que é lista E form E endpoint de exportação, tudo condicionado por parâmetros).
- Redirecionar com `header('Location: ...')` cru em vez dos helpers `Html::redirect()`/`Html::back()`.

## Checklist

- [ ] Toda URL de plugin construída via helper do itemtype, nunca concatenação manual
- [ ] `front/<itemtype>.php` = lista; `front/<itemtype>.form.php` = CRUD
- [ ] Cada ação (add/update/delete) com right próprio checado
- [ ] Redirecionamentos via `Html::redirect()`/`Html::back()`
- [ ] Nenhum código dependente do caminho físico do plugin no disco

## Dicas de performance

- `Search::show()` já pagina e otimiza a query da lista — não pré-carregue todos os itens manualmente antes de chamá-lo.
- Evite processamento pesado antes do `Html::header()` — atrasa o primeiro byte da resposta.

## Dicas de segurança

- Todo `front/*.php` que recebe POST precisa do token CSRF (garantido pelo `csrf_compliant` do plugin) e da checagem de right correspondente à ação, não apenas à visualização.
- `$_GET['id']`/`$_POST['id']` sempre convertidos para `(int)` antes de uso em `getFromDB`/`check()`.

## Referências

- Plugin tutorial (estrutura de front/ajax): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Upgrade 11 — mudanças de roteamento: https://glpi-developer-documentation.readthedocs.io/en/master/upgradeguides/glpi-11.0.html
- Documentos relacionados: `12-AJAX.md`, `04-Rights.md`, `GLPI11/00-Migration-Guide.md`
