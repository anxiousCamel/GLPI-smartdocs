# 23 — Composer

## Objetivo

Usar Composer corretamente dentro de um plugin GLPI: `composer.json` mínimo com autoload PSR-4, gestão de dependências PHP próprias sem colidir com as do core, e a decisão de commitar ou não a pasta `vendor/`.

## Conceitos

- **Cada plugin tem seu próprio `composer.json`**, independente do `composer.json` do core do GLPI. Isso permite ao plugin declarar dependências PHP próprias (uma lib de parsing, um SDK de terceiro) sem tocar nas dependências do GLPI.
- **Autoload PSR-4 é o que viabiliza o namespace moderno** `GlpiPlugin\Meuplugin\` → `src/` (ver `24-Namespaces.md`). Sem isso configurado corretamente no `composer.json`, as classes do plugin não são encontradas pelo autoloader.
- **`vendor/` do plugin é separado do `vendor/` do GLPI** — não há compartilhamento automático de dependências entre o core e os plugins (cada um resolve as suas). Isso evita conflito de versão entre uma lib que o GLPI usa e a mesma lib numa versão diferente que o plugin precisa, mas também significa que o plugin carrega seu próprio `autoload.php` separadamente.
- **Decisão de commitar `vendor/`**: como o marketplace/distribuição de plugin GLPI normalmente NÃO roda `composer install` no ambiente do cliente, a prática usual é **commitar `vendor/` no pacote de release** (gerado com `composer install --no-dev --optimize-autoloader`), mantendo `vendor/` fora do controle de versão apenas durante o desenvolvimento (via `.gitignore`) e gerando-o no processo de build/empacotamento.

## Funcionamento interno

O autoloader do GLPI, ao inicializar um plugin ativo, inclui o `vendor/autoload.php` do próprio plugin (quando presente) além do autoload PSR-4 nativo do core para classes de plugin — isso é o que permite `use GlpiPlugin\Meuplugin\Coisa;` funcionar sem exigir um `require` manual em cada arquivo.

## Fluxograma

```
Desenvolvimento:
  composer.json (fonte de verdade) + composer.lock
  composer install → vendor/ (local, git-ignored)

Build/Release:
  composer install --no-dev --optimize-autoloader
      │
      ▼
  vendor/ empacotado junto ao .zip/.skill de distribuição
      │
      ▼
Cliente instala o plugin (sem rodar composer) → autoload já funciona
```

## Exemplos corretos

### composer.json mínimo com PSR-4

```json
{
    "name": "anxiouscamel/meuplugin",
    "description": "Plugin Meuplugin para GLPI",
    "type": "glpi-plugin",
    "license": "GPL-3.0-or-later",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "squizlabs/php_codesniffer": "^3.7"
    },
    "autoload": {
        "psr-4": {
            "GlpiPlugin\\Meuplugin\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "GlpiPlugin\\Meuplugin\\Tests\\": "tests/"
        }
    }
}
```

### .gitignore no desenvolvimento, script de build gerando vendor/ para release

```
# .gitignore (repositório de desenvolvimento)
/vendor/
composer.lock
```

```bash
# tools/build.sh (gera o pacote de distribuição)
composer install --no-dev --optimize-autoloader
zip -r meuplugin.zip . -x ".git/*" "tests/*" "tools/*"
```

## Exemplos incorretos

```json
// ERRADO: namespace de autoload que não bate com a convenção
// GlpiPlugin\<Nome>\ — quebra a resolução automática que o core
// espera para classes de plugin modernas.
{
    "autoload": {
        "psr-4": {
            "Meuplugin\\": "src/"
        }
    }
}
```

```php
// ERRADO: exigir que o administrador rode "composer install" no
// servidor de produção após instalar o plugin — a maioria dos
// ambientes de cliente não tem Composer disponível/permitido, e o
// marketplace não executa esse passo automaticamente.
```

```json
// ERRADO: dependências de desenvolvimento (phpunit, phpcs) dentro de
// "require" em vez de "require-dev" — infla o vendor/ de produção
// distribuído com o plugin desnecessariamente.
{
    "require": {
        "php": ">=8.1",
        "phpunit/phpunit": "^10.0"
    }
}
```

## Boas práticas

- Namespace de autoload sempre `GlpiPlugin\<Nome>\` → `src/`, consistente com `24-Namespaces.md`.
- Dependências de teste/lint em `require-dev`; só o estritamente necessário em runtime vai em `require`.
- Gere `vendor/` otimizado (`--optimize-autoloader`, `--no-dev`) como parte do processo de build/empacotamento, não como responsabilidade do cliente.
- Trave versões de dependências de terceiros (`^x.y` com cuidado, ou `composer.lock` versionado no processo de release) para builds reprodutíveis.

## Anti-patterns

- Depender de o ambiente do cliente ter Composer instalado.
- Autoload que não segue a convenção de namespace esperada pelo core.
- Misturar dependências de dev com as de produção no mesmo bloco.
- Nenhum `composer.json` (classes carregadas via `include`/`require` manual espalhados pelo código).

## Checklist

- [ ] `composer.json` com autoload PSR-4 `GlpiPlugin\<Nome>\` → `src/`
- [ ] `require-dev` separado de `require`
- [ ] Processo de build gera `vendor/` otimizado para o pacote de release
- [ ] Nenhuma dependência exige ação manual do cliente (`composer install` em produção)

## Dicas de performance

- `--optimize-autoloader` gera um mapa de classes estático, mais rápido que a resolução PSR-4 dinâmica — sempre use isso no build de release.
- Dependências de dev nunca vão para o `vendor/` de produção — reduz I/O de autoload desnecessário.

## Dicas de segurança

- Audite dependências de terceiros antes de incluir (`composer audit` ou equivalente) — uma dependência vulnerável dentro de `vendor/` do plugin é tão explorável quanto uma vulnerabilidade no próprio código do plugin.
- Trave versões (via `composer.lock` no processo de build) para builds reprodutíveis e auditáveis — evita que uma atualização silenciosa de dependência introduza comportamento inesperado em produção.

## Referências

- Tutorial oficial (menção a Composer/npm como pré-requisitos): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Documentos relacionados: `01-Plugin-Structure.md`, `24-Namespaces.md`, `25-Testing.md`
