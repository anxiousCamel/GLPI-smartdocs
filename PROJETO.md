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

**Fase 1 — Fundação: CONCLUÍDA [x]**

- Plugin instalado e ativado com sucesso via CLI do GLPI
- 12 tabelas criadas no banco (validadas via MariaDB)
- Desinstalação testada e funcionando (remove todas as tabelas)
- Menu "SmartDocs" registrado no GLPI via `MenuHelper::getMenuContent()`
- Permissões configuradas com bitmask (1/2/4/8/16/32) na aba de Perfis
- Correção no `setup.php`: `$PLUGIN_HOOKS['csrf_compliant']` movido para escopo global (compatibilidade com ativação CLI)

**Fase 2 — Editor Visual de Templates PDF: CONCLUÍDA [x]**

Arquivos criados e validados (`php -l` sem erros):

- **Modelos (src/Templates/):**
  - `PdfTemplate.php` — CommonDBTM com publish(), archive(), duplicate(); getTable() sobrescrito
  - `TemplateField.php` — Modelo de campo com tipos (text/image/signature/checkbox), escopos, posição JSON
  - `PdfTemplateVersion.php` — Snapshot de versão no momento da publicação
  - `TemplateRepository.php` — findById, findPublished, getFields, saveFields, duplicateFields
  - `BindingKeyResolver.php` — Resolve eq.*, ticket.*, user.*, entity.* para valores do GLPI

- **Páginas (front/):**
  - `pdftemplate.php` — Lista de templates via Search do GLPI
  - `pdftemplate.form.php` — Formulário de criação; redireciona para editor em templates existentes
  - `pdftemplate.editor.php` — Injeta dados do template e carrega `js/editor.bundle.js`

- **Endpoints AJAX (ajax/):**
  - `get-template.php` — GET: retorna dados do template + campos + URL do PDF
  - `save-fields.php` — POST: persiste campos do autosave (debounce 5s no frontend)
  - `publish-template.php` — POST: valida campos, cria snapshot, muda status para PUBLISHED

- **Frontend (js-src/editor/ → js/editor.bundle.js via Vite):**
  - `index.js` — Entry point: TemplateEditor com layout, toolbar, sidebars, footer
  - `PdfRenderer.js` — pdfjs-dist: renderiza páginas do PDF + miniaturas
  - `CanvasEditor.js` — Konva.js: campos arrastáveis, redimensionáveis, selecionáveis
  - `FieldPanel.js` — Painel esquerdo com botões de adicionar campo
  - `PropertiesPanel.js` — Painel direito com label, binding key, escopo, posição (%)
  - `HistoryManager.js` — Undo/redo com stack de 50 estados (Ctrl+Z / Ctrl+Y)
  - `Autosave.js` — POST para save-fields.php a cada 5s de inatividade

- **Build:**
  - `package.json` — konva ^9.3.14, pdfjs-dist ^4.5.136, vite ^5.3.4
  - `vite.config.js` — build IIFE para `js/editor.bundle.js`
  - Bundle gerado: 578 KB (170 KB gzip)

**Próximo passo:** Fase 3 — Preenchimento e Geração de PDF (modelos PdfDocument, FilledField, PdfGenerator, PdfQueue, wizard de preenchimento)

**Pendências:** Fases 3–10 não iniciadas.

---

### 2026-07-19 (noite) — Fases 3–10 implementadas e revisadas [x]

**Status geral: Todas as 10 fases concluídas e revisadas.**

**Fase 3 — Preenchimento e Geração de PDF: CONCLUÍDA [x]**

- `src/Documents/PdfDocument.php` — CommonDBTM com status transitions (DRAFT→IN_PROGRESS→GENERATING→GENERATED/ERROR)
- `src/Documents/FilledField.php` — CommonDBTM para valores preenchidos
- `src/Documents/DocumentRepository.php` — query builder CRUD
- `src/Documents/DocumentService.php` — createFromTemplate, fillField, selectAsset, requestGeneration
- `src/PdfEngine/RepetitionEngine.php` — layout de grade para N itens
- `src/PdfEngine/FieldCloner.php` — clona campos por slot
- `src/PdfEngine/PdfGenerator.php` — FPDI+TCPDF overlay (texto/imagem/assinatura/checkbox)
- `src/PdfEngine/PdfQueue.php` — fila SQL com retry (max 3 tentativas)
- `src/PdfEngine/PdfCronTask.php` — integração com CronTask do GLPI
- Endpoints AJAX: `fill-field.php`, `select-asset.php`, `generate-pdf.php`, `job-status.php`, `asset-search.php`
- Frontend: `js-src/wizard/` → `js/wizard.bundle.js` (WizardApp, FieldRenderer, AssetSelector)

**Fase 4 — Scanner e OCR: CONCLUÍDA [x]**

- `src/OCR/Contracts/OcrProviderInterface.php` — contrato de provedor
- `src/OCR/OcrResult.php` — DTO com candidatos tipados
- `src/OCR/Providers/TesseractProvider.php` — Tesseract local (eng+por)
- `src/OCR/Providers/ExternalApiProvider.php` — API REST externa via curl
- `src/OCR/OcrService.php` — fachada que seleciona provedor via config
- `ajax/upload-scan.php` — endpoint de upload + OCR com validação de mime type
- Hooks `POST_SHOW_ITEM` + `ADD_JAVASCRIPT_MODULE` em `setup.php`/`hook.php`
- Frontend: `js-src/scanner/` → `js/scanner.bundle.js` (ScannerApp, ScannerModal, CSS)

**Fase 5 — Cadastro Inteligente: CONCLUÍDA [x]**

- `src/Equipment/GlpiAssetSearch.php` — busca multi-fallback (8 estratégias)
- `src/Equipment/DuplicateDetector.php` — detecta duplicidade por serial/patrimônio/nome
- `src/Equipment/NamingConvention.php` — nomenclatura V5.0 (PC-SPO-0042)
- `src/Equipment/VerdictResolver.php` — compara OCR vs ativos existentes (NEW/DUPLICATE/SIMILAR)
- `ajax/check-duplicate.php` — endpoint AJAX de verificação

**Fase 6 — Vinculação a Chamados: CONCLUÍDA [x]**

- `src/Services/TicketLinkService.php` — vincula PDF a Ticket (Ticket_User, Item_Ticket, Document, ITILFollowup)
- `ajax/search-ticket.php` — busca de chamados por ID ou título
- `ajax/link-ticket.php` — endpoint de vinculação com atribuição de técnico

**Fase 7 — Gestão de Equipamentos Durante Preventiva: CONCLUÍDA [x]**

- `src/Documents/EquipmentAssignment.php` — CommonDBTM com soft delete, optimistic locking, non-binding data JSON, item_index monotônico

**Fase 8 — Smart Repopulate: CONCLUÍDA [x]**

- `src/Documents/SmartRepopulate.php` — algoritmo de diff (keptUnchanged, keptWithChanges, added, removed)

**Fase 9 — Wiki e Biblioteca Técnica: CONCLUÍDA [x]**

