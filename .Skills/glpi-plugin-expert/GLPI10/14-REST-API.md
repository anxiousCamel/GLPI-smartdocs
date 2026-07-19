# 14 — REST API

## Objetivo

Entender a API REST nativa do GLPI (Low-Level, `apirest.php`), como itemtypes de plugin já são automaticamente expostos por ela, e as opções realistas quando um plugin precisa de endpoints próprios — sem inventar mecanismos que a API legada não suporta de forma limpa.

## Conceitos

- **Duas gerações de API convivem no ecossistema GLPI:** a **Low-Level API (v1)**, `apirest.php`, ligada diretamente a `CommonDBTM`/`Search` (é a que existe e funciona hoje em 10.x e 11.x); e a **High-Level API (v2)**, `api.php` (introduzida a partir do 10.1/11), com OAuth2, OpenAPI e abstrações mais estáveis — em expansão, mas a v1 continua sendo a superfície principal para a maioria dos plugins ainda.
- **Autenticação da API v1**: `initSession` retorna um `Session-Token`; toda chamada subsequente envia esse token (header `Session-Token`) e opcionalmente um `App-Token` de aplicação. Autenticação inicial pode ser Basic Auth (login/senha) ou `user_token` (chave de acesso remoto do usuário).
- **Todo itemtype `CommonDBTM` — inclusive de plugin — já é acessível via `apirest.php/:itemtype/`** desde que a classe seja reconhecida como tal e implemente/herde `canView`/`canCreate`/`canUpdate`/`canDelete`/`canPurge` corretamente. **Não existe** mecanismo limpo e suportado para adicionar endpoints *customizados* dentro do `apirest.php` do core a partir de um plugin.
- **Quando um plugin precisa de lógica que não é CRUD puro de itemtype** (uma ação composta, um cálculo, uma integração), a via oficialmente reconhecida pela comunidade é o plugin expor seu **próprio script `apirest.php`** (ex.: `/plugins/meuplugin/apirest.php/AcaoCustomizada`), tratando roteamento e autenticação manualmente dentro dele — não uma extensão do `apirest.php` do core.

## Funcionamento interno

Para um itemtype comum, `apirest.php/:itemtype/` delega para `Glpi\Api\API::getItems()` (ou equivalente), que por sua vez usa `Search::getDatas()`/`CommonDBTM::getFromDB()` sob o capô — respeitando exatamente os mesmos rights e regras de entidade que a UI usa. Por isso, um itemtype de plugin bem-comportado (rights corretos, `getTable()` por convenção) já "ganha" API REST de graça, sem código adicional.

Quando o plugin precisa de algo além de CRUD (ex.: "aprovar em lote", "sincronizar agora"), a prática recomendada pela própria comunidade oficial (ver referência) é o plugin manter seu próprio arquivo `apirest.php` na raiz, reimplementando o mínimo de roteamento necessário e reaproveitando a autenticação de sessão do GLPI (`Session`) — não tentando "pendurar" rotas no `apirest.php` nativo.

## Fluxograma

```
Cliente externo
      │  POST /apirest.php/initSession  (Basic Auth ou user_token)
      ▼
GLPI retorna Session-Token
      │
      ▼
GET/POST/PUT/DELETE /apirest.php/GlpiPlugin\Meuplugin\Coisa/  (Session-Token no header)
      │
      ▼
Glpi\Api\API → canView/canCreate/canUpdate/canDelete/canPurge do itemtype
      │  (mesmos rights e entidade que a UI usa)
      ▼
CommonDBTM::add/update/delete/getFromDB
      │
      ▼
JSON de resposta
```

Para lógica além de CRUD:

```
Cliente externo → GET /plugins/meuplugin/apirest.php/AcaoCustomizada
      │
      ▼
plugin roteia manualmente dentro do próprio apirest.php
      │  reaproveita Session (mesma auth do core)
      ▼
lógica em src/ → resposta JSON própria
```

## Exemplos corretos

### Itemtype de plugin já acessível via API nativa (nenhum código extra necessário)

```php
<?php
// Nenhum código de "API" é necessário aqui — o CRUD já funciona via
// apirest.php/GlpiPlugin\Meuplugin\Coisa/ desde que o itemtype siga
// as convenções normais de CommonDBTM (right, tabela, canView etc.)

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;

class Coisa extends CommonDBTM
{
    public static $rightname = 'plugin_meuplugin_coisa';
    // getTable(), canViewItem() etc. herdados normalmente.
}
```

