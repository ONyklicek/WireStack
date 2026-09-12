<?php

declare(strict_types=1);

use NyonCode\WireBoost\Exceptions\McpConfigException;
use NyonCode\WireBoost\Install\AgentRegistry;
use NyonCode\WireBoost\Install\Agents\ClaudeCode;
use NyonCode\WireBoost\Install\Agents\Cursor;
use NyonCode\WireBoost\Install\Agents\Junie;
use NyonCode\WireBoost\Install\Agents\Vscode;
use NyonCode\WireBoost\Install\GuidelineComposer;
use NyonCode\WireBoost\Install\McpInstaller;
use NyonCode\WireBoost\Install\SkillInstaller;
use NyonCode\WireBoost\Support\WirePackages;

/**
 * A WirePackages reporting exactly the named packages as installed.
 */
function installedPackages(string ...$names): WirePackages
{
    $versions = [];

    foreach ($names as $name) {
        $versions[WirePackages::composerName($name)] = '2.0.0';
    }

    return new WirePackages($versions);
}

/**
 * Everything the stack has — the shape the monorepo itself is in.
 */
function everyPackage(): WirePackages
{
    return installedPackages(...WirePackages::all());
}

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

it('refuses to merge into a config file it cannot parse, instead of replacing it', function () {
    // The regression this exists for: a read that answered `[]` for an
    // unparseable file turned the merge into a replacement, and every other
    // server in it was gone with a tick printed beside it.
    $path = $this->base.'/.mcp.json';
    file_put_contents($path, '{"mcpServers": {"other": {"command": "node"},}}');

    try {
        (new McpInstaller)->install(new ClaudeCode, $this->base);
        $this->fail('Expected a malformed config to stop the install.');
    } catch (McpConfigException $e) {
        expect($e->getMessage())->toContain($path)
            ->and(file_get_contents($path))->toContain('"other"');
    }
});

it('refuses a config whose root is not an object', function () {
    file_put_contents($this->base.'/.mcp.json', '"just a string"');

    (new McpInstaller)->install(new ClaudeCode, $this->base);
})->throws(McpConfigException::class, 'its root is string');

it('says which file could not be written', function () {
    // A directory where the config file should be: `file_put_contents` cannot
    // write it, and without the guard it returned false and the caller believed
    // the server had been configured.
    mkdir($this->base.'/.mcp.json');

    (new McpInstaller)->install(new ClaudeCode, $this->base);
})->throws(McpConfigException::class, 'could not be written');

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
    expect((new GuidelineComposer(['/no/such/dir'], everyPackage()))->compose())->toBe('');
});

it('renders markdown and blade guideline files', function () {
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/a.md', '# Plain markdown');
    file_put_contents($dir.'/b.blade.php', 'Rendered blade');

    $composed = (new GuidelineComposer([$dir], everyPackage()))->compose();

    expect($composed)->toContain('Plain markdown')->toContain('Rendered blade');
});

it('skips a guideline whose package this application does not have', function () {
    // A guideline is named after its package, and telling an agent about
    // `ListPage` where there is no wire-panels is how a session ends in a class
    // that does not exist.
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/core.blade.php', 'Always shipped');
    file_put_contents($dir.'/wire-table.blade.php', 'Table conventions');
    file_put_contents($dir.'/wire-panels.blade.php', 'Panel conventions');
    file_put_contents($dir.'/wire-modules.blade.php', 'Module conventions');

    $composed = (new GuidelineComposer([$dir], installedPackages('wire-table', 'wire-module-media')))->compose();

    expect($composed)->toContain('Always shipped')
        ->toContain('Table conventions')
        ->toContain('Module conventions')
        ->not->toContain('Panel conventions');
});

it('matches a guideline package through either extension', function () {
    // `wire-panels.blade.php` and `wire-panels.md` are one guideline written two
    // ways; pathinfo() would only ever take `.php` off the first.
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/wire-admin.md', 'Shell conventions');

    expect((new GuidelineComposer([$dir], installedPackages('wire-core')))->compose())->toBe('');
});