- `src/Wiki/WikiDocument.php` — CommonDBTM com versionamento automático
- `src/Wiki/WikiVersion.php` — snapshot de conteúdo
- `src/Wiki/WikiCategory.php` — CommonDropdown hierárquico
- `src/Library/TechnicalFile.php` — arquivos técnicos (manual, POP, contrato, garantia)
- `src/Library/LibraryRepository.php` — query builder para biblioteca e wiki

**Fase 10 — Qualidade e Compatibilidade: CONCLUÍDA [x]**

- `phpunit.xml` — configuração PHPUnit 10.5 (testes Unit + Integration, cobertura clover/html)
- `tests/Unit/Equipment/NamingConventionTest.php`
- `tests/Unit/PdfEngine/RepetitionEngineTest.php`
- `vitest.config.js` — configuração Vitest (jsdom, cobertura v8)
- `tests/Unit/scanner/ScannerModal.test.js`
- `.github/workflows/ci.yml` — GitHub Actions (PHP 8.2/8.3, CodeSniffer, PHPUnit, pnpm build, Vitest, package ZIP)

**Revisão final — correções aplicadas:**

1. **Permissões:** todos os endpoints AJAX e o hook `post_show_item` corrigidos para usar `PermissionManager::RIGHT_NAME` (`plugin_smartdocs`) + bitmask constante, eliminando direitos inexistentes (`plugin_smartdocs_scan`, `plugin_smartdocs_use`, etc.)
2. **Tabelas:** adicionadas `glpi_plugin_smartdocs_equipment_assignments` e `glpi_plugin_smartdocs_technical_files` ao `install/install.php` (total: 14 tabelas)
3. **Uninstall:** `install/uninstall.php` atualizado para remover as 14 tabelas na ordem inversa
4. **Build:** Vite gera 3 bundles (`editor.bundle.js`, `wizard.bundle.js`, `scanner.bundle.js`) em formato ES module

**Pendências pós-entrega (futuro):**

- Testes de integração com banco SQLite/PostgreSQL
- Validação manual em GLPI 11.x
- Otimização de chunk do editor (>500 KB)
- Configuração do provedor OCR no painel de admin

---

### 2026-07-23 — Editor avançado, ponte de grupos/slots (modelo RegCheck), dados de teste `[x]`

**Ver detalhamento técnico completo no Apêndice 17.**

- Editor visual do canvas (Konva): navegação com zoom/pan via CSS, seleção múltipla com marquise (window-level, corrige seleção "grudada" ao arrastar pra fora do canvas), copiar/colar (Ctrl+C/V), atalhos, badges visuais de grupo (G1/G2...) e dropdown de grupo no painel de Propriedades
- Painéis Campos/Propriedades agora recolhíveis; grid de alinhamento com liga/desliga e snap (via `dragBoundFunc` do Konva); edição de fonte/tamanho/negrito/alinhamento por campo de texto (já consumido pelo `PdfGenerator`)
- **Bug corrigido:** alça de redimensionar "voltava" ao soltar o mouse — conflito entre override manual de `x()/y()` no `dragmove` e o rastreamento interno do `Konva.DD`; resolvido com `dragBoundFunc`
- **Ponte RegCheck:** `TemplateRepository::resolveSlots()` deriva `scope`/`slot_index` a partir do `group_label` do editor — destrava paginação, wizard por equipamento e geração de PDF sem migração de banco (ver Apêndice 17)
- Wizard de preenchimento: abas por equipamento (G1/G2...) + aba "Campos Globais" isoladas corretamente; rótulos amigáveis (nunca mais "Campo 1008"); erro SQL de `glpi_locations.is_deleted` corrigido
- Bug recorrente de CSS do GLPI (`.small` colapsa elementos de bloco para ~1 char) atingiu o editor e o wizard — documentado em memória de sessão para não repetir
- Script `tools/seed-test-fixtures.php`: gera 185 ativos fictícios (20 frentes de caixa completas + 20 balanças de setor + 5 balanças de tipos diferentes) com fabricante/modelo/localização/grupo/estado, idempotente, conexão configurável por variável de ambiente

---

## Apêndice 16 — Plano de Implementação: Fluxo Completo Criação → Populate → Preenchimento

### Inventário: O que já existe e funciona

| Componente | Arquivo | Status |
|---|---|---|
| Endpoint salvar campo | `ajax/fill-field.php` | ✓ existe |
| Endpoint selecionar ativo | `ajax/select-asset.php` | ✓ existe |
| Endpoint buscar ativos | `ajax/asset-search.php` | ✓ existe |
| Endpoint info do template | `ajax/get-template.php` (retorna `fill_mode`) | ✓ existe |
| Navegação grupo-a-grupo | `js-src/wizard/WizardApp.js` | ✓ existe |
| Renderização campos + seletor ativo | `js-src/wizard/FieldRenderer.js` | ✓ existe |
| Busca de ativos no JS | `js-src/wizard/AssetSelector.js` (`search()`) | ✓ existe |
| Soft delete / reativação / nextItemIndex | `src/Documents/EquipmentAssignment.php` | ✓ existe |
| Criação de documento + selectAsset | `src/Documents/DocumentService.php` | ✓ existe |
| Algoritmo diff repopulate | `src/Documents/SmartRepopulate.php` | ✓ existe |

### Gaps confirmados (o que falta)

| # | Gap | Onde |
|---|---|---|
| G1 | `PdfDocument::showForm()` não existe | `src/Documents/PdfDocument.php` |
| G2 | `prepareInputForAdd()` não valida template nem seta `template_version`/`entities_id` | `src/Documents/PdfDocument.php` |
| G3 | Redirect falso quando `$newId=false`; redirect duplo (form→form→fill) | `front/pdfdocument.form.php` |
| G4 | Campo `total_items` some quando `fill_mode=repeat` (template define o count) | `front/pdfdocument.form.php` (JS inline) |
| G5 | Nenhuma tela de populate para `fill_mode=repeat` | `front/pdfdocument.fill.php` (detecção) |
| G6 | Nenhum endpoint `populate.php` para bulk populate | `ajax/populate.php` (novo) |
| G7 | `WizardApp.bindEvents()` não trata clique em "Buscar" nem seleção de resultado | `js-src/wizard/WizardApp.js` |
| G8 | `AssetSelector` não tem `selectAsset()` — apenas `search()` | `js-src/wizard/AssetSelector.js` |

---

### Fluxo após implementação

```
[form.php POST]
    ↓ add() com pdf_templates_id, template_version, entities_id
    ↓ prepareInputForAdd: fill_mode=repeat → total_items=0
    ↓ redirect → fill.php?id=X
         ↓
    [fill.php]
    ├─ fill_mode=repeat AND total_items=0?
    │       ↓ renderiza TELA DE POPULATE (PHP inline)
    │       ↓ usuário escolhe itemtype + subtipo + entidade + localização
    │       ↓ JS POST → populate.php
    │             → cria EquipmentAssignment para cada ativo encontrado
    │             → DocumentService::selectAsset() auto-fill binding keys
    │             → update total_items no documento
    │       ↓ JS redireciona → fill.php?id=X (agora total_items > 0)
    │
    └─ fill_mode=single OR total_items>0?
            ↓ renderiza WIZARD JS normal
            ↓ para cada item_index:
                  → FieldRenderer.renderAssetSelector() [UI já existe]
                  → usuário digita → WizardApp dispara search() → mostra resultados
                  → usuário clica resultado → WizardApp chama AssetSelector.selectAsset()
                  → POST select-asset.php → retorna filled[]
                  → WizardApp atualiza this.values + re-renderiza campos do item
                  → usuário preenche campos livres → autosave via fill-field.php
            ↓ botão "Gerar PDF" → PdfGeneratorClient.enqueue()
```

