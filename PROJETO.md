# INSTRUÇÃO DE TRABALHO — SmartDocs Plugin GLPI

---

## SUMÁRIO

1. [Objetivo](#1-objetivo)
2. [Referência](#2-referência)
3. [Glossário](#3-glossário)
4. [Público Alvo](#4-público-alvo)
5. [Confidencialidade e Sigilo das Informações](#5-confidencialidade-e-sigilo-das-informações)
6. [Regras Gerais](#6-regras-gerais)
   - 6.1 [O que é essencial para a Versão 1.0?](#61-o-que-é-essencial-para-a-versão-10)
   - 6.2 [Expectativas Futuras](#62-expectativas-futuras)
   - 6.3 [Especificação Técnica e Arquitetura](#63-especificação-técnica-e-arquitetura)
   - 6.4 [Documentação da API (Endpoints)](#64-documentação-da-api-endpoints)
   - 6.5 [Guia de Configuração do Ambiente (Setup)](#65-guia-de-configuração-do-ambiente-setup)
   - 6.6 [Padrões de Código e Versionamento](#66-padrões-de-código-e-versionamento)
   - 6.7 [Inteligência Artificial](#67-inteligência-artificial)
   - 6.8 [Documentação de Testes e Deploy](#68-documentação-de-testes-e-deploy)
7. [Fluxo do Processo](#7-fluxo-do-processo)
8. [Documentos de Referência](#8-documentos-de-referência)
9. [Distribuição](#9-distribuição)
10. [Autoridade e Responsabilidade](#10-autoridade-e-responsabilidade)
11. [Apêndice Técnico — Análise do RegCheck](#11-apêndice-técnico--análise-do-regcheck)
12. [Apêndice Técnico — Arquitetura Detalhada do Plugin](#12-apêndice-técnico--arquitetura-detalhada-do-plugin)
13. [Apêndice Técnico — Módulos](#13-apêndice-técnico--módulos)
14. [Apêndice Técnico — Banco de Dados](#14-apêndice-técnico--banco-de-dados)
15. [Registro de Progresso](#15-registro-de-progresso)

---

## 1. OBJETIVO

Desenvolver o plugin **SmartDocs** para o sistema GLPI (versões 10.x e 11.x), incorporando ao GLPI as funcionalidades hoje realizadas por um sistema externo (RegCheck), eliminando a necessidade de manter duas aplicações separadas em paralelo.

O plugin transforma o GLPI em uma plataforma completa de:

- **Documentação técnica:** geração de documentos PDF com layout visual configurável (preventivas, laudos, checklists, etiquetas)
- **Digitalização inteligente:** leitura de QR Code, código de barras e OCR de etiquetas via câmera do dispositivo, com pré-preenchimento automático de campos de cadastro no GLPI
- **Gestão de preventivas:** preenchimento guiado de documentos vinculados a equipamentos, técnicos e chamados do GLPI
- **Biblioteca técnica:** repositório de manuais, procedimentos, contratos e garantias vinculados a qualquer objeto do GLPI

Todo o fluxo ocorre dentro do próprio GLPI, utilizando os usuários, perfis, entidades e ativos já cadastrados, sem necessidade de login ou sincronização com sistema externo.

---

## 2. REFERÊNCIA

Não aplicável.

---

## 3. GLOSSÁRIO

- **Software:** conjunto de instruções, dados ou programas lógicos que dizem aos componentes físicos (hardware) de um computador, celular ou dispositivo o que e como fazer.
- **Hardware:** é a parte física e tangível de computadores e dispositivos eletrônicos, incluindo componentes internos (processador, memória) e externos (mouse, monitor).
- **Front-end:** é a parte visível e interativa de um site ou aplicativo, construída com HTML, CSS e JavaScript.
- **Back-end:** é a parte "invisível" de um site ou aplicativo, responsável pela lógica de negócios, gerenciamento de dados e funcionamento do servidor.
- **Stack Tecnológica:** é o conjunto de ferramentas, linguagens de programação, frameworks, bancos de dados e serviços de infraestrutura utilizados para desenvolver e executar uma aplicação.
- **API:** é um conjunto de regras e normas que permite que diferentes sistemas de software se comuniquem e troquem dados entre si.
- **Endpoints:** é o ponto final de comunicação, geralmente uma URL em APIs, que conecta clientes a servidores para acessar recursos.
- **Logs:** registros cronológicos automáticos de eventos, atividades ou transações ocorridas em sistemas, servidores, redes ou aplicativos.
- **GLPI:** é um sistema de código aberto (open-source) gratuito para gerenciamento de ativos de TI e central de serviços (helpdesk/service desk).
- **Helpdesk:** é um serviço de suporte técnico focado em resolver problemas simples e dúvidas de clientes ou funcionários, geralmente no "primeiro nível".
- **Service desk:** é uma central de serviços de TI estratégica, agindo como ponto único de contato (SPOC) para usuários, colaboradores e clientes, focado na gestão de incidentes, solicitações e problemas.
- **SETUP:** configuração, montagem ou organização de equipamentos (hardware/software) para que um ambiente de desenvolvimento ou produção funcione corretamente.
- **Padrão REST:** estilo arquitetural para sistemas distribuídos, como web services, focado na manipulação de recursos via HTTP.
  - **Método GET:** solicita e recupera dados de um servidor, sem modificá-los.
  - **Método POST:** envia dados ao servidor para criar ou atualizar recursos.
  - **Método PUT:** atualiza ou substitui completamente um recurso existente.
  - **Método DELETE:** exclui um recurso específico no servidor.
- **Plugin GLPI:** módulo de extensão instalado dentro do GLPI que adiciona funcionalidades sem modificar o código-fonte principal do sistema.
- **OCR (Optical Character Recognition):** tecnologia que converte imagens de texto (fotos, scans, PDFs) em texto digital editável e pesquisável.
- **Template:** modelo de documento pré-configurado com campos posicionados, que pode ser preenchido múltiplas vezes para gerar documentos padronizados.
- **Binding Key:** vínculo entre um campo de um template e um dado específico de um objeto do GLPI (ex: `eq.serie` → campo "Número de série" de um ativo).
- **Preventiva:** documento gerado durante uma manutenção preventiva de equipamentos, registrando o estado e as ações realizadas em cada item.
- **RegCheck:** sistema externo (Node.js/Next.js) que hoje executa as funções a serem incorporadas pelo plugin SmartDocs.
- **QR Code / Código de Barras:** etiquetas de identificação de equipamentos lidas via câmera para preenchimento automático de dados.
- **Canvas:** área de desenho no navegador (tecnologia HTML5) usada para renderizar o editor visual de templates.
- **Wizard:** interface de preenchimento em etapas que guia o usuário campo a campo.
- **Soft Delete:** exclusão lógica de um registro — o dado não é apagado do banco, apenas marcado como removido, preservando o histórico.
- **Optimistic Locking:** técnica para evitar conflitos em edições simultâneas — valida que o dado não foi alterado por outro usuário antes de salvar.

---

## 4. PÚBLICO ALVO

O plugin será utilizado pelos seguintes perfis dentro da empresa:

| Perfil                         | Forma de uso                                                                                                                                                 |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Técnico de TI (campo)**      | Usa o scanner de câmera para ler etiquetas de equipamentos; preenche documentos de preventiva no tablet ou smartphone durante a execução do serviço em campo |
| **Analista de TI**             | Cria e configura templates de documentos PDF; vincula documentos gerados a chamados no GLPI; realiza cadastro de ativos com auxílio do OCR                   |
| **Coordenador / Gestor de TI** | Acompanha documentação gerada; acessa biblioteca técnica (manuais, contratos, garantias); consulta histórico de preventivas vinculadas a chamados            |
| **Administrador do GLPI**      | Instala e configura o plugin; define permissões de acesso por perfil; configura o provedor de OCR (local ou API externa)                                     |

---

## 5. CONFIDENCIALIDADE E SIGILO DAS INFORMAÇÕES

Conforme previsto no regulamento interno, no item "Proibições Gerais", é expressamente proibido violar e/ou transmitir assuntos restritos da empresa, divulgando, por quaisquer meios, informações ou fatos de natureza corporativa. Diante disso, ressaltamos que deve ser mantido sigilo absoluto sobre informações relacionadas a:

- Projetos, estratégias, atividades operacionais ou administrativas da empresa;
- Dados de clientes, fornecedores, parceiros e colaboradores.

Todos os colaboradores são responsáveis por zelar pela confidencialidade das informações às quais têm acesso. O descumprimento deste procedimento poderá resultar em medidas disciplinares, incluindo, em casos mais graves, a rescisão contratual por justa causa.

**Informações adicionais pertinentes ao projeto:**

- O código-fonte do plugin é de propriedade da empresa e não deve ser compartilhado externamente.
- Credenciais de ambiente (tokens de OCR, senhas de banco) não devem ser versionadas no repositório de código.
- Documentos gerados (preventivas, laudos) podem conter dados de clientes e ativos; o acesso é controlado pelo sistema de perfis do GLPI.
- Resultados de OCR armazenados podem conter dados de patrimônio e serial de equipamentos e estão sujeitos às mesmas regras de sigilo.

---

## 6. REGRAS GERAIS

### 6.1 O que é essencial para a Versão 1.0?

A versão 1.0 deve entregar o fluxo completo de ponta a ponta, cobrindo o uso mais frequente da equipe de campo:

**1. Editor Visual de Templates PDF**

- Upload de PDF base (formulário em branco, etiqueta, laudo)
- Posicionamento de campos por arrastar e soltar sobre o PDF
- Tipos de campo: texto, imagem, assinatura, checkbox
- Configuração de campos automáticos (binding keys para dados do ativo GLPI)
- Publicação e arquivamento de templates
- Versionamento automático ao publicar

**2. Preenchimento de Documentos (Wizard)**

- Seleção de template publicado
- Wizard guiado campo a campo
- Seleção de ativo GLPI → campos com binding key preenchidos automaticamente
- Suporte a múltiplos equipamentos por documento (preventivas com N itens)
- Salvamento automático de rascunho

**3. Geração de PDF**

- Geração assíncrona do PDF final com todos os campos aplicados
- Suporte a repetição em grade (ex: 50 etiquetas em um único PDF)
- Download do arquivo gerado

**4. Scanner de Etiquetas com OCR**

- Botão de câmera ao lado dos campos de cadastro de ativos no GLPI
- Leitura de QR Code e código de barras (nativo e fallback)
- OCR de etiquetas com extração de serial, patrimônio e modelo
- Apresentação de candidatos para o usuário confirmar
- Pré-preenchimento automático do campo selecionado

**5. Vinculação a Chamados GLPI**

- Vincular documento gerado a um chamado existente
- Atribuição de técnico ao chamado
- Upload do PDF como documento do chamado com followup automático

**6. Controle de Acesso**

- Permissões por perfil usando o sistema nativo do GLPI
- Restrição por entidade (filial)

---

### 6.2 Expectativas Futuras

| Versão | Funcionalidade                                                                                              |
| ------ | ----------------------------------------------------------------------------------------------------------- |
| 1.1    | Gestão de equipamentos durante preventiva (adicionar, editar, remover sem perder dados preenchidos)         |
| 1.1    | Smart Repopulate — re-sincronização inteligente da lista de equipamentos do documento com os ativos do GLPI |
| 1.2    | Wiki de documentação técnica com editor WYSIWYG, versionamento e categorias                                 |
| 1.2    | Biblioteca técnica — manuais, POPs, contratos e garantias vinculados a objetos GLPI                         |
| 1.3    | Pesquisa full-text em documentos gerados e resultados de OCR                                                |
| 1.3    | Suporte a múltiplos provedores de OCR (Tesseract local, Google Vision, AWS Textract)                        |
| 2.0    | Compatibilidade garantida e testada com GLPI 11.x                                                           |
| 2.0    | Notificações em tempo real (WebSocket) ao concluir geração de PDF                                           |
| Futuro | Extração automática de campos de PDFs existentes via IA                                                     |
| Futuro | Exportação em lote (ZIP com múltiplos PDFs)                                                                 |

---

### 6.3 Especificação Técnica e Arquitetura

#### Stack Tecnológica

**Back-end:**

| Tecnologia      | Versão      | Uso                                                |
| --------------- | ----------- | -------------------------------------------------- |
| PHP             | 8.2+        | Linguagem principal do plugin                      |
| GLPI            | 10.x / 11.x | Framework host — banco, auth, permissões, objetos  |
| MySQL / MariaDB | 8.0+        | Banco de dados (compartilhado com o GLPI)          |
| Composer        | 2.x         | Gerenciador de dependências PHP                    |
| FPDI            | ^2.5        | Abertura de PDFs existentes como base para overlay |
| TCPDF           | ^6.7        | Renderização de texto e imagem sobre o PDF         |
| Tesseract OCR   | 5.x         | Motor OCR local (opcional, instalado no servidor)  |

**Front-end:**

| Tecnologia          | Versão | Uso                                                      |
| ------------------- | ------ | -------------------------------------------------------- |
| JavaScript ES6+     | —      | Lógica de interface                                      |
| Konva.js            | ^9.x   | Canvas drag-and-drop no editor de templates              |
| pdfjs-dist          | ^4.x   | Renderização de páginas do PDF no browser                |
| Tesseract.js        | ^5.x   | OCR no browser para o scanner de câmera                  |
| ZXing WASM          | ^0.3.x | Leitura de QR Code e código de barras (fallback Firefox) |
| BarcodeDetector API | nativa | Leitura de QR/barcode (Chrome/Safari — sem bundle)       |
| Vite                | ^5.x   | Bundler — compila JS/CSS                                 |
| TinyMCE             | ^6.x   | Editor WYSIWYG para Wiki (reutiliza o do GLPI)           |

#### Modelagem de Dados (DER simplificado)

```
glpi_plugin_smartdocs_pdf_templates
  id | name | status | version | fill_mode | pdf_file_documents_id | entities_id

glpi_plugin_smartdocs_pdf_template_fields
  id | pdf_templates_id | type | page_index | position (JSON) | scope | binding_key

glpi_plugin_smartdocs_pdf_template_versions
  id | pdf_templates_id | version | fields_snapshot (JSON)

glpi_plugin_smartdocs_pdf_documents
  id | name | status | total_items | pdf_templates_id | template_version
     | generated_pdf_documents_id | metadata (JSON) | entities_id

glpi_plugin_smartdocs_pdf_filled_fields
  id | pdf_documents_id | pdf_template_fields_id | item_index | value | file_documents_id

glpi_plugin_smartdocs_pdf_jobs
  id | pdf_documents_id | status | attempts | error_message | date_processed

glpi_plugin_smartdocs_links
  id | smartdocs_type | smartdocs_id | itemtype | items_id

glpi_plugin_smartdocs_ocr_results
  id | source_type | file_hash | raw_text | candidates (JSON) | used_candidate (JSON)
     | itemtype | items_id | users_id

glpi_plugin_smartdocs_configs
  id | name | value
```

O campo `metadata (JSON)` do documento armazena:

- `assignments` — lista de equipamentos vinculados com itemIndex, removedAt (soft delete)
- `assignmentHistory` — log de operações para auditoria
- `nextItemIndex` — próximo índice disponível (monotonicamente crescente)
- `populateFilter` — filtros usados na criação (para Smart Repopulate)

#### Binding Keys — Vínculo campo ↔ dado GLPI

| binding key      | Dado do GLPI           | Objeto         |
| ---------------- | ---------------------- | -------------- |
| `eq.serie`       | `serial`               | Qualquer ativo |
| `eq.patrimonio`  | `otherserial`          | Qualquer ativo |
| `eq.modelo`      | nome do modelo         | Qualquer ativo |
| `eq.numero`      | `name`                 | Qualquer ativo |
| `eq.ip`          | IP associado           | NetworkName    |
| `eq.localizacao` | `name`                 | Location       |
| `ticket.id`      | `id`                   | Ticket         |
| `ticket.titulo`  | `name`                 | Ticket         |
| `user.nome`      | `firstname + realname` | User           |
| `user.email`     | `email`                | User           |
| `entity.nome`    | `name`                 | Entity         |

#### Protótipo Visual das Telas

**Tela 1 — Lista de Templates**
Menu lateral do GLPI → SmartDocs → Templates PDF. Tabela com nome, status (badge colorido: rascunho/publicado/arquivado), versão, data de criação, ações (editar, publicar, arquivar, duplicar). Botão "Novo Template" abre formulário de upload do PDF base.

**Tela 2 — Editor Visual de Template**

- _Barra superior:_ nome do template, status, indicador de autosave, botão "Publicar"
- _Painel esquerdo:_ lista de tipos de campo arrastáveis (texto, imagem, assinatura, checkbox)
- _Centro:_ PDF renderizado no browser com canvas Konva.js sobreposto — campos aparecem como retângulos coloridos com label e alças de redimensionamento
- _Painel direito:_ ao selecionar campo — tipo, label, binding key (dropdown), configurações visuais
- _Inferior:_ miniaturas de páginas para navegação

**Tela 3 — Wizard de Preenchimento**

- _Cabeçalho:_ nome do documento, template, barra de progresso
- _Campos globais:_ aparecem uma vez (data, técnico, número do chamado com buscador inline)
- _Campos por item:_ para cada equipamento — buscador de ativo GLPI (nome/serial), campos com binding key preenchidos automaticamente, campos livres para preenchimento manual
- _Lateral:_ lista de campos com indicador de preenchido/pendente
- _Rodapé:_ botão "Gerar PDF" habilitado ao concluir campos obrigatórios

**Tela 4 — Scanner de Etiquetas**
Acionado por ícone de câmera ao lado de qualquer campo de serial/patrimônio no GLPI. Modal com: preview ao vivo da câmera, botão de captura, indicador de processamento ("Lendo código…" / "Reconhecendo texto…"), lista de candidatos identificados (tipo + valor + confiança), botão de confirmação por candidato. Opção de recapturar ou digitar manualmente.

**Tela 5 — Vinculação a Chamado**
Modal acessível a partir de documento com status GENERATED. Campo de número do chamado com busca e preview (título, status, técnico atual). Campo de técnico (autocomplete de usuários GLPI). Preview do que será vinculado: lista de ativos + PDF gerado. Botão "Confirmar" com feedback de sucesso/erro.

---

### 6.4 Documentação da API (Endpoints)

O plugin expõe endpoints AJAX internos que exigem sessão ativa do GLPI. Não é uma API pública independente — usa o sistema de autenticação de sessão do GLPI.

**Padrão REST — todos em `plugins/smartdocs/ajax/`**

| Endpoint                       | Método | Descrição                                                     |
| ------------------------------ | ------ | ------------------------------------------------------------- |
| `save-fields.php`              | POST   | Salva campos do template (autosave)                           |
| `fill-field.php`               | POST   | Preenche um campo do documento                                |
| `generate-pdf.php`             | POST   | Enfileira geração do PDF                                      |
| `job-status.php`               | GET    | Polling do status da geração                                  |
| `upload-scan.php`              | POST   | OCR server-side de arquivo enviado                            |
| `asset-search.php`             | GET    | Busca multi-fallback em ativos GLPI                           |
| `link-ticket.php`              | POST   | Vincula documento e ativos ao chamado                         |
| `smart-repopulate-preview.php` | POST   | Preview do diff de repopulação (read-only)                    |
| `smart-repopulate.php`         | POST   | Executa repopulação inteligente                               |
| `equipment-assignment.php`     | POST   | Adicionar/editar/mover/remover equipamento durante preventiva |

**Contratos de Entrada/Saída (exemplos principais):**

```
POST generate-pdf.php
  Entrada:  { "document_id": 42 }
  Saída OK: { "success": true, "job_id": 7 }

GET job-status.php?job_id=7
  Saída OK: { "status": "DONE", "generated_pdf_id": 88 }
  Saída err: { "status": "ERROR", "message": "Falha ao abrir PDF base" }

POST upload-scan.php
  Entrada:  multipart — campo "file" com imagem/PDF
  Saída OK: { "candidates": [
               { "type": "serial", "value": "ABC123", "confidence": 0.88 },
               { "type": "patrimonio", "value": "001234", "confidence": 0.91 }
             ]}

POST link-ticket.php
  Entrada:  { "document_id": 42, "ticket_id": 500, "technician_id": 12 }
  Saída OK: { "success": true }

POST equipment-assignment.php
  Entrada:  { "document_id": 42, "action": "add", "items_id": 99,
              "itemtype": "Computer", "expected_updated_at": "2026-07-19T10:00:00Z" }
  Saída OK: { "success": true, "updated_at": "2026-07-19T10:01:00Z" }
```

**Códigos de Erro:**

| HTTP | Código interno           | Situação                                                         |
| ---- | ------------------------ | ---------------------------------------------------------------- |
| 400  | `INVALID_STATUS`         | Documento não está no status esperado                            |
| 400  | `MISSING_FILTER`         | Filtros obrigatórios não informados                              |
| 400  | `LAST_EQUIPMENT`         | Tentativa de remover o único equipamento ativo                   |
| 400  | `NO_EQUIPMENT_REMAINING` | Repopulação removeria todos os equipamentos                      |
| 401  | `UNAUTHORIZED`           | Sessão GLPI inválida ou expirada                                 |
| 403  | `FORBIDDEN`              | Perfil sem permissão para a operação                             |
| 404  | `NOT_FOUND`              | Recurso não encontrado                                           |
| 409  | `CONFLICT`               | Documento modificado por outro usuário — cliente deve recarregar |
| 422  | `VALIDATION_ERROR`       | Dados de entrada inválidos                                       |
| 500  | `SERVER_ERROR`           | Erro interno (ver logs do servidor)                              |

---

### 6.5 Guia de Configuração do Ambiente (Setup)

**Pré-requisitos:**

| Item                         | Versão mínima                           | Obrigatório                        |
| ---------------------------- | --------------------------------------- | ---------------------------------- |
| GLPI instalado e funcionando | 10.0.x                                  | Sim                                |
| PHP                          | 8.2                                     | Sim                                |
| MySQL / MariaDB              | 8.0                                     | Sim                                |
| Composer                     | 2.x                                     | Sim                                |
| Node.js                      | 20.x LTS                                | Sim (compilar assets)              |
| pnpm                         | 8.x                                     | Sim (compilar assets)              |
| Extensões PHP                | `gd`, `mbstring`, `curl`, `json`, `zip` | Sim                                |
| Tesseract OCR (binário)      | 5.x                                     | Não (apenas OCR local no servidor) |

**Variáveis de Ambiente / Configuração do Plugin:**

Definidas no painel após instalação em `Configuração → Plugins → SmartDocs`:

| Configuração            | Valores possíveis                              | Padrão    |
| ----------------------- | ---------------------------------------------- | --------- |
| `ocr_provider`          | `browser` / `tesseract_local` / `external_api` | `browser` |
| `ocr_api_url`           | URL da API externa de OCR                      | —         |
| `ocr_api_key`           | Chave de autenticação da API de OCR            | —         |
| `pdf_max_file_size_mb`  | Número inteiro                                 | `20`      |
| `cron_interval_minutes` | Número inteiro                                 | `2`       |
| `scanner_languages`     | `eng` / `por` / `eng+por`                      | `eng+por` |

**Passo a passo de instalação:**

```bash
# 1. Clonar na pasta de plugins do GLPI
cd /var/www/glpi/plugins
git clone <repositório> smartdocs

# 2. Instalar dependências PHP
cd smartdocs
composer install --no-dev --optimize-autoloader

# 3. Compilar assets frontend
pnpm install
pnpm build

# 4. No GLPI:
#    Configuração → Plugins → SmartDocs → Instalar → Ativar

# 5. Configurar:
#    Configuração → Plugins → SmartDocs → Configurações
#    Definir provedor OCR, tamanho máximo de arquivo, intervalo do cron

# 6. Permissões por perfil:
#    Administração → Perfis → [perfil] → aba SmartDocs

# 7. Verificar CronTask:
#    Configuração → Ações automatizadas → SmartDocsPdfQueue → Ativa
```

---

### 6.6 Padrões de Código e Versionamento

**Padrão de Nomenclatura:**

| Elemento        | Padrão                         | Exemplo                                     |
| --------------- | ------------------------------ | ------------------------------------------- |
| Classes PHP     | PascalCase                     | `PdfGenerator`, `TicketLinkService`         |
| Métodos PHP     | camelCase                      | `generatePdf()`, `computeDiff()`            |
| Variáveis PHP   | snake_case                     | `$template_id`, `$filled_fields`            |
| Tabelas SQL     | snake_case com prefixo         | `glpi_plugin_smartdocs_pdf_jobs`            |
| Arquivos PHP    | kebab-case                     | `pdf-generator.php`                         |
| Funções JS      | camelCase                      | `renderCanvas()`, `startScanner()`          |
| Componentes Vue | PascalCase                     | `TemplateEditor.vue`                        |
| Branches Git    | kebab-case com prefixo de tipo | `feature/scanner-ocr`, `fix/pdf-margin`     |
| Commits Git     | Conventional Commits           | `feat: adiciona editor visual de templates` |

**Padrões de Código PHP:**

- PSR-4 para autoload via Composer
- PSR-12 para estilo de código
- Namespace raiz: `GlpiPlugin\SmartDocs`
- Proibido lógica de negócio em `hook.php`, `front/`, `ajax/` — esses arquivos apenas chamam Controllers
- Todo acesso ao banco via Repository — sem SQL em Services ou Controllers
- Sem métodos `@deprecated` do GLPI — garantia de compatibilidade com 11.x

**Git Flow Simplificado:**

| Branch      | Finalidade                                        |
| ----------- | ------------------------------------------------- |
| `main`      | Código em produção — merge apenas via PR aprovado |
| `develop`   | Integração de features                            |
| `feature/*` | Novas funcionalidades                             |
| `fix/*`     | Correções de bugs                                 |
| `release/*` | Preparação de release                             |

**Versionamento Semântico:** `MAJOR.MINOR.PATCH` (ex: `1.0.0`, `1.1.0`)

- Cada `MINOR` ou `MAJOR` inclui script de atualização em `sql/update-X.X.X.sql`
- Declarado em `setup.php` na função `plugin_version_smartdocs()`

---

### 6.7 Inteligência Artificial

**A. IA no desenvolvimento (ferramentas de assistência):**

- **Claude Code (Anthropic):** assistente principal de desenvolvimento utilizado para geração de código PHP, JavaScript e SQL, revisão de lógica de algoritmos, documentação técnica e planejamento de arquitetura.
- As instruções de contexto do projeto são mantidas neste arquivo (`PROJETO.md`) e em `CLAUDE.md` na raiz.
- **Validação obrigatória:** todo código gerado por IA deve ser revisado por um desenvolvedor antes de ser integrado ao repositório.

**B. IA no produto (OCR):**

O plugin possui sistema de OCR com múltiplos provedores configuráveis:

| Provedor                   | Onde roda                 | Custo                    | Quando usar                   |
| -------------------------- | ------------------------- | ------------------------ | ----------------------------- |
| **Browser (Tesseract.js)** | No dispositivo do usuário | Gratuito                 | Padrão — privacidade, offline |
| **Local (Tesseract)**      | No servidor PHP           | Gratuito (infra própria) | Maior precisão, PDFs          |
| **API Externa**            | Servidor do provedor      | Pago por uso             | Máxima precisão, volume alto  |

A troca de provedor é feita no painel de configuração sem alteração de código.

---

### 6.8 Documentação de Testes e Deploy

**Plano de Testes:**

| Tipo            | Ferramenta               | Meta                       | O que testa                                                              |
| --------------- | ------------------------ | -------------------------- | ------------------------------------------------------------------------ |
| Unitário PHP    | PHPUnit                  | 70%+ Services/Repositories | RepetitionEngine, FieldCloner, NamingConvention, diff do SmartRepopulate |
| Integração PHP  | PHPUnit + banco de teste | Fluxos críticos            | Geração de PDF, vínculo a chamado, OCR local                             |
| Unitário JS     | Vitest                   | Scanner pipeline           | Parser OCR, dHash, deduplicação de candidatos                            |
| Manual          | —                        | 100% das telas             | Wizard, editor visual, scanner de câmera, vinculação a chamado           |
| Compatibilidade | Manual                   | —                          | GLPI 10.x e GLPI 11.x                                                    |

**Pipeline de CI/CD:**

```
Push → GitHub Actions:
  1. PHP CodeSniffer (PSR-12)
  2. PHPUnit (banco SQLite em memória)
  3. pnpm build (compilação dos assets JS)
  4. Vitest (testes JS)
  5. Se tudo OK: gera artefato ZIP do plugin
  6. Em merge na main: deploy em ambiente de homologação
```

**Prazos desejados / Prioridade:**

| Fase | Funcionalidades                           | Prioridade |
| ---- | ----------------------------------------- | ---------- |
| 1    | Fundação (setup, hook, SQL, permissões)   | Alta       |
| 2    | Editor Visual de Templates PDF            | Alta       |
| 3    | Preenchimento e Geração de PDF            | Alta       |
| 4    | Scanner e OCR                             | Alta       |
| 5    | Cadastro Inteligente (busca, vereditos)   | Média      |
| 6    | Vinculação a Chamados GLPI                | Média      |
| 7    | Gestão de Equipamentos Durante Preventiva | Média      |
| 8    | Smart Repopulate                          | Baixa      |
| 9    | Wiki e Biblioteca Técnica                 | Baixa      |
| 10   | Qualidade, testes, GLPI 11.x              | Baixa      |

**Detalhe do prazo de entrega por módulo:**

| Módulo                                   | Semanas desde início |
| ---------------------------------------- | -------------------- |
| Fundação + estrutura                     | 1–2                  |
| Editor Visual de Templates               | 3–5                  |
| Preenchimento + Geração PDF              | 4–7                  |
| Scanner + OCR                            | 5–8                  |
| Cadastro Inteligente                     | 7–9                  |
| Vinculação a Chamados                    | 8–10                 |
| Gestão de Preventivas + Smart Repopulate | 10–13                |
| Wiki + Biblioteca                        | 13–15                |
| Testes + GLPI 11.x                       | 15–16                |

**Prazo acordado com a Gestão:** a definir em reunião de kickoff.

---

## 7. FLUXO DO PROCESSO

### Fluxo 1 — Criação de Template PDF

```
Acessa SmartDocs → Templates
  → Clica "Novo Template" → faz upload do PDF base
  → Arrasta campos sobre as páginas do PDF no editor visual
  → Configura cada campo (tipo, binding key, visual)
  → Autosave a cada 5 segundos
  → Clica "Publicar" → template disponível para uso
```

### Fluxo 2 — Preenchimento e Geração de PDF

```
Seleciona template publicado → cria novo documento
  → Define quantidade de itens (equipamentos)
  → Wizard: preenche campos globais (data, técnico, chamado)
  → Para cada item:
      seleciona ativo GLPI → binding keys preenchidos automaticamente
      preenche campos livres (observações, assinatura, fotos)
  → Clica "Gerar PDF" → job enfileirado
  → Aguarda processamento (polling 3s) → PDF gerado
  → Baixa PDF ou vincula diretamente a um chamado
```

### Fluxo 3 — Scanner de Etiqueta / OCR

```
Está no formulário de cadastro de ativo no GLPI
  → Clica ícone de câmera ao lado do campo (serial, patrimônio, modelo)
  → Modal abre com preview da câmera
  → Aponta para etiqueta e captura
  → Sistema tenta QR Code / código de barras primeiro
      → Se encontrou: apresenta candidatos imediatamente
      → Se não encontrou: roda OCR (grayscale → contraste → threshold → Tesseract)
  → Lista de candidatos exibida (tipo, valor, confiança)
  → Usuário confirma o candidato → campo preenchido → modal fecha
```

### Fluxo 4 — Vinculação de Documento a Chamado

```
Acessa documento com status GENERATED
  → Clica "Vincular ao Chamado"
  → Digita número do chamado → sistema exibe título, status, técnico atual
  → (Opcional) Seleciona técnico responsável
  → Confirma
  → Sistema executa:
      atribui técnico ao chamado (Ticket_User)
      vincula cada ativo ao chamado (Item_Ticket)
      faz upload do PDF como Document no GLPI
      cria ITILFollowup com referência ao PDF
  → Confirmação de sucesso
```

### Fluxo 5 — Cadastro de Ativo com OCR

```
Acessa formulário de novo ativo no GLPI (Computer, Printer, etc.)
  → Clica ícone de câmera ao lado do campo "Número de série"
  → Captura foto da etiqueta do equipamento
  → OCR extrai: serial, patrimônio, modelo
  → Usuário confirma cada candidato → campos preenchidos
  → Sistema verifica duplicidade (busca por serial/patrimônio)
      → Se duplicado: alerta com link para o ativo existente
      → Se não: prossegue com o cadastro normal do GLPI
```

---

## 8. DOCUMENTOS DE REFERÊNCIA

| Código  | Registro                             | Tipo                         | Retenção / Local                     | Descarte               |
| ------- | ------------------------------------ | ---------------------------- | ------------------------------------ | ---------------------- |
| DOC-001 | PROJETO.md (este arquivo)            | Documento técnico interno    | Repositório do projeto / indefinido  | Não aplicável          |
| DOC-002 | RegCheck — documentação técnica      | Referência de sistema legado | `C:\Dev\RegCheck\docs\`              | Após migração completa |
| DOC-003 | Documentação oficial GLPI Plugin Dev | Referência externa           | glpi-developer-documentation.rtfd.io | Não aplicável          |
| DOC-004 | FPDI — Documentação                  | Referência de biblioteca     | setasign.com/products/fpdi/manual    | Não aplicável          |
| DOC-005 | Tesseract.js — Documentação          | Referência de biblioteca     | github.com/naptha/tesseract.js       | Não aplicável          |
| DOC-006 | Konva.js — Documentação              | Referência de biblioteca     | konvajs.org/docs                     | Não aplicável          |

---

## 9. DISTRIBUIÇÃO

| Setor                         | Motivo                                                  |
| ----------------------------- | ------------------------------------------------------- |
| TI — Desenvolvimento          | Executa o projeto                                       |
| TI — Infraestrutura           | Configuração do servidor (PHP, Tesseract, dependências) |
| TI — Gestão                   | Acompanhamento de prazos e entregas                     |
| Operações de Campo (técnicos) | Usuários finais do scanner e wizard de preventivas      |

---

## 10. AUTORIDADE E RESPONSABILIDADE

| Função                                       | Perfil               | Nome                     |
| -------------------------------------------- | -------------------- | ------------------------ |
| **Autoridade** — pode alterar este documento | Gerente de TI        | Clayton Ivan Silva Leite |
| **Autoridade** — pode alterar este documento | Diretora de TI       | Alice Vieira Miranda     |
| **Responsável** — executa o desenvolvimento  | Desenvolvedor        | Luiz Belmonte            |
| **Responsável** — elaboração do documento    | Analista de Processo | Diêmeson De Jesus Xavier |

---

## CONTROLE DE ALTERAÇÕES

| Rev. | Justificativa                             | Alteração                                                                                             | Elaborado/Revisado por | Data       |
| ---- | ----------------------------------------- | ----------------------------------------------------------------------------------------------------- | ---------------------- | ---------- |
| 1.0  | Criação do Documento                      | —                                                                                                     | Diêmeson Xavier        | 25/03/2026 |
| 1.1  | Detalhamento técnico do projeto SmartDocs | Preenchimento de todas as seções com base na análise do RegCheck e definição da arquitetura do plugin | Luiz Belmonte          | 19/07/2026 |

---

## CONTROLE DE APROVAÇÕES

| Elaborado/Revisado por   | Cargo                | Data |
| ------------------------ | -------------------- | ---- |
| Diêmeson De Jesus Xavier | Analista de Processo |      |
| Clayton Ivan Silva Leite | Gerente de TI        |      |

| Aprovado por         | Cargo                                   | Data |
| -------------------- | --------------------------------------- | ---- |
| Ana Paula De Lima    | Coordenador(a) de Administração Pessoal |      |
| Alice Vieira Miranda | Diretor(a) TI, ecommerce, Marketing     |      |

---

---

# APÊNDICES TÉCNICOS

> Os apêndices a seguir contêm o detalhamento técnico completo para o time de desenvolvimento. Não fazem parte do documento formal de instrução de trabalho, mas residem neste mesmo arquivo como fonte única de verdade do projeto.

---

## 11. APÊNDICE TÉCNICO — Análise do RegCheck

### 11.1 O que é o RegCheck

Sistema web monorepo TypeScript que hoje serve de ponte entre a equipe de TI e o GLPI. Construído porque o GLPI não tinha as interfaces certas para o fluxo real de trabalho de campo.

| Camada   | Tecnologia                                    |
| -------- | --------------------------------------------- |
| Frontend | Next.js 15 App Router — porta 3000            |
| Backend  | Express.js 4 — porta 4000                     |
| Banco    | PostgreSQL 16 via Prisma ORM                  |
| Storage  | MinIO (S3-compatible) — PDFs e imagens        |
| Fila     | Redis 7 + BullMQ — geração assíncrona de PDFs |

### 11.2 O que o Plugin Herda do GLPI (não precisa recriar)

| Funcionalidade                | No RegCheck                       | No Plugin                        |
| ----------------------------- | --------------------------------- | -------------------------------- |
| Autenticação e sessões        | WebAuthn + LDAP (em dev)          | GLPI nativo                      |
| Controle de acesso por perfil | Sem auth no MVP                   | `ProfileRight` do GLPI           |
| Entidades e visibilidade      | Campo`glpiEntityId` na Loja       | Sistema de entidades do GLPI     |
| Usuários e técnicos           | Modelo User próprio               | `glpi_users`                     |
| Chamados                      | Via API REST HTTP                 | Objeto nativo`Ticket`            |
| Ativos                        | Modelo Equipamento próprio + sync | Objetos nativos (Computer, etc.) |
| Upload de documentos          | MinIO/S3                          | `Document` nativo do GLPI        |
| Notificações                  | Não implementado                  | Sistema do GLPI                  |
| Auditoria GLPI                | Módulo inteiro dedicado           | Eliminado — dados já no GLPI     |

### 11.3 Mapeamento RegCheck → Plugin

| Módulo RegCheck                  | Módulo Plugin                            | Estratégia                                 |
| -------------------------------- | ---------------------------------------- | ------------------------------------------ |
| Editor visual Konva.js           | `src/Templates/` + `js/editor.bundle.js` | Porta direta do JS                         |
| RepetitionEngine + FieldCloner   | `src/PdfEngine/`                         | Porta 1:1 de TS para PHP                   |
| Worker de geração (BullMQ)       | `src/PdfEngine/PdfQueue.php` + CronTask  | Fila via tabela SQL                        |
| Overlay em PDF (`pdf-lib`)       | `src/PdfEngine/PdfGenerator.php`         | FPDI + TCPDF                               |
| Gestão de equipamentos           | GLPI nativo                              | Sem porta necessária                       |
| Scanner pipeline JS              | `js/scanner.bundle.js`                   | Reutiliza bundle compilado                 |
| OCR server-side                  | `src/OCR/`                               | PHP: Tesseract wrapper                     |
| Auditoria GLPI (HTTP)            | Eliminada                                | MySQL direto via GLPI                      |
| Busca multi-fallback             | `src/Equipment/GlpiAssetSearch.php`      | MySQL direto (sem HTTP)                    |
| Sistema de vereditos             | `src/Equipment/VerdictResolver.php`      | OCR vs dado existente                      |
| bindingKey + FilledField         | `src/Templates/BindingKeyResolver.php`   | Porta 1:1                                  |
| Gestão equip. durante preventiva | `src/Documents/EquipmentAssignment.php`  | Porta lógica soft delete / optimistic lock |
| Smart Repopulate                 | `src/Documents/SmartRepopulate.php`      | Porta 1:1 do algoritmo de diff             |
| Vinculação a chamados (HTTP)     | `src/Services/TicketLinkService.php`     | Classes nativas GLPI (sem HTTP)            |
| Nomenclatura V5.0                | `src/Equipment/NamingConvention.php`     | Porta 1:1                                  |

### 11.4 Detalhe do Scanner Pipeline (porta direta do RegCheck)

```
Captura (câmera / upload de arquivo)
  → dHash da imagem (64-bit perceptual hash)
  → Cache L1 (Map memória, ~0ms) → Cache L2 (IndexedDB, TTL 24h)
  → Deduplicação de sessão (histórico de 100 imagens)
  → Resize via OffscreenCanvas (target: 800–1200px)
  → BarcodeDetector nativo (Chrome/Safari) → confiança 0.95
      ↳ fallback ZXing WASM (Firefox) → confiança 0.90
      ↳ se barcode/QR detectado: retorna candidatos (OCR não executa)
  → Web Worker: grayscale → contrast stretch → binary threshold
  → Tesseract.js (eng+por) — até 3 tentativas com parâmetros adaptativos:
      tentativa 0: threshold=auto, contrast=1.0, maxWidth=800px
      tentativa 1: threshold=auto, contrast=1.3, maxWidth=1000px
      tentativa 2: threshold=140,  contrast=1.5, maxWidth=1200px
  → Parser: classifica texto em candidatos tipados (patrimônio, serial, modelo)
  → Deduplicação de candidatos por type:value (mantém maior confiança)
  → Retorna lista ordenada por confiança DESC
```

### 11.5 Smart Repopulate — Algoritmo de Diff

```
Entrada: assignments ativos do documento + lista de ativos GLPI (filtrado por tipo/local)

Para cada ativo GLPI:
  se tem assignment ativo com mesmo itemId:
    compara campos via binding keys
    → se idêntico: keptUnchanged
    → se diferente: keptWithChanges (lista de campos alterados com old/new)
  senão se tem assignment com removedAt para este itemId:
    → added (isReactivation=true, mantém itemIndex original)
  senão:
    → added (isReactivation=false, novo itemIndex)

Para cada assignment ativo sem correspondência no GLPI:
  → removed (soft delete: marca removedAt)

Regras de execução:
  - Non-Binding Fields (fotos, checklists, assinaturas) NUNCA alterados
  - itemIndex NUNCA reutilizado (nextItemIndex monotonicamente crescente)
  - Toda operação em transação atômica MySQL
  - expectedUpdatedAt valida ausência de conflito (409 se divergir)
  - Rejeita se resultado final seria 0 equipamentos ativos
```

---

## 12. APÊNDICE TÉCNICO — Arquitetura Detalhada do Plugin

### 12.1 Estrutura de Diretórios

```
plugins/smartdocs/
├── ajax/                        # Endpoints AJAX (thin — delegam para Controllers)
├── api/                         # REST API do plugin
├── css/                         # Estilos compilados
├── front/                       # Páginas PHP do GLPI (thin — delegam para Controllers)
├── inc/                         # Classes GLPI legacy (se necessário)
├── install/
│   ├── install.php
│   └── uninstall.php
├── js/                          # Assets compilados via Vite
│   ├── editor.bundle.js         # Editor visual de templates
│   ├── scanner.bundle.js        # Scanner OCR (porta do RegCheck)
│   └── wizard.bundle.js         # Wizard de preenchimento
├── locales/                     # Traduções .po / .mo
├── sql/
│   └── install.sql
├── src/
│   ├── GlpiCompat/              # Camada de compatibilidade v10/v11
│   │   ├── GlpiVersion.php
│   │   ├── MenuHelper.php
│   │   └── HookHelper.php
│   ├── Templates/
│   │   ├── PdfTemplate.php
│   │   ├── PdfTemplateVersion.php
│   │   ├── TemplateField.php
│   │   ├── TemplateRepository.php
│   │   └── BindingKeyResolver.php
│   ├── PdfEngine/
│   │   ├── PdfGenerator.php
│   │   ├── RepetitionEngine.php
│   │   ├── FieldCloner.php
│   │   └── PdfQueue.php
│   ├── Documents/
│   │   ├── PdfDocument.php
│   │   ├── FilledField.php
│   │   ├── EquipmentAssignment.php
│   │   ├── SmartRepopulate.php
│   │   ├── DocumentRepository.php
│   │   └── DocumentService.php
│   ├── OCR/
│   │   ├── Contracts/
│   │   │   ├── OcrProviderInterface.php
│   │   │   └── OcrResult.php
│   │   ├── Providers/
│   │   │   ├── TesseractProvider.php
│   │   │   └── ExternalApiProvider.php
│   │   └── OcrService.php
│   ├── Equipment/
│   │   ├── GlpiAssetSearch.php
│   │   ├── DuplicateDetector.php
│   │   ├── VerdictResolver.php
│   │   └── NamingConvention.php
│   ├── Library/
│   │   ├── TechnicalFile.php
│   │   └── LibraryRepository.php
│   ├── Wiki/
│   │   ├── WikiDocument.php
│   │   ├── WikiVersion.php
│   │   └── WikiCategory.php
│   ├── Services/
│   │   ├── TicketLinkService.php
│   │   └── DocumentUploadService.php
│   ├── Search/
│   │   └── FullTextSearch.php
│   ├── Permissions/
│   │   └── PermissionManager.php
│   ├── Controllers/
│   ├── DTO/
│   ├── Repositories/
│   └── Utils/
├── composer.json
├── setup.php
└── hook.php
```

### 12.2 Camadas

```
front/ ajax/ api/       ← Interface (thin — só instancia Controller)
         ↓
    Controllers/        ← Recebe request, valida sessão/permissão, chama Service, retorna response
         ↓
      Services/         ← Regras de negócio / casos de uso
         ↓
    Repositories/       ← Acesso ao banco (MySQL via DBmysql do GLPI)
         ↓
GLPI core / MySQL       ← Infraestrutura
```

### 12.3 Compatibilidade GLPI 10.x / 11.x

O GLPI 11 trouxe mudanças em namespaces, menus e métodos. Estratégia de isolamento:

- `GlpiVersion::is11OrAbove()` — detecta versão em runtime
- `MenuHelper` — registra menus com API correta para cada versão
- `HookHelper` — hooks com assinaturas que mudaram entre versões
- Proibido usar métodos `@deprecated` do GLPI 10 (serão removidos no 11)
- Usar `__()` e `_n()` para traduções (sem `$LANG` — removido no 11)
- `setup.php` declara: `PLUGIN_SMARTDOCS_MIN_GLPI = '10.0.0'` e `PLUGIN_SMARTDOCS_MAX_GLPI = '11.99.99'`
- Testes de compatibilidade antes de cada release nos dois ambientes

### 12.4 Geração de PDF (decisão de biblioteca)

**Problema:** aplicar overlays (texto, imagem, checkbox, assinatura) em posição arbitrária sobre um PDF existente.

**Decisão:** `setasign/fpdi` (importa páginas do PDF base) + `tecnickcom/tcpdf` (renderiza conteúdo sobre as páginas). FPDI abre o PDF original como template de página; TCPDF desenha os campos nas coordenadas calculadas.

**Fila sem Redis:** tabela `glpi_plugin_smartdocs_pdf_jobs` + `CronTask` do GLPI. Status: `PENDING → PROCESSING → DONE / ERROR`. Máximo 3 tentativas. Frontend faz polling a cada 3s via `ajax/job-status.php`.

---

## 13. APÊNDICE TÉCNICO — Módulos

### 13.1 Editor Visual de Templates

- States: `DRAFT → PUBLISHED → ARCHIVED`
- Coordenadas relativas 0.0–1.0 (independentes de resolução)
- Autosave: AJAX a cada 5s quando `isDirty = true`
- Undo/redo: array de histórico no frontend (Ctrl+Z / Ctrl+Y)
- Publicação: snapshot dos campos em JSON → `glpi_plugin_smartdocs_pdf_template_versions`
- Documentos ficam vinculados à `template_version` usada na criação

### 13.2 RepetitionEngine (porta de TS para PHP)

```php
// Calcula layout de grade para N itens
computeLayout(int $totalItems, array $config): RepetitionLayout
// config: rows, columns, itemsPerPage, offsetX, offsetY, startX, startY
// retorna: totalPages, pageItems[{ itemIndex, pageIndex, offsetX, offsetY }]

// FieldCloner: clona campos do template para cada slot
cloneForItems(array $baseFields, int $totalItems, array $config): array
// retorna campos com position.x/y ajustados e computedPageIndex
```

### 13.3 EquipmentAssignment (gestão durante preventiva)

Invariantes obrigatórias:

1. `itemIndex` monotonicamente crescente — nunca reutilizado
2. `totalItems` = count de assignments sem `removedAt`
3. Non-binding fields preservados em todas as operações
4. Transação MySQL atômica em cada operação
5. `expectedUpdatedAt` validado antes de qualquer escrita (409 se divergir)

Operações: `add`, `edit`, `moveLocation`, `remove` (soft delete), `reactivate`

### 13.4 Busca Multi-Fallback de Ativos (GlpiAssetSearch)

Cascata de 8 estratégias em paralelo nos tipos: Computer, Peripheral, Printer, Monitor, NetworkEquipment, Phone:

```
1. serial → campo serial (exato)
2. serial → campo serial (LIKE %valor%)
3. patrimônio → campo otherserial (original, sem zeros, com padding)
4. serial → campo otherserial
5. patrimônio → campo serial
6. nome local → campo name
7. nome local → campo otherserial
8. patrimônio → campo name / serial → campo name
```

Resultados deduplicados por `id` do ativo. Retorna lista com `matchType` (SERIAL/PATRIMONY/NAME/NONE).

### 13.5 TicketLinkService (vinculação a chamados)

```php
// Executa via classes nativas PHP do GLPI (sem HTTP externo)
linkDocumentToTicket(int $docId, int $ticketId, ?int $technicianId): void

// Internamente:
// 1. Ticket_User::add(['tickets_id', 'users_id', 'type' => 2])
// 2. Para cada ativo: Item_Ticket::add(['tickets_id', 'itemtype', 'items_id'])
// 3. Document::add() → faz upload do PDF gerado
// 4. ITILFollowup::add(['tickets_id', 'content', 'documents_id'])
// 5. Document_Item::add() → vincula ao ticket e ao followup
```

---

## 14. APÊNDICE TÉCNICO — Banco de Dados

### Templates

```sql
glpi_plugin_smartdocs_pdf_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  status ENUM('DRAFT','PUBLISHED','ARCHIVED') DEFAULT 'DRAFT',
  version INT DEFAULT 1,
  fill_mode ENUM('single','repeat') DEFAULT 'single',
  pdf_file_documents_id INT,
  entities_id INT DEFAULT 0,
  is_recursive TINYINT DEFAULT 0,
  users_id_creator INT,
  date_creation DATETIME,
  date_mod DATETIME
)

glpi_plugin_smartdocs_pdf_template_fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pdf_templates_id INT NOT NULL,
  type ENUM('text','image','signature','checkbox') NOT NULL,
  page_index INT NOT NULL,
  position JSON NOT NULL,          -- { x, y, width, height } 0.0–1.0
  config JSON,
  scope ENUM('global','item') DEFAULT 'global',
  slot_index INT,
  binding_key VARCHAR(100),
  date_creation DATETIME,
  date_mod DATETIME
)

glpi_plugin_smartdocs_pdf_template_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pdf_templates_id INT NOT NULL,
  version INT NOT NULL,
  fields_snapshot JSON NOT NULL,
  date_creation DATETIME
)
```

### Documentos

```sql
glpi_plugin_smartdocs_pdf_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  status ENUM('DRAFT','IN_PROGRESS','GENERATING','GENERATED','ERROR') DEFAULT 'DRAFT',
  total_items INT DEFAULT 1,
  pdf_templates_id INT NOT NULL,
  template_version INT NOT NULL,
  generated_pdf_documents_id INT,
  metadata JSON,
  entities_id INT DEFAULT 0,
  users_id_creator INT,
  date_creation DATETIME,
  date_mod DATETIME
)

glpi_plugin_smartdocs_pdf_filled_fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pdf_documents_id INT NOT NULL,
  pdf_template_fields_id INT NOT NULL,
  item_index INT DEFAULT 0,
  value TEXT,
  file_documents_id INT,
  date_creation DATETIME,
  date_mod DATETIME
)

glpi_plugin_smartdocs_pdf_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pdf_documents_id INT NOT NULL,
  status ENUM('PENDING','PROCESSING','DONE','ERROR') DEFAULT 'PENDING',
  attempts INT DEFAULT 0,
  error_message TEXT,
  date_creation DATETIME,
  date_processed DATETIME
)
```

### Wiki, Biblioteca e Vínculos

```sql
glpi_plugin_smartdocs_wiki_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  wiki_categories_id INT DEFAULT 0,
  entities_id INT DEFAULT 0,
  is_recursive TINYINT DEFAULT 0
)

glpi_plugin_smartdocs_wiki_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  content LONGTEXT,
  version INT DEFAULT 1,
  wiki_categories_id INT DEFAULT 0,
  entities_id INT DEFAULT 0,
  is_recursive TINYINT DEFAULT 0,
  users_id_creator INT,
  users_id_lastupdater INT,
  date_creation DATETIME,
  date_mod DATETIME,
  FULLTEXT KEY ft_content (name, content)
)

glpi_plugin_smartdocs_wiki_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wiki_documents_id INT NOT NULL,
  version INT NOT NULL,
  content LONGTEXT,
  users_id INT,
  date_creation DATETIME
)

-- Associações genéricas (qualquer objeto GLPI)
glpi_plugin_smartdocs_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  smartdocs_type ENUM('pdf_document','wiki_document','library_file') NOT NULL,
  smartdocs_id INT NOT NULL,
  itemtype VARCHAR(100) NOT NULL,
  items_id INT NOT NULL,
  date_creation DATETIME,
  INDEX idx_item (itemtype, items_id)
)

-- OCR: histórico de resultados
glpi_plugin_smartdocs_ocr_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  source_type ENUM('upload','camera') NOT NULL,
  file_hash VARCHAR(64),
  raw_text TEXT,
  candidates JSON,
  used_candidate JSON,
  itemtype VARCHAR(100),
  items_id INT,
  users_id INT,
  date_creation DATETIME
)

-- Configuração
glpi_plugin_smartdocs_configs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  value TEXT,
  date_mod DATETIME
)
```

---

## 15. IMPLEMENTAÇÃO — PASSO A PASSO

> Guia de desenvolvimento sequencial. Cada fase só começa quando a anterior está funcionando no Docker local. Status: `[ ]` pendente · `[x]` concluído · `[~]` em andamento.

---

### FASE 1 — Fundação

**Objetivo:** plugin aparece no gerenciador do GLPI, instala sem erros, cria as tabelas no banco e exibe entrada no menu.

**Arquivos a criar (nesta ordem):**

#### 1.1 `composer.json`

Define o autoload PSR-4 e as dependências PHP. Primeiro arquivo a criar pois os outros dependem do namespace.

```
Namespace raiz: GlpiPlugin\SmartDocs → src/
Dependências:
  setasign/fpdi: ^2.5
  tecnickcom/tcpdf: ^6.7
  thiagoalessio/tesseract_ocr: ^2.12
  smalot/pdfparser: ^2.0
  intervention/image: ^3.0
Dev:
  phpunit/phpunit: ^11
```

Após criar: rodar `composer install` dentro do container.

#### 1.2 `setup.php`

Ponto de entrada do plugin. O GLPI carrega este arquivo automaticamente ao detectar a pasta em `plugins/`.

Funções obrigatórias que o GLPI procura:

| Função                                   | O que faz                                                         |
| ---------------------------------------- | ----------------------------------------------------------------- |
| `plugin_version_smartdocs()`             | Retorna array com name, version, author, homepage, minGlpiVersion |
| `plugin_smartdocs_check_prerequisites()` | Valida PHP >= 8.2, extensões necessárias                          |
| `plugin_smartdocs_check_config()`        | Sempre retorna true (config feita no painel)                      |
| `plugin_smartdocs_init()`                | Inclui hook.php e registra o autoload do Composer                 |

#### 1.3 `hook.php`

Todas as funções de hook do GLPI. Nesta fase, apenas as essenciais:

| Função                              | O que faz                                     |
| ----------------------------------- | --------------------------------------------- |
| `plugin_smartdocs_install()`        | Chama `install/install.php` → cria tabelas    |
| `plugin_smartdocs_uninstall()`      | Chama `install/uninstall.php` → dropa tabelas |
| `plugin_smartdocs_getMenuContent()` | Registra entradas no menu lateral do GLPI     |

#### 1.4 `install/install.php`

Cria todas as tabelas do banco. Deve ser idempotente — se tabela já existe, não recria.

Ordem de criação (respeitar dependências de FK):

1. `glpi_plugin_smartdocs_configs`
2. `glpi_plugin_smartdocs_pdf_templates`
3. `glpi_plugin_smartdocs_pdf_template_versions`
4. `glpi_plugin_smartdocs_pdf_template_fields`
5. `glpi_plugin_smartdocs_pdf_documents`
6. `glpi_plugin_smartdocs_pdf_filled_fields`
7. `glpi_plugin_smartdocs_pdf_jobs`
8. `glpi_plugin_smartdocs_links`
9. `glpi_plugin_smartdocs_ocr_results`
10. `glpi_plugin_smartdocs_wiki_categories`
11. `glpi_plugin_smartdocs_wiki_documents`
12. `glpi_plugin_smartdocs_wiki_versions`

Também insere configurações padrão em `glpi_plugin_smartdocs_configs`.

#### 1.5 `install/uninstall.php`

Dropa todas as tabelas na ordem inversa da criação. Remove entradas de `glpi_displaypreferences` e `glpi_profiles` relacionadas ao plugin.

#### 1.6 `src/Permissions/PermissionManager.php`

Integração com o sistema de perfis do GLPI (`ProfileRight`).

Direitos a registrar:

| Constante                  | Valor | O que controla             |
| -------------------------- | ----- | -------------------------- |
| `SMARTDOCS_TEMPLATE_READ`  | 1     | Ver templates              |
| `SMARTDOCS_TEMPLATE_WRITE` | 2     | Criar/editar templates     |
| `SMARTDOCS_DOCUMENT_READ`  | 4     | Ver documentos             |
| `SMARTDOCS_DOCUMENT_WRITE` | 8     | Criar/preencher documentos |
| `SMARTDOCS_OCR_USE`        | 16    | Usar scanner OCR           |
| `SMARTDOCS_ADMIN`          | 32    | Configurar o plugin        |

#### 1.7 `front/smartdocs.php`

Página inicial do plugin (lista de documentos recentes). Thin — apenas verifica sessão e inclui o Controller.

**Critério de conclusão da Fase 1:**

- [ ] Plugin aparece em `Configuração → Plugins`
- [ ] Botão "Instalar" funciona sem erros
- [ ] Tabelas criadas no banco (verificar no phpMyAdmin)
- [ ] Entrada "SmartDocs" aparece no menu lateral
- [ ] Botão "Desinstalar" remove as tabelas

---

### FASE 2 — Editor Visual de Templates PDF

**Objetivo:** usuário consegue criar um template, fazer upload de um PDF, posicionar campos arrastando e publicar.

**Dependências:** Fase 1 concluída.

**Arquivos a criar:**

#### 2.1 `src/Templates/PdfTemplate.php`

Modelo principal do template. Estende `CommonDBTM` do GLPI para herdar CRUD, ACL e logs automáticos.

Campos mapeados: `name`, `status`, `version`, `fill_mode`, `pdf_file_documents_id`, `entities_id`

Métodos principais:

- `publish()` → muda status para PUBLISHED, salva snapshot de campos em `PdfTemplateVersion`
- `archive()` → muda status para ARCHIVED
- `duplicate()` → cria cópia em DRAFT

#### 2.2 `src/Templates/TemplateField.php`

Modelo de campo posicionado. Um template tem N campos.

Campos: `pdf_templates_id`, `type`, `page_index`, `position` (JSON), `config` (JSON), `scope`, `binding_key`

#### 2.3 `src/Templates/TemplateRepository.php`

Acesso ao banco para templates e campos. Métodos:

- `findById(int $id): ?array`
- `findPublished(int $entityId): array`
- `saveFields(int $templateId, array $fields): void`
- `getFields(int $templateId): array`

#### 2.4 `src/Templates/BindingKeyResolver.php`

Traduz uma binding key + ID do ativo GLPI → valor concreto.

```
resolve('eq.serie', 'Computer', 42) → '5CD1234XYZ'
resolve('eq.patrimonio', 'Computer', 42) → '001234'
resolve('user.nome', 'User', 7) → 'João Silva'
resolve('ticket.id', 'Ticket', 500) → '500'
```

Usa as classes nativas do GLPI (`Computer::getFromDB()`, `User::getFromDB()`, etc.)

#### 2.5 `front/pdftemplate.php`

Lista de templates. Usa `Search` do GLPI para exibir tabela paginada com filtros.

#### 2.6 `front/pdftemplate.form.php`

Formulário de criação/edição. Para templates novos: formulário de upload do PDF base. Para templates existentes: redireciona para o editor visual.

#### 2.7 `front/pdftemplate.editor.php`

Página do editor visual. Carrega o bundle JS `js/editor.bundle.js`. PHP apenas injeta as variáveis necessárias (template_id, fields JSON, PDF URL).

#### 2.8 `ajax/get-template.php`

Retorna dados do template (campos + URL do PDF) em JSON para o editor JS.

#### 2.9 `ajax/save-fields.php`

Recebe array de campos do autosave JS e persiste via `TemplateRepository::saveFields()`.

#### 2.10 `ajax/publish-template.php`

Executa `PdfTemplate::publish()` — valida que há pelo menos 1 campo, cria versão snapshot.

#### 2.11 `js-src/editor/` (frontend Vite)

Componentes do editor visual:

```
js-src/editor/
  index.js           ← entry point
  PdfRenderer.js     ← carrega e renderiza PDF com pdfjs-dist
  CanvasEditor.js    ← Konva.js: campos arrastáveis sobre o PDF
  FieldPanel.js      ← painel esquerdo com tipos de campo
  PropertiesPanel.js ← painel direito com propriedades do campo selecionado
  HistoryManager.js  ← undo/redo
  Autosave.js        ← timer + POST para ajax/save-fields.php
```

Após `pnpm build`: gera `js/editor.bundle.js`

**Critério de conclusão da Fase 2:**

- [ ] Criar template com nome e upload de PDF
- [ ] PDF renderizado no editor (páginas visíveis)
- [ ] Arrastar campo de texto para cima do PDF
- [ ] Campo salvo com posição correta (verificar no banco)
- [ ] Autosave funciona (log no console a cada 5s)
- [ ] Undo/redo com Ctrl+Z / Ctrl+Y
- [ ] Publicar template muda status e cria versão

---

### FASE 3 — Preenchimento e Geração de PDF

**Objetivo:** usuário cria documento a partir de template publicado, preenche campos, gera o PDF e faz download.

**Dependências:** Fase 2 concluída, pelo menos 1 template publicado.

**Arquivos a criar:**

#### 3.1 `src/Documents/PdfDocument.php`

Modelo do documento. Estende `CommonDBTM`.

Campos: `name`, `status`, `total_items`, `pdf_templates_id`, `template_version`, `generated_pdf_documents_id`, `metadata` (JSON)

Transições de status válidas:

```
DRAFT → IN_PROGRESS (ao abrir wizard)
IN_PROGRESS → GENERATING (ao clicar "Gerar PDF")
GENERATING → GENERATED (worker conclui)
GENERATING → ERROR (worker falha)
ERROR → GENERATING (retentar)
```

#### 3.2 `src/Documents/FilledField.php`

Modelo de campo preenchido. Um documento tem N campos preenchidos (um por `item_index`).

#### 3.3 `src/Documents/DocumentRepository.php`

Acesso ao banco. Métodos:

- `findById(int $id): ?array`
- `saveFilledField(int $docId, int $fieldId, int $itemIndex, string $value): void`
- `getFilledFields(int $docId): array`
- `updateStatus(int $docId, string $status): void`

#### 3.4 `src/Documents/DocumentService.php`

Regras de negócio do documento. Métodos:

- `createFromTemplate(int $templateId, string $name, int $totalItems): int`
- `fillField(int $docId, int $fieldId, int $itemIndex, mixed $value): void`
- `selectAsset(int $docId, int $itemIndex, string $itemtype, int $itemsId): void` → resolve binding keys e preenche automaticamente
- `requestGeneration(int $docId): int` → cria job na fila, retorna job_id

#### 3.5 `src/PdfEngine/RepetitionEngine.php`

Porta 1:1 do TypeScript. Calcula layout de grade para N itens.

```php
computeLayout(int $totalItems, array $config): array
// retorna: ['totalPages' => N, 'pageItems' => [[itemIndex, pageIndex, offsetX, offsetY], ...]]
```

#### 3.6 `src/PdfEngine/FieldCloner.php`

Porta 1:1 do TypeScript. Clona campos base para cada slot.

```php
cloneForItems(array $baseFields, int $totalItems, array $config): array
// retorna campos com position.x/y ajustados e computedPageIndex
```

#### 3.7 `src/PdfEngine/PdfGenerator.php`

Aplica overlays no PDF base usando FPDI + TCPDF.

```php
generate(string $pdfBasePath, array $pages, array $fieldOverlays): string
// retorna caminho do PDF gerado
```

Tipos de overlay suportados:

- `text`: posiciona string no PDF com fonte e tamanho configurados
- `image`: redimensiona e posiciona imagem
- `checkbox`: desenha quadrado preenchido ou vazio
- `signature`: posiciona imagem da assinatura

#### 3.8 `src/PdfEngine/PdfQueue.php`

Gerencia a fila de jobs na tabela `glpi_plugin_smartdocs_pdf_jobs`.

```php
enqueue(int $documentId): int          // insere job PENDING, retorna job_id
getStatus(int $jobId): string          // retorna status atual
processNext(): void                    // pega próximo PENDING, processa, atualiza status
```

#### 3.9 `src/PdfEngine/PdfCronTask.php`

Integração com o sistema de CronTask do GLPI.

Registrado no `hook.php` via `plugin_smartdocs_cronInfo()` e `plugin_smartdocs_cronProcessPdfQueue()`.

Executa `PdfQueue::processNext()` a cada N minutos (configurável).

#### 3.10 `ajax/fill-field.php`

Recebe `{ document_id, field_id, item_index, value }` → chama `DocumentService::fillField()`.

#### 3.11 `ajax/select-asset.php`

Recebe `{ document_id, item_index, itemtype, items_id }` → resolve binding keys e retorna campos preenchidos para o frontend atualizar o wizard.

#### 3.12 `ajax/generate-pdf.php`

Recebe `{ document_id }` → chama `DocumentService::requestGeneration()` → retorna `{ job_id }`.

#### 3.13 `ajax/job-status.php`

Recebe `?job_id=X` → retorna `{ status, generated_pdf_id? }`. Frontend faz polling a cada 3s.

#### 3.14 `front/pdfdocument.php`

Lista de documentos com filtro por status.

#### 3.15 `front/pdfdocument.fill.php`

Página do wizard de preenchimento. PHP injeta dados do documento e template. JS controla a navegação entre campos.

**Critério de conclusão da Fase 3:**

- [ ] Criar documento a partir de template publicado
- [ ] Wizard exibe campos na ordem correta
- [ ] Selecionar ativo GLPI preenche binding keys automaticamente
- [ ] Campos preenchidos salvos no banco
- [ ] Clicar "Gerar PDF" cria job na fila
- [ ] CronTask processa o job
- [ ] PDF gerado aparece para download
- [ ] Repetição: documento com 3 itens gera PDF com 3 slots preenchidos

---

### FASE 4 — Scanner e OCR

**Objetivo:** botão de câmera ao lado dos campos de ativo GLPI; captura foto → extrai serial/patrimônio/modelo → preenche campo.

**Dependências:** Fase 1 concluída (fases 2 e 3 podem ser paralelas).

**Arquivos a criar:**

#### 4.1 `src/OCR/Contracts/OcrProviderInterface.php`

```php
interface OcrProviderInterface {
    public function process(string $filePath): OcrResult;
    public function supports(string $mimeType): bool;
}
```

#### 4.2 `src/OCR/OcrResult.php`

DTO com os candidatos extraídos:

```php
class OcrResult {
    public array $candidates; // [['type' => 'serial', 'value' => 'ABC123', 'confidence' => 0.88], ...]
    public string $rawText;
}
```

#### 4.3 `src/OCR/Providers/TesseractProvider.php`

Executa Tesseract via `shell_exec`. Pré-processa imagem (resize, grayscale) antes de passar ao Tesseract. Usa `thiagoalessio/tesseract_ocr`.

#### 4.4 `src/OCR/Providers/ExternalApiProvider.php`

Envia imagem para API REST configurada. Adapta resposta para `OcrResult`.

#### 4.5 `src/OCR/OcrService.php`

Fachada que seleciona o provedor conforme `glpi_plugin_smartdocs_configs.ocr_provider`. Parseia texto bruto em candidatos tipados (mesmo algoritmo do RegCheck portado para PHP).

#### 4.6 `ajax/upload-scan.php`

Recebe upload de imagem/PDF → chama `OcrService::process()` → retorna JSON com candidatos.

#### 4.7 `hook.php` — injeção do botão de câmera

Registrar hook `post_show_item` para injetar botão de câmera ao lado dos campos de serial/patrimônio nos formulários de ativos GLPI:

```php
// Em hook.php:
function plugin_smartdocs_post_show_item($params) {
    // injeta <button class="smartdocs-scanner-btn"> ao lado de input[name="serial"]
    // e input[name="otherserial"]
}
```

#### 4.8 `js-src/scanner/` (frontend Vite — porta do RegCheck)

```
js-src/scanner/
  index.js              ← entry point, exporta ScannerModal
  ScannerModal.js       ← modal com preview de câmera
  ScanPipeline.js       ← orquestrador do pipeline
  BarcodeService.js     ← BarcodeDetector + ZXing fallback
  OcrService.js         ← Tesseract.js wrapper
  AdaptiveOcr.js        ← parâmetros adaptativos por tentativa
  ImageHash.js          ← dHash para cache
  CacheService.js       ← L1 (memória) + L2 (IndexedDB)
  OcrParser.js          ← texto → candidatos tipados
  CandidateList.js      ← UI de seleção de candidatos
```

Após `pnpm build`: gera `js/scanner.bundle.js`

O bundle é injetado nas páginas de formulário de ativo via hook PHP.

**Critério de conclusão da Fase 4:**

- [ ] Botão de câmera aparece ao lado do campo "Número de série" em Computer
- [ ] Modal de câmera abre com preview ao vivo
- [ ] Captura de QR Code retorna valor imediatamente
- [ ] Captura de etiqueta de texto executa OCR e retorna candidatos
- [ ] Usuário confirma candidato → campo preenchido
- [ ] Upload de imagem (fallback sem câmera) também funciona
- [ ] Provedor server-side (Tesseract) funciona via `ajax/upload-scan.php`

---

### FASE 5 — Cadastro Inteligente

**Objetivo:** ao preencher um ativo com dados do OCR, o sistema verifica duplicatas e sugere o ativo existente.

**Dependências:** Fase 4 concluída.

**Arquivos a criar:**

#### 5.1 `src/Equipment/GlpiAssetSearch.php`

Busca multi-fallback nos ativos GLPI via MySQL direto (sem HTTP).

```php
search(string $serial, string $patrimonio, string $name): array
// retorna [['id', 'itemtype', 'name', 'serial', 'otherserial', 'matchType'], ...]
```

Cascata de 8 estratégias em paralelo para Computer, Peripheral, Printer, Monitor, NetworkEquipment, Phone.

#### 5.2 `src/Equipment/DuplicateDetector.php`

Usa `GlpiAssetSearch` para verificar se serial ou patrimônio já existem antes de salvar.

```php
detect(string $serial, string $patrimonio): ?array
// retorna o ativo duplicado encontrado, ou null
```

#### 5.3 `src/Equipment/NamingConvention.php`

Porta da nomenclatura V5.0 do RegCheck:

```php
generate(string $entityCode, string $categoria, string $subcategoria, int $sequencial): string
// LJ{entityCode}-{categoria}-{subcategoria}-{seq com 3 dígitos}
// ex: LJ01-PC-COM-003
```

#### 5.4 `ajax/check-duplicate.php`

Recebe `{ serial, patrimonio }` → retorna candidatos duplicados para o frontend exibir alerta.

#### 5.5 Hook `pre_item_add` em Computer, Printer, etc.

Intercepta o salvamento de novos ativos para verificar duplicidade. Se encontrar duplicata, bloqueia e exibe alerta com link para o ativo existente.

**Critério de conclusão da Fase 5:**

- [ ] Ao digitar serial em novo Computer, sistema verifica duplicata em tempo real
- [ ] Alerta exibido com link para ativo existente
- [ ] Busca multi-fallback encontra ativos por serial E patrimônio

---

### FASE 6 — Vinculação a Chamados

**Objetivo:** documento GENERATED pode ser vinculado a um chamado GLPI, atribuindo técnico, ativos e anexando o PDF.

**Dependências:** Fase 3 concluída.

**Arquivos a criar:**

#### 6.1 `src/Services/TicketLinkService.php`

```php
link(int $docId, int $ticketId, ?int $technicianId): void
```

Executa em sequência:

1. `Ticket_User::add()` → atribui técnico (type=2)
2. Para cada ativo no documento com `itemtype`/`items_id`: `Item_Ticket::add()`
3. `Document::add()` → cria Document no GLPI com o PDF gerado
4. `ITILFollowup::add()` → cria followup com menção ao documento
5. `Document_Item::add()` → vincula ao ticket e ao followup

#### 6.2 `ajax/search-ticket.php`

Recebe `?q=500` → retorna dados do chamado (título, status, técnico atual) para preview no modal.

#### 6.3 `ajax/link-ticket.php`

Recebe `{ document_id, ticket_id, technician_id? }` → chama `TicketLinkService::link()`.

#### 6.4 `js-src/link-ticket/` (frontend)

Modal de vinculação a chamado:

- Campo de busca por número do chamado com debounce 600ms
- Preview do chamado encontrado
- Autocomplete de técnico
- Botão confirmar

**Critério de conclusão da Fase 6:**

- [ ] Botão "Vincular ao Chamado" aparece em documento GENERATED
- [ ] Busca por ID retorna dados do chamado corretamente
- [ ] Confirmação vincula ativos, anexa PDF e cria followup
- [ ] Verificar no chamado GLPI: ativo vinculado, PDF anexado, followup criado

---

### FASE 7 — Gestão de Equipamentos Durante Preventiva

**Objetivo:** usuário pode adicionar, editar, mover e remover equipamentos de um documento IN_PROGRESS sem perder dados já preenchidos.

**Dependências:** Fase 3 concluída.

**Arquivos a criar:**

#### 7.1 `src/Documents/EquipmentAssignment.php`

Porta 1:1 do `DocumentEquipmentService` do RegCheck.

```php
add(int $docId, array $input, string $expectedUpdatedAt): array
edit(int $docId, int $itemsId, array $input, string $expectedUpdatedAt): array
moveLocation(int $docId, int $itemsId, int $newLocationId, string $expectedUpdatedAt): array
remove(int $docId, int $itemsId, string $expectedUpdatedAt): array
reactivate(int $docId, int $itemsId, string $expectedUpdatedAt): array
```

Invariantes obrigatórias (ver Apêndice 13.3).

#### 7.2 `ajax/equipment-assignment.php`

Roteador: recebe `action` (add/edit/move/remove/reactivate) → chama método correspondente de `EquipmentAssignment`.

**Critério de conclusão da Fase 7:**

- [ ] Painel de gestão acessível dentro do wizard
- [ ] Adicionar equipamento cria novo slot sem apagar dados dos outros
- [ ] Remover equipamento marca removedAt mas preserva FilledFields
- [ ] Reativar restaura equipamento com mesmo itemIndex
- [ ] Conflito de concorrência retorna 409

---

### FASE 8 — Smart Repopulate

**Objetivo:** atualizar a lista de equipamentos de um documento com base nos ativos atuais do GLPI, sem destruir dados preenchidos.

**Dependências:** Fase 7 concluída.

**Arquivos a criar:**

#### 8.1 `src/Documents/SmartRepopulate.php`

Porta 1:1 do `SmartRepopulateService` do RegCheck.

```php
preview(int $docId, ?string $itemtype, ?int $locationId): array  // read-only
execute(int $docId, string $expectedUpdatedAt, ?string $itemtype, ?int $locationId): array
computeDiff(array $activeAssignments, array $glpiAssets): array
```

#### 8.2 `ajax/smart-repopulate-preview.php` e `ajax/smart-repopulate.php`

Preview e execução. Preview é read-only, sem efeitos colaterais.

**Critério de conclusão da Fase 8:**

- [ ] Preview mostra diff correto (mantidos, novos, removidos)
- [ ] Execução aplica diff sem alterar non-binding fields
- [ ] Reativação de equipamento removido anteriormente funciona

---

### FASE 9 — Wiki e Biblioteca Técnica

**Objetivo:** área de documentação interna com editor WYSIWYG e repositório de arquivos técnicos.

**Dependências:** Fase 1 concluída.

**Arquivos a criar:**

#### 9.1 `src/Wiki/WikiDocument.php`, `WikiVersion.php`, `WikiCategory.php`

Modelos com versionamento automático ao salvar. `WikiDocument` estende `CommonDBTM`.

#### 9.2 `front/wikidocument.php`, `front/wikidocument.form.php`

Lista e formulário de edição com TinyMCE.

#### 9.3 Hook de tab em objetos GLPI

Adicionar aba "Documentos" em Computer, Ticket, User, etc. via `CommonGLPI::getTabNameForItem()` e `displayTabContentForItem()`.

#### 9.4 `src/Library/TechnicalFile.php`

Arquivos técnicos vinculados a objetos GLPI. Usa `Document` nativo do GLPI para o armazenamento físico.

**Critério de conclusão da Fase 9:**

- [ ] Criar documento Wiki com editor WYSIWYG
- [ ] Versões criadas ao salvar, histórico acessível
- [ ] Aba "SmartDocs" aparece em Computer com documentos vinculados
- [ ] Upload de arquivo técnico vinculado a um ativo

---

### FASE 10 — Qualidade e Compatibilidade

**Objetivo:** cobertura mínima de testes, CI funcionando, plugin validado no GLPI 11.x.

#### 10.1 Testes PHPUnit

```
tests/
  Unit/
    PdfEngine/RepetitionEngineTest.php
    PdfEngine/FieldClonerTest.php
    Equipment/NamingConventionTest.php
    Documents/SmartRepopulateTest.php
    OCR/OcrParserTest.php
  Integration/
    Documents/DocumentServiceTest.php
    Services/TicketLinkServiceTest.php
```

#### 10.2 Testes Vitest

```
js-src/__tests__/
  scanner/OcrParser.test.js
  scanner/BarcodeService.test.js
  editor/HistoryManager.test.js
```

#### 10.3 GitHub Actions CI

```yaml
# .github/workflows/ci.yml
on: [push, pull_request]
jobs:
  php:
    - composer install
    - vendor/bin/phpcs (PSR-12)
    - vendor/bin/phpunit
  js:
    - pnpm install
    - pnpm build
    - pnpm test
```

#### 10.4 Compatibilidade GLPI 11.x

- Testar em container GLPI 11.x separado
- Corrigir via `GlpiCompat/GlpiVersion.php` qualquer diferença de API
- Atualizar `plugin_version_smartdocs()` com range de versão suportada

**Critério de conclusão da Fase 10:**

- [ ] PHPUnit passa com 70%+ de cobertura em Services/Repositories
- [ ] Vitest passa em scanner e editor
- [ ] CI verde no GitHub Actions
- [ ] Plugin instala e funciona no GLPI 11.x

---

## 16. REGISTRO DE PROGRESSO

### 2026-07-19

- Projeto iniciado
- Analisado o RegCheck em detalhe (executive-summary, architecture, GLPI-INTEGRATION, flows, data-model, scanner, equipment-management-during-preventive, smart-repopulate)
- Mapeadas todas as funcionalidades RegCheck → módulos do plugin
- Identificado o que o plugin herda do GLPI (elimina necessidade de recriar auth, entidades, ativos, chamados, documentos)
- Documento de Instrução de Trabalho preenchido com base na análise técnica
- Apêndices técnicos detalhados consolidados neste arquivo
- Implementação passo a passo documentada (Fases 1–10)
- Ambiente Docker criado: GLPI 10.x + MariaDB + phpMyAdmin em localhost:8080
- **Próximo passo:** Fase 1 — criar `composer.json`, `setup.php`, `hook.php`, `install/install.php`

### 2026-07-19 (tarde) — Fase 1 em andamento `[~]`

**Status geral: Fase 1 de 10 em andamento (~85%). Nenhuma fase concluída ainda.**

**Concluído nesta sessão:**

- Leitura integral do PROJETO.md e análise do item 15 (Fases 1–10)
- Ambiente Docker verificado: GLPI 10.0.15 (porta 8080), MariaDB 10.11 (3306), phpMyAdmin (8081)
- **Correção 1 no `docker-compose.yml` (refatoração necessária):** plugin era montado em `/var/www/html/plugins/smartdocs`, diretório que o GLPI não lê. Corrigido para `/var/www/html/glpi/plugins/smartdocs` (GLPI instalado em `/var/www/html/glpi`, docroot Apache em `public/`)
- **Correção 2 no `docker-compose.yml` (refatoração necessária):** volumes `glpi_files` e `glpi_config` apontavam para `/var/www/html/files` e `/var/www/html/config` (fora do diretório do GLPI — nunca persistiram nada). Corrigidos para `/var/www/html/glpi/files` e `/var/www/html/glpi/config`
- **Fase 1 — arquivos criados (todos validados com `php -l`):**
  - `composer.json` — PSR-4 `GlpiPlugin\SmartDocs` → `src/`; deps fpdi ^2.5, tcpdf ^6.7, tesseract_ocr ^2.12, pdfparser ^2.0, intervention/image ^3.0; dev phpunit ^11. `composer install` executado (vendor/ gerado)
  - `setup.php` — `plugin_version_smartdocs()` (min 10.0.0 / max 11.99.99), `check_prerequisites()` (PHP ≥ 8.2 + ext gd, mbstring, curl, json, zip), `check_config()`, `plugin_smartdocs_init()` (autoload Composer + hook.php + menu_toadd + registerClass Profile)
  - `hook.php` — `plugin_smartdocs_install()`, `plugin_smartdocs_uninstall()`, `plugin_smartdocs_getMenuContent()` (fachada → MenuHelper)
  - `install/install.php` — 12 tabelas do Apêndice 14 em ordem de dependência (CREATE TABLE IF NOT EXISTS, InnoDB/utf8mb4) + seed das 6 configurações padrão (seção 6.5) + direitos padrão. Idempotente
  - `install/uninstall.php` — drop das 12 tabelas em ordem inversa + limpeza de `glpi_displaypreferences`, `glpi_profilerights` e `glpi_crontasks`
  - `src/Permissions/PermissionManager.php` — 6 direitos bitmask (`plugin_smartdocs`): TEMPLATE_READ=1, TEMPLATE_WRITE=2, DOCUMENT_READ=4, DOCUMENT_WRITE=8, OCR_USE=16, ADMIN=32; aba "SmartDocs" na tela de Perfis via `displayRightsChoiceMatrix`; direito total concedido a perfis administrativos na instalação
  - `src/GlpiCompat/GlpiVersion.php` — detecção de versão GLPI em runtime (is11OrAbove, is10, isSupported)
  - `src/GlpiCompat/MenuHelper.php` — menu top-level "SmartDocs" (multi-entradas, filtrado por permissão e por `class_exists` do módulo)
  - `src/Controllers/DashboardController.php` — painel inicial (status GLPI/PHP + atalhos de módulos)
  - `front/smartdocs.php` — página inicial thin (só sessão + delega ao Controller)

**Decisões técnicas registradas:**

1. **Menu GLPI 10:** a especificação cita `plugin_smartdocs_getMenuContent()` em hook.php (padrão legado ≤ 9.5). No GLPI 10/11 o menu é resolvido por classes em `$PLUGIN_HOOKS['menu_toadd']` com `getMenuContent()` estático (verificado em `src/Html.php` do GLPI 10.0.15). A função legada foi mantida em hook.php como fachada delegando ao `MenuHelper`, preservando a especificação e a compatibilidade.
2. **Nomes de tabela:** o GLPI derivaria `glpi_plugin_smartdocs_templates_pdftemplates` para `GlpiPlugin\SmartDocs\Templates\PdfTemplate` (verificado em `DbUtils::getTableForItemType`). Como o Apêndice 14 fixa nomes próprios (ex: `glpi_plugin_smartdocs_pdf_templates`), os modelos `CommonDBTM` das próximas fases sobrescreverão `getTable()` explicitamente.
3. **Permissões:** bitmask único `plugin_smartdocs` em `glpi_profilerights` (valores 1/2/4/8/16/32 da Fase 1.6); o salvamento da aba de perfil é feito pelo fluxo nativo do GLPI (`Profile::prepareInputForUpdate` processa os inputs `_plugin_smartdocs` — verificado em `src/Profile.php`).
4. **Composer no ambiente:** o container GLPI não tem composer; dependências instaladas via imagem oficial `composer:2` montando o diretório do plugin.

**Bloqueio atual (ambiente, não código):**

- A recriação do container `smartdocs-glpi` (necessária para aplicar a Correção 1) apagou `/var/www/html/glpi/config/config_db.php` e `glpicrypt.key`, que estavam na camada volátil do container (o entrypoint da imagem diouxx/glpi não os recria). O banco MariaDB (volume persistente) está intacto com todos os dados do GLPI.
- Sintoma: web redireciona (302) para o instalador e o console falha com "Unable to connect to database".

**Para retomar (próxima sessão) — nesta ordem:**

1. `docker compose up -d` (recriar o container GLPI com os novos mounts de volume já corrigidos)
2. Restaurar `/var/www/html/glpi/config/config_db.php` com as credenciais do `.env` (host `mariadb`, db/user/pass `glpi`) — persistirá no volume `glpi_config`
3. Regenerar `glpicrypt.key`: `docker exec smartdocs-glpi php /var/www/html/glpi/bin/console glpi:security:change_key` (dados criptografados anteriores, se houver, serão perdidos — aceitável em dev)
4. Validar: `curl http://localhost:8080/` (200, tela de login) e console conectando ao banco
5. Instalar/ativar o plugin: `php bin/console glpi:plugin:install smartdocs --username=glpi` e `glpi:plugin:activate smartdocs`
6. Validar critérios da Fase 1: plugin visível em Configuração → Plugins; 12 tabelas criadas (phpMyAdmin :8081); entrada "SmartDocs" no menu lateral; desinstalação remove as tabelas
7. Marcar critérios da Fase 1 como concluídos e iniciar a Fase 2 (Editor Visual de Templates)

**Pendências:** nenhuma de código na Fase 1. Fases 2–10 não iniciadas.
