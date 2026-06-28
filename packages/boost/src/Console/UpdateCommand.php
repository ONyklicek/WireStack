<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Console;

use Illuminate\Console\Command;
use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsSkills;
use NyonCode\WireBoost\Install\AgentRegistry;
use NyonCode\WireBoost\Install\GuidelineComposer;
use NyonCode\WireBoost\Install\SkillInstaller;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'wire-boost:update', description: 'Refresh WireStack Boost guidelines and skills for configured agents.')]
class UpdateCommand extends Command
{
    protected $signature = 'wire-boost:update {--agent=* : Agent keys to refresh; defaults to every agent with an existing guideline file.}';

    protected $description = 'Refresh WireStack Boost guidelines and skills for configured agents.';

    public function handle(AgentRegistry $registry): int
    {
        $base = base_path();
        $guidelines = GuidelineComposer::default();
        $skills = SkillInstaller::default();

        /** @var array<int, string> $only */
        $only = (array) $this->option('agent');
        $refreshed = 0;

        foreach ($registry->all() as $key => $agent) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $guidelinePath = $agent instanceof SupportsGuidelines ? $agent->guidelinesPath($base) : null;

            // Without an explicit selection, only touch agents already set up.
            if ($only === [] && ($guidelinePath === null || ! is_file($guidelinePath))) {
                continue;
            }

            if ($guidelinePath !== null) {
                $guidelines->installInto($guidelinePath);
            }

            if ($agent instanceof SupportsSkills) {
                $skills->install($agent->skillsPath($base));
            }

            $this->components->info("Refreshed {$agent->name()}.");
            $refreshed++;
        }

        if ($refreshed === 0) {
            $this->components->warn('No configured agents found to refresh. Run wire-boost:install first.');
        }

        return self::SUCCESS;
    }
}
