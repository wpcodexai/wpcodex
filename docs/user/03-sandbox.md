# Sandbox

The sandbox is a directory where AI agents can write PHP files that are loaded automatically on every WordPress request. It lets agents persist functionality — custom hooks, shortcodes, REST endpoints, scheduled tasks — without modifying theme files or creating a full plugin.

---

## Location

```
wp-content/wp-allyworker-sandbox/
```

The constant `ALLY_WORKER_SANDBOX_DIR` holds this path and is available in all PHP executed via `allyworker/php-execute`.

---

## How files are loaded

On every request, AllyWorker iterates the sandbox directory and `require`s each `.php` file. Files are loaded in filesystem order. An `index.php` stub file is excluded automatically so accidental directory listings are prevented.

**Crash recovery:** If a file throws a fatal error during loading, AllyWorker catches the shutdown and marks that file as disabled (adds `.disabled` to the filename) so it does not break the site on the next request.

---

## Writing a sandbox file

Ask your agent to write a file to the sandbox directory. Example:

```
Write this to the sandbox:

<?php
add_action('init', function() {
    register_post_type('product', [
        'label'  => 'Products',
        'public' => true,
    ]);
});
```

The agent will call `allyworker/file-write` with the path set to `ALLY_WORKER_SANDBOX_DIR . 'my-cpt.php'`.

---

## Disabling and re-enabling files

When you want to temporarily stop loading a file without deleting it:

```
allyworker/file-disable
  path: /path/to/wp-content/wp-allyworker-sandbox/my-cpt.php
```

This renames the file to `my-cpt.php.disabled`. To restore it:

```
allyworker/file-enable
  path: /path/to/wp-content/wp-allyworker-sandbox/my-cpt.php.disabled
```

---

## Managing sandbox files in the admin

Go to **AllyWorker → Sandbox** to see all files currently in the sandbox, their status (enabled/disabled), and the option to delete them.

---

## Best practices

**Keep files lean.** Sandbox files run on every request. Avoid heavy queries, remote HTTP calls, or slow operations at the top level — use WordPress hooks (`init`, `rest_api_init`, etc.) so code runs only when needed.

**One concern per file.** Give each file a descriptive name that reflects its purpose: `register-products-cpt.php`, `add-discount-endpoint.php`. This makes it easy to disable individual pieces without affecting others.

**Validate before finalising.** After writing a file, ask your agent to check that the site is still responding:
```
Call allyworker/php-execute with: echo get_bloginfo('name');
```
If it fails, the sandbox file likely caused a fatal error and was auto-disabled.

**Move to a real plugin when ready.** Sandbox files are not a permanent home for production code. Once a feature is stable, ask your agent to package it as a proper plugin or add it to your theme's `functions.php`.

---

## Security

Sandbox files execute with full WordPress privileges. Anyone who can write to the sandbox directory or create sandbox files via AllyWorker can run arbitrary PHP. Keep AllyWorker disabled (`AI Abilities → OFF`) on production sites, and restrict access to the sandbox directory via your web server if needed.