```
# Uso externo (nenhum endpoint próprio necessário):
curl -X GET \
  -H "Session-Token: $TOKEN" \
  -H "App-Token: $APP_TOKEN" \
  "https://glpi.exemplo/apirest.php/GlpiPlugin%5CMeuplugin%5CCoisa/"
```

### Endpoint próprio do plugin para ação não-CRUD

```php
<?php
// plugins/meuplugin/apirest.php
// Roteamento mínimo e explícito — não tenta imitar o dispatcher do core.

include('../../inc/includes.php'); // GLPI 10.x

header('Content-Type: application/json; charset=UTF-8');

// Reaproveita a autenticação de sessão padrão do GLPI (Session-Token
// já validado pelo bootstrap normal via cookie/sessão autenticada).
Session::checkLoginUser();

$partes = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$recurso = $partes[0] ?? '';

switch ($recurso) {
    case 'AprovarLote':
        Session::checkRight(\GlpiPlugin\Meuplugin\Coisa::$rightname, \GlpiPlugin\Meuplugin\Coisa::APPROVE);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $resultado = \GlpiPlugin\Meuplugin\Coisa::aprovarEmLote($input['ids'] ?? []);
        echo json_encode($resultado);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Recurso não encontrado']);
}
```

## Exemplos incorretos

```php
// ERRADO: tentar "registrar" um endpoint customizado dentro do
// apirest.php do CORE via hook inexistente. A API legada não expõe
// esse ponto de extensão — qualquer solução assim depende de editar
// o core (proibido) ou é pura fantasia que quebra no próximo update.
```

```php
// ERRADO: reimplementar autenticação do zero (usuário/senha em texto
// no próprio script) em vez de reaproveitar Session::checkLoginUser()/
// o pipeline de autenticação já existente do GLPI.
```

```php
// ERRADO: expor um itemtype de plugin via API mas com rights mal
// configurados (canViewItem sempre true) — a API herda EXATAMENTE
// os rights configurados na classe; um bug de right vira uma falha
// de segurança de API também.
```

## Boas práticas

- Para CRUD simples de um itemtype de plugin, não escreva nada: a API já funciona se os rights estiverem corretos. Teste isso explicitamente antes de assumir que precisa de endpoint próprio.
- Reserve um `apirest.php` próprio do plugin apenas para ações que não são CRUD de um único itemtype.
- Reaproveite a sessão/autenticação do GLPI (`Session`) em vez de inventar autenticação paralela.
- Documente no README do plugin quais itemtypes são expostos via API nativa e quais endpoints próprios existem, com exemplos de `curl`.

## Anti-patterns

- Duplicar lógica de CRUD já coberta pela API nativa dentro de um `apirest.php` próprio.
- Autenticação paralela (tokens próprios do plugin) quando a sessão do GLPI já resolve o problema.
- Expor dado sensível via API confiando que "ninguém vai descobrir a URL" — API é superfície pública tanto quanto qualquer `front/`.

## Checklist

- [ ] Rights do itemtype corretos e testados diretamente via `apirest.php` nativo antes de escrever qualquer coisa própria
- [ ] Endpoint próprio (se existir) só cobre ações que não são CRUD simples
- [ ] Autenticação reaproveita `Session`, não um esquema paralelo
- [ ] Toda entrada de endpoint próprio validada e tipada
- [ ] Right específico da ação checado dentro do endpoint próprio

## Dicas de performance

- A API nativa já delega para `Search`/`CommonDBTM`, então herda os mesmos cuidados de índice e paginação descritos em `05-Database.md`/`10-Search.md`.
- Endpoints próprios que processam lotes grandes devem paginar/limitar tamanho de entrada — nunca aceitar arrays de ids sem limite.

## Dicas de segurança

- API é superfície de ataque igual a qualquer outro entry point: right, CSRF (quando aplicável) e validação de entrada não são opcionais.
- No GLPI 11, endpoints próprios de plugin (`/apirest.php`) precisam ser registrados como *stateless* corretamente se não usarem cookie de sessão tradicional (ver `GLPI11/00-Migration-Guide.md`, seção de `SessionManager::registerPluginStatelessPath`).

## Referências

- REST API (V1) oficial: https://help.glpi-project.org/documentation/modules/configuration/general/api/api
- RESTful API (V2): https://help.glpi-project.org/documentation/modules/configuration/general/api/restful-api-v2
- Discussão oficial sobre endpoints custom em plugin: https://forum.glpi-project.org/viewtopic.php?id=292281
- Documentos relacionados: `04-Rights.md`, `12-AJAX.md`, `GLPI11/00-Migration-Guide.md`
