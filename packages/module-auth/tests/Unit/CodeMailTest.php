<?php

declare(strict_types=1);

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\ValueObjects\OneTimeCode;

/*
 * The mail a code arrives in — the only part of these flows a person reads.
 */

function aCode(CodePurpose $purpose = CodePurpose::Login): OneTimeCode
{
    return new OneTimeCode($purpose, 'ann@example.com', '483021', Carbon::now()->addMinutes(10));
}

afterEach(function () {
    OneTimeCodeNotification::$toMailCallback = null;
});

it('says which flow it belongs to, and how long the code lasts', function () {
    $mail = (new OneTimeCodeNotification(aCode(CodePurpose::VerifyEmail)))->toMail(new CodeUser);

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe(__('wire-module-auth::messages.code_mail.verify_email.subject'))
        // The digits in two chunks: the next thing that happens to them is being
        // read off one screen and typed into another.
        ->and($mail->introLines)->toContain('**483 021**')
        ->and($mail->introLines)->toContain(__('wire-module-auth::messages.code_mail.expires', ['minutes' => 10]));
});

it('sounds like the flow it was sent for', function () {
    $login = (new OneTimeCodeNotification(aCode(CodePurpose::Login)))->toMail(new CodeUser);
    $reset = (new OneTimeCodeNotification(aCode(CodePurpose::ResetPassword)))->toMail(new CodeUser);

    // One wording per purpose: a shared template that tries to be all four reads
    // like none of them.
    expect($reset->subject)->not->toBe($login->subject);
});

it('goes by mail, and gets out of the way of an application that has its own', function () {
    expect((new OneTimeCodeNotification(aCode()))->via(new CodeUser))->toBe(['mail']);

    OneTimeCodeNotification::toMailUsing(fn ($notifiable, OneTimeCode $code) => 'ours: '.$code->code);

    expect((new OneTimeCodeNotification(aCode()))->toMail(new CodeUser))->toBe('ours: 483021');
});
