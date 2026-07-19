# Template: RESTController

Endpoint próprio de API para ações não-CRUD. Ver `GLPI10/14-REST-API.md`.

> Antes de usar este template: confirme que a necessidade realmente não é
> CRUD simples de um itemtype — nesse caso, `apirest.php/GlpiPlugin\Meuplugin\Coisa/`
> nativo já funciona sem nenhum código adicional, desde que os rights da
> classe estejam corretos.

## apirest.php (raiz do plugin)

```php
<?php

declare(strict_types=1);

include('../../inc/includes.php'); // GLPI 10.x

header('Content-Type: application/json; charset=UTF-8');

// Reaproveita a autenticação de sessão padrão do GLPI
Session::checkLoginUser();

$partes = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$recurso = $partes[0] ?? '';

switch ($recurso) {
    case 'AprovarLote':
        Session::checkRight(
            \GlpiPlugin\Meuplugin\Coisa::$rightname,
            \GlpiPlugin\Meuplugin\Coisa::APPROVE
        );

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids   = array_map('intval', $input['ids'] ?? []);

        $resultado = \GlpiPlugin\Meuplugin\Coisa::aprovarEmLote($ids);
        echo json_encode($resultado);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Recurso não encontrado']);
}
```

## Método correspondente (lógica real, testável)

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

/**
 * Aprova em lote, retornando sucesso/falha por id.
 *
 * @param int[] $ids
 * @return array{ok: int[], ko: int[]}
 */
public static function aprovarEmLote(array $ids): array
{
    $resultado = ['ok' => [], 'ko' => []];

    foreach ($ids as $id) {
        $item = new self();
        if ($item->getFromDB($id) && $item->update(['id' => $id, 'status' => 'aprovado'])) {
            $resultado['ok'][] = $id;
        } else {
            $resultado['ko'][] = $id;
        }
    }

    return $resultado;
}
```

## Checklist pós-cópia

- [ ] Confirmado que CRUD nativo não resolve antes de criar este endpoint
- [ ] Autenticação via `Session::checkLoginUser()`, não esquema paralelo
- [ ] Right específico da ação checado
- [ ] Entrada (JSON body, ids) validada e tipada antes de uso
- [ ] Limite de tamanho de lote considerado para `aprovarEmLote`