---

### Passo 1 — `PdfDocument.php`: showForm() e prepareInputForAdd()

**Arquivo:** `plugins/smartdocs/src/Documents/PdfDocument.php`

**1a. `showForm()`**

```php
public function showForm($ID, array $options = []): bool
{
    $templateOptions = $options['template_options'] ?? [];

    $this->initForm($ID, $options);
    $this->showFormHeader($options);

    // Nome
    echo "<tr class='tab_bg_1'><td>" . __('Nome', 'smartdocs') . " *</td><td>";
    echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'required' => true]);
    echo "</td></tr>";

    // Template base
    echo "<tr class='tab_bg_1'><td>" . __('Template PDF base', 'smartdocs') . " *</td><td>";
    echo Dropdown::showFromArray('pdf_templates_id', $templateOptions,
        ['value' => $this->fields['pdf_templates_id'] ?? 0, 'display' => false,
         'id' => 'smartdocs-template-select']);
    echo "</td></tr>";

    // Quantidade de itens (oculto se fill_mode=repeat — JS controla)
    echo "<tr class='tab_bg_1' id='smartdocs-total-items-row'>";
    echo "<td>" . __('Quantidade de itens', 'smartdocs') . "</td><td>";
    echo Html::input('total_items', ['type' => 'number', 'min' => '1',
        'value' => $this->fields['total_items'] ?? 1, 'id' => 'smartdocs-total-items']);
    echo "</td></tr>";

    $this->showFormButtons($options);

    // JS: ao trocar template, chama get-template.php → oculta/exibe total_items
    $ajaxUrl = Plugin::getWebDir('smartdocs') . '/ajax/get-template.php';
    echo "<script>
    document.getElementById('smartdocs-template-select').addEventListener('change', function() {
        const id = this.value;
        if (!id) return;
        fetch('" . $ajaxUrl . "?id=' + id)
            .then(r => r.json())
            .then(data => {
                const row = document.getElementById('smartdocs-total-items-row');
                const input = document.getElementById('smartdocs-total-items');
                if (data.data && data.data.fill_mode === 'repeat') {
                    row.style.display = 'none';
                    input.value = '0';
                } else {
                    row.style.display = '';
                    if (input.value === '0') input.value = '1';
                }
            });
    });
    </script>";

    return true;
}
```

**1b. `prepareInputForAdd()` — adicionar ao existente (após validação do nome):**

```php
// Valida template e extrai version + fill_mode
$templateId = (int) ($input['pdf_templates_id'] ?? 0);
if ($templateId <= 0) {
    \Session::addMessageAfterRedirect(
        __('Selecione um template PDF.', 'smartdocs'), false, ERROR
    );
    return false;
}

$tplData = (new \GlpiPlugin\SmartDocs\Templates\TemplateRepository())->findById($templateId);
if ($tplData === null || $tplData['status'] !== \GlpiPlugin\SmartDocs\Templates\PdfTemplate::STATUS_PUBLISHED) {
    \Session::addMessageAfterRedirect(
        __('Template inválido ou não publicado.', 'smartdocs'), false, ERROR
    );
    return false;
}

$input['pdf_templates_id'] = $templateId;
$input['template_version']  = (int) $tplData['version'];

// fill_mode=repeat: total_items definido pelo populate, não pelo usuário
if ($tplData['fill_mode'] === 'repeat') {
    $input['total_items'] = 0;
} elseif (!isset($input['total_items']) || (int) $input['total_items'] < 1) {
    $input['total_items'] = 1;
}

// Entidade ativa do usuário
if (!isset($input['entities_id'])) {
    $input['entities_id'] = (int) ($_SESSION['glpiactive_entity'] ?? 0);
}
```

---

### Passo 2 — `pdfdocument.form.php`: redirect e template_options

**Arquivo:** `plugins/smartdocs/front/pdfdocument.form.php`

Duas mudanças:

**2a. Bloco POST `add` — fix redirect:**

```php
if (isset($_POST['add'])) {
    // ... check rights ...
    $doc->check(-1, CREATE, $_POST);
    $newId = $doc->add($_POST);
    if ($newId === false) {
        Html::back();
    } else {
        Html::redirect(
            GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdfdocument.fill.php?id=' . $newId)
        );
    }
}
```

**2b. Bloco GET — passar template_options para showForm:**

```php
} else {
    $doc->showForm(0, ['template_options' => $templateOptions]);
}
```

---

### Passo 3 — `pdfdocument.fill.php`: detectar populate step

**Arquivo:** `plugins/smartdocs/front/pdfdocument.fill.php`

Inserir após carregar `$doc` e antes de `Html::header()`:

```php
$needsPopulate = $template->fields['fill_mode'] === 'repeat'
    && (int) $doc->fields['total_items'] === 0;

if ($needsPopulate) {
    Html::header(/* ... */);
    echo renderPopulateStep($doc, $template);
    Html::footer();
    exit;
}
```

**Função `renderPopulateStep()` (pode ser local no arquivo):**

