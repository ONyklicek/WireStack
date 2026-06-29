<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Console;

use Illuminate\Console\Command;
use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;
use NyonCode\WireBoost\Contracts\SupportsSkills;
use NyonCode\WireBoost\Install\AgentRegistry;
use NyonCode\WireBoost\Install\GuidelineComposer;
use NyonCode\WireBoost\Install\McpInstaller;
use NyonCode\WireBoost\Install\SkillInstaller;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'wire-boost:install', description: 'Configure AI agents with the WireStack Boost MCP server, guidelines and skills.')]
class InstallCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wire-boost:install {--agent=* : Agent keys to configure (claude, codex, cursor, gemini, vscode, junie)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure AI agents with the WireStack Boost MCP server, guidelines and skills.';

    /**
     * Execute the console command.
     */
    public function handle(AgentRegistry $registry, McpInstaller $mcp): void
    {
        $keys = $this->selectedKeys($registry);

        if ($keys === []) {
            $this->components->warn('No agents selected.');

            return;
        }

        $base = base_path();
        $guidelines = GuidelineComposer::default();
        $skills = SkillInstaller::default();

        foreach ($keys as $key) {
            $agent = $registry->get($key);

            if ($agent === null) {
                $this->components->warn("Unknown agent [{$key}].");

                continue;
            }

            $this->components->info("Configuring {$agent->name()}…");

            if ($agent instanceof SupportsMcp) {
                $this->components->task('MCP server', fn (): bool => (bool) $mcp->install($agent, $base));
            }

            if ($agent instanceof SupportsGuidelines) {
                $this->components->task('Guidelines', function () use ($guidelines, $agent, $base): bool {
                    $guidelines->installInto($agent->guidelinesPath($base));

                    return true;
                });
            }

            if ($agent instanceof SupportsSkills) {
                $this->components->task('Skills', fn (): bool => $skills->install($agent->skillsPath($base)) !== []);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function selectedKeys(AgentRegistry $registry): array
    {
        /** @var array<int, string> $option */
        $option = (array) $this->option('agent');

        if ($option !== []) {
            return $option;
        }

        /** @var array<int, string> $selected */
        $selected = multiselect(
            label: 'Which agents should WireStack Boost configure?',
            options: $registry->options(),
        );

        return $selected;
    }
}
