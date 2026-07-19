# 16 — Notificações

## Objetivo

Integrar um itemtype de plugin ao sistema de notificações do GLPI: criar um `NotificationTarget` próprio, disparar eventos via `NotificationEvent::raiseEvent()`, e (avançado) registrar um modo de notificação inteiramente novo (SMS, webhook etc.).

## Conceitos

- **Três peças do mecanismo:** `Notification` (a regra: "quando X acontecer, notificar Y usando o template Z"), `NotificationTemplate` (o conteúdo, com tags substituíveis), e `NotificationTarget<Itemtype>` (a classe que sabe: quais eventos existem para este itemtype, quais tags ele expõe, e quem são os destinatários possíveis).
- **Para um itemtype de plugin ganhar notificações**, cria-se `GlpiPlugin\Meuplugin\NotificationTargetCoisa extends NotificationTarget`, implementando `getEvents()` (lista de eventos possíveis) e, tipicamente, sobrescrevendo `addDataForTemplate()` para popular as tags custom com dados do objeto.
- **Disparo é sempre explícito**: em algum ponto do código (tipicamente em `post_addItem`/`post_updateItem`/um cron), o plugin chama `NotificationEvent::raiseEvent($nomeDoEvento, $itemInstance, $opcoesExtras)`. Isso NÃO acontece magicamente — sem essa chamada, nenhuma notificação é enviada mesmo com `Notification`/`NotificationTemplate` configurados.
- **Modos de entrega** (`mail`, `browser` no core) são extensíveis: um plugin pode registrar um modo totalmente novo (`sms`, `webhook`) via `Notification_NotificationTemplate::registerMode()`, desde que implemente uma classe `Plugin<Nome>Notification<Modo>` — recurso avançado, raramente necessário para plugins de negócio comuns.

## Funcionamento interno

Ao chamar `NotificationEvent::raiseEvent($event, $item, $options)`, o core localiza as `Notification` ativas cadastradas para aquele itemtype/evento, resolve os destinatários (via `NotificationTarget::addSpecificTargets()`/`addAdditionalTargets()`), monta o conteúdo substituindo tags (`addDataForTemplate()` popula `$this->data['##tag##']`), e enfileira o envio no modo configurado (fila de e-mail = `glpi_queuednotifications`, processada depois por um `CronTask` nativo do core).

`getTags()` declara as tags disponíveis (usadas no editor de template pelo administrador); `addDataForTemplate($event, $options)` é o método que de fato resolve o VALOR de cada tag para a instância específica do evento — é aqui que se busca dado relacionado (ex.: nome de usuário responsável) e popula `$this->data`.

## Fluxograma

```
post_addItem() / post_updateItem() / cron / ação de negócio
      │
      ▼
NotificationEvent::raiseEvent('meu_evento', $item, $options)
      │
      ▼
localiza Notification ativas para (itemtype, evento)
      │
      ▼
NotificationTargetCoisa::addSpecificTargets() / addAdditionalTargets()
      │  resolve destinatários (usuário, grupo, entidade...)
      ▼
NotificationTargetCoisa::addDataForTemplate($event, $options)
      │  popula tags ##coisa.name##, ##coisa.status## etc.
      ▼
NotificationTemplate renderiza o conteúdo final
      ▼
Fila de envio (glpi_queuednotifications) → CronTask nativo → e-mail/browser
```

## Exemplos corretos

### NotificationTarget de itemtype próprio

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use NotificationTarget;

class NotificationTargetCoisa extends NotificationTarget
{
    /** Chave do evento — usada em raiseEvent() e na UI de configuração */
    public const EVENTO_APROVADA = 'coisa_aprovada';

    /**
     * Eventos disponíveis para este itemtype, exibidos na tela de
     * configuração de Notificações.
     */
    public function getEvents(): array
    {
        return [
            self::EVENTO_APROVADA => __('Coisa aprovada', 'meuplugin'),
        ];
    }

    /**
     * Declara as tags disponíveis para uso no template.
     */
    public function getTags(): array
    {
        $tags = [
            'coisa.name'        => __('Nome da Coisa', 'meuplugin'),
            'coisa.responsavel' => __('Responsável pela aprovação', 'meuplugin'),
        ];

        foreach ($tags as $tag => $label) {
            $this->addTagToList([
                'tag'   => $tag,
                'label' => $label,
                'value' => true,
            ]);
        }

        parent::getTags();
        return $this->tag_descriptions;
    }

