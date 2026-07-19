# GLPI Plugin Expert — Knowledge Base

KB técnica para desenvolvimento profissional de plugins GLPI 10.x/11.x, projetada para consumo por LLMs (RAG/Skill) e por humanos.

## Princípios

- **Não é um conjunto de prompts.** É documentação técnica completa, pesquisável e independente de modelo.
- **Nada é cópia da documentação oficial.** Todo conteúdo é reescrito, explicando *como* o GLPI funciona internamente e *por que* cada padrão existe.
- **Arquitetura antes de código.** Boas práticas, manutenção e compatibilidade futura têm prioridade sobre atalhos.

## Estrutura

| Pasta | Conteúdo |
|---|---|
| `SKILL.md` | Roteador: qual documento ler para cada tarefa |
| `GLPI10/` | 34 documentos técnicos numerados (00–32 + 99-Rules) |
| `GLPI11/` | Guia de migração 10→11 e diferenças conhecidas |
| `PROMPTS/` | Prompts operacionais por tipo de tarefa |
| `Templates/` | Esqueletos de código prontos para uso |
| `Examples/` | Análise de plugins oficiais reais |
| `Checklists/` | Validações pré-entrega |
| `References/` | Fontes oficiais: URL, versão, data de consulta, quando consultar |

## Formato dos documentos GLPI10/

Cada documento segue o mesmo esqueleto: Objetivo → Conceitos → Funcionamento interno → Fluxograma → Exemplos corretos → Exemplos incorretos → Boas práticas → Anti-patterns → Checklist → Performance → Segurança → Referências.

## Versões cobertas

- **GLPI 10.0.x** — base principal.
- **GLPI 11.0.x** — diferenças e migração em `GLPI11/`; todo código novo desta KB já nasce minimizando o custo de migração.
