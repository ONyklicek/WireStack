<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleUsers\Console\Concerns\InteractsWithRoles;
use NyonCode\WireModuleUsers\Install\CreateFirstAdministrator;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Roles;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * `php artisan wire:user` — an account, whenever one is wanted.
 *
 * {@see CreateFirstAdministrator} makes the account that gets you in, and stops
 * there on purpose: it is a step in an installer, and an installer that offered
 * to add another administrator on every run is one nobody could run twice
 * safely. That left the second account with no answer but `php artisan tinker`,
 * which is exactly the gap `make:filament-user` exists to fill in the framework
 * this one is measured against.
 *
 * So the logic lives in {@see Accounts} and this is the other way in. Between
 * them: the step decides *when* to offer, this one is asked.
 *
 * ## Scriptable, and honest about it
 *
 * `--name`, `--email` and `--password` make it usable from a provisioning
 * script; anything not given is asked for, and anything not given **and** not
 * askable stops the command rather than being invented. A generated password in
 * a deploy log is a credential in a log.
 *
 *   php artisan wire:user
 *   php artisan wire:user --name=Jane --email=jane@example.com --password=… --super-admin
 *   php artisan wire:user --email=… --password=… --role=editor --role=support
 *
 * Roles for an account that already exists are {@see WireAssignRoleCommand}.
 */
#[AsCommand(name: 'wire:user')]
class WireUserCommand extends Command
{
    use InteractsWithRoles;

    protected $signature = 'wire:user
        {--name= : The name to store}
        {--email= : The address they sign in with}
        {--password= : Their password, hashed before it is written}
        {--super-admin : Make the account a super-admin, who can do everything in every team}
        {--role=* : Roles to give the account, created where the application has none}';

    protected $description = 'Create a user for this application.';

    public function handle(Accounts $accounts): int
    {
        if ($accounts->model() === null) {
            $this->components->error('No user model. Point wire-module-users.model at yours.');

            return self::FAILURE;
        }

        if (! $accounts->ready()) {
            $this->components->error('No users table yet. Run php artisan migrate first.');

            return self::FAILURE;
        }

        // Read before the account is written, because "is this the first one"
        // is the question, and afterwards the answer is always no.
        $existed = $accounts->any();

        $name = $this->answer('name', 'Name', 'Administrator');
        $email = $this->email($accounts);

        if ($email === null) {
            return self::FAILURE;
        }

        $password = $email === '' ? '' : $this->password($accounts);

        if ($password === null) {
            return self::FAILURE;
        }

        if ($email === '' || $password === '') {
            $this->components->error('An e-mail address and a password are both required.');

            return self::FAILURE;
        }

        try {
            $user = $accounts->create($name, $email, $password);
        } catch (Throwable $e) {
            $this->components->error('Could not create the account: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Created {$email}.");

        $this->grant($accounts, $user, $existed);

        return self::SUCCESS;
    }

    /**
     * The address, held to the rule the user form holds it to.
     *
     * Asked again while it is not an address, when it was asked for; refused
     * outright when it came from `--email`, because a script passing a bad one
     * wants a failure, not a prompt it cannot answer.
     *
     * @return string|null The address; '' when none was given; null when it was refused.
     */
    protected function email(Accounts $accounts): ?string
    {
        $given = $this->option('email');
        $asked = ! (is_string($given) && $given !== '') && $this->input->isInteractive();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $email = trim($this->answer('email', 'E-mail address'));
            $problem = $email === '' ? null : $accounts->emailProblem($email);

            if ($problem === null) {
                return $email;
            }

            $this->components->error($problem.'.');

            if (! $asked) {
                return null;
            }
        }

        return null;
    }

    /**
     * A value from an option, or asked for, or the default unattended.
     */
    protected function answer(string $option, string $question, ?string $default = null): string
    {
        $given = $this->option($option);

        if (is_string($given) && $given !== '') {
            return $given;
        }

        return $this->input->isInteractive()
            ? (string) $this->ask($question, $default)
            : (string) $default;
    }

    /**
     * The password, which is never defaulted, held to the application's policy.
     *
     * An empty one is an account anybody can use and a generated one printed
     * into a deploy log is a credential in a log, so an unattended run without
     * `--password` is refused by the caller rather than answered here.
     *
     * Typed, it is asked twice — it is hidden, and a typo is an account nobody
     * can sign in as — and asked again while the policy refuses it. Passed as
     * `--password`, a refused one fails the command, as `--email` does.
     *
     * @return string|null The password; '' when none was given; null when it was refused.
     */
    protected function password(Accounts $accounts): ?string
    {
        $given = $this->option('password');

        if (is_string($given) && $given !== '') {
            $problem = $accounts->passwordProblem($given);

            if ($problem !== null) {
                $this->components->error($problem.'.');

                return null;
            }

            return $given;
        }

        if (! $this->input->isInteractive()) {
            return '';
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $password = (string) $this->secret('Password');

            if ($password === '') {
                return '';
            }

            $problem = $accounts->passwordProblem($password)
                ?? ((string) $this->secret('Password again') === $password ? null : 'The two passwords are not the same');

            if ($problem === null) {
                return $password;
            }

            $this->components->error($problem.'.');
        }

        return null;
    }

    /**
     * Give the account whatever was asked for.
     *
     * Silently nothing where the application has no roles: the module works
     * without `nyoncode/laravel-permission-extended`, and an account with no
     * role there is complete rather than half-made. A role that cannot be given
     * leaves the command successful — the account is made and usable, and the
     * role is `wire:assign-role` away.
     *
     * The super-admin is its own question, never a role in the list: asked with
     * `--super-admin`, and offered unprompted only for the first account, since
     * that one is somebody letting themselves in and every later one is an
     * ordinary user until said otherwise.
     *
     * @param  bool  $existed  Whether anybody could already sign in before this ran.
     */
    protected function grant(Accounts $accounts, Model $user, bool $existed): void
    {
        if (! Roles::enabled()) {
            return;
        }

        if ($this->option('super-admin') || (! $existed && $this->input->isInteractive())) {
            $this->grantSuperAdmin($accounts, $user);
        }

        $this->grantRoles($accounts, $user, $this->requestedRoles($accounts, offer: true));
    }
}
