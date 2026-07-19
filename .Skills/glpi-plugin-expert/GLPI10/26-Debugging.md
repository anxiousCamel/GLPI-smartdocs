# 26 — Debugging

## Objetivo

Diagnosticar problemas em plugin GLPI de forma eficiente: modo debug do core, logs específicos, inspeção de query SQL gerada pelo query builder, e erros comuns de plugin com sua causa típica.

## Conceitos

- **Modo debug do GLPI** (ativável por perfil de usuário em *Preferências* ou globalmente em configuração) exibe, ao final de cada página, informações de profiling: queries executadas, tempo de cada uma, hooks disparados, uso de memória. É a primeira ferramenta a ativar ao investigar lentidão ou comportamento inesperado.
- **`Toolbox::logInFile($arquivo, $mensagem)`** grava em `files/_log/<arquivo>.log` — o mecanismo de log de arquivo nativo do GLPI, usado inclusive pelo próprio core (ex.: log de cron). Plugins devem usar esse mecanismo em vez de inventar logging próprio.
- **Erros fatais de plugin geralmente aparecem em `files/_log/php-errors.log`** (ou equivalente configurado no PHP) — sempre o primeiro lugar a checar quando uma página quebra sem mensagem clara na tela.
- **Erros de instalação/ativação** (`check_prerequisites`, `check_config` falhando silenciosamente) são a causa mais comum de "meu plugin não aparece"/"não ativa" — sempre checar o retorno dessas funções e as mensagens que elas emitem.

## Funcionamento interno

Quando o modo debug está ativo, o GLPI intercepta cada chamada ao `$DB` para registrar a query gerada pelo query builder, o tempo de execução e a origem (stack trace resumido) — útil para confirmar que uma query construída via array está gerando o SQL esperado, sem precisar adivinhar. Esse profiling é exibido inline na página (rodapé) quando o usuário tem o modo habilitado no perfil.

## Fluxograma

```
Sintoma: página do plugin quebra ou se comporta errado
      │
      ▼
1. Ativar modo debug (perfil do usuário) → ver queries/hooks/tempo
      │
      ▼
2. Checar files/_log/php-errors.log → erro fatal/warning específico?
      │
      ▼
3. Se plugin não instala/ativa → checar retorno de
   check_prerequisites()/check_config() e logs de instalação
      │
      ▼
4. Se comportamento de hook inesperado → checar ordem de carga de
   plugins e se outro plugin também está inscrito no mesmo hook
```

## Exemplos corretos

### Log próprio do plugin usando o mecanismo nativo

```php
<?php

use Toolbox;

/**
 * Registra eventos relevantes do plugin no log dedicado
 * files/_log/meuplugin.log — não em error_log genérico.
 */
function logMeuplugin(string $mensagem): void
{
    Toolbox::logInFile('meuplugin', $mensagem . "\n");
}

// Uso:
logMeuplugin('Sincronização iniciada para 42 itens.');
```

### Verificando por que o plugin não ativa

```php
<?php
// hook.php

function plugin_meuplugin_check_prerequisites(): bool
{
    if (!extension_loaded('curl')) {
        echo __('A extensão PHP curl é necessária.', 'meuplugin');
        return false;
    }
    return true;
}

function plugin_meuplugin_check_config(bool $verbose = false): bool
{
    if (empty(\Config::getConfigurationValues('plugin:meuplugin')['api_key'] ?? null)) {
        if ($verbose) {
            echo __('Configure a chave de API antes de ativar.', 'meuplugin');
        }
        return false;
    }
    return true;
}
```

### Inspecionando a query gerada por um critério do query builder (debug pontual)

```php
<?php

$criteria = [
    'FROM'  => 'glpi_plugin_meuplugin_coisas',
    'WHERE' => ['entities_id' => Session::getActiveEntities()],
];

// Durante investigação local, é possível inspecionar o SQL final
// através do modo debug do GLPI (rodapé da página) — não é necessário
// (nem recomendado) montar SQL manualmente só para conferir.
$iterator = $DB->request($criteria);
```

## Exemplos incorretos

```php
// ERRADO: usar error_log()/var_dump() direto em produção — polui
// logs do servidor sem seguir o padrão de log do GLPI e pode vazar
// dado sensível em lugares não monitorados pelo administrador do GLPI.
error_log(print_r($_POST, true));
```

```php
// ERRADO: silenciar exceção/erro com @ para "resolver" um bug em vez
// de investigar a causa raiz via log/modo debug. Esconde o sintoma
// e garante que o mesmo bug reapareça de forma mais difícil de
// rastrear depois.
$resultado = @$item->getFromDB($id);
```

```php
// ERRADO: concluir que "o plugin não funciona" sem checar
// files/_log/php-errors.log primeiro — a maioria dos problemas de
// ativação/execução deixa rastro claro ali.
```

## Boas práticas

- Ative o modo debug do GLPI como primeiro passo de qualquer investigação de lentidão ou comportamento inesperado.
- Use `Toolbox::logInFile()` para logging próprio do plugin — mantém tudo no local que o administrador já monitora (`files/_log/`).
- Retorne mensagens claras em `check_prerequisites`/`check_config` — é a interface direta com o administrador tentando ativar o plugin.
- Ao depurar hooks, lembre que outros plugins podem estar inscritos no mesmo hook e alterar o estado antes do seu código rodar (ver `03-Hooks.md`).

## Anti-patterns

- `var_dump`/`print_r`/`error_log` direto em código de produção.
- Suprimir erros com `@` em vez de tratar a causa.
- Assumir que um comportamento estranho é "bug do GLPI" antes de checar logs e modo debug.
- Debugar hooks sem considerar a possível interferência de outros plugins inscritos no mesmo ponto de extensão.

## Checklist

- [ ] Logging do plugin usa `Toolbox::logInFile()`, não mecanismo próprio
- [ ] `check_prerequisites`/`check_config` retornam mensagens claras de falha
- [ ] Nenhum `var_dump`/`print_r`/`@` de supressão de erro em código de produção
- [ ] Modo debug do GLPI usado como primeira ferramenta de investigação

## Dicas de performance

- O profiling do modo debug tem custo — mantenha-o desligado em produção para usuários comuns, ativando pontualmente para investigação.
- Logs de arquivo (`Toolbox::logInFile`) têm custo de I/O; evite chamá-los em loops de alta frequência (ex.: dentro de um hook de item disparado em importação em massa) sem throttling.

## Dicas de segurança

- Nunca logue dados sensíveis (senhas, tokens) mesmo em log de debug — arquivos de log costumam ter proteção de acesso mais fraca que o banco de dados.
- Erros expostos diretamente na tela (em vez de logados) podem vazar detalhes de implementação para o usuário final — trate exceção de forma que o log receba o detalhe técnico e a tela receba uma mensagem genérica.

## Referências

- Developer API (Toolbox, logging): https://glpi-developer-documentation.readthedocs.io/en/master/devapi/mainobjects.html
- Documentos relacionados: `02-Lifecycle.md`, `03-Hooks.md`, `25-Testing.md`, `29-Performance.md`