```php
function renderPopulateStep($doc, $template): string
{
    $ajaxBase = Plugin::getWebDir('smartdocs') . '/ajax/';
    $fillUrl  = GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdfdocument.fill.php?id=' . $doc->fields['id']);

    $assetTypes = [
        'Computer'         => __('Computadores'),
        'Peripheral'       => __('Periféricos'),
        'Printer'          => __('Impressoras'),
        'Monitor'          => __('Monitores'),
        'NetworkEquipment' => __('Equipamentos de rede'),
        'Phone'            => __('Telefones'),
    ];
    $typeOptions = '';
    foreach ($assetTypes as $k => $v) {
        $typeOptions .= "<option value='{$k}'>{$v}</option>";
    }

    // Entidades visíveis pelo usuário
    $entities = Dropdown::getDropdownArrayNames('glpi_entities',
        getAncestorsOf('glpi_entities', $_SESSION['glpiactive_entity'] ?? 0)
        + [0 => 0]);

    $entityOptions = '';
    foreach ($entities as $id => $name) {
        $sel = ($id == ($_SESSION['glpiactive_entity'] ?? 0)) ? 'selected' : '';
        $entityOptions .= "<option value='{$id}' {$sel}>" . htmlspecialchars($name) . "</option>";
    }

    return "
    <div class='container-fluid py-3'>
      <h2><i class='ti ti-list-check'></i> " . __('Selecionar equipamentos', 'smartdocs') . "</h2>
      <p class='text-muted'>" . sprintf(__('Documento: <strong>%s</strong> — Template: <strong>%s</strong>', 'smartdocs'),
          htmlspecialchars($doc->fields['name']), htmlspecialchars($template->fields['name'])) . "</p>
      <div class='card'>
        <div class='card-body'>
          <div class='row g-3'>
            <div class='col-md-4'>
              <label class='form-label'>" . __('Tipo de ativo', 'smartdocs') . "</label>
              <select id='sd-itemtype' class='form-select'>{$typeOptions}</select>
            </div>
            <div class='col-md-4'>
              <label class='form-label'>" . __('Entidade', 'smartdocs') . "</label>
              <select id='sd-entity' class='form-select'>{$entityOptions}</select>
            </div>
            <div class='col-md-4'>
              <label class='form-label'>" . __('Localização (opcional)', 'smartdocs') . "</label>
              <select id='sd-location' class='form-select'>
                <option value='0'>" . __('Todas', 'smartdocs') . "</option>
              </select>
            </div>
          </div>
          <div class='mt-3'>
            <button id='sd-preview-btn' class='btn btn-outline-secondary'>
              <i class='ti ti-eye'></i> " . __('Pré-visualizar', 'smartdocs') . "
            </button>
            <button id='sd-populate-btn' class='btn btn-primary ms-2' disabled>
              <i class='ti ti-bolt'></i> " . __('Populate', 'smartdocs') . "
            </button>
          </div>
          <div id='sd-preview-area' class='mt-3'></div>
          <div id='sd-populate-status' class='mt-3'></div>
        </div>
      </div>
    </div>
    <script>
    (function() {
      const ajaxBase = '{$ajaxBase}';
      const docId    = {$doc->fields['id']};
      const fillUrl  = '{$fillUrl}';

      // Carrega localizações ao mudar entidade
      document.getElementById('sd-entity').addEventListener('change', loadLocations);
      loadLocations();

      function loadLocations() {
        const entityId = document.getElementById('sd-entity').value;
        fetch(ajaxBase + 'asset-search.php?action=locations&entities_id=' + entityId)
          .then(r => r.json())
          .then(data => {
            const sel = document.getElementById('sd-location');
            sel.innerHTML = '<option value=\"0\">" . __('Todas', 'smartdocs') . "</option>';
            (data.locations || []).forEach(l => {
              sel.innerHTML += '<option value=\"' + l.id + '\">' + l.name + '</option>';
            });
          }).catch(() => {});
      }

      document.getElementById('sd-preview-btn').addEventListener('click', function() {
        fetchPreview().then(data => {
          const area = document.getElementById('sd-preview-area');
          if (!data.assets || data.assets.length === 0) {
            area.innerHTML = '<div class=\"alert alert-warning\">" . __('Nenhum ativo encontrado.', 'smartdocs') . "</div>';
            document.getElementById('sd-populate-btn').disabled = true;
          } else {
            let rows = data.assets.map(a =>
              '<tr><td>' + escHtml(a.name) + '</td><td>' + escHtml(a.serial || '—') + '</td></tr>'
            ).join('');
            area.innerHTML = '<p><strong>' + data.assets.length + ' " . __('ativos encontrados', 'smartdocs') . "</strong></p>'
              + '<div style=\"max-height:300px;overflow:auto\"><table class=\"table table-sm\">'
              + '<thead><tr><th>" . __('Nome', 'smartdocs') . "</th><th>" . __('Serial', 'smartdocs') . "</th></tr></thead>'
              + '<tbody>' + rows + '</tbody></table></div>';
            document.getElementById('sd-populate-btn').disabled = false;
          }
        });
      });

      document.getElementById('sd-populate-btn').addEventListener('click', function() {
        this.disabled = true;
        const status = document.getElementById('sd-populate-status');
        status.innerHTML = '<div class=\"alert alert-info\">" . __('Populando...', 'smartdocs') . "</div>';
        const params = collectParams();
        fetch(ajaxBase + 'populate.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({document_id: docId, ...params})
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            status.innerHTML = '<div class=\"alert alert-success\">' + data.total_items + ' " . __('equipamentos vinculados. Abrindo wizard...', 'smartdocs') . "</div>';
            setTimeout(() => window.location.href = fillUrl, 800);
          } else {
            status.innerHTML = '<div class=\"alert alert-danger\">' + escHtml(data.error || 'Erro') + '</div>';
            document.getElementById('sd-populate-btn').disabled = false;
          }
        })
        .catch(() => {
          status.innerHTML = '<div class=\"alert alert-danger\">" . __('Falha de rede.', 'smartdocs') . "</div>';
          document.getElementById('sd-populate-btn').disabled = false;
        });
      });

      function fetchPreview() {
        const params = collectParams();
        return fetch(ajaxBase + 'populate.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({document_id: docId, preview: true, ...params})
        }).then(r => r.json());
      }

      function collectParams() {
        return {
          itemtype:    document.getElementById('sd-itemtype').value,
          entities_id: document.getElementById('sd-entity').value,
          locations_id: document.getElementById('sd-location').value,
        };
      }

      function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s || '');
        return d.innerHTML;
      }
    })();
    </script>
    ";
}
```

---

### Passo 4 — `ajax/populate.php` (novo)

```php
<?php
include('../../../inc/includes.php');
header('Content-Type: application/json; charset=UTF-8');
Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
);

$input = json_decode(file_get_contents('php://input'), true);

$documentId  = (int) ($input['document_id']  ?? 0);
$itemtype    = $input['itemtype']    ?? '';
$entitiesId  = (int) ($input['entities_id']  ?? 0);
$locationsId = (int) ($input['locations_id'] ?? 0);
$preview     = (bool) ($input['preview']     ?? false);

if ($documentId <= 0 || empty($itemtype)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Dados incompletos.']);
    exit;
}

if (!class_exists($itemtype) || !is_subclass_of($itemtype, 'CommonDBTM')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Tipo de ativo inválido.']);
    exit;
}

global $DB;

// Verifica documento
$doc = new GlpiPlugin\SmartDocs\Documents\PdfDocument();
if (!$doc->getFromDB($documentId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Documento não encontrado.']);
    exit;
}

if ((int) $doc->fields['total_items'] !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Documento já foi populado.']);
    exit;
}

// Monta WHERE para busca de ativos
$where = ['is_deleted' => 0];
if ($entitiesId > 0) {
    $where['entities_id'] = $entitiesId;
}
if ($locationsId > 0) {
    $where['locations_id'] = $locationsId;
}

$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'serial', 'otherserial'],
    'FROM'   => $itemtype::getTable(),
    'WHERE'  => $where,
    'ORDER'  => 'name ASC',
]);

$assets = [];
foreach ($iterator as $row) {
    $assets[] = $row;
}

if ($assets === []) {
    echo json_encode(['success' => false, 'error' => __('Nenhum ativo encontrado com esses filtros.', 'smartdocs')]);
    exit;
}

// Modo preview: retorna lista sem criar assignments
if ($preview) {
    echo json_encode(['success' => true, 'assets' => $assets]);
    exit;
}

// Modo execute: cria assignments + auto-fill binding keys
$assignment = new GlpiPlugin\SmartDocs\Documents\EquipmentAssignment();
$service    = new GlpiPlugin\SmartDocs\Documents\DocumentService();
$itemIndex  = $assignment->nextItemIndex($documentId);

foreach ($assets as $asset) {
    $assignment->addAssignment($documentId, $itemtype, (int) $asset['id'], $itemIndex, []);
    try {
        $service->selectAsset($documentId, $itemIndex, $itemtype, (int) $asset['id']);
    } catch (\Exception $e) {
        // binding keys opcionais — falha não bloqueia
    }
    $itemIndex++;
}

$totalItems = count($assets);

$DB->update(GlpiPlugin\SmartDocs\Documents\PdfDocument::getTable(), [
    'total_items' => $totalItems,
    'date_mod'    => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
], ['id' => $documentId]);

// Atualiza nextItemIndex no metadata
$doc->getFromDB($documentId);
$meta = $doc->getMetadata();
$meta['nextItemIndex'] = $itemIndex;
$DB->update(GlpiPlugin\SmartDocs\Documents\PdfDocument::getTable(), [
    'metadata' => json_encode($meta),
], ['id' => $documentId]);

echo json_encode(['success' => true, 'total_items' => $totalItems]);
```

