/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./src/admin/components/abilities.js"
/*!*******************************************!*\
  !*** ./src/admin/components/abilities.js ***!
  \*******************************************/
() {

/**
 * Abilities Hub — enhance toggle experience.
 *
 * @file  src/admin/components/abilities.js
 * @since 1.0.0
 *
 * The checkboxes already submit a form via onchange. This module adds
 * optimistic UI (immediate card class swap) and a brief loading state.
 */

document.addEventListener('DOMContentLoaded', () => {
  const hub = document.getElementById('allyworker-abilities-settings');
  if (!hub) return;
  hub.querySelectorAll('.allyworker-toggle input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', () => {
      const card = checkbox.closest('.allyworker-ability-card');
      if (!card) return;
      const isNowEnabled = checkbox.checked;

      // Optimistic UI — swap class immediately before form submits.
      card.classList.toggle('is-enabled', isNowEnabled);
      card.classList.toggle('is-disabled', !isNowEnabled);
      const label = card.querySelector('.allyworker-toggle__label');
      if (label) {
        label.textContent = isNowEnabled ? window.allyworkerData?.i18n?.enabled ?? 'Enabled' : window.allyworkerData?.i18n?.disabled ?? 'Disabled';
      }
    });
  });
});

/***/ },

/***/ "./src/admin/components/configuration.js"
/*!***********************************************!*\
  !*** ./src/admin/components/configuration.js ***!
  \***********************************************/
() {

/**
 * Configuration page — all logic for Steps 1–3.
 *
 * @file  src/admin/components/configuration.js
 * @since 1.0.0
 *
 * Server-side data is passed via window.allyworkerConfig (set by wp_add_inline_script
 * in ConfigurationPage::render() before this script loads).
 *
 * Source : src/admin/components/configuration.js
 * Output : assets/admin/admin.js  (bundled by wp-scripts build)
 */

document.addEventListener('DOMContentLoaded', () => {
  const page = document.getElementById('allyworker-configuration');
  if (!page) return;

  // Server-side config
  const cfg = window.allyworkerConfig || {};
  const MCP_URL = cfg.mcpUrl || '';
  const USERNAME = cfg.username || '';
  const AJAX_URL = cfg.ajaxUrl || '';
  const AJAX_NONCE = cfg.ajaxNonce || '';
  const REVOKE_NONCE = cfg.revokeNonce || '';
  const DEFAULT_NAME = cfg.defaultName || 'allyworker';
  const PASTE_TPL = cfg.pasteTemplate || '';
  const AJAX_GENERATE = cfg.ajaxGenerate || '';
  const AJAX_REVOKE = cfg.ajaxRevoke || '';
  const L10N = cfg.l10n || {};

  //  State
  let mcpName = DEFAULT_NAME;
  let passwordValue = '';
  let passwordPlaceholder = true;
  let manualClient = 'claude-code';
  let npxlessClient = 'claude';
  const NAME_PH = '__ALLY_WORKER_MCP_NAME__';
  const PW_PH = '__ALLY_WORKER_PW_SLOT__';

  //  Per-client configs

  /**
   * Returns the current application password, or a placeholder string.
   *
   * @since  1.0.0
   * @return {string} The actual password value or 'YOUR-APP-PASSWORD'.
   */
  function pw() {
    return passwordPlaceholder ? 'YOUR-APP-PASSWORD' : passwordValue;
  }

  /**
   * Builds a JSON MCP server configuration object for the given root key.
   *
   * @since  1.0.0
   * @param  {string} rootKey Top-level key in the config JSON (e.g. 'mcpServers').
   * @return {string} Formatted JSON string.
   */
  function buildServerObj(rootKey) {
    const obj = {};
    obj[rootKey] = {
      [mcpName]: {
        command: 'npx',
        args: ['-y', '@automattic/mcp-wordpress-remote@latest'],
        env: {
          WP_API_URL: MCP_URL,
          WP_API_USERNAME: USERNAME,
          WP_API_PASSWORD: pw()
        }
      }
    };
    return JSON.stringify(obj, null, 4);
  }
  const CONFIGS = {
    'claude-code': {
      getCode: () => `claude mcp add '${mcpName}' \\\n` + `  --env WP_API_URL='${MCP_URL}' \\\n` + `  --env WP_API_USERNAME='${USERNAME}' \\\n` + `  --env WP_API_PASSWORD='${pw()}' \\\n` + `  -- npx -y @automattic/mcp-wordpress-remote@latest`,
      hint: 'Run in your terminal.',
      paths: {}
    },
    'claude-desktop': {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>claude_desktop_config.json</code>.',
      paths: {
        macOS: '~/Library/Application Support/Claude/claude_desktop_config.json',
        Windows: '%APPDATA%\\Claude\\claude_desktop_config.json'
      }
    },
    codex: {
      getCode: () => `[mcp_servers.${mcpName}]\ncommand = "npx"\nargs = ["-y", "@automattic/mcp-wordpress-remote@latest"]\n\n` + `[mcp_servers.${mcpName}.env]\nWP_API_URL = "${MCP_URL}"\nWP_API_USERNAME = "${USERNAME}"\nWP_API_PASSWORD = "${pw()}"`,
      hint: 'Add to <code>config.toml</code>.',
      paths: {
        'macOS / Linux': '~/.codex/config.toml',
        Windows: '%USERPROFILE%\\.codex\\config.toml'
      }
    },
    antigravity: {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>mcp_config.json</code>.',
      paths: {
        'macOS / Linux': '~/.gemini/antigravity/mcp_config.json',
        Windows: '%USERPROFILE%\\.gemini\\antigravity\\mcp_config.json'
      }
    },
    cursor: {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>mcp.json</code>.',
      paths: {
        Global: '~/.cursor/mcp.json',
        Project: '.cursor/mcp.json'
      }
    },
    vscode: {
      getCode: () => buildServerObj('servers'),
      hint: 'Add to <code>mcp.json</code>.',
      paths: {
        Workspace: '.vscode/mcp.json',
        User: 'Run: MCP: Open User Configuration (command palette)'
      }
    },
    'github-copilot': {
      getCode: () => buildServerObj('servers'),
      hint: 'Add to <code>mcp.json</code>.',
      paths: {
        Project: '.github/copilot/mcp.json'
      }
    },
    windsurf: {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>mcp_config.json</code>.',
      paths: {
        'macOS / Linux': '~/.codeium/windsurf/mcp_config.json',
        Windows: '%USERPROFILE%\\.codeium\\windsurf\\mcp_config.json'
      }
    },
    cline: {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>cline_mcp_settings.json</code>.',
      paths: {
        'Via UI': 'Cline sidebar → MCP Servers → Configure MCP Servers'
      }
    },
    'gemini-cli': {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>settings.json</code>.',
      paths: {
        Global: '~/.gemini/settings.json',
        Project: '.gemini/settings.json'
      }
    },
    'roo-code': {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>mcp.json</code>.',
      paths: {
        Project: '.roo/mcp.json',
        'Via UI': 'Roo Code sidebar → MCP Servers → Configure MCP Servers'
      }
    },
    'amazon-q': {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>mcp.json</code>.',
      paths: {
        Global: '~/.aws/amazonq/mcp.json',
        Project: '.amazonq/mcp.json'
      }
    },
    zed: {
      getCode: () => JSON.stringify({
        context_servers: {
          [mcpName]: {
            source: 'custom',
            enabled: true,
            command: 'npx',
            args: ['-y', '@automattic/mcp-wordpress-remote@latest'],
            env: {
              WP_API_URL: MCP_URL,
              WP_API_USERNAME: USERNAME,
              WP_API_PASSWORD: pw()
            }
          }
        }
      }, null, 4),
      hint: 'Add to <code>settings.json</code>.',
      paths: {
        'macOS / Linux': '~/.config/zed/settings.json'
      }
    },
    'kilo-code': {
      getCode: () => buildServerObj('mcpServers'),
      hint: 'Add to <code>mcp.json</code>.',
      paths: {
        Project: '.kilocode/mcp.json',
        'Via UI': 'Kilo Code sidebar → MCP Servers → Configure MCP Servers'
      }
    },
    opencode: {
      getCode: () => JSON.stringify({
        mcp: {
          [mcpName]: {
            type: 'local',
            command: ['npx', '-y', '@automattic/mcp-wordpress-remote@latest'],
            environment: {
              WP_API_URL: MCP_URL,
              WP_API_USERNAME: USERNAME,
              WP_API_PASSWORD: pw()
            }
          }
        }
      }, null, 4),
      hint: 'Add to <code>opencode.json</code>.',
      paths: {
        Project: 'opencode.json',
        Global: '~/.config/opencode/opencode.json'
      }
    }
  };

  //  Render

  /**
   * Re-renders all output sections (paste block, manual config, npx-free config).
   *
   * @since 1.0.0
   */
  function renderAll() {
    renderPaste();
    renderManualConfig();
    renderNpxless();
  }

  /**
   * Renders the Claude paste block with the current MCP name and password.
   *
   * @since 1.0.0
   */
  function renderPaste() {
    const el = document.getElementById('allyworker-paste-text');
    if (!el) return;
    el.textContent = PASTE_TPL.split(NAME_PH).join(mcpName).split(PW_PH).join(passwordPlaceholder ? 'YOUR-APP-PASSWORD' : passwordValue);
  }

  /**
   * Renders the manual client config code block and hint for the active client tab.
   *
   * @since 1.0.0
   */
  function renderManualConfig() {
    const c = CONFIGS[manualClient];
    if (!c) return;
    const codeEl = document.getElementById('allyworker-config-code');
    if (codeEl) codeEl.textContent = c.getCode();
    const hintEl = document.getElementById('allyworker-config-hint');
    if (hintEl) hintEl.innerHTML = c.hint;
    const pathsEl = document.getElementById('allyworker-config-paths');
    if (!pathsEl) return;
    const keys = Object.keys(c.paths || {});
    if (keys.length) {
      pathsEl.innerHTML = '<ul style="margin:4px 0 0;padding-left:20px;">' + keys.map(k => `<li><strong>${k}</strong>: <code>${c.paths[k]}</code></li>`).join('') + '</ul>';
      pathsEl.style.display = '';
    } else {
      pathsEl.innerHTML = '';
      pathsEl.style.display = 'none';
    }
  }

  /**
   * Renders the npx-free (streamable HTTP) config code block for the active client tab.
   *
   * @since 1.0.0
   */
  function renderNpxless() {
    const codeEl = document.getElementById('allyworker-npxless-code');
    const hintEl = document.getElementById('allyworker-npxless-hint');
    const pathEl = document.getElementById('allyworker-npxless-paths');
    if (!codeEl) return;
    const rawPw = passwordPlaceholder ? 'YOUR-APP-PASSWORD' : passwordValue.replace(/\s+/g, '');
    const encoded = passwordPlaceholder ? 'BASE64_ENCODED_CREDENTIALS' : btoa(USERNAME + ':' + rawPw);
    if (npxlessClient === 'codex') {
      codeEl.textContent = `[mcp_servers.${mcpName}]\nurl = "${MCP_URL}"\nhttp_headers = { Authorization = "Basic ${encoded}" }`;
      if (hintEl) hintEl.textContent = "Add to your project's .codex/config.toml file.";
      if (pathEl) pathEl.innerHTML = '<ul style="margin:4px 0 0;padding-left:20px;"><li><strong>Project</strong>: <code>.codex/config.toml</code></li><li><strong>Global</strong>: <code>~/.codex/config.toml</code></li></ul>';
    } else {
      codeEl.textContent = JSON.stringify({
        mcpServers: {
          [mcpName]: {
            type: 'http',
            url: MCP_URL,
            headers: {
              Authorization: `Basic ${encoded}`
            }
          }
        }
      }, null, 2);
      if (hintEl) hintEl.textContent = "Add to your project's .mcp.json file.";
      if (pathEl) pathEl.innerHTML = '<ul style="margin:4px 0 0;padding-left:20px;"><li><strong>Project</strong>: <code>.mcp.json</code></li></ul>';
    }
  }

  // Step unlock helpers

  /**
   * Reveals a step card with a fade-in animation and scrolls it into view.
   *
   * @since 1.0.0
   * @param {string} stepId The element ID of the step card to reveal.
   */
  function unlockStep(stepId) {
    const card = document.getElementById(stepId);
    if (!card) return;

    // Show the card with a smooth fade-in.
    card.style.opacity = '0';
    card.style.display = '';
    card.style.transition = 'opacity .35s ease';

    // Trigger reflow so the transition plays.
    // eslint-disable-next-line no-unused-expressions
    card.offsetHeight;
    card.style.opacity = '1';

    // Scroll to the newly revealed card after the animation.
    setTimeout(() => {
      card.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
      });
    }, 100);
  }

  // Client tab switching
  page.querySelectorAll('#allyworker-manual-tabs .allyworker-client-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      manualClient = tab.dataset.client;
      page.querySelectorAll('#allyworker-manual-tabs .allyworker-client-tab').forEach(t => t.classList.toggle('is-active', t === tab));
      renderManualConfig();
    });
  });
  page.querySelectorAll('.allyworker-npxless-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      npxlessClient = tab.dataset.client;
      tab.closest('.allyworker-client-tabs')?.querySelectorAll('.allyworker-client-tab').forEach(t => t.classList.toggle('is-active', t === tab));
      renderNpxless();
    });
  });

  // Server name

  /**
   * Updates the MCP server name and re-renders all config blocks.
   *
   * @global
   * @since  1.0.0
   * @param  {string} value The new server name entered by the user.
   */
  window.allyworkerUpdateName = value => {
    mcpName = value.trim() || DEFAULT_NAME;
    const warning = document.getElementById('allyworker-name-warning');
    const suggestion = document.getElementById('allyworker-name-suggestion');
    if (warning) warning.style.display = value.length >= 25 ? '' : 'none';
    if (suggestion) suggestion.style.display = value.trim().length > 0 && !value.toLowerCase().includes('allyworker') ? '' : 'none';
    renderAll();
  };

  /**
   * Toggles the server name input field visibility.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The toggle button element.
   */
  window.allyworkerToggleServerName = btn => {
    const field = document.getElementById('allyworker-name-field');
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    field.style.display = expanded ? 'none' : 'block';
    btn.setAttribute('aria-expanded', String(!expanded));
    if (!expanded) document.getElementById('allyworker-mcp-name')?.focus();
  };

  //  Manual config collapsible

  /**
   * Toggles the manual config panel open or closed.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The toggle button element.
   */
  window.allyworkerToggleManualConfig = btn => {
    const panel = document.getElementById('allyworker-manual-config');
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    panel.style.display = expanded ? 'none' : '';
    btn.setAttribute('aria-expanded', String(!expanded));
    if (!expanded) renderManualConfig();
  };

  /**
   * Opens the manual config panel and scrolls it into view.
   *
   * @global
   * @since 1.0.0
   */
  window.allyworkerOpenManualConfig = () => {
    const panel = document.getElementById('allyworker-manual-config');
    const toggle = document.getElementById('allyworker-manual-toggle');
    panel.style.display = '';
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    renderManualConfig();
    panel.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  };

  // npx-free collapsible

  /**
   * Toggles the npx-free (streamable HTTP) config panel open or closed.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The toggle button element.
   */
  window.allyworkerToggleNpxless = btn => {
    const panel = document.getElementById('allyworker-npxless-config');
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    panel.style.display = expanded ? 'none' : '';
    btn.setAttribute('aria-expanded', String(!expanded));
    if (!expanded) renderNpxless();
  };

  // Paste block

  /**
   * Toggles the Claude paste block between collapsed and expanded views.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The expand/collapse button element.
   */
  window.allyworkerToggleExpandPaste = btn => {
    const content = document.getElementById('allyworker-paste-content');
    const expanded = btn.getAttribute('aria-expanded') === 'true';
    content.classList.toggle('is-expanded', !expanded);
    btn.setAttribute('aria-expanded', String(!expanded));
    btn.textContent = expanded ? L10N.showFull || 'Show full text' : L10N.showLess || 'Show less';
  };

  /**
   * Copies the Claude paste block text to the clipboard.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The copy button element.
   */
  window.allyworkerCopyPaste = btn => {
    const text = document.getElementById('allyworker-paste-text')?.textContent || '';
    const warning = document.getElementById('allyworker-paste-warning');
    navigator.clipboard.writeText(text).then(() => {
      const orig = btn.textContent;
      btn.textContent = L10N.copied || 'Copied!';
      if (warning) warning.style.display = '';
      setTimeout(() => {
        btn.textContent = orig;
        if (warning) warning.style.display = 'none';
      }, 4000);
    });
  };

  // Copy buttons (config + npx-free)

  /**
   * Copies the manual config code block to the clipboard.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The copy button element.
   */
  window.allyworkerCopyConfig = btn => copyEl('allyworker-config-code', btn);

  /**
   * Copies the npx-free config code block to the clipboard.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The copy button element.
   */
  window.allyworkerCopyNpxless = btn => copyEl('allyworker-npxless-code', btn);

  /**
   * Copies the text content of a DOM element to the clipboard.
   *
   * @since  1.0.0
   * @param  {string}      id  The element ID whose text content to copy.
   * @param  {HTMLElement} btn The copy button element (receives a temporary "Copied!" label).
   */
  function copyEl(id, btn) {
    const text = document.getElementById(id)?.textContent || '';
    navigator.clipboard.writeText(text).then(() => {
      const orig = btn.textContent;
      btn.textContent = L10N.copied || 'Copied!';
      setTimeout(() => {
        btn.textContent = orig;
      }, 1500);
    });
  }

  // Application Password — copy

  /**
   * Copies the generated application password to the clipboard.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The copy button element.
   */
  window.allyworkerCopyPassword = btn => {
    navigator.clipboard.writeText(passwordValue).then(() => {
      const orig = btn.textContent;
      btn.textContent = L10N.copied || 'Copied!';
      setTimeout(() => {
        btn.textContent = orig;
      }, 1500);
    });
  };

  // Application Password — generate (AJAX)

  /**
   * Sends an AJAX request to generate a new WordPress application password.
   *
   * @global
   * @since  1.0.0
   * @param  {HTMLElement} btn The "Generate" button element.
   */
  window.allyworkerGeneratePassword = btn => {
    const nameWrap = document.getElementById('allyworker-pw-name-wrap');
    const nameInput = document.getElementById('allyworker-pw-name');
    const isSecond = nameWrap && nameWrap.style.display !== 'none';
    const name = nameInput?.value.trim() || '';
    const spinner = document.getElementById('allyworker-pw-spinner');

    // Second+ time: name is required.
    if (isSecond && name === '') {
      nameInput?.focus();
      alert(L10N.errorNameRequired || 'Please enter a name for this password.');
      return;
    }
    btn.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    const fd = new FormData();
    fd.append('action', AJAX_GENERATE);
    fd.append('nonce', AJAX_NONCE);
    fd.append('name', name);
    fetch(AJAX_URL, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(r => r.json()).then(data => {
      btn.disabled = false;
      if (spinner) spinner.style.display = 'none';
      if (!data.success) {
        alert(data.data || L10N.errorGenerate);
        return;
      }
      passwordValue = data.data.password;
      passwordPlaceholder = false;
      const reveal = document.getElementById('allyworker-pw-reveal');
      const pwEl = document.getElementById('allyworker-pw-value');
      if (reveal) reveal.style.display = '';
      if (pwEl) pwEl.textContent = passwordValue;
      btn.textContent = L10N.generateAnother || 'Generate another application password';

      // Reveal the name field for subsequent passwords and clear it.
      const nameWrap2 = document.getElementById('allyworker-pw-name-wrap');
      const nameInput2 = document.getElementById('allyworker-pw-name');
      if (nameWrap2) nameWrap2.style.display = '';
      if (nameInput2) nameInput2.value = '';

      // Append row to existing table and show it.
      const tbody = document.getElementById('allyworker-pw-tbody');
      const existing = document.getElementById('allyworker-pw-existing');
      if (tbody) {
        // Show full name (prefix visible in the table).
        const displayName = data.data.name;
        const tr = document.createElement('tr');
        tr.dataset.uuid = data.data.uuid;
        tr.innerHTML = `<td class="allyworker-pw-table__name">${displayName}</td>` + `<td class="allyworker-pw-table__meta">${data.data.created}</td>` + `<td class="allyworker-pw-table__meta">${L10N.never || 'Never'}</td>` + `<td class="allyworker-pw-table__actions"><button type="button" class="button button-small allyworker-pw-revoke-btn" onclick="allyworkerRevokePassword('${data.data.uuid}', this)">${L10N.revoke || 'Revoke'}</button></td>`;
        tbody.appendChild(tr);
      }

      // Show the table section and update count badge.
      if (existing) {
        existing.style.display = '';
        const countEl = document.getElementById('allyworker-pw-count');
        if (countEl) {
          const tbody2 = document.getElementById('allyworker-pw-tbody');
          countEl.textContent = `(${tbody2 ? tbody2.rows.length : 1})`;
        }
      }

      // Unlock Step 3 — password exists now.
      unlockStep('allyworker-step-3');
      renderAll();
    }).catch(() => {
      btn.disabled = false;
      if (spinner) spinner.style.display = 'none';
      alert(L10N.errorNetwork);
    });
  };

  // Application Password — revoke (AJAX)

  /**
   * Sends an AJAX request to revoke an existing WordPress application password.
   *
   * @global
   * @since  1.0.0
   * @param  {string}      uuid The UUID of the application password to revoke.
   * @param  {HTMLElement} btn  The "Revoke" button element.
   */
  window.allyworkerRevokePassword = (uuid, btn) => {
    if (!confirm(L10N.revokeConfirm)) return;
    const fd = new FormData();
    fd.append('action', AJAX_REVOKE);
    fd.append('nonce', REVOKE_NONCE);
    fd.append('uuid', uuid);
    fetch(AJAX_URL, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(r => r.json()).then(data => {
      if (!data.success) {
        alert(data.data || 'Error.');
        return;
      }
      document.querySelector(`[data-uuid="${uuid}"]`)?.remove();
      // Update count badge; hide section if no rows remain.
      const tbody = document.getElementById('allyworker-pw-tbody');
      const existing = document.getElementById('allyworker-pw-existing');
      if (tbody) {
        const countEl = document.getElementById('allyworker-pw-count');
        if (countEl) countEl.textContent = `(${tbody.rows.length})`;
        if (existing && tbody.rows.length === 0) {
          existing.style.display = 'none';
        }
      }
    });
  };

  // Init
  renderAll();
});

/***/ },

/***/ "./src/admin/components/copy-button.js"
/*!*********************************************!*\
  !*** ./src/admin/components/copy-button.js ***!
  \*********************************************/
() {

/**
 * Copy-to-clipboard for the Connect page prompt textarea.
 *
 * @file  src/admin/components/copy-button.js
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('allyworker-copy-prompt');
  if (!btn) return;
  const targetId = btn.getAttribute('data-target') ?? '';
  const textarea = document.getElementById(targetId);
  const originalText = btn.textContent ?? '';
  btn.addEventListener('click', async () => {
    if (!textarea) return;
    try {
      if (navigator.clipboard) {
        await navigator.clipboard.writeText(textarea.value);
      } else {
        textarea.select();
        document.execCommand('copy');
      }
      btn.textContent = window.allyworkerData?.i18n?.saved ?? 'Copied!';
      btn.disabled = true;
      setTimeout(() => {
        btn.textContent = originalText;
        btn.disabled = false;
      }, 2000);
    } catch {
      btn.textContent = window.allyworkerData?.i18n?.error ?? 'Error';
      setTimeout(() => {
        btn.textContent = originalText;
      }, 2000);
    }
  });
});

/***/ },

/***/ "./src/admin/components/notices.js"
/*!*****************************************!*\
  !*** ./src/admin/components/notices.js ***!
  \*****************************************/