it('installs guidelines into a fresh file, then replaces between markers', function () {
    $dir = $this->base.'/guidelines';
    mkdir($dir, 0755, true);
    file_put_contents($dir.'/a.md', 'First content');
    $composer = new GuidelineComposer([$dir], everyPackage());

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

    (new GuidelineComposer([$dir], everyPackage()))->installInto($target);

    $result = (string) file_get_contents($target);
    expect($result)->toContain('Existing project notes')->toContain('Block content');
});

// ── SkillInstaller ───────────────────────────────────────────────────────────

it('copies the shipped skill modules', function () {
    $target = $this->base.'/skills';

    $installed = SkillInstaller::default()->install($target);

    expect($installed)->toContain('wire-table-development', 'wire-forms-development', 'wire-v2-upgrade')
        ->and(is_file($target.'/wire-table-development/SKILL.md'))->toBeTrue();
});

it('skips a skill whose package this application does not have', function () {
    $target = $this->base.'/skills';

    $installed = SkillInstaller::default()->install($target);

    expect($installed)->toContain('wire-v2-upgrade')
        ->and(is_dir($target.'/wire-table-development'))->toBeTrue();

    $lean = $this->base.'/lean-skills';
    $onlyCore = (new SkillInstaller([realpath(__DIR__.'/../../resources/boost/skills')], installedPackages('wire-core')))
        ->install($lean);

    expect($onlyCore)->toContain('wire-core-development', 'wire-v2-upgrade')
        ->not->toContain('wire-table-development', 'wire-panels-development')
        ->and(is_dir($lean.'/wire-table-development'))->toBeFalse();
});

it('lets a project override one file of a shipped skill', function () {
    // The project directory is read after the shipped one, so it replaces what
    // it names without having to restate the rest of the module.
    $shipped = $this->base.'/shipped';
    $project = $this->base.'/project';
    mkdir($shipped.'/wire-core-development', 0755, true);
    mkdir($project.'/wire-core-development/reference', 0755, true);
    file_put_contents($shipped.'/wire-core-development/SKILL.md', 'Shipped skill');
    file_put_contents($shipped.'/wire-core-development/NOTES.md', 'Shipped notes');
    file_put_contents($project.'/wire-core-development/SKILL.md', 'Project skill');
    file_put_contents($project.'/wire-core-development/reference/extra.md', 'Project extra');

    $target = $this->base.'/merged';
    $installed = (new SkillInstaller([$shipped, $project], installedPackages('wire-core')))->install($target);

    expect($installed)->toBe(['wire-core-development'])
        ->and(file_get_contents($target.'/wire-core-development/SKILL.md'))->toBe('Project skill')
        ->and(file_get_contents($target.'/wire-core-development/NOTES.md'))->toBe('Shipped notes')
        ->and(file_get_contents($target.'/wire-core-development/reference/extra.md'))->toBe('Project extra');
});

it('returns no skills when the source is missing', function () {
    expect((new SkillInstaller(['/no/such/source'], everyPackage()))->install($this->base.'/skills'))->toBe([]);
});

it('refuses a config file it cannot open, rather than replacing it', function () {
    // Unreadable is not the same as absent: writing anyway would drop every
    // server already configured there, which is the one outcome a developer
    // cannot undo from the message.
    //
    // Skipped when the suite runs as root, because root can read a 0000 file and
    // the branch then cannot be reached at all — which is a property of the
    // environment rather than of the code.
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        expect(true)->toBeTrue();

        return;
    }

    $agent = new ClaudeCode;
    $path = $agent->mcpConfigPath($this->base);

    file_put_contents($path, '{"mcpServers":{}}');
    chmod($path, 0000);

    try {
        expect(fn () => (new McpInstaller)->install($agent, $this->base))
            ->toThrow(McpConfigException::class, 'could not be read');
    } finally {
        chmod($path, 0644);
    }
});

it('refuses when the configuration directory cannot be created', function () {
    // A file where the directory has to go: mkdir() fails and cannot be retried
    // into existence, which is the one case the retry after it does not cover.
    $base = $this->base.'/not-a-directory';
    file_put_contents($base, 'a file');

    expect(fn () => (new McpInstaller)->install(new ClaudeCode, $base))
        ->toThrow(McpConfigException::class, 'cannot be created');
});
