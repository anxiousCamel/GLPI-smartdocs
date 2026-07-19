# ReleaseChecklist — Antes de Publicar uma Nova Versão

## Versionamento
- [ ] `MEUPLUGIN_VERSION` incrementado corretamente (semver: patch/minor/major conforme o impacto)
- [ ] `CHANGELOG.md` atualizado com as mudanças desta versão
- [ ] Faixa de compatibilidade GLPI (`requirements.glpi.min/max`) revisada e testada

## Migração
- [ ] `install()` testado como UPDATE a partir da versão anterior publicada
- [ ] `install()` testado como UPDATE a partir de pelo menos duas versões atrás (não só a imediatamente anterior)
- [ ] Nenhuma operação destrutiva de dado do usuário em nenhum caminho de update
- [ ] Mensagens de `$migration->displayMessage()` claras para updates com processamento longo

## Empacotamento
- [ ] `composer install --no-dev --optimize-autoloader` rodado antes de empacotar
- [ ] Diretórios de desenvolvimento excluídos do pacote (`.git/`, `tests/`, `tools/`, `node_modules/`)
- [ ] Assets JS/CSS compilados presentes (se o plugin usa build step — ver `GLPI10/21-Vue.md`)
- [ ] `locales/*.mo` compilados a partir dos `.po` mais recentes

## Testes
- [ ] Suíte de testes completa passando
- [ ] Instalação limpa testada (plugin nunca instalado antes)
- [ ] Update testado (ver seção Migração acima)
- [ ] Desativação → reativação testada
- [ ] Desinstalação testada, confirmando que nada fica órfão no banco

## Segurança
- [ ] `Checklists/SecurityChecklist.md` completo
- [ ] Nenhuma credencial/chave de API de desenvolvimento hardcoded no código

## Documentação
- [ ] README atualizado (requisitos, instalação, configuração, rights, changelog)
- [ ] Screenshots/exemplos desatualizados removidos ou atualizados

## Pós-release
- [ ] Tag de versão criada no repositório
- [ ] Release notes publicadas
