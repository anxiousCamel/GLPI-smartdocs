# SmartDocs — Plugin GLPI

Documentação técnica, digitalização inteligente (OCR/QR Code) e gestão de preventivas integrados ao GLPI 10.x / 11.x.

---

## 🚀 QUICKSTART (5 minutos)

```bash
# 1. Copiar para pasta de plugins do GLPI
cp -r smartdocs /var/www/glpi/plugins/

# 2. Instalar dependências PHP (obrigatório!)
cd /var/www/glpi/plugins/smartdocs
composer install --no-dev --optimize-autoloader

# 3. Ativar no GLPI
#    Configuração → Plugins → SmartDocs → Instalar → Ativar
#    → O plugin mostra automaticamente o que falta fazer
```

> ⚠️ **Sem o `composer install` o menu SmartDocs não aparece.** Se você pulou este passo, o plugin mostra um banner vermelho no dashboard do GLPI com instruções.

---

## Pré-requisitos

| Requisito | Mínimo |
|-----------|--------|
| GLPI | 10.0.0 |
| PHP | 8.2 |
| Extensões PHP | `gd`, `mbstring`, `curl`, `json`, `zip` |
| Composer | 2.x |

---

## Instalação detalhada

### 1. Copiar o plugin

Coloque a pasta `smartdocs` dentro de `glpi/plugins/`:

```
glpi/
└── plugins/
    └── smartdocs/   ← aqui
```

### 2. Instalar dependências PHP

```bash
cd plugins/smartdocs
composer install --no-dev --optimize-autoloader
```

> Sem este passo o menu SmartDocs **não aparece** no GLPI. O plugin detecta automaticamente e mostra um aviso no dashboard.

### 3. Ativar no GLPI

1. Acesse **Configuração → Plugins**
2. Localize **SmartDocs** na lista
3. Clique **Instalar** (cria as tabelas no banco)
4. Clique **Ativar**

### 4. Verificar setup

Abra **SmartDocs → Página Inicial**. O dashboard exibe um checklist interativo:

- ✅ Dependências Composer
- ✅ Extensões PHP
- ✅ Tabelas do banco
- ✅ Permissões por perfil
- ✅ Cron ativo
- ✅ Templates publicados
- ✅ OCR configurado

Cada item pendente mostra um botão que leva direto ao lugar certo para resolver.

### 5. Configurar

- **Permissões:** Administração → Perfis → [perfil] → aba SmartDocs
- **OCR:** SmartDocs → Configurações → escolha provedor (`browser` = padrão, sem dependências no servidor)
- **Cron:** Configuração → Tarefas automáticas → SmartDocsPdfQueue → ative

---

## Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Templates PDF** | Crie layouts visuais com campos posicionados sobre um PDF base |
| **Documentos** | Gere PDFs preenchidos a partir dos templates, vinculados a equipamentos |
| **Scanner OCR** | Leia QR Code, código de barras ou texto de etiquetas pela câmera |
| **Wiki** | Base de conhecimento interna por categoria, com versionamento |
| **Biblioteca Técnica** | Manuais, POPs, contratos e garantias vinculados a qualquer objeto GLPI |

---

## Diagnóstico

Acesse **SmartDocs → Página Inicial → Página de diagnóstico** (ou diretamente `/plugins/smartdocs/front/diagnostic.php`) para ver:

- Versões de tudo (GLPI, PHP, plugin, extensões)
- Status do cron e jobs pendentes
- Contadores de registros
- Permissões por perfil
- Status das extensões PHP

---

## Permissões

As permissões são configuradas em **Administração → Perfis → SmartDocs**.

| Direito | Descrição |
|---------|-----------|
| Leitura de Templates | Ver templates publicados |
| Escrita de Templates | Criar/editar/publicar templates |
| Leitura de Documentos | Ver documentos gerados |
| Escrita de Documentos | Criar e preencher documentos |
| Uso de OCR | Acessar scanner de câmera nos formulários de ativos |
| Administração | Acesso total + configurações do plugin |

---

## Troubleshooting

**Menu SmartDocs não aparece**
→ Execute `composer install` na pasta do plugin. Sem `vendor/autoload.php` os hooks não são registrados. O plugin mostra um banner vermelho no dashboard do GLPI com instruções.

**Erro na instalação / tabelas não criadas**
→ Verifique se o usuário do banco tem permissão `CREATE TABLE`. Confirme que PHP 8.2+ está ativo e as extensões listadas estão habilitadas.

**OCR não funciona**
→ Por padrão o OCR usa o navegador (WebAssembly). Para Tesseract local, instale `tesseract-ocr` no servidor e configure em **SmartDocs → Configurações → OCR**.

**PDF não é gerado**
→ A geração é assíncrona via CronTask. Configure o cron do GLPI (`glpi/front/cron.php`) para rodar a cada 2 minutos, ou execute manualmente em **Configuração → Tarefas automáticas → SmartDocsPdfQueue**.

---

## Desinstalação

1. **Configuração → Plugins → SmartDocs → Desinstalar**

Isso remove todas as tabelas `glpi_plugin_smartdocs_*` e os dados do plugin.

---

## Licença

GPL-3.0-or-later — Luiz Belmonte