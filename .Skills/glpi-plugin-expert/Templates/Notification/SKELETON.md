# Template: Notification

NotificationTarget de itemtype próprio. Ver `GLPI10/16-Notifications.md`.

## src/NotificationTargetCoisa.php

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use NotificationTarget;

class NotificationTargetCoisa extends NotificationTarget
{
    public const EVENTO_APROVADA = 'coisa_aprovada';

    public function getEvents(): array
    {
        return [
            self::EVENTO_APROVADA => __('Coisa aprovada', 'meuplugin'),
        ];
    }

    public function getTags(): array
    {
        $tags = [
            'coisa.name'        => __('Nome da Coisa', 'meuplugin'),
            'coisa.responsavel' => __('Responsável pela aprovação', 'meuplugin'),
        ];

        foreach ($tags as $tag => $label) {
            $this->addTagToList(['tag' => $tag, 'label' => $label, 'value' => true]);
        }

        parent::getTags();
        return $this->tag_descriptions;
    }

    public function addDataForTemplate($event, $options = []): void
    {
        /** @var Coisa $coisa */
        $coisa = $this->obj;

        $this->data['##coisa.name##']       = $coisa->getField('name');
        $this->data['##coisa.responsavel##'] = $options['responsavel_nome'] ?? '';

        $this->getTags();
        foreach ($this->tag_descriptions[self::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    public function addAdditionalTargets($event = ''): void
    {
        if ($event === self::EVENTO_APROVADA) {
            $this->addTarget(\Notification::AUTHOR, __('Autor', 'meuplugin'));
        }
    }
}
```

## Disparo (a partir de um efeito colateral pós-persistência)

```php
<?php

use NotificationEvent;
use GlpiPlugin\Meuplugin\NotificationTargetCoisa;

NotificationEvent::raiseEvent(
    NotificationTargetCoisa::EVENTO_APROVADA,
    $coisa, // instância já carregada via getFromDB()
    ['responsavel_nome' => $_SESSION['glpiname'] ?? '']
);
```

## Checklist pós-cópia

- [ ] `NotificationTarget<Itemtype>` implementado (descoberto por convenção de nome pelo core)
- [ ] Disparo feito em efeito colateral pós-persistência, nunca em `prepareInputFor*`
- [ ] Tags namespaced pelo domínio (`coisa.*`, não genéricas)
- [ ] Nenhum envio de e-mail direto fora do pipeline nativo
