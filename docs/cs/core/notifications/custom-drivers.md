---
order: 50
summary: "Doručení notifikace někam, o čem tenhle balíček neví — kontrakt a kde se driver registruje."
---

# Vlastní drivery

Slack, řádek v logu, vlastní websocket, testovací špeh: driver je jedno malé
rozhraní a volající kód se nikdy nedozví, který odpověděl. Tenhle šev drží každou
notifikaci ve frameworku doručitelnou kamkoli.

## Vlastní drivery

Implementujte kontrakt `NotificationDriver` — jeho jediná metoda `send()` dostane notifikaci a (volitelně) Livewire komponentu v scope:

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

class SlackDriver implements NotificationDriver
{
    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        Http::post('https://hooks.slack.com/...', [
            'text' => $notification->title . ': ' . $notification->message,
        ]);
    }
}
```

Zaregistrujte ho jako globální výchozí v service provideru (`boot()`):

```php
use NyonCode\WireCore\Notifications\NotificationManager;

NotificationManager::setDefaultDriver(new SlackDriver());
```

Nebo ho použijte pro jednu komponentu/volání bez změny globálního výchozího:

```php
$this->setNotificationDriver(new SlackDriver());      // pro jednotlivé komponenty (trait)
NotificationManager::send($notification, new SlackDriver()); // pro jednotlivá volání
```

## Související

- [Notifikace](index.md) — objekt, který driver dostane
- [Toasty](toasts.md) a [Perzistentní notifikace](persistent.md) — dva dodávané drivery
- [Pluginy](../plugins/index.md) — registrace driveru z balíčku
- [Konfigurace](../../start/configuration.md) — pojmenování výchozího driveru