    /**
     * Popula os valores reais das tags para esta instância do evento.
     */
    public function addDataForTemplate($event, $options = []): void
    {
        /** @var Coisa $coisa */
        $coisa = $this->obj;

        $this->data['##coisa.name##']        = $coisa->getField('name');
        $this->data['##coisa.responsavel##']  = $options['responsavel_nome'] ?? '';

        $this->getTags();
        foreach ($this->tag_descriptions[self::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    /**
     * Define quem recebe (além dos alvos padrão como "usuário responsável").
     */
    public function addAdditionalTargets($event = ''): void
    {
        if ($event === self::EVENTO_APROVADA) {
            $this->addTarget(\Notification::AUTHOR, __('Autor', 'meuplugin'));
        }
    }
}
```

### Disparando o evento

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa, após aprovar (ex.: dentro do
// processMassiveActionsForOneItemtype de 11-MassiveActions.md)

use NotificationEvent;
use GlpiPlugin\Meuplugin\NotificationTargetCoisa;

NotificationEvent::raiseEvent(
    NotificationTargetCoisa::EVENTO_APROVADA,
    $this, // instância de Coisa, já com getFromDB() carregado
    ['responsavel_nome' => $_SESSION['glpiname'] ?? '']
);
```

### Registro necessário no install (para a Notification aparecer configurável)

```php
// A classe NotificationTargetCoisa é descoberta automaticamente pelo
// core por convenção de nome (NotificationTarget<Itemtype>) — não
// exige registro explícito de hook, mas o itemtype precisa estar
// corretamente resolvido (getTable, getForeignKeyField etc.).
```

## Exemplos incorretos

```php
// ERRADO: esperar que configurar uma Notification na UI baste, sem
// nenhuma chamada a NotificationEvent::raiseEvent() em lugar nenhum
// do código. Nenhum e-mail é enviado — a configuração fica "morta".
```

```php
// ERRADO: montar o corpo do e-mail manualmente (string concatenada)
// e enviar via mail()/PHPMailer direto, ignorando todo o pipeline de
// NotificationTemplate/fila. Perde configuração do administrador,
// fila de reenvio, log de notificações e i18n de template.
```

```php
// ERRADO: addDataForTemplate fazendo query pesada sem necessidade
// (ex.: recarregando o item inteiro do banco quando $this->obj já
// tem os dados) — o método roda para cada destinatário/idioma.
```

## Boas práticas

- Sempre disparar eventos a partir de efeitos colaterais (`post_addItem`, `post_updateItem`, ação de negócio explícita), nunca dentro de `prepareInputFor*` (a operação ainda pode ser vetada).
- Declarar tags com nomes namespaced pelo domínio (`coisa.name`, não `name` genérico) para não colidir com tags de outros contextos.
- Deixar o conteúdo/idioma do e-mail inteiramente a cargo do `NotificationTemplate` configurável pelo admin — o plugin só fornece os DADOS (tags), nunca o texto final hardcoded.
- Reutilizar `Notification::AUTHOR`/constantes de alvo do core quando o destinatário for um papel já conhecido (autor, requerente, técnico), evitando reinventar resolução de usuário.

## Anti-patterns

- Enviar e-mail direto via `mail()`/biblioteca externa em vez do pipeline de notificações do GLPI.
- Templates de conteúdo hardcoded em PHP em vez de deixar o texto configurável via `NotificationTemplate`.
- Disparar o mesmo evento múltiplas vezes para a mesma transação lógica (ex.: uma vez em `prepareInputForUpdate` e outra em `post_updateItem`).
- Ignorar `getEvents()`/`getTags()` e tentar popular `$this->data` com chaves que não foram declaradas — o editor de template do admin nunca mostra essas tags como disponíveis.

## Checklist

- [ ] `NotificationTarget<Itemtype>` implementado com `getEvents()`, `getTags()`, `addDataForTemplate()`
- [ ] `NotificationEvent::raiseEvent()` chamado no ponto certo do ciclo de vida (pós-persistência)
- [ ] Tags namespaced pelo domínio do plugin
- [ ] Nenhum envio de e-mail feito fora do pipeline nativo
- [ ] Uninstall remove `Notification`/`NotificationTemplate` criados pelo plugin, se aplicável

## Dicas de performance

- `addDataForTemplate` roda por destinatário/idioma — evite consultas repetidas; calcule uma vez e reutilize via propriedade da instância quando possível.
- Eventos disparados em lote (ex.: massive action que aprova 500 itens) devem considerar agregação ou throttling se o volume de e-mails gerados for alto.

## Dicas de segurança

- Nunca inclua dado sensível numa tag que pode ser exposta a um destinatário que não deveria vê-lo — a lista de destinatários é configurável pelo admin e pode ser mais ampla do que o autor do plugin imagina.
- Eventos disparados a partir de input do usuário devem ser validados antes do disparo — não deixe o conteúdo da notificação refletir texto não sanitizado de forma que quebre o template (ex.: quebra de tags Twig do template, se aplicável).

## Referências

- Notification modes oficial: https://glpi-developer-documentation.readthedocs.io/en/master/plugins/notifications.html
- Exemplo real de NotificationTarget de plugin: https://gist.github.com/antoine1003/3b887f7e68e66b1e21a592694584d1f1
- Documentos relacionados: `02-Lifecycle.md`, `03-Hooks.md`, `15-Cron.md`
