<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use NyonCode\WireBoost\Console\McpCommand;
use NyonCode\WireBoost\Install\AgentRegistry;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/wire-boost-cmd-'.uniqid();
    mkdir($this->base, 0755, true);
    app()->setBasePath($this->base);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->base));
});

it('configures a selected agent', function () {
    $this->artisan('wire-boost:install', ['--agent' => ['claude']])
        ->assertSuccessful();

    expect(is_file($this->base.'/.mcp.json'))->toBeTrue()
        ->and(is_file($this->base.'/CLAUDE.md'))->toBeTrue()
        ->and(is_file($this->base.'/.claude/skills/wire-table-development/SKILL.md'))->toBeTrue();

    expect(file_get_contents($this->base.'/CLAUDE.md'))->toContain('WireStack');
});

it('warns about an unknown agent', function () {
    $this->artisan('wire-boost:install', ['--agent' => ['bogus']])
        ->expectsOutputToContain('Unknown agent')
        ->assertSuccessful();
});

it('prompts to select agents when none are passed', function () {
    $options = (new AgentRegistry)->options();

    $this->artisan('wire-boost:install')
        ->expectsChoice('Which agents should WireStack Boost configure?', ['claude'], $options)
        ->assertSuccessful();

    expect(is_file($this->base.'/.mcp.json'))->toBeTrue();
});

it('warns when the prompt selects nothing', function () {
    $options = (new AgentRegistry)->options();

    $this->artisan('wire-boost:install')
        ->expectsChoice('Which agents should WireStack Boost configure?', [], $options)
        ->expectsOutputToContain('No agents selected')
        ->assertSuccessful();
});

it('refreshes guidelines and skills for a selected agent', function () {
    $this->artisan('wire-boost:update', ['--agent' => ['claude']])
        ->assertSuccessful();

    expect(is_file($this->base.'/CLAUDE.md'))->toBeTrue()
        ->and(is_file($this->base.'/.claude/skills/wire-core-development/SKILL.md'))->toBeTrue();
});

it('warns when there is nothing to refresh', function () {
    $this->artisan('wire-boost:update')
        ->expectsOutputToContain('No configured agents')
        ->assertSuccessful();
});

it('refreshes already-configured agents by default', function () {
    $this->artisan('wire-boost:install', ['--agent' => ['claude']])->assertSuccessful();

    $this->artisan('wire-boost:update')
        ->expectsOutputToContain('Refreshed')
        ->assertSuccessful();
});

it('delegates the mcp command to mcp:start', function () {
    $command = new class extends McpCommand
    {
        /** @var array<int, array{0: string, 1: array<string, mixed>}> */
        public array $calls = [];

        public function call($command, array $arguments = []): int
        {
            $this->calls[] = [$command, $arguments];

            return self::SUCCESS;
        }
    };

    expect($command->handle())->toBe(0)
        ->and($command->calls)->toBe([['mcp:start', ['handle' => 'wire-boost']]]);
});

it('registers the wire-boost mcp server', function () {
    expect(Mcp::getLocalServer('wire-boost'))->not->toBeNull();
});