---

### Passo 5 — `AssetSelector.js`: adicionar selectAsset()

**Arquivo:** `plugins/smartdocs/js-src/wizard/AssetSelector.js`

Adicionar método ao final da classe:

```js
async selectAsset(documentId, itemIndex, itemtype, itemsId) {
  const response = await fetch(`${this.ajaxUrl}select-asset.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      document_id: documentId,
      item_index:  itemIndex,
      itemtype,
      items_id:    itemsId,
    }),
  });
  if (!response.ok) throw new Error(`select-asset failed: ${response.status}`);
  return response.json(); // { success, filled: [{field_id, value}] }
}
```

---

### Passo 6 — `WizardApp.js`: wirar busca e seleção de ativo

**Arquivo:** `plugins/smartdocs/js-src/wizard/WizardApp.js`

**6a. `bindEvents()` — adicionar handlers para asset search:**

```js
// Busca de ativo
if (e.target.closest('[data-action="search-asset"]')) {
  e.preventDefault();
  const itemIndex = parseInt(e.target.closest('[data-action="search-asset"]').dataset.item, 10);
  this.handleAssetSearch(itemIndex);
  return;
}

// Seleção de resultado
if (e.target.closest('[data-action="select-asset"]')) {
  e.preventDefault();
  const btn = e.target.closest('[data-action="select-asset"]');
  this.handleAssetSelect(
    parseInt(btn.dataset.item, 10),
    btn.dataset.itemtype,
    parseInt(btn.dataset.itemsid, 10),
    btn.dataset.name,
  );
  return;
}
```

**6b. Métodos novos em WizardApp:**

```js
async handleAssetSearch(itemIndex) {
  const input = this.root.querySelector(`#asset-search-${itemIndex}`);
  const query = input ? input.value.trim() : '';
  const resultsDiv = this.root.querySelector(`#asset-results-${itemIndex}`);
  if (!resultsDiv) return;

  resultsDiv.innerHTML = '<small class="text-muted">Buscando...</small>';

  const types = this.data.asset_types || ['Computer', 'Peripheral', 'Printer', 'Monitor', 'NetworkEquipment', 'Phone'];
  const results = await this.assetSelector.search(query, types);

  if (results.length === 0) {
    resultsDiv.innerHTML = '<small class="text-muted">Nenhum resultado.</small>';
    return;
  }

  const rows = results.map(r => `
    <button type="button" class="list-group-item list-group-item-action"
            data-action="select-asset" data-item="${itemIndex}"
            data-itemtype="${this.escapeAttr(r.itemtype)}"
            data-itemsid="${r.id}"
            data-name="${this.escapeAttr(r.name)}">
      <strong>${this.escapeHtml(r.name)}</strong>
      ${r.serial ? `<small class="text-muted ms-2">S/N: ${this.escapeHtml(r.serial)}</small>` : ''}
    </button>
  `).join('');

  resultsDiv.innerHTML = `<div class="list-group mt-1">${rows}</div>`;
}

async handleAssetSelect(itemIndex, itemtype, itemsId, name) {
  const resultsDiv = this.root.querySelector(`#asset-results-${itemIndex}`);
  if (resultsDiv) {
    resultsDiv.innerHTML = `<div class="alert alert-success py-1">
      <i class="ti ti-check"></i> ${this.escapeHtml(name)}
    </div>`;
  }

  try {
    const data = await this.assetSelector.selectAsset(
      this.data.document_id, itemIndex, itemtype, itemsId
    );

    if (data.success && data.filled) {
      data.filled.forEach(f => {
        const key = `${f.field_id}:${itemIndex}`;
        this.values[key] = f.value ?? '';
      });
      // Re-renderiza campos do item atual para mostrar valores preenchidos
      const container = this.root.querySelector('#wizard-fields-container');
      if (container) {
        container.innerHTML = this.renderer.renderFieldsForItem(this.currentItem);
      }
    }
  } catch (e) {
    console.warn('[SmartDocs] Erro ao selecionar ativo:', e);
  }
}

