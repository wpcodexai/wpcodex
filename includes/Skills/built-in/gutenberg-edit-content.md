---
name: gutenberg-edit-content
description: Create or edit WordPress content using the native Gutenberg block editor via the WPCodex Block Editor Queue. Activate when the user asks to build, rebuild, migrate, or update a post, page, or template using Gutenberg/native blocks.
enable_prompt: true
enable_agentic: true
---

# Editing Gutenberg Content

Use this playbook for native WordPress block editor work. Static Gutenberg blocks require browser-side JavaScript serialization before queued content goes live — the WPCodex Block Editor Queue admin page is part of the workflow.

## Start Here

1. Call `wpcodex/php-execute` to check if the Gutenberg editor is active:
   ```php
   return function_exists('use_block_editor_for_post_type');
   ```
2. Confirm the target post type supports the block editor.
3. Ask the user to keep the **Block Editor Queue** admin page open while you work (WPCodex → Block Editor). Changes queued for static/native blocks cannot finalize without that page.

## Read Before Writing

- Use `wpcodex/php-execute` with `get_post( $post_id )` and `parse_blocks( $post->post_content )` to read the current block structure before editing.
- Do not stack pending changes on the same target without confirming the previous batch is finalized.

## Compose With Registered Blocks

Build content from registered blocks supplied as `{ name, attributes, innerBlocks }`.

**Core blocks:** `core/heading` (set `level`), `core/paragraph`, `core/list` + `core/list-item`, `core/image`, `core/quote`, `core/buttons` + `core/button`, `core/table`, `core/code`, `core/separator`. Use `core/group` / `core/columns` + `core/column` with `innerBlocks` for layout.

**Third-party blocks** (WooCommerce, Kadence, ACF, etc.): if a registered block exists, use it. Discover available blocks:
```php
return array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
```

**Avoid `core/html`** except for small fragments with no registered-block equivalent. Never wrap whole sections in raw HTML blocks.

## Write Path

Use `wpcodex/php-execute` to call `wp_update_post()` with serialized block content built with `serialize_blocks()`:

```php
$blocks = [
    [
        'blockName'    => 'core/heading',
        'attrs'        => [ 'level' => 2 ],
        'innerBlocks'  => [],
        'innerHTML'    => '<h2 class="wp-block-heading">Hello</h2>',
        'innerContent' => [ '<h2 class="wp-block-heading">Hello</h2>' ],
    ],
    [
        'blockName'    => 'core/paragraph',
        'attrs'        => [],
        'innerBlocks'  => [],
        'innerHTML'    => '<p>Content here.</p>',
        'innerContent' => [ '<p>Content here.</p>' ],
    ],
];

$content = serialize_blocks( $blocks );
return wp_update_post( [ 'ID' => $post_id, 'post_content' => $content ] );
```

## Completion

After writing, verify the saved block tree:
```php
$post = get_post( $post_id );
return parse_blocks( $post->post_content );
```

Confirm the block names and structure match what was intended before reporting success.
