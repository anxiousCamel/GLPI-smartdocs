# 25 — Testes

## Objetivo

Estruturar testes de plugin GLPI: onde ficam, como rodam contra uma instância GLPI real (unit tests de plugin não são isolados do framework), e a transição histórica de framework de teste que afeta o que se encontra em exemplos antigos vs atuais.

## Conceitos

- **Testes de plugin GLPI não são unit tests puros e isolados** — a maioria roda contra um GLPI instalado e funcional (banco de dados incluso), porque `CommonDBTM`/`Session`/`$DB` são profundamente acoplados ao ambiente. São, na prática, testes de integração/funcionais rotulados como "unit tests".
- **Framework de teste**: o núcleo do GLPI migrou de PHPUnit para **atoum** por volta de 2017 (por causa de recursos como mocking de funções nativas e tipagem estrita de asserts), e documentação/plugins mais recentes (2026) voltaram a referenciar **PHPUnit** como o padrão atual — plugins recentes do próprio time GLPI (ex.: `glpi-inventory-plugin`) já usam PHPUnit. **Ao gerar/revisar testes, confirme qual framework o `composer.json`/CI do plugin já usa antes de escolher um novo** — não misture os dois sem necessidade.
- **Estrutura padrão**: testes ficam em `tests/units/` (histórico, estilo atoum) ou `tests/` (PHPUnit), com um arquivo de teste por classe testada, seguindo o nome da classe original.
- **Isolamento de banco**: os testes funcionais do core (via `DbTestCase`) rodam cada teste dentro de uma transação de banco revertida ao final — evita que um teste contamine o próximo. Plugins que testam persistência devem seguir o mesmo padrão quando disponível.

## Funcionamento interno

Rodar os testes de um plugin exige uma instância GLPI de teste com o plugin instalado e ativado (o bootstrap do teste tipicamente instala/ativa o plugin programaticamente antes de rodar a suíte, verificando pré-requisitos via `plugin_<chave>_check_prerequisites()`). O executor (`atoum` ou `phpunit`) é apontado para o diretório de testes do plugin com um bootstrap próprio que carrega o ambiente do GLPI.

## Fluxograma

```
tests/bootstrap.php (do plugin)
      │  include GLPI_ROOT/tests/GLPITestCase.php (+ DbTestCase.php)
      │  instala/ativa o plugin programaticamente
      ▼
executor (atoum ou phpunit) roda tests/ do plugin
      │
      ▼
cada teste: transação de banco → asserts → rollback
      │
      ▼
relatório (xunit/testdox) — integrável a CI
```

## Exemplos corretos

### Teste funcional (estilo PHPUnit, testando ciclo de vida de itemtype)

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin\Tests;

use DbTestCase;
use GlpiPlugin\Meuplugin\Coisa;

/**
 * Testa o ciclo de vida básico do itemtype Coisa.
 */
class CoisaTest extends DbTestCase
{
    public function testAddRequerNome(): void
    {
        $coisa = new Coisa();

        $id = $coisa->add(['name' => '']); // inválido — sem nome

        $this->assertFalse($id, 'Deveria rejeitar Coisa sem nome.');
    }

    public function testAddComNomeValido(): void
    {
        $coisa = new Coisa();

        $id = $coisa->add(['name' => 'Coisa de teste']);

        $this->assertGreaterThan(0, $id);
        $this->assertTrue($coisa->getFromDB($id));
        $this->assertSame('Coisa de teste', $coisa->fields['name']);
    }
}
```

### Bootstrap mínimo do plugin (padrão histórico, ainda válido conceitualmente)

```php
<?php
// tests/bootstrap.php

define('GLPI_ROOT', dirname(__DIR__, 4)); // ajustar conforme profundidade real

include_once GLPI_ROOT . '/tests/GLPITestCase.php';
include_once GLPI_ROOT . '/tests/DbTestCase.php';

