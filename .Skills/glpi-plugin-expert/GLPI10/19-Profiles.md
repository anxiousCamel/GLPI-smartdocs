# 19 — Profiles (Integração com Perfis)

## Objetivo

Integrar os rights do plugin à tela oficial *Administração > Perfis*, para que um administrador consiga conceder/revogar permissões do plugin pela mesma UI usada para o resto do GLPI, sem telas paralelas.

## Conceitos

- **`Profile` é o itemtype do core que representa um perfil** (conjunto de rights). A matriz de checkboxes que o administrador vê é montada dinamicamente a partir de abas registradas em `Profile` — cada aba corresponde a um grupo de rights (um itemtype ou um conjunto lógico).
- **Um plugin ganha sua própria seção na matriz de Perfis** registrando uma classe `Profile` própria (`GlpiPlugin\Meuplugin\Profile`, tipicamente estendendo `\Profile` ou implementando o padrão de aba de `CommonGLPI`) e associando-a como aba via `Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']])` — o mesmo mecanismo de abas de `08-CommonGLPI.md`, aplicado especificamente ao itemtype `Profile`.
- **`getAllRights()`/método equivalente na classe de perfil do plugin** declara quais rights (nome + rótulo) pertencem ao plugin, permitindo que o core desenhe automaticamente os checkboxes de READ/UPDATE/CREATE/DELETE/PURGE (e níveis customizados) para cada um.

## Funcionamento interno

Ao abrir o form de um `Profile`, o core monta as abas via `defineTabs()`; a aba do plugin (registrada por `addtabon`) aparece como mais uma seção. Dentro dela, a classe de perfil do plugin desenha uma matriz de checkboxes correspondente a cada `$rightname` que os itemtypes do plugin declaram (via `getRightsForForm()`/`getRights()`, ver `04-Rights.md`). Ao salvar, o core grava os valores escolhidos em `glpi_profilerights` (uma linha por combinação `profiles_id` + `name` do right).

## Fluxograma

```
Administração > Perfis > [Perfil X] > aba "Meu Plugin"
      │
      ▼
GlpiPlugin\Meuplugin\Profile::showForm()
      │  itera os itemtypes do plugin que declaram $rightname
      │  desenha checkbox por nível (READ/UPDATE/CREATE/DELETE/PURGE/customizado)
      ▼
[admin marca/desmarca e salva]
      ▼
grava/atualiza glpi_profilerights (profiles_id, name, rights)
      │
      ▼
Session::haveRight() do usuário passa a refletir o novo valor no próximo login/troca de perfil
```

## Exemplos corretos

### Classe de Profile do plugin

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use Profile as CoreProfile;
use Profile_User;
use CommonGLPI;

/**
 * Aba de rights do plugin dentro da tela de Perfis.
 */
class Profile extends CoreProfile
{
    /**
     * Rótulo da aba, exibido no form de Profile do core.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof CoreProfile)) {
            return '';
        }

        return __('Meu Plugin', 'meuplugin');
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof CoreProfile)) {
            return false;
        }

        self::showFormMeuplugin($item);
        return true;
    }

    /**
     * Desenha a matriz de rights do plugin para o perfil informado.
     */
    private static function showFormMeuplugin(CoreProfile $profile): void
    {
        $profileId = (int) $profile->getField('id');

        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . __('Coisas', 'meuplugin') . "</th></tr>";

        \Profile::dropdownRights(
            [READ, CREATE, UPDATE, DELETE, PURGE, Coisa::APPROVE => __('Aprovar', 'meuplugin')],
            'plugin_meuplugin_coisa',
            self::getRightValueForProfile($profileId, 'plugin_meuplugin_coisa')
        );

        echo "</table>";
        echo "</div>";
    }

    private static function getRightValueForProfile(int $profileId, string $rightname): int
    {
        // Consulta glpi_profilerights via API do core (ProfileRight::getForProfile
        // ou equivalente), retornando o bitmask atual salvo.
        return (int) \ProfileRight::getForProfile($profileId, $rightname);
    }
}
```

### Registro no `plugin_init`

```php
<?php
// setup.php

use GlpiPlugin\Meuplugin\Profile;

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    \Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);
}
```

### Criando o right para perfis já existentes (na instalação)

```php
<?php
// Ao instalar, super-admin (ou perfil equivalente) ganha o right pleno
// automaticamente; demais perfis ficam sem o right até o admin conceder.

use ProfileRight;

function plugin_meuplugin_install(): bool
{
    // ...
    ProfileRight::addProfileRights(['plugin_meuplugin_coisa']);
    return true;
}

function plugin_meuplugin_uninstall(): bool
{
    ProfileRight::deleteProfileRights(['plugin_meuplugin_coisa']);
    return true;
}
```

## Exemplos incorretos

```php
// ERRADO: criar uma tela de configuração PRÓPRIA e separada para
// gerenciar quem pode fazer o quê no plugin, ignorando a tela nativa
// de Perfis. Fragmenta a administração de rights em dois lugares e
// confunde o administrador (e geralmente reimplementa pior o que o
// core já resolve).
```

```php
// ERRADO: esquecer ProfileRight::addProfileRights() no install —
// o right nem aparece em glpi_profilerights até o primeiro acesso à
// tela de Perfis daquele right específico, gerando comportamento
// inconsistente logo após a instalação.
```

```php
// ERRADO: uninstall que não chama deleteProfileRights() — deixa
// entradas fantasma em glpi_profilerights referenciando um right
// que não existe mais.
```

## Boas práticas

- Sempre integre à tela nativa de Perfis via aba registrada — é o padrão que todo administrador de GLPI já conhece.
- `ProfileRight::addProfileRights()` no install e `deleteProfileRights()` no uninstall, simetricamente (ver `02-Lifecycle.md`).
- Agrupe rights logicamente relacionados numa única seção/tabela da aba, com rótulos claros.
- Documente no README quais rights o plugin cria e o que cada nível (incluindo customizados) permite.

## Anti-patterns

- Tela de configuração de permissões paralela à tela de Perfis do core.
- Right criado em runtime (só quando o primeiro item é usado) em vez de no install.
- Esquecer de expor níveis customizados (`APPROVE` etc.) na matriz — o right existe no código mas nenhum admin consegue concedê-lo pela UI.

## Checklist

- [ ] Classe `Profile` do plugin registrada como aba de `\Profile` via `Plugin::registerClass`
- [ ] Todos os rights do plugin (incluindo customizados) aparecem na matriz da aba
- [ ] `ProfileRight::addProfileRights()` no install / `deleteProfileRights()` no uninstall
- [ ] Nenhuma tela de permissão paralela fora da UI nativa de Perfis

## Dicas de performance

- A matriz de rights é montada uma vez por abertura do form de Profile — evite consultas custosas dentro de `showFormMeuplugin`; os valores de `glpi_profilerights` são poucos registros e baratos de ler.

## Dicas de segurança

- Um right que existe no código mas não aparece na UI de Perfis tende a ficar "sempre concedido" por engano (esquecido em `true` durante desenvolvimento) — expor na UI é também uma proteção contra esse tipo de deslize.
- `deleteProfileRights()` no uninstall evita que um right "morto" seja reaproveitado inadvertidamente por outro plugin no futuro caso reutilize a mesma string de nome.

## Referências

- Developer API — Rights e Profile: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/rights.html
- Documentos relacionados: `04-Rights.md`, `08-CommonGLPI.md`, `02-Lifecycle.md`
