<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleUsers\Install\CreateFirstAdministrator;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Roles;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function Laravel\Prompts\multiselect;

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
 *   php artisan wire:user --name=Jane --email=jane@example.com --password=… --admin
 *   php artisan wire:user --email=… --password=… --role=editor --role=support
 */
#[AsCommand(name: 'wire:user')]
class WireUserCommand extends Command
{
    protected $signature = 'wire:user
        {--name= : The name to store}
        {--email= : The address they sign in with}
        {--password= : Their password, hashed before it is written}
        {--admin : Give the account the super-admin role}
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
        $email = $this->answer('email', 'E-mail address');
        $password = $this->password();

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
     * The password, which is never defaulted.
     *
     * An empty one is an account anybody can use and a generated one printed
     * into a deploy log is a credential in a log, so an unattended run without
     * `--password` is refused by the caller rather than answered here.
     */
    protected function password(): string
    {
        $given = $this->option('password');

        if (is_string($given) && $given !== '') {
            return $given;
        }

        return $this->input->isInteractive() ? (string) $this->components->secret('Password') : '';
    }

    /**
     * Give the account whatever roles were asked for.
     *
     * Silently nothing where the application has no roles: the module works
     * without `nyoncode/laravel-permission-extended`, and an account with no
     * role there is complete rather than half-made.
     *
     * @param  Model  $user
     * @param  bool  $existed  Whether anybody could already sign in before this ran.
     */
    protected function grant(Accounts $accounts, $user, bool $existed): void
    {
        if (! Roles::enabled()) {
            return;
        }

        /** @var array<int, string> $roles */
        $roles = array_values(array_filter((array) $this->option('role')));

        if ($this->option('admin')) {
            $roles[] = $accounts->superAdminRole();
        }

        if ($roles === [] && $this->input->isInteractive()) {
            $roles = $this->choose($accounts, $existed);
        }

        foreach (array_unique($roles) as $role) {
            try {
                $accounts->assign($user, $role);
                $this->components->twoColumnDetail('Role', "<fg=green>{$role}</>");
            } catch (Throwable $e) {
                // The account is made and usable; only the role is missing, and
                // that is a screen away rather than a reason to call the whole
                // command failed and leave somebody wondering.
                $this->components->warn("Could not give it `{$role}`: ".$e->getMessage());
            }
        }
    }

    /**
     * Offer the roles this application already has.
     *
     * Super-admin is pre-picked only for the first account, because that one is
     * somebody letting themselves in and every later one is an ordinary user
     * until said otherwise.
     *
     * @return array<int, string>
     */
    protected function choose(Accounts $accounts, bool $existed): array
    {
        $superAdmin = $accounts->superAdminRole();
        $available = $accounts->roles();

        if (! in_array($superAdmin, $available, true)) {
            $available[] = $superAdmin;
        }

        /** @var array<int, string> $picked */
        $picked = multiselect(
            label: 'Which roles should this account have?',
            options: array_combine($available, $available),
            default: $existed ? [] : [$superAdmin],
            hint: 'Space picks one, enter confirms.',
        );

        return $picked;
    }
}