$plugin = new \Plugin();
$plugin->checkStates(true);
$plugin->getFromDBbyDir('meuplugin');

if (!plugin_meuplugin_check_prerequisites()) {
    echo "\nPré-requisitos não atendidos!\n";
    exit(1);
}

if (!$plugin->isInstalled('meuplugin')) {
    $plugin->install($plugin->getID());
}

if (!$plugin->isActivated('meuplugin')) {
    $plugin->activate($plugin->getID());
}
```

## Exemplos incorretos

```php
// ERRADO: testar CommonDBTM instanciando a classe sem nenhum ambiente
// GLPI carregado (sem $DB, sem Session) — a maioria dos métodos vai
// falhar por dependências globais ausentes. Plugin de GLPI não é
// testável como uma classe PHP isolada comum sem o bootstrap correto.
```

```php
// ERRADO: teste que insere dados e nunca limpa/reverte — polui o
// banco de teste para as próximas execuções, causando falhas
// intermitentes difíceis de depurar.
```

```php
// ERRADO: misturar testes escritos em estilo atoum (assertions
// fluentes tipo $this->integer($id)->isGreaterThan(0)) com testes
// PHPUnit no mesmo diretório sem um executor único configurado —
// escolha um framework por plugin e seja consistente.
```

## Boas práticas

- Confirme o framework de teste já em uso pelo `composer.json`/CI do plugin antes de escrever/gerar novos testes.
- Um arquivo de teste por classe testada, espelhando o namespace (`GlpiPlugin\Meuplugin\Tests\CoisaTest` para `GlpiPlugin\Meuplugin\Coisa`).
- Prefira testes que cobrem o CICLO DE VIDA real (add com dado inválido, add válido, update, delete) em vez de só testar getters triviais.
- Rode a suíte localmente (ou via container/CI) antes de cada release — testes que só existem no papel não pegam regressão nenhuma.

## Anti-patterns

- Testes sem bootstrap de ambiente GLPI, tentando isolar `CommonDBTM` como se fosse POJO puro.
- Testes que alteram dados e não revertem, contaminando execuções subsequentes.
- Mistura de frameworks de teste (atoum + PHPUnit) no mesmo plugin sem padronização.
- Cobertura só de "caminho feliz", sem testar validação de input inválido/direitos insuficientes.

## Checklist

- [ ] Framework de teste identificado e usado consistentemente (PHPUnit ou atoum, conforme o que o plugin já usa)
- [ ] Bootstrap de teste instala/ativa o plugin programaticamente
- [ ] Testes cobrem ciclo de vida (add válido/inválido, update, delete) do itemtype principal
- [ ] Testes revertem estado (transação/rollback) sem poluir execuções futuras
- [ ] Suíte roda com sucesso antes de cada release

## Dicas de performance

- Reaproveitar transação de banco por teste (`DbTestCase`) é mais rápido e seguro do que criar/apagar dados manualmente a cada teste.
- Suítes grandes se beneficiam de rodar em paralelo apenas se o framework/ambiente suportar isolamento real entre processos (atoum roda cada teste em processo separado por padrão).

## Dicas de segurança

- Testes que exercitam `prepareInputFor*`/rights são a forma mais barata de garantir que uma regressão de segurança (ex.: right insuficiente esquecido) seja pega antes do release.
- Nunca rode a suíte de testes contra uma instância de produção — sempre um ambiente descartável.

## Referências

- Unit Testing oficial (bootstrap, estrutura, atoum): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/test.html
- Confirmação do uso atual de PHPUnit no core: https://glpi-developer-documentation.readthedocs.io/_/downloads/en/latest/pdf/
- Exemplo real com PHPUnit (plugin oficial): https://github.com/glpi-project/glpi-inventory-plugin/blob/main/README.tests.md
- Documentos relacionados: `07-CommonDBTM.md`, `23-Composer.md`, `26-Debugging.md`
