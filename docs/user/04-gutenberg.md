# Gutenberg / Block Editor

AllyWorker lets AI agents write Gutenberg block content to posts, pages, and templates through a browser-based finalizer. Because block serialization requires the JavaScript block registry, content changes go through a queue and are finalized in an open Block Editor tab rather than written directly by the server.

---

## How it works

1. The agent queues a block change (a **batch** containing one or more **items**).
2. You open the **Block Editor Queue** page in your browser.
3. The queue page applies each pending change using the live JavaScript block registry.
4. The post is saved. The batch is marked finalized.

---

## Single-post workflow (`gutenberg-write-content`)

For a single post change, the agent uses the convenience ability `allyworker/gutenberg-write-content`, which creates the batch, adds the item, and enables finalization in one call.

**What the agent returns:**

```json
{
  "batch_id": 42,
  "item_id": 7,
  "post_id": 123,
  "post_title": "My Page",
  "batch_status": "ready",
  "finalization_url": "https://example.com/wp-admin/admin.php?page=allyworker-block-editor-queue&batch=42",
  "finalizer_runtime": { "online": false, ... },
  "user_instruction": "Open the Block Editor Queue to apply this change."
}
```

The agent will tell you to open `finalization_url` if the Block Editor Queue is not already open. Open it and the change applies automatically — no extra clicks needed.

---

## Multi-post workflow

For changing multiple posts in one atomic batch:

1. **Create the batch:**
   ```
   allyworker/gutenberg-create-pending-batch
     label: "Homepage redesign"
   ```

2. **Add one item per post:**
   ```
   allyworker/gutenberg-add-pending-change
     batch_id:   42
     post_id:    123
     block_spec: [{ "name": "core/paragraph", "attributes": { "content": "Hello world." } }]
   ```
   Repeat for each post.

3. **Enable finalization:**
   ```
   allyworker/gutenberg-enable-batch-finalization
     batch_id: 42
   ```

4. Open the **Block Editor Queue** (`allyworker-block-editor-queue`) to apply all changes.

---

## Block spec format

Block specs are plain JSON objects. Each item in `block_spec` represents a top-level block:

```json
[
  {
    "name": "core/heading",
    "attributes": { "level": 2, "content": "Welcome" }
  },
  {
    "name": "core/paragraph",
    "attributes": { "content": "This is the intro paragraph." }
  },
  {
    "name": "core/columns",
    "attributes": {},
    "innerBlocks": [
      { "name": "core/column", "attributes": {}, "innerBlocks": [
        { "name": "core/paragraph", "attributes": { "content": "Left column." } }
      ]},
      { "name": "core/column", "attributes": {}, "innerBlocks": [
        { "name": "core/paragraph", "attributes": { "content": "Right column." } }
      ]}
    ]
  }
]
```

Use registered block names (e.g. `core/paragraph`, `core/image`, `core/group`, `acf/my-block`). The agent can discover which blocks are available by asking the finalizer runtime or by inspecting the site's block registry.

---

## Block Editor Queue page

Navigate to **AllyWorker → Block Editor** to manage pending batches.

The page shows:
- All **ready** batches waiting for finalization
- **Finalizing** batches currently being processed
- **Finalized** batches (success)
- **Failed** or **conflicted** batches

When you have the page open, batches in "ready" state are picked up automatically. The agent monitors finalization via `allyworker/gutenberg-get-finalizer-runtime` or by polling a REST endpoint — it will notify you once the change is applied.

**Keep the page open** while changes are being finalized. Navigating away mid-finalization marks the batch as interrupted and it will retry on the next page load.

---

## Reading current content

Before writing, the agent typically reads the current block content:

```
allyworker/gutenberg-get-content
  post_id: 123
```

Returns the parsed block tree so the agent can inspect the current structure before proposing changes.

---

## Raw HTML blocks

By default, `allyworker/gutenberg-write-content` rejects a `block_spec` that contains only `core/html` (raw HTML) blocks. This prevents agents from bypassing the block system. To allow raw HTML intentionally:

```json
{
  "post_id": 123,
  "block_spec": [...],
  "allow_raw_html": true
}
```

---

## Troubleshooting

**Batch stuck in "ready" / not applying**
The Block Editor Queue page must be open in a browser tab connected to your site. Open `finalization_url` and keep it open.

**"Conflicted" status**
Another batch was finalized for the same post after this batch was created, causing a conflict. The agent will report this and may ask you to review the conflict before retrying.

**"Failed" status**
Finalization failed (e.g. a block in the spec was not registered). Check the failure message on the Block Editor Queue page. The agent can delete the failed batch and create a new one with corrected block specs.
