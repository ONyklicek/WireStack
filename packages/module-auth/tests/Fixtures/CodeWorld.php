<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\WireModuleAuthServiceProvider;

/**
 * The installation the code flows are tested against.
 *
 * Three things every one of those tests needs, in one place because getting any
 * of them subtly wrong makes a passing test that proves nothing:
 *
 *  - **the flows are switched on *and the provider re-registered*.** Every flow
 *    is off by default, and its routes are registered while the package boots —
 *    so config set from inside a test is set after the router has already been
 *    told there is nothing to register. `register(force: true)` runs the route
 *    file again with the switch on, which is the only way a test can meet the
 *    URLs an application meets;
 *  - **the real migration**, run from the package's own directory rather than a
 *    `Schema::create()` written here, so the table these tests pass against is
 *    the table an application publishes;
 *  - **a frame to render in**, standing in for the shell exactly as the other
 *    screen tests do.
 */
final class CodeWorld
{
    /**
     * Switch flows on and rebuild the routes, as an application's config would.
     *
     * @param  array<string, mixed>  $flows  `['login' => true, …]`, plus any
     *                                       other `codes.*` key a test wants to
     *                                       bend — the expiry, the attempts, the
     *                                       resend window.
     */
    public static function enable(array $flows): void
    {
        foreach ($flows as $key => $value) {
            config()->set('wire-module-auth.codes.'.$key, $value);
        }

        app()->register(WireModuleAuthServiceProvider::class, force: true);
    }

    /**
     * Keep a note of every code the real store mints.
     *
     * After {@see enable()}, never before: re-registering the provider rebinds
     * the store, and a decorator wrapped around the old binding would be quietly
     * thrown away.
     */
    public static function recordCodes(): RecordingCodes
    {
        $recorder = new RecordingCodes(app(OneTimeCodes::class));

        app()->instance(OneTimeCodes::class, $recorder);

        return $recorder;
    }

    /** The schema the flows read and write: the application's users, and the codes. */
    public static function migrate(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Artisan::call('migrate', [
            '--path' => realpath(__DIR__.'/../../database/migrations'),
            '--realpath' => true,
        ]);
    }

    /** The shell's frame, as the screen tests stand it in. */
    public static function frame(): void
    {
        View::addNamespace('wire-admin', __DIR__.'/views');
        Blade::component(Frame::SHELL_LAYOUT, AuthFrame::class);
    }

    /** Somebody to sign in as. */
    public static function user(array $attributes = [], string $model = CodeUser::class): CodeUser
    {
        /** @var CodeUser $user */
        $user = $model::query()->create(array_merge([
            'name' => 'Ann Example',
            'email' => 'ann@example.com',
            'password' => Hash::make('correct-horse'),
            'email_verified_at' => now(),
        ], $attributes));

        return $user;
    }
}