() {

/**
 * Dismissible admin notices.
 *
 * @file  src/admin/components/notices.js
 * @since 1.0.0
 *
 * Delegates to WordPress's built-in notice dismissal; this just handles
 * any custom .allyworker-notice elements that aren't standard .notice divs.
 */

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.allyworker-notice .notice-dismiss').forEach(btn => {
    btn.addEventListener('click', () => {
      const notice = btn.closest('.allyworker-notice');
      if (notice) {
        notice.style.transition = 'opacity .2s';
        notice.style.opacity = '0';
        setTimeout(() => notice.remove(), 200);
      }
    });
  });
});

/***/ },

/***/ "./src/admin/components/skills.js"
/*!****************************************!*\
  !*** ./src/admin/components/skills.js ***!
  \****************************************/
() {

/**
 * Skills page enhancements.
 *
 * @file  src/admin/components/skills.js
 * @since 1.0.0
 *
 *  1. Auto-grow the Markdown body textarea as content grows.
 *  2. Confirm skill name slug format before submitting the new-skill form.
 *  3. Highlight the active frontmatter format hint.
 */

document.addEventListener('DOMContentLoaded', () => {
  // Auto-grow textarea
  const textarea = document.querySelector('.allyworker-skill-editor');
  if (textarea) {
    const autoGrow = () => {
      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';
    };
    textarea.addEventListener('input', autoGrow);
    autoGrow(); // Run once on load.
  }

  // Slug validation
  const nameInput = document.getElementById('skill_name');
  const form = nameInput?.closest('form');
  if (nameInput && form && !nameInput.readOnly) {
    // Auto-slugify as user types.
    nameInput.addEventListener('input', () => {
      nameInput.value = nameInput.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-');
    });
    form.addEventListener('submit', e => {
      const val = nameInput.value.trim();
      if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(val)) {
        e.preventDefault();
        nameInput.focus();
        nameInput.setCustomValidity('Skill name must be lowercase and hyphen-separated (e.g. my-skill-name).');
        nameInput.reportValidity();
      } else {
        nameInput.setCustomValidity('');
      }
    });
  }
});

