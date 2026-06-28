---
title: Installation
order: 20
summary: Install the package and configure your AI agents with one command.
---

# Installation

Wire Boost is a development tool. Require it with `--dev`:

```bash
composer require nyoncode/wire-boost --dev
```

Then run the installer:

```bash
php artisan wire-boost:install
```

The installer asks which agents to configure and, for each one:

1. Registers the MCP server in the agent's config file (e.g. `.mcp.json`).
2. Merges the wireStack guidelines into the agent's guideline file (e.g. `CLAUDE.md`, `AGENTS.md`).
3. Installs the agent skills (e.g. into `.claude/skills/`).

The command is **idempotent** — re-run it any time. You can also target agents non-interactively:

```bash
php artisan wire-boost:install --agent=claude --agent=cursor
```

## Supported agents

| Agent | MCP | Guidelines | Skills |
|-------|:---:|:----------:|:------:|
| Claude Code (`claude`) | ✓ | ✓ | ✓ |
| Codex (`codex`) | ✓ | ✓ | |
| Cursor (`cursor`) | ✓ | ✓ | |
| Gemini CLI (`gemini`) | ✓ | ✓ | |
| GitHub Copilot / VS Code (`vscode`) | ✓ | ✓ | |
| Junie (`junie`) | | ✓ | ✓ |

## Keeping resources up to date

After upgrading your wire packages, refresh the generated guidelines and skills:

```bash
php artisan wire-boost:update
```

With no arguments it refreshes every agent that already has a guideline file; pass `--agent=<key>` to
target specific agents.

## Starting the MCP server

Agents launch the server for you via the config the installer writes. To start it manually:

```bash
php artisan wire-boost:mcp
```

This registers the server under the local handle `wire-boost` and delegates to Laravel MCP's
`mcp:start`. See [MCP Server & Tools](mcp-tools.md).

## Git

The generated files (`.mcp.json`, `CLAUDE.md`, `AGENTS.md`, skill directories) are safe to commit so
your team shares the same setup, or to add to `.gitignore` and regenerate per machine — both work
because `wire-boost:install` is idempotent.
