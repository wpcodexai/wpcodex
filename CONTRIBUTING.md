# Contributing to Worker AI

Thank you for your interest in contributing. This document explains the development workflow, code standards, and the process for submitting changes.

---

## Before You Start

- Check the [open issues](https://github.com/wpworker/wpworker/issues) — your idea or bug may already be tracked
- For significant new features, open an issue first to discuss the approach before writing code
- For security vulnerabilities, see [SECURITY.md](./SECURITY.md) — do not open a public issue

---

## Development Setup

```bash
# Clone into your WordPress plugins directory
git clone https://github.com/wpworkerai/worker-ai.git wp-content/plugins/worker-ai
cd wp-content/plugins/worker-ai

# PHP dependencies (includes wordpress/mcp-adapter via Jetpack Autoloader)
composer install

# Node build tooling (JSX + SCSS via @wordpress/scripts)
npm install

# Build front-end assets
npm run build
```

### Requirements

| Tool | Version |
|---|---|
| PHP | 8.0+ |
| Composer | 2.x |
| Node.js | 18+ |
| WordPress | 6.9+ (Abilities API required) |
| WP-CLI | 2.8+ (optional) |

---

## Project Structure

```
wpworker/
├── worker-ai.php        # Entry point — loads MCP Adapter, registers abilities
├── includes/            # PSR-4 source root (namespace: WPWorker\)
│   ├── Abilities/       # One file per ability — each calls wp_register_ability()
│   ├── Runner/          # Execution logic: PhpRunner, CliRunner, DbRunner, FileManager
│   └── Skills/          # DB engine: Repository, AdminPage, Schema
├── src/                 # Front-end source: src/admin/ (React JSX) + src/frontend/ (vanilla JS)
├── assets/              # Compiled output — never edit directly
├── tests/               # PHPUnit suites: tests/Unit/ and tests/Integration/
└── docs/                # Developer documentation
```

See [README.md](./README.md) for the full annotated tree.

---

## Code Standards

### PHP

All PHP must follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) with these additional requirements:

- Every file starts with `declare(strict_types=1);`
- PHP 8.0 minimum — use typed properties, typed parameters, and return types on every method
- Namespace root: `WPWorker\` → `includes/`; one class per file; file name matches class name
- **Ability naming:** `wpworker/kebab-case` — e.g. `wpworker/php-execute`, `wpworker/skill-list`
- **Hook names:** prefixed `wpworker_` — e.g. `wpworker_after_activate`
- **Option names:** prefixed `wpworker_` — e.g. `wpworker_enable_php_execute`
- **Transient keys:** prefixed `wpworker_transient_`
- Output escaping on every echoed value: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- CSRF protection: `wp_nonce_field()` on every form; `check_admin_referer()` on every handler
- Capability checks: `current_user_can('manage_options')` at the start of every admin render and POST handler
- No raw `echo` of user input or database values without escaping
- No inline SQL — always use `$wpdb->prepare()` for parameterised queries
- No `eval()` — PHP execution uses the temp-file sandbox in `PhpRunner`

### Front-end (JavaScript + SCSS)

- **Source** lives in `src/admin/index.js` (React JSX) and `src/frontend/index.js` (vanilla JS)
- **Never edit** `assets/` directly — it is compiled output from `wp-scripts build`
- Compile with `npm run build` before committing; compiled output is committed
- No TypeScript — plain JavaScript with JSX. `@wordpress/scripts` handles JSX via Babel
- No jQuery — use the native DOM API, `@wordpress/api-fetch`, and `@wordpress/components`
- Admin data is passed from PHP to JS via `wp_localize_script()` or `wp_add_inline_script()` only — never echo raw PHP into `<script>` tags

### Commits

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

```
type(scope): short description

feat(abilities): add wpworker/hook-inspect ability
fix(runner): handle missing WP-CLI binary gracefully
docs(skills): add Bricks builder skill example
refactor(abilities): extract permission check to helper
test(runner): add unit tests for PhpRunner sandbox
chore(deps): update phpstan to 1.11
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `security`

---

## Running Quality Checks

All of these must pass before a PR can be merged:

```bash
# PHP syntax check
composer lint

# Static analysis — PHPStan level 8 with WordPress stubs
composer analyze

# Unit and integration tests
composer test

# JavaScript + CSS linting
npm run lint

# Build front-end assets (webpack via @wordpress/scripts — JSX + SCSS)
npm run build
```

Running all checks at once:

```bash
composer lint && composer analyze && composer test && npm run lint && npm run build
```

---

## Adding a New Ability

1. Create `includes/Abilities/YourAbility.php` — namespace `WPWorker\Abilities`
2. Register on the `wp_abilities_api_init` hook:

```php
<?php

declare(strict_types=1);

namespace WPWorker\Abilities;

/**
 * Registers the wpworker/your-ability ability.
 */
add_action( 'wp_abilities_api_init', static function (): void {
    wp_register_ability( 'wpworker/your-ability', [
        'label'               => __( 'Your Ability', 'worker-ai' ),
        'description'         => __( 'One-sentence description for the AI agent.', 'worker-ai' ),
        'category'            => 'wpworker',
        'input_schema'        => [
            'type'       => 'object',
            'properties' => [
                'param_name' => [
                    'type'        => 'string',
                    'description' => 'Description of this parameter.',
                ],
            ],
            'required'   => [ 'param_name' ],
        ],
        'output_schema'       => [
            'type'        => 'string',
            'description' => 'Description of the return value.',
        ],
        'execute_callback'    => static function ( array $args ): string {
            // Delegate to a Runner class — keep ability files thin.
            return ( new \WPWorker\Runner\YourRunner() )->run( $args['param_name'] );
        },
        'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
        'meta'                => [
            'annotations' => [ 'readonly' => false, 'destructive' => false ],
            'mcp'         => [ 'public' => true, 'type' => 'tool' ],
        ],
    ] );
} );
```

3. Add a unit test in `tests/Unit/Abilities/YourAbilityTest.php`
4. Document the ability in [README.md](./README.md) MCP Abilities table
5. If the ability needs a new Runner, add it to `includes/Runner/`

---

## Writing Tests

Tests live in `tests/Unit/` and `tests/Integration/`. We use [PHPUnit](https://phpunit.de/) with [Brain\Monkey](https://brain-wp.github.io/BrainMonkey/) for WordPress function mocking.

```php
<?php

declare(strict_types=1);

namespace WPWorker\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

final class YourTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_something(): void {
        $this->assertTrue( true );
    }
}
```

Test naming: `test_{method_name}_{scenario}` — e.g. `test_run_returns_output_when_code_is_valid`.

---

## Pull Request Process

1. **Fork** the repository and create a branch from `main`:
   ```bash
   git checkout -b feat/your-feature-name
   ```

2. **Make your changes** following the code standards above

3. **Run all quality checks** — PRs that fail CI will not be reviewed

4. **Update documentation** — if you add or change an ability, update README.md; if you change behaviour, update CLAUDE.md and GEMINI.md

5. **Open the PR** against `main` with:
   - A clear title in Conventional Commits format
   - A description of what changed and why
   - Any relevant issue numbers (`Closes #123`)

6. **Respond to review feedback** — maintainers may request changes before merging

### PR Checklist

- [ ] `composer lint` passes
- [ ] `composer analyze` passes
- [ ] `composer test` passes
- [ ] `npm run lint` passes
- [ ] `npm run build` passes
- [ ] New ability documented in README.md MCP Abilities table
- [ ] New ability has at least one unit test
- [ ] `CHANGELOG.txt` entry added under `Unreleased`
- [ ] No `release-info.json` changes (updated programmatically on release)

---

## Changelog

Add an entry to `CHANGELOG.txt` under the `[Unreleased]` section for every PR:

```
[Unreleased]
- feat: add wpworker/hook-inspect ability (lists active filters/actions on a hook)
- fix: handle missing WP-CLI binary with a clear error message
- docs: add Bricks builder skill example
```

---

## Questions

Open a [GitHub Discussion](https://github.com/wpworker/wpworker/discussions) for questions about contributing, architecture, or roadmap.