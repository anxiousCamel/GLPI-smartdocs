# BugFix — Prompt Operacional

Use quando a tarefa for **corrigir um bug reportado em plugin GLPI**.

## Sequência obrigatória

1. **Reproduzir o problema antes de corrigir.** Se não for possível reproduzir, pedir mais informação (versão do GLPI, passos, mensagem de erro exata, log relevante) em vez de adivinhar a causa.
2. **Consultar `GLPI10/26-Debugging.md`** — checar `files/_log/php-errors.log`, ativar modo debug se for questão de performance/query, checar `check_prerequisites`/`check_config` se for problema de instalação/ativação.
3. **Identificar a causa raiz**, não só o sintoma. Sintomas comuns e onde procurar:
   - Página em branco / erro fatal → `php-errors.log`
   - Plugin não ativa → `check_prerequisites()`/`check_config()`
   - Hook não dispara → nome de hook/constante `Hooks::*` incorreta (`03-Hooks.md`)
   - Dado errado exibido para o usuário → checar filtro de entidade (`18-Entities.md`) ou ordem de execução de hooks concorrentes de outro plugin
   - Direito não funciona como esperado → bitmask comparado com `===` em vez de `Session::haveRight` (`04-Rights.md`)
   - Update quebra dados existentes → `install()` não idempotente/destrutivo (`02`, `06`)
4. **Corrigir a causa raiz**, não sintoma superficial (nunca "silenciar" o erro com `@` — ver `26-Debugging.md`).
5. **Adicionar um teste que cobre o bug** (regressão), se a suíte de testes existir ou estiver sendo criada (`25-Testing.md`).
6. **Verificar se o mesmo padrão de bug existe em outro lugar do plugin** (bugs de segurança/lógica frequentemente se repetem em código copiado/colado).

## Perguntas a fazer se a informação não foi dada

- Em qual versão exata do GLPI o bug ocorre?
- O bug ocorre para todos os usuários ou só para um perfil/right específico?
- Há mensagem de erro na tela ou no log?
- O bug é novo (regressão) ou sempre existiu?

## O que nunca fazer

- Corrigir sintoma sem entender a causa (ex.: adicionar `@` para suprimir warning em vez de investigar por que ele ocorre).
- Corrigir um bug de segurança de forma que reduza uma checagem de right/CSRF "para simplificar".
