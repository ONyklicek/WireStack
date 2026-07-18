<?php

declare(strict_types=1);

use NyonCode\WireBoost\Install\AgentRegistry;
use NyonCode\WireBoost\Install\Agents\ClaudeCode;
use NyonCode\WireBoost\Install\Agents\Cursor;
use NyonCode\WireBoost\Install\Agents\Junie;
use NyonCode\WireBoost\Install\Agents\Vscode;
use NyonCode\WireBoost\Install\GuidelineComposer;
use NyonCode\WireBoost\Install\McpInstaller;
use NyonCode\WireBoost\Install\SkillInstaller;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/wire-boost-install-'.uniqid();
    mkdir($this->base, 0755, true);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->base));
});

// ── Agents & registry ────────────────────────────────────────────────────────

it('declares agent capabilities', function () {
    $claude = new ClaudeCode;
    expect($claude->supportsMcp())->toBeTrue()
        ->and($claude->supportsGuidelines())->toBeTrue()
        ->and($claude->supportsSkills())->toBeTrue()
        ->and($claude->mcpServersKey())->toBe('mcpServers')
        ->and($claude->skillsPath('/base'))->toBe('/base/.claude/skills');

    $junie = new Junie;
    expect($junie->supportsMcp())->toBeFalse()
        ->and($junie->supportsSkills())->toBeTrue();

    $cursor = new Cursor;
    expect($cursor->supportsSkills())->toBeFalse()
        ->and($cursor->guidelinesPath('/base'))->toEndWith('.mdc');

    expect((new Vscode)->mcpServersKey())->toBe('servers');
});

it('registers and resolves agents', function () {
    $registry = new AgentRegistry;

    expect($registry->all())->toHaveCount(6)
        ->and($registry->get('claude'))->toBeInstanceOf(ClaudeCode::class)
        ->and($registry->get('nope'))->toBeNull()
        ->and($registry->options())->toHaveKey('claude');
});

// ── McpInstaller ─────────────────────────────────────────────────────────────

it('writes and merges the mcp server entry idempotently', function () {
    $installer = new McpInstaller;
    $agent = new ClaudeCode;

    $path = $installer->install($agent, $this->base);
    $installer->install($agent, $this->base);

    $config = json_decode((string) file_get_contents($path), true);

    expect($path)->toBe($this->base.'/.mcp.json')
        ->and($config['mcpServers']['wire-boost']['command'])->toBe('php')
        ->and($config['mcpServers']['wire-boost']['args'])->toBe(['artisan', 'wire-boost:mcp']);
});

it('preserves existing mcp config when merging', function () {
    $path = $this->base.'/.mcp.json';
    file_put_contents($path, json_encode(['mcpServers' => ['other' => ['command' => 'node']]]));

    (new McpInstaller)->install(new ClaudeCode, $this->base);

    $config = json_decode((string) file_get_contents($path), true);

    expect($config['mcpServers'])->toHaveKeys(['other', 'wire-boost']);
});

it('uses the agent specific servers key', function () {
    $path = (new McpInstaller)->install(new Vscode, $this->base);
    $config = json_decode((string) file_get_contents($path), true);

    expect($config)->toHaveKey('servers')
        ->and($config['servers'])->toHaveKey('wire-boost');
});

// ── GuidelineComposer ────────────────────────────────────────────────────────

it('composes the shipped guidelines', function () {
    expect(GuidelineComposer::default()->compose())->toContain('WireStack');
});

it('emits example component tags literally instead of rendering them (blade guidelines are Blade-rendered)', function () {
    // Regression H5: the guideline files are compiled through Blade::render, so a
    // bare `<x-wire-notifications::toast-container />` used as documentation would
    // actually be rendered — emitting live Alpine/Livewire markup into the text
    // (or throwing when the component needs a runtime). @verbatim keeps them as
    // the literal example tags a reader (or the MCP client) is meant to copy.
    $composed = GuidelineComposer::default()->compose();

    expect($composed)
        ->toContain('<x-wire-notifications::toast-container />')
        ->toContain('<x-wire-actions::modal-host />')
        // If a tag had actually rendered, its compiled Alpine attributes would leak.
        ->not->toContain('x-data="wireToast')
        ->not->toContain('wire:snapshot');
});

it('returns an empty string when no guideline directory exists', function () {
    expect((new GuidelineComposer(['/no/such/dir']))->compose())->toBe('');
});

it('renders markdown and blade guideline files', function () {
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/a.md', '# Plain markdown');
    file_put_contents($dir.'/b.blade.php', 'Rendered blade');

    $composed = (new GuidelineComposer([$dir]))->compose();

    expect($composed)->toContain('Plain markdown')->toContain('Rendered blade');
});

it('installs guidelines into a fresh file, then replaces between markers', function () {
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/a.md', 'First content');
    $composer = new GuidelineComposer([$dir]);

    $target = $this->base.'/CLAUDE.md';
    $composer->installInto($target);

    expect(file_get_contents($target))->toContain('First content')->toContain('wire-boost:guidelines:start');

    file_put_contents($dir.'/a.md', 'Second content');
    $composer->installInto($target);

    $result = (string) file_get_contents($target);
    expect($result)->toContain('Second content')
        ->and($result)->not->toContain('First content')
        ->and(substr_count($result, 'wire-boost:guidelines:start'))->toBe(1);
});

it('appends guidelines to an existing file without markers', function () {
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/a.md', 'Block content');

    $target = $this->base.'/AGENTS.md';
    file_put_contents($target, "# Existing project notes\n");

    (new GuidelineComposer([$dir]))->installInto($target);

    $result = (string) file_get_contents($target);
    expect($result)->toContain('Existing project notes')->toContain('Block content');
});

// ── SkillInstaller ───────────────────────────────────────────────────────────

it('copies the shipped skill modules', function () {
    $target = $this->base.'/skills';

    $installed = SkillInstaller::default()->install($target);

    expect($installed)->toContain('wire-table-development', 'wire-forms-development')
        ->and(is_file($target.'/wire-table-development/SKILL.md'))->toBeTrue();
});

it('returns no skills when the source is missing', function () {
    expect((new SkillInstaller('/no/such/source'))->install($this->base.'/skills'))->toBe([]);
});
