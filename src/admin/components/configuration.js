/**
 * Configuration page — all logic for Steps 1–3.
 *
 * Server-side data is passed via window.wpcodexConfig (set by wp_add_inline_script
 * in ConfigurationPage::render() before this script loads).
 *
 * Source : src/admin/components/configuration.js
 * Output : assets/admin/admin.js  (bundled by wp-scripts build)
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const page = document.getElementById( 'wpcodex-configuration' );
	if ( ! page ) return;

	// Server-side config 
	const cfg = window.wpcodexConfig || {};
	const MCP_URL       = cfg.mcpUrl       || '';
	const USERNAME      = cfg.username     || '';
	const AJAX_URL      = cfg.ajaxUrl      || '';
	const AJAX_NONCE    = cfg.ajaxNonce    || '';
	const REVOKE_NONCE  = cfg.revokeNonce  || '';
	const DEFAULT_NAME  = cfg.defaultName  || 'wpcodex';
	const PASTE_TPL     = cfg.pasteTemplate || '';
	const AJAX_GENERATE = cfg.ajaxGenerate || '';
	const AJAX_REVOKE   = cfg.ajaxRevoke   || '';
	const L10N          = cfg.l10n         || {};

	//  State 
	let mcpName             = DEFAULT_NAME;
	let passwordValue       = '';
	let passwordPlaceholder = true;
	let manualClient        = 'claude-code';
	let npxlessClient       = 'claude';

	const NAME_PH = '__WPCODEX_MCP_NAME__';
	const PW_PH   = '__WPCODEX_PW_SLOT__';

	//  Per-client configs 
	function pw() { return passwordPlaceholder ? 'YOUR-APP-PASSWORD' : passwordValue; }

	function buildServerObj( rootKey ) {
		const obj = {};
		obj[rootKey] = {
			[mcpName]: {
				command: 'npx',
				args: [ '-y', '@automattic/mcp-wordpress-remote@latest' ],
				env: { WP_API_URL: MCP_URL, WP_API_USERNAME: USERNAME, WP_API_PASSWORD: pw() },
			},
		};
		return JSON.stringify( obj, null, 4 );
	}

	const CONFIGS = {
		'claude-code': {
			getCode: () =>
				`claude mcp add '${mcpName}' \\\n` +
				`  --env WP_API_URL='${MCP_URL}' \\\n` +
				`  --env WP_API_USERNAME='${USERNAME}' \\\n` +
				`  --env WP_API_PASSWORD='${pw()}' \\\n` +
				`  -- npx -y @automattic/mcp-wordpress-remote@latest`,
			hint:  'Run in your terminal.',
			paths: {},
		},
		'claude-desktop': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>claude_desktop_config.json</code>.',
			paths: { macOS: '~/Library/Application Support/Claude/claude_desktop_config.json', Windows: '%APPDATA%\\Claude\\claude_desktop_config.json' },
		},
		'codex': {
			getCode: () =>
				`[mcp_servers.${mcpName}]\ncommand = "npx"\nargs = ["-y", "@automattic/mcp-wordpress-remote@latest"]\n\n` +
				`[mcp_servers.${mcpName}.env]\nWP_API_URL = "${MCP_URL}"\nWP_API_USERNAME = "${USERNAME}"\nWP_API_PASSWORD = "${pw()}"`,
			hint:  'Add to <code>config.toml</code>.',
			paths: { 'macOS / Linux': '~/.codex/config.toml', Windows: '%USERPROFILE%\\.codex\\config.toml' },
		},
		'antigravity': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>mcp_config.json</code>.',
			paths: { 'macOS / Linux': '~/.gemini/antigravity/mcp_config.json', Windows: '%USERPROFILE%\\.gemini\\antigravity\\mcp_config.json' },
		},
		'cursor': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>mcp.json</code>.',
			paths: { Global: '~/.cursor/mcp.json', Project: '.cursor/mcp.json' },
		},
		'vscode': {
			getCode: () => buildServerObj( 'servers' ),
			hint:  'Add to <code>mcp.json</code>.',
			paths: { Workspace: '.vscode/mcp.json', User: 'Run: MCP: Open User Configuration (command palette)' },
		},
		'github-copilot': {
			getCode: () => buildServerObj( 'servers' ),
			hint:  'Add to <code>mcp.json</code>.',
			paths: { Project: '.github/copilot/mcp.json' },
		},
		'windsurf': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>mcp_config.json</code>.',
			paths: { 'macOS / Linux': '~/.codeium/windsurf/mcp_config.json', Windows: '%USERPROFILE%\\.codeium\\windsurf\\mcp_config.json' },
		},
		'cline': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>cline_mcp_settings.json</code>.',
			paths: { 'Via UI': 'Cline sidebar → MCP Servers → Configure MCP Servers' },
		},
		'gemini-cli': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>settings.json</code>.',
			paths: { Global: '~/.gemini/settings.json', Project: '.gemini/settings.json' },
		},
		'roo-code': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>mcp.json</code>.',
			paths: { Project: '.roo/mcp.json', 'Via UI': 'Roo Code sidebar → MCP Servers → Configure MCP Servers' },
		},
		'amazon-q': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>mcp.json</code>.',
			paths: { Global: '~/.aws/amazonq/mcp.json', Project: '.amazonq/mcp.json' },
		},
		'zed': {
			getCode: () => JSON.stringify(
				{ context_servers: { [mcpName]: { source: 'custom', enabled: true, command: 'npx', args: [ '-y', '@automattic/mcp-wordpress-remote@latest' ], env: { WP_API_URL: MCP_URL, WP_API_USERNAME: USERNAME, WP_API_PASSWORD: pw() } } } },
				null, 4
			),
			hint:  'Add to <code>settings.json</code>.',
			paths: { 'macOS / Linux': '~/.config/zed/settings.json' },
		},
		'kilo-code': {
			getCode: () => buildServerObj( 'mcpServers' ),
			hint:  'Add to <code>mcp.json</code>.',
			paths: { Project: '.kilocode/mcp.json', 'Via UI': 'Kilo Code sidebar → MCP Servers → Configure MCP Servers' },
		},
		'opencode': {
			getCode: () => JSON.stringify(
				{ mcp: { [mcpName]: { type: 'local', command: [ 'npx', '-y', '@automattic/mcp-wordpress-remote@latest' ], environment: { WP_API_URL: MCP_URL, WP_API_USERNAME: USERNAME, WP_API_PASSWORD: pw() } } } },
				null, 4
			),
			hint:  'Add to <code>opencode.json</code>.',
			paths: { Project: 'opencode.json', Global: '~/.config/opencode/opencode.json' },
		},
	};

	//  Render 
	function renderAll() {
		renderPaste();
		renderManualConfig();
		renderNpxless();
	}

	function renderPaste() {
		const el = document.getElementById( 'wpcodex-paste-text' );
		if ( ! el ) return;
		el.textContent = PASTE_TPL
			.split( NAME_PH ).join( mcpName )
			.split( PW_PH   ).join( passwordPlaceholder ? 'YOUR-APP-PASSWORD' : passwordValue );
	}

	function renderManualConfig() {
		const c = CONFIGS[ manualClient ];
		if ( ! c ) return;

		const codeEl = document.getElementById( 'wpcodex-config-code' );
		if ( codeEl ) codeEl.textContent = c.getCode();

		const hintEl = document.getElementById( 'wpcodex-config-hint' );
		if ( hintEl ) hintEl.innerHTML = c.hint;

		const pathsEl = document.getElementById( 'wpcodex-config-paths' );
		if ( ! pathsEl ) return;
		const keys = Object.keys( c.paths || {} );
		if ( keys.length ) {
			pathsEl.innerHTML = '<ul style="margin:4px 0 0;padding-left:20px;">'
				+ keys.map( k => `<li><strong>${k}</strong>: <code>${c.paths[k]}</code></li>` ).join( '' )
				+ '</ul>';
			pathsEl.style.display = '';
		} else {
			pathsEl.innerHTML = '';
			pathsEl.style.display = 'none';
		}
	}

	function renderNpxless() {
		const codeEl = document.getElementById( 'wpcodex-npxless-code' );
		const hintEl = document.getElementById( 'wpcodex-npxless-hint' );
		const pathEl = document.getElementById( 'wpcodex-npxless-paths' );
		if ( ! codeEl ) return;

		const rawPw   = passwordPlaceholder ? 'YOUR-APP-PASSWORD' : passwordValue.replace( /\s+/g, '' );
		const encoded = passwordPlaceholder ? 'BASE64_ENCODED_CREDENTIALS' : btoa( USERNAME + ':' + rawPw );

		if ( npxlessClient === 'codex' ) {
			codeEl.textContent = `[mcp_servers.${mcpName}]\nurl = "${MCP_URL}"\nhttp_headers = { Authorization = "Basic ${encoded}" }`;
			if ( hintEl ) hintEl.textContent = "Add to your project's .codex/config.toml file.";
			if ( pathEl ) pathEl.innerHTML = '<ul style="margin:4px 0 0;padding-left:20px;"><li><strong>Project</strong>: <code>.codex/config.toml</code></li><li><strong>Global</strong>: <code>~/.codex/config.toml</code></li></ul>';
		} else {
			codeEl.textContent = JSON.stringify(
				{ mcpServers: { [mcpName]: { type: 'http', url: MCP_URL, headers: { Authorization: `Basic ${encoded}` } } } },
				null, 2
			);
			if ( hintEl ) hintEl.textContent = "Add to your project's .mcp.json file.";
			if ( pathEl ) pathEl.innerHTML = '<ul style="margin:4px 0 0;padding-left:20px;"><li><strong>Project</strong>: <code>.mcp.json</code></li></ul>';
		}
	}

	// Step unlock helpers 
	function unlockStep( stepId ) {
		const card = document.getElementById( stepId );
		if ( ! card ) return;

		// Show the card with a smooth fade-in.
		card.style.opacity = '0';
		card.style.display = '';
		card.style.transition = 'opacity .35s ease';

		// Trigger reflow so the transition plays.
		// eslint-disable-next-line no-unused-expressions
		card.offsetHeight;

		card.style.opacity = '1';

		// Scroll to the newly revealed card after the animation.
		setTimeout( () => {
			card.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}, 100 );
	}

	// Client tab switching 
	page.querySelectorAll( '#wpcodex-manual-tabs .wpcodex-client-tab' ).forEach( tab => {
		tab.addEventListener( 'click', () => {
			manualClient = tab.dataset.client;
			page.querySelectorAll( '#wpcodex-manual-tabs .wpcodex-client-tab' ).forEach( t =>
				t.classList.toggle( 'is-active', t === tab )
			);
			renderManualConfig();
		} );
	} );

	page.querySelectorAll( '.wpcodex-npxless-tab' ).forEach( tab => {
		tab.addEventListener( 'click', () => {
			npxlessClient = tab.dataset.client;
			tab.closest( '.wpcodex-client-tabs' )?.querySelectorAll( '.wpcodex-client-tab' ).forEach( t =>
				t.classList.toggle( 'is-active', t === tab )
			);
			renderNpxless();
		} );
	} );

	// Server name
	window.wpcodexUpdateName = ( value ) => {
		mcpName = value.trim() || DEFAULT_NAME;
		const warning    = document.getElementById( 'wpcodex-name-warning' );
		const suggestion = document.getElementById( 'wpcodex-name-suggestion' );
		if ( warning )    warning.style.display    = value.length >= 25 ? '' : 'none';
		if ( suggestion ) suggestion.style.display = ( value.trim().length > 0 && ! value.toLowerCase().includes( 'wpcodex' ) ) ? '' : 'none';
		renderAll();
	};

	window.wpcodexToggleServerName = ( btn ) => {
		const field    = document.getElementById( 'wpcodex-name-field' );
		const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		field.style.display = expanded ? 'none' : 'block';
		btn.setAttribute( 'aria-expanded', String( ! expanded ) );
		if ( ! expanded ) document.getElementById( 'wpcodex-mcp-name' )?.focus();
	};

	//  Manual config collapsible 
	window.wpcodexToggleManualConfig = ( btn ) => {
		const panel    = document.getElementById( 'wpcodex-manual-config' );
		const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		panel.style.display = expanded ? 'none' : '';
		btn.setAttribute( 'aria-expanded', String( ! expanded ) );
		if ( ! expanded ) renderManualConfig();
	};

	window.wpcodexOpenManualConfig = () => {
		const panel  = document.getElementById( 'wpcodex-manual-config' );
		const toggle = document.getElementById( 'wpcodex-manual-toggle' );
		panel.style.display = '';
		if ( toggle ) toggle.setAttribute( 'aria-expanded', 'true' );
		renderManualConfig();
		panel.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	};

	// npx-free collapsible
	window.wpcodexToggleNpxless = ( btn ) => {
		const panel    = document.getElementById( 'wpcodex-npxless-config' );
		const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		panel.style.display = expanded ? 'none' : '';
		btn.setAttribute( 'aria-expanded', String( ! expanded ) );
		if ( ! expanded ) renderNpxless();
	};

	// Paste block 
	window.wpcodexToggleExpandPaste = ( btn ) => {
		const content  = document.getElementById( 'wpcodex-paste-content' );
		const expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		content.classList.toggle( 'is-expanded', ! expanded );
		btn.setAttribute( 'aria-expanded', String( ! expanded ) );
		btn.textContent = expanded ? ( L10N.showFull || 'Show full text' ) : ( L10N.showLess || 'Show less' );
	};

	window.wpcodexCopyPaste = ( btn ) => {
		const text    = document.getElementById( 'wpcodex-paste-text' )?.textContent || '';
		const warning = document.getElementById( 'wpcodex-paste-warning' );
		navigator.clipboard.writeText( text ).then( () => {
			const orig = btn.textContent;
			btn.textContent = L10N.copied || 'Copied!';
			if ( warning ) warning.style.display = '';
			setTimeout( () => {
				btn.textContent = orig;
				if ( warning ) warning.style.display = 'none';
			}, 4000 );
		} );
	};

	// Copy buttons (config + npx-free) 
	window.wpcodexCopyConfig = ( btn ) => copyEl( 'wpcodex-config-code', btn );
	window.wpcodexCopyNpxless = ( btn ) => copyEl( 'wpcodex-npxless-code', btn );

	function copyEl( id, btn ) {
		const text = document.getElementById( id )?.textContent || '';
		navigator.clipboard.writeText( text ).then( () => {
			const orig = btn.textContent;
			btn.textContent = L10N.copied || 'Copied!';
			setTimeout( () => { btn.textContent = orig; }, 1500 );
		} );
	}

	// Application Password — copy 
	window.wpcodexCopyPassword = ( btn ) => {
		navigator.clipboard.writeText( passwordValue ).then( () => {
			const orig = btn.textContent;
			btn.textContent = L10N.copied || 'Copied!';
			setTimeout( () => { btn.textContent = orig; }, 1500 );
		} );
	};

	// Application Password — generate (AJAX)
	window.wpcodexGeneratePassword = ( btn ) => {
		const nameWrap  = document.getElementById( 'wpcodex-pw-name-wrap' );
		const nameInput = document.getElementById( 'wpcodex-pw-name' );
		const isSecond  = nameWrap && nameWrap.style.display !== 'none';
		const name      = nameInput?.value.trim() || '';
		const spinner   = document.getElementById( 'wpcodex-pw-spinner' );

		// Second+ time: name is required.
		if ( isSecond && name === '' ) {
			nameInput?.focus();
			alert( L10N.errorNameRequired || 'Please enter a name for this password.' );
			return;
		}
		btn.disabled = true;
		if ( spinner ) spinner.style.display = 'inline-block';

		const fd = new FormData();
		fd.append( 'action', AJAX_GENERATE );
		fd.append( 'nonce',  AJAX_NONCE );
		fd.append( 'name',   name );

		fetch( AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( r => r.json() )
			.then( data => {
				btn.disabled = false;
				if ( spinner ) spinner.style.display = 'none';
				if ( ! data.success ) { alert( data.data || L10N.errorGenerate ); return; }

				passwordValue       = data.data.password;
				passwordPlaceholder = false;

				const reveal = document.getElementById( 'wpcodex-pw-reveal' );
				const pwEl   = document.getElementById( 'wpcodex-pw-value' );
				if ( reveal ) reveal.style.display = '';
				if ( pwEl )   pwEl.textContent = passwordValue;

				btn.textContent = L10N.generateAnother || 'Generate another application password';

				// Reveal the name field for subsequent passwords and clear it.
				const nameWrap2 = document.getElementById( 'wpcodex-pw-name-wrap' );
				const nameInput2 = document.getElementById( 'wpcodex-pw-name' );
				if ( nameWrap2 ) nameWrap2.style.display = '';
				if ( nameInput2 ) nameInput2.value = '';

				// Append row to existing table and show it.
				const tbody    = document.getElementById( 'wpcodex-pw-tbody' );
				const existing = document.getElementById( 'wpcodex-pw-existing' );
				if ( tbody ) {
					// Show full name (prefix visible in the table).
					const displayName = data.data.name;
					const tr = document.createElement( 'tr' );
					tr.dataset.uuid = data.data.uuid;
					tr.innerHTML =
						`<td class="wpcodex-pw-table__name">${displayName}</td>` +
						`<td class="wpcodex-pw-table__meta">${data.data.created}</td>` +
						`<td class="wpcodex-pw-table__meta">${L10N.never || 'Never'}</td>` +
						`<td class="wpcodex-pw-table__actions"><button type="button" class="button button-small wpcodex-pw-revoke-btn" onclick="wpcodexRevokePassword('${data.data.uuid}', this)">${L10N.revoke || 'Revoke'}</button></td>`;
					tbody.appendChild( tr );
				}

				// Show the table section and update count badge.
				if ( existing ) {
					existing.style.display = '';
					const countEl = document.getElementById( 'wpcodex-pw-count' );
					if ( countEl ) {
						const tbody2 = document.getElementById( 'wpcodex-pw-tbody' );
						countEl.textContent = `(${tbody2 ? tbody2.rows.length : 1})`;
					}
				}

				// Unlock Step 3 — password exists now.
				unlockStep( 'wpcodex-step-3' );

				renderAll();
			} )
			.catch( () => {
				btn.disabled = false;
				if ( spinner ) spinner.style.display = 'none';
				alert( L10N.errorNetwork );
			} );
	};

	// Application Password — revoke (AJAX)
	window.wpcodexRevokePassword = ( uuid, btn ) => {
		if ( ! confirm( L10N.revokeConfirm ) ) return;

		const fd = new FormData();
		fd.append( 'action', AJAX_REVOKE );
		fd.append( 'nonce',  REVOKE_NONCE );
		fd.append( 'uuid',   uuid );

		fetch( AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( r => r.json() )
			.then( data => {
				if ( ! data.success ) { alert( data.data || 'Error.' ); return; }
				document.querySelector( `[data-uuid="${uuid}"]` )?.remove();
				// Update count badge; hide section if no rows remain.
				const tbody    = document.getElementById( 'wpcodex-pw-tbody' );
				const existing = document.getElementById( 'wpcodex-pw-existing' );
				if ( tbody ) {
					const countEl = document.getElementById( 'wpcodex-pw-count' );
					if ( countEl ) countEl.textContent = `(${tbody.rows.length})`;
					if ( existing && tbody.rows.length === 0 ) {
						existing.style.display = 'none';
					}
				}
			} );
	};

	// Init 
	renderAll();
} );
