<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Notifications;

use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

/**
 * The mail a code arrives in.
 *
 * Deliberately plain: a subject, the digits, how long they last, and the line
 * that says to ignore the mail if it was not asked for. No link — a mail whose
 * code is beside a button that signs you in is a mail where the code is
 * decoration, and the whole point of these flows is that the credential is
 * something a person carries across to a screen they are already looking at.
 *
 * **Not queued, and that is a choice an application overrides rather than
 * inherits.** A code is worth nothing after ten minutes; a queue that is not
 * running turns "your code did not arrive" into a support ticket that reads like
 * a bug in the sign-in. An application with a working queue wraps this — its own
 * notification, its own `ShouldQueue` — through {@see toMailUsing()}.
 *
 * The mail's wording is a translation key per purpose, because "here is your
 * sign-in code" and "confirm this address" are different sentences in every
 * language and a shared template is a mail that reads like neither.
 */
class OneTimeCodeNotification extends Notification
{
    /**
     * An application's own mail, in place of this one.
     *
     * Laravel's own convention (`ResetPassword::toMailUsing()`), copied
     * deliberately: it is the callback an application already knows how to
     * register, and it takes the whole message rather than a slot in it — a
     * brand, an attachment, a different channel entirely.
     *
     * @var (Closure(mixed, OneTimeCode): mixed)|null
     */
    public static ?Closure $toMailCallback = null;

    public function __construct(public readonly OneTimeCode $code) {}

    /**
     * @param  Closure(mixed, OneTimeCode): mixed  $callback
     */
    public static function toMailUsing(Closure $callback): void
    {
        static::$toMailCallback = $callback;
    }

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): mixed
    {
        if (static::$toMailCallback !== null) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->code);
        }

        $key = $this->code->purpose->translationKey();
        $minutes = max(1, (int) round(now()->diffInMinutes($this->code->expiresAt)));

        return (new MailMessage)
            ->subject(__($key.'.subject'))
            ->line(__($key.'.line'))
            // The digits on a line of their own, spaced, because the next thing
            // that happens to them is being read off one screen and typed into
            // another.
            ->line('**'.$this->spaced().'**')
            ->line(__('wire-module-auth::messages.code_mail.expires', ['minutes' => $minutes]))
            ->line(__('wire-module-auth::messages.code_mail.ignore'));
    }

    /** `483 021` rather than `483021` — six digits read as two chunks, not one number. */
    private function spaced(): string
    {
        return trim(chunk_split($this->code->code, 3, ' '));
    }
}