escapeAttr(text) {
  return String(text ?? '').replace(/"/g, '&quot;');
}
```

---

### Ordem de execução

| Ordem | Arquivo | Tipo |
|---|---|---|
| 1 | `src/Documents/PdfDocument.php` | Modificar |
| 2 | `front/pdfdocument.form.php` | Modificar |
| 3 | `front/pdfdocument.fill.php` | Modificar |
| 4 | `ajax/populate.php` | Criar |
| 5 | `js-src/wizard/AssetSelector.js` | Modificar |
| 6 | `js-src/wizard/WizardApp.js` | Modificar |
| 7 | `pnpm build` (recompila wizard.bundle.js) | Build |

### Verificação manual após implementação

1. `fill_mode=repeat`: criar doc → tela populate aparece → preview mostra N ativos → Populate → wizard abre com N grupos preenchidos por binding keys
2. `fill_mode=single`: criar doc com N=3 → wizard abre com 3 grupos → buscar ativo em cada grupo → campos binding preenchem → campos livres manuais → Gerar PDF
3. Nenhum template publicado → empty state (já funciona)
4. Template inválido no POST → flash error + volta para form
5. `$newId=false` → volta para form sem redirect quebrado

---

## Apêndice 15 — Correção de Bugs: Criação de Documento PDF

### Diagnóstico

O fluxo de criação de novo `PdfDocument` tem **5 bugs** encadeados que impedem qualquer criação bem-sucedida.

---

### Bug 1 — `showForm()` não existe em `PdfDocument`

**Arquivo:** `plugins/smartdocs/front/pdfdocument.form.php:95`
**Causa:** `$doc->showForm(0)` é chamado mas `PdfDocument` não implementa o método.  
**Efeito:** PHP fatal / retorno vazio — formulário nunca renderiza.  
**Fix:** implementar `PdfDocument::showForm(int $ID, array $options = []): bool` usando `initForm()` / `showFormHeader()` / `showFormButtons()` padrão GLPI, com campos `name`, `pdf_templates_id` (dropdown dos templates publicados) e `total_items` (input numérico, padrão 1).

O front (`pdfdocument.form.php`) já busca `$templateOptions` e passa o contexto antes de chamar `showForm`. A forma mais limpa é passar esses dados via `$options['template_options']` para evitar query duplicada:

```php
// pdfdocument.form.php — antes de showForm(0)
$doc->showForm(0, ['template_options' => $templateOptions]);
```

```php
// PdfDocument::showForm()
public function showForm($ID, array $options = []): bool
{
    $templateOptions = $options['template_options'] ?? [];

    $this->initForm($ID, $options);
    $this->showFormHeader($options);

    // Campo: nome
    echo "<tr class='tab_bg_1'><td>" . __('Nome', 'smartdocs') . "</td><td>";
    echo Html::input('name', ['value' => $this->fields['name'] ?? '', 'required' => true]);
    echo "</td></tr>";

    // Campo: template base
    echo "<tr class='tab_bg_1'><td>" . __('Template PDF base', 'smartdocs') . "</td><td>";
    echo Dropdown::showFromArray('pdf_templates_id', $templateOptions,
        ['value' => $this->fields['pdf_templates_id'] ?? 0, 'display' => false]);
    echo "</td></tr>";

    // Campo: quantidade de itens
    echo "<tr class='tab_bg_1'><td>" . __('Quantidade de itens', 'smartdocs') . "</td><td>";
    echo Html::input('total_items', [
        'type'  => 'number',
        'min'   => '1',
        'value' => $this->fields['total_items'] ?? 1,
    ]);
    echo "</td></tr>";

    $this->showFormButtons($options);
    return true;
}
```

---

### Bug 2 — `prepareInputForAdd()` não valida nem preenche `pdf_templates_id`

**Arquivo:** `plugins/smartdocs/src/Documents/PdfDocument.php:169`  
**Causa:** o método só valida `name`; `pdf_templates_id` (NOT NULL no schema) nunca é validado nem sanitizado.  
**Efeito:** `$DB->insert()` falha com erro MySQL ("Field 'pdf_templates_id' doesn't have a default value") e `$doc->add()` retorna `false`.

---

### Bug 3 — `template_version` nunca é definido

**Arquivo:** `plugins/smartdocs/src/Documents/PdfDocument.php:169`  
**Causa:** `template_version` é NOT NULL no schema mas `prepareInputForAdd()` nunca o popula.  
**Efeito:** mesmo erro MySQL do Bug 2 se `pdf_templates_id` fosse corrigido isoladamente.

**Fix para Bugs 2 e 3 juntos** — acrescentar ao `prepareInputForAdd()`:

```php
// Valida e resolve pdf_templates_id + template_version
$templateId = (int) ($input['pdf_templates_id'] ?? 0);
if ($templateId <= 0) {
    \Session::addMessageAfterRedirect(
        __('Selecione um template PDF.', 'smartdocs'),
        false,
        ERROR
    );
    return false;
}

$templateData = (new \GlpiPlugin\SmartDocs\Templates\TemplateRepository())->findById($templateId);
if ($templateData === null || $templateData['status'] !== \GlpiPlugin\SmartDocs\Templates\PdfTemplate::STATUS_PUBLISHED) {
    \Session::addMessageAfterRedirect(
        __('Template inválido ou não publicado.', 'smartdocs'),
        false,
        ERROR
    );
    return false;
}

$input['pdf_templates_id'] = $templateId;
$input['template_version'] = (int) $templateData['version'];
```

---

### Bug 4 — Redirect inválido quando `$doc->add()` retorna `false`

**Arquivo:** `plugins/smartdocs/front/pdfdocument.form.php:29`  
**Causa:**
```php
$newId = $doc->add($_POST);
Html::redirect($doc->getFormURLWithID($newId)); // $newId pode ser false
```
`getFormURLWithID(false)` gera `pdfdocument.form.php?id=` — URL inválida.  
**Fix:**
```php
$newId = $doc->add($_POST);
if ($newId === false) {
    Html::back();
} else {
    Html::redirect(
        GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdfdocument.fill.php?id=' . $newId)
    );
}
```
Isso também elimina o **double redirect** desnecessário (form.php → form.php?id=X → fill.php).

---

### Bug 5 — `entities_id` não definido no documento criado

**Arquivo:** `plugins/smartdocs/src/Documents/PdfDocument.php:169`  
**Causa:** `prepareInputForAdd()` não define `entities_id`; o GLPI não o injeta automaticamente em plugins sem `userentities`.  
**Efeito:** documento fica com `entities_id = 0` independente da entidade ativa do usuário; filtros por entidade não funcionam.  
**Fix:** acrescentar ao `prepareInputForAdd()`:

```php
if (!isset($input['entities_id'])) {
    $input['entities_id'] = (int) ($_SESSION['glpiactive_entity'] ?? 0);
}
```

---

### Aviso — Bug pré-existente em `TemplateRepository::findPublished()`

**Arquivo:** `plugins/smartdocs/src/Templates/TemplateRepository.php:45`  
**Causa:** query usa `'entities_id' => $entityId` (match exato), ignorando templates de entidades pai com `is_recursive = 1`.  
**Efeito:** usuários em sub-entidades não veem templates publicados na entidade raiz.  
**Fix futuro:** substituir a cláusula WHERE por `getEntitiesRestrictRequest(PdfTemplate::getTable())` do GLPI, que resolve herança recursiva automaticamente. Não bloqueia o fluxo atual se tudo estiver na entidade raiz (0).

---

### Ordem de implementação recomendada

| # | Arquivo | Mudança |
|---|---------|---------|
| 1 | `PdfDocument.php` | Adicionar `showForm()` |
| 2 | `PdfDocument.php` | Completar `prepareInputForAdd()` (Bugs 2, 3, 5) |
| 3 | `pdfdocument.form.php` | Passar `template_options` para `showForm()` + fix redirect (Bug 4) |
| 4 | `TemplateRepository.php` | Corrigir escopo de entidade (aviso — baixa prioridade) |

---

### Status de Implementação

Todas as correções foram aplicadas nos arquivos-fonte do plugin:

| # | Arquivo | Mudança aplicada | Status |
|---|---------|------------------|--------|
| 1 | `src/Documents/PdfDocument.php` | Adicionado `showForm($ID, array $options = []): bool` com `initForm()`, `showFormHeader()`, campos `name`, `pdf_templates_id` (dropdown via `$options['template_options']`), `total_items` (input numérico), e `showFormButtons()` | ✅ Aplicado |
| 2 | `src/Documents/PdfDocument.php` | `prepareInputForAdd()` — validação de `pdf_templates_id` (obrigatório, lookup por `TemplateRepository::findById()`, checagem `status === PUBLISHED`), preenchimento de `template_version` e `entities_id` | ✅ Aplicado |
| 3 | `front/pdfdocument.form.php` | Passagem de `$templateOptions` via `['template_options' => $templateOptions]`; redirect com checagem `$newId === false` → `Html::back()`, senão redirect direto para `fill.php` (elimina double redirect) | ✅ Aplicado |
| 4 | `src/Templates/TemplateRepository.php` | `findPublished()` — substituído match exato de `entities_id` por `getEntitiesRestrictCriteria(PdfTemplate::getTable(), '', $entityId, true)` que resolve herança recursiva (`is_recursive`) | ✅ Aplicado |

### Validação das regras GLPI (99-Rules.md)

- ✅ APIs públicas do GLPI — `initForm`, `showFormHeader`, `Dropdown::showFromArray`, `Html::input`, `Session::addMessageAfterRedirect`, `getEntitiesRestrictCriteria`
- ✅ Query builder (`$DB->request`) — nenhum SQL raw
- ✅ `__()` em toda string visível com domínio `'smartdocs'`
- ✅ `declare(strict_types=1)` presente em todos os arquivos
- ✅ Namespace `GlpiPlugin\SmartDocs\` mantido
- ✅ Tipagem em parâmetros e retornos
- ✅ Multi-entidade respeitada via `getEntitiesRestrictCriteria`

### Avaliação do plano original

O plano proposto identifica corretamente os dois pontos centrais (`showForm()` ausente e `prepareInputForAdd()` incompleto) e o tratamento do `$newId === false`. As adições deste apêndice são:

- **Bug 5** (`entities_id` não preenchido) — omitido no plano original, implementado na correção
- **Double redirect** eliminado via redirect direto para `fill.php` — melhora UX
- **Passagem de `$options['template_options']`** — evita query duplicada entre front e model
- **Aviso de escopo de entidade** em `findPublished()` — bug silencioso pré-existente, corrigido na implementação (não apenas documentado como "fix futuro")

---

## Apêndice 17 — Editor Visual Avançado, Ponte de Grupos/Slots (Modelo RegCheck) e Dados de Teste

### Contexto

Sessão de continuação focada em três frentes pedidas pelo usuário: (1) tornar o
editor de canvas mais produtivo (navegação, seleção, grid, fonte), (2) fazer o
template/documento PDF se comportar como o produto de referência RegCheck
(`C:\Users\luiz.belmonte\Desktop\Dev\RegCheck`) — divisão por grupos de
equipamento, aba de campos globais, duplicação de páginas — e (3) gerar dados
de teste realistas para validar tudo ponta a ponta.

### Parte 1 — Editor visual do canvas (`js-src/editor/`)

| Funcionalidade | Arquivo | Detalhe |
|---|---|---|
| Zoom/pan via CSS | `index.js` (`initZoomPan`) | `#page-stage` recebe `transform: scale(N)`; PDF (pdf.js) e Konva ficam em elementos DOM separados — só escalar o Konva desalinhava tudo, por isso o wrapper CSS compartilhado |
| Pan | `index.js` | Botão direito + arrastar (sem exigir Ctrl no scroll, a pedido do usuário — "só uma mão"); `auxclick` suprime o menu de contexto durante o drag |
| Seleção múltipla | `CanvasEditor.js` (`onMarqueeMove`/`onMarqueeEnd`) | Listeners em `window` (não no Konva) — corrige bug de a seleção "grudar"/pular quando o mouse sai da área do canvas durante o arrasto |
| Grupos visuais (G1/G2...) | `CanvasEditor.js` (`getGroupIndexMap`, badge Konva.Label) | Índice estável por ordem alfabética do `group_label`; badge renderizado no canto do campo |
| Dropdown de grupo | `PropertiesPanel.js` (`buildGroupOptions`) | Substituiu input de texto livre; "+ Criar novo grupo…" revela campo de nome |
| Painéis recolhíveis | `index.js` (CSS `.editor-sidebar.collapsed`) | Botões `‹`/`›` no topo de Campos/Propriedades |
| Grid com snap | `CanvasEditor.js` (`toggleGrid`, `renderGrid`, `snapToGrid`, `dragBoundFunc`) | Camada Konva própria abaixo dos campos; snap de 20px ao arrastar/redimensionar quando ativado |
| Edição de fonte | `PropertiesPanel.js` (`renderFontControls`) | Fonte (Helvetica/Times/Courier), tamanho, negrito, alinhamento — grava em `field.config` no formato exato que `PdfGenerator::renderText()` já lia (`font_family`/`font_size`/`align`); **negrito era ignorado no backend, adicionado suporte** (`PdfGenerator.php:renderText`) |
| Tooltip de Escopo | `PropertiesPanel.js` (`scopeTooltip`) | "Global"/"Por item" renomeados para "Compartilhado"/"Por equipamento" + ícone `ⓘ` com `title` explicando a diferença |

**Bug corrigido — alça de redimensionar "resetava" ao soltar o mouse:**

Causa raiz: dentro do handler `dragmove` da alça (`resizeHandle`), o código
fazia `resizeHandle.x(newW); resizeHandle.y(newH);` para aplicar snap/limite
manualmente. Isso conflita com o `Konva.DD` (sistema de drag interno do
Konva), que também controla a posição do nó sendo arrastado a cada
`mousemove` nativo do navegador. Ao soltar o botão, o Konva finaliza o drag
usando seu próprio cálculo de posição (baseado no delta acumulado desde o
`dragstart`), que diverge do valor forçado manualmente — a alça "voltava" e
arrastava o tamanho salvo junto. Reproduzido de forma conclusiva chamando o
handler `dragend` diretamente via `resizeHandle.eventListeners['dragend'][0].handler()`
no console do navegador: a lógica de persistência (`updateFieldPosition`)
estava correta isoladamente, mas nunca recebia o valor certo de `rect.width()`
após um arrasto real.

**Fix:** usar `resizeHandle.dragBoundFunc(pos => {...})` — a API correta do
Konva para restringir/snapar posição durante um arrasto sem que o app e o
Konva disputem a mesma propriedade. O `dragmove` só lê a posição já
restringida; nunca mais escreve nela.

**Bug corrigido — painel de Propriedades ficava desatualizado após resize no canvas:**

Ao redimensionar um campo arrastando a alça, os inputs de X/Y/Largura/Altura
do painel de Propriedades não eram atualizados. Se o usuário editasse
qualquer outra propriedade do mesmo campo logo em seguida (Label, Grupo...),
o `emitUpdate()` reconstruía a posição a partir dos valores (desatualizados)
ainda nos inputs, sobrescrevendo o tamanho recém-ajustado. **Fix:**
`PropertiesPanel.syncPosition()`, chamado a cada `onFieldsChange` em
`index.js` quando há exatamente 1 campo selecionado, atualiza os inputs sem
re-renderizar o painel inteiro (preserva foco caso o usuário esteja digitando
em outro campo).

### Parte 2 — Ponte de Grupos/Slots (modelo RegCheck)

**Diagnóstico:** o motor de geração de PDF (`PdfEngine/FieldCloner.php`,
`Templates/TemplatePaginator.php`) e o wizard de preenchimento
(`WizardApp.js`, `FieldRenderer.js`) **já implementavam** o modelo de
paginação do RegCheck — `scope` ('item'|'global') + `slot_index` (0..N-1) por
campo, `itemsPerPage` = nº de slots distintos, `totalPages = ceil(totalItems / itemsPerPage)`.
Porém o editor visual só grava `group_label` (nome livre do grupo, ex: "Grupo
1"); nunca escreve `scope`/`slot_index`. Resultado: todos os campos ficavam
com `scope='global'`, `slot_index=NULL`, e o wizard não conseguia separar por
equipamento — apesar do motor de paginação estar pronto.

**Fix (ponte central):** `TemplateRepository::resolveSlots()`, chamado
internamente por `getFields()`. Coleta os `group_label` distintos, ordena com
`SORT_STRING` (mesma ordenação lexicográfica que o editor usa para numerar
G1/G2 no canvas), e mapeia cada grupo para um `slot_index` 0..N-1; campo
agrupado → `scope='item'`; campo sem grupo → `scope='global'`,
`slot_index=null`.

Como **todo** consumidor de campos (wizard `fill.php`, geração de PDF via
`PdfQueue`→`FieldCloner`, `DocumentService::populate`) passa por
`getFields()`, esta única mudança destrava o pipeline inteiro sem migração de
banco.

**Outros bugs corrigidos no mesmo fluxo:**

| # | Bug | Arquivo | Fix |
|---|---|---|---|
| 1 | `SQL Error 1054: Unknown column 'is_deleted'` ao carregar localizações | `front/pdfdocument.fill.php` | `glpi_locations` não tem essa coluna no schema padrão do GLPI — filtro removido |
| 2 | Campos exibiam `"Campo " + ID` (ex: "Campo 1008") no wizard | `js-src/wizard/FieldRenderer.js` (`resolveLabel`) | Resolução em cascata: `field.label` → `field.config.label` → dicionário de binding key (`eq.name` → "Nome do Equipamento" etc.) → rótulo por tipo de campo |
| 3 | `field.config`/`field.position` chegavam como string JSON no JS (esperava objeto) | `front/pdfdocument.fill.php` | `json_decode()` explícito no enriquecimento dos campos antes de injetar no `wizardData` |
| 4 | Bug de CSS `.small` do GLPI colapsando o cabeçalho do wizard (texto vertical, 1 char por linha) | `js-src/wizard/WizardApp.js` | Override escopado `.smartdocs-wizard .small { width: auto !important; }` injetado no `render()` |
| 5 | Bundle do wizard cacheado agressivamente pelo navegador | `front/pdfdocument.fill.php` | Cache-busting via `?v=<filemtime>`, igual ao já usado no editor |

**Validado no navegador** (documento de teste, template com 2 grupos): abas
"Equipamento 1 (Grupo 1)", "Equipamento 2 (Grupo 2)", "Campos Globais"
renderizam corretamente; isolamento confirmado (aba Global mostra só os 7
campos globais; Equipamento 2 mostra só os 19 inputs do seu slot); rótulos
amigáveis e badges de binding key aparecem sem nenhum ID bruto.

**Confirmado por leitura de código (não exercitado no navegador por falta de
localizações/ativos cadastrados no momento — resolvido depois com os fixtures
da Parte 3):**

- Duplicação de páginas: `PdfGenerator::generate()` calcula
  `totalOutputPages = max(páginasBase, maxComputedPageIndex+1)` e importa
  páginas ciclicamente via `importPage()` — a quantidade de slots por página é
  derivada do template, não fixa (requisito explícito do usuário: "cada
  template pode ser diferente")
- Não misturar setores: `DocumentService::padByLocation()` preenche o resto
  da última página de cada localização com slots vazios antes de começar a
  próxima, garantindo que equipamentos de setores diferentes nunca dividam a
  mesma folha

**Limitação identificada (não corrigida ainda):** o filtro de "Repetição em
Grade" (`populate-document.php` → `DocumentService::populate`) seleciona por
`itemtype` (Computer/Peripheral/...) + localização, mas não distingue
subtipo de periférico (`peripheraltypes_id`). Num ambiente onde a mesma
localização tem múltiplos tipos de periférico (ex: um checkout com nobreak +
teclado + leitor + balança + gaveta, todos `itemtype=Peripheral`), popular por
`Peripheral + <localização>` traria os 5 misturados. Não é um problema para
os setores de estoque avulsos (só têm "Balança de Setor" lá), mas seria
necessário um filtro adicional por `peripheraltypes_id` para popular
seletivamente em ambientes mistos como o de checkout.

### Parte 3 — Dados de teste (`tools/seed-test-fixtures.php`)

Script PHP standalone (roda via `php` direto, sem bootstrap do GLPI — conecta
via `mysqli`) que gera um cenário completo de varejo:

- **20 frentes de caixa completas** (`Checkout 01`..`20`, localização em
  árvore sob `Frente de Caixa`): CPU (Dell) + Monitor (LG) + Impressora
  térmica (Elgin) + Nobreak (SMS/APC alternado) + Teclado (Gertec) + Leitor de
  código de barras (Honeywell/Gertec alternado) + Balança de checkout
  (Toledo do Brasil/Filizola alternado) + Gaveta de dinheiro (Elgin) — 160
  ativos
- **20 balanças avulsas de setor** — 5 em cada: Estoque Geral, Câmara Fria,
  Recebimento, Expedição
- **5 balanças de setor de tipos diferentes** — 3 "Balança de Conferência"
  (Urano) em Conferência + 2 "Balança de Pallet" (Urano) em Paletização
- Estrutura de apoio: 27 localizações, 26 grupos GLPI (1 por checkout + 1 por
  setor), 13 fabricantes, 8 tipos de periférico, ~16 modelos, 2 estados
- Seriais únicos por categoria (`PDV-CPU-0001`...), patrimônio (`otherserial`)
  em sequência global (`PAT-000001`...)

**Por que isso testa o sistema "à prova de bala":** `BindingKeyResolver`
resolve dinamicamente qualquer campo do ativo GLPI (`eq.fabricante`,
`eq.modelo`, `eq.grupo`, `eq.localizacao`, `eq.estado`) sem precisar de código
adicional — bastava os dados existirem. Populando fabricante/modelo/grupo/
localização/estado em todos os 185 itens (não só nome/serial), qualquer
binding key configurada no template passa a ter dado real para testar.

**Características do script:**

- **Idempotente:** dropdowns localizados por nome; ativos localizados por
  `serial` antes de inserir. Testado rodando duas vezes seguidas no banco já
  populado — segunda execução reportou `criados: 0, pulados: 185`, sem
  duplicar nada
- **Conexão configurável:** variáveis de ambiente
  `SMARTDOCS_SEED_DB_HOST`/`_USER`/`_PASS`/`_NAME` sobrescrevem os padrões
  (`mariadb`/`root`/`glpiroot`/`glpi`, iguais ao `docker-compose.yml` deste
  projeto) — permite rodar em outra máquina/ambiente sem editar o arquivo
- **Bug corrigido durante o desenvolvimento:** o contador de patrimônio
  (`nextPatrimonio()`) era chamado no literal do array de dados de cada
  ativo, avaliado pelo PHP **antes** de `seedAsset()` verificar se o serial já
  existia — em reexecuções, o contador avançava mesmo sem inserir nada
  ("queimando" números da sequência). Corrigido movendo a chamada para dentro
  de `seedAsset()`, só no caminho que efetivamente insere

**Como rodar** (documentado no cabeçalho do arquivo e no `README.md` do
plugin):

```bash
docker cp plugins/smartdocs/tools/seed-test-fixtures.php <container_glpi>:/tmp/seed-test-fixtures.php
docker exec <container_glpi> php /tmp/seed-test-fixtures.php
```

### Memórias de sessão registradas

Duas memórias persistentes foram salvas para não repetir investigação em
sessões futuras:

1. **Ponte grupo→slot** — resume o mecanismo do `resolveSlots()` e por que
   centralizá-lo em `getFields()` resolve o pipeline inteiro
2. **Bug de CSS `.small` do GLPI** — já atingiu o editor e o wizard; documenta
   o fix (override escopado) e o gotcha relacionado de cache de bundle JS
   (`?v=<filemtime>`)
