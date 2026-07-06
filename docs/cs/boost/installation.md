---
title: Instalace
order: 20
summary: Nainstalujte balíček a nakonfigurujte své AI agenty jedním příkazem.
---

# Instalace

Wire Boost je vývojový nástroj. Vyžadujte ho s `--dev`:

```bash
composer require nyoncode/wire-boost --dev
```

Pak spusťte instalátor:

```bash
php artisan wire-boost:install
```

Instalátor se zeptá, které agenty nakonfigurovat, a pro každého:

1. Zaregistruje MCP server v config souboru agenta (např. `.mcp.json`).
2. Sloučí wireStack guidelines do souboru guideline agenta (např. `CLAUDE.md`, `AGENTS.md`).
3. Nainstaluje agent skills (např. do `.claude/skills/`).

Příkaz je **idempotentní** — spusťte ho kdykoli znovu. Agenty můžete cílit i neinteraktivně:

```bash
php artisan wire-boost:install --agent=claude --agent=cursor
```

## Podporovaní agenti

| Agent | MCP | Guidelines | Skills |
|-------|:---:|:----------:|:------:|
| Claude Code (`claude`) | ✓ | ✓ | ✓ |
| Codex (`codex`) | ✓ | ✓ | |
| Cursor (`cursor`) | ✓ | ✓ | |
| Gemini CLI (`gemini`) | ✓ | ✓ | |
| GitHub Copilot / VS Code (`vscode`) | ✓ | ✓ | |
| Junie (`junie`) | | ✓ | ✓ |

## Udržování zdrojů aktuálních

Po upgradu vašich wire balíčků obnovte vygenerované guidelines a skills:

```bash
php artisan wire-boost:update
```

Bez argumentů obnoví každého agenta, který už má soubor guideline; předejte `--agent=<key>` pro
cílení konkrétních agentů.

## Spuštění MCP serveru

Agenti server spouštějí za vás přes config, který instalátor zapíše. Pro manuální spuštění:

```bash
php artisan wire-boost:mcp
```

To zaregistruje server pod lokálním handle `wire-boost` a deleguje na Laravel MCP
`mcp:start`. Viz [MCP Server a nástroje](mcp-tools.md).

## Git

Vygenerované soubory (`.mcp.json`, `CLAUDE.md`, `AGENTS.md`, adresáře skills) je bezpečné commitnout, aby
váš tým sdílel stejné nastavení, nebo je přidat do `.gitignore` a regenerovat per stroj — obojí funguje,
protože `wire-boost:install` je idempotentní.