/***/ },

/***/ "./src/scss/admin.scss"
/*!*****************************!*\
  !*** ./src/scss/admin.scss ***!
  \*****************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!****************************!*\
  !*** ./src/admin/index.js ***!
  \****************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _scss_admin_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./../scss/admin.scss */ "./src/scss/admin.scss");
/* harmony import */ var _components_abilities_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/abilities.js */ "./src/admin/components/abilities.js");
/* harmony import */ var _components_abilities_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_components_abilities_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_configuration_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/configuration.js */ "./src/admin/components/configuration.js");
/* harmony import */ var _components_configuration_js__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_components_configuration_js__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _components_copy_button_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/copy-button.js */ "./src/admin/components/copy-button.js");
/* harmony import */ var _components_copy_button_js__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_components_copy_button_js__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _components_notices_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/notices.js */ "./src/admin/components/notices.js");
/* harmony import */ var _components_notices_js__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_components_notices_js__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _components_skills_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./components/skills.js */ "./src/admin/components/skills.js");
/* harmony import */ var _components_skills_js__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_components_skills_js__WEBPACK_IMPORTED_MODULE_5__);
/**
 * AllyWorker Admin — entry point.
 *
 * @file   src/admin/index.js
 * @since  1.0.0
 *
 * Source : src/admin/index.js
 * Output : assets/admin/admin.js  (via wp-scripts build)
 *
 * Handles:
 *  1. Copy-to-clipboard for the Connect page prompt textarea
 *  2. Dismissible admin notices
 *  3. Abilities Hub — toggle submit via JS (prevents full page reload)
 *  4. Skills page — auto-resize textarea
 */






})();

/******/ })()
;