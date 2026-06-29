<?php

declare(strict_types=1);

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;
use NyonCode\WireBoost\Contracts\SupportsSkills;
use NyonCode\WireBoost\Install\AgentRegistry;
use NyonCode\WireBoost\Install\GuidelineComposer;
use NyonCode\WireBoost\Install\SkillInstaller;
use NyonCode\WireBoost\Mcp\Tools\BrowserLogs;
use NyonCode\WireBoost\Mcp\WireBoostServer;
use NyonCode\WireBoost\Support\ComponentScanner;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/wire-boost-edges-'.uniqid();
    mkdir($this->base, 0755, true);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->base));
});

it('exposes an input schema for every registered tool', function () {
    /** @var array<int, class-string> $tools */
    $tools = (new ReflectionClass(WireBoostServer::class))->getDefaultProperties()['tools'];

    foreach ($tools as $tool) {
        $array = app()->make($tool)->toArray();

        expect($array)->toHaveKey('name')
            ->and($array)->toHaveKey('inputSchema');
    }
});

it('builds a path for every agent capability it supports', function () {
    foreach ((new AgentRegistry)->all() as $agent) {
        if ($agent instanceof SupportsMcp) {
            expect($agent->mcpConfigPath('/base'))->toBeString()->not->toBeEmpty()
                ->and($agent->mcpServersKey())->toBeString()->not->toBeEmpty();
        }

        if ($agent instanceof SupportsGuidelines) {
            expect($agent->guidelinesPath('/base'))->toStartWith('/base');
        }

        if ($agent instanceof SupportsSkills) {
            expect($agent->skillsPath('/base'))->toStartWith('/base');
        }
    }
});

it('creates missing directories when installing guidelines', function () {
    $dir = $this->base.'/src';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/a.md', 'Nested guideline body');

    $target = $this->base.'/deeply/nested/CLAUDE.md';
    (new GuidelineComposer([$dir]))->installInto($target);

    expect(is_file($target))->toBeTrue();
});

it('copies skill modules that contain nested files', function () {
    $source = $this->base.'/source';
    mkdir($source.'/demo-skill/reference', 0755, true);
    file_put_contents($source.'/demo-skill/SKILL.md', "---\nname: demo-skill\n---\nBody");
    file_put_contents($source.'/demo-skill/reference/notes.md', 'Extra reference');

    $installed = (new SkillInstaller($source))->install($this->base.'/skills');

    expect($installed)->toBe(['demo-skill'])
        ->and(is_file($this->base.'/skills/demo-skill/reference/notes.md'))->toBeTrue();
});

it('defaults browser log entries to the configured maximum', function () {
    config()->set('wire-boost.tools.browser_logs', true);
    $path = $this->base.'/browser.log';
    config()->set('wire-boost.browser_logs.path', $path);
    config()->set('wire-boost.browser_logs.max_entries', 2);
    file_put_contents($path, "a\nb\nc\nd\n");

    WireBoostServer::tool(BrowserLogs::class)
        ->assertOk()
        ->assertSee('"count":2');
});

it('skips non-class, abstract and non-livewire files while scanning', function () {
    $dir = $this->base.'/scan';
    mkdir($dir, 0755, true);

    file_put_contents($dir.'/helpers.php', "<?php\n\nfunction wire_boost_noop() { return true; }\n");
    file_put_contents($dir.'/Orphan.php', "<?php\n\nnamespace Ghost\\Pkg;\n\nclass Orphan {}\n");

    expect((new ComponentScanner)->scan([$dir]))->toBe([]);
});
