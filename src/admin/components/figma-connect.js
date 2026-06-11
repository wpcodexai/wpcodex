/**
 * Figma Integration — Integrations page JS.
 *
 * Handles:
 * - Official Figma MCP: client tab switching, config render, copy URL + snippet
 * - WPCodex Figma Abilities: enable/disable toggle, PAT connect modal, disconnect
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const cfg = window.wpcodexFigma;
	if ( ! cfg ) return; // Not on the Integrations page.

	// ── Helpers ───────────────────────────────────────────────────────────────

	function ajax( action, nonce, data ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		for ( const [ key, val ] of Object.entries( data ) ) {
			body.append( key, val );
		}
		return fetch( cfg.ajaxUrl, { method: 'POST', body } ).then( r => r.json() );
	}

	function copyText( text, btn ) {
		navigator.clipboard.writeText( text ).then( () => {
			const orig = btn.textContent;
			btn.textContent = cfg.l10n.copied;
			setTimeout( () => { btn.textContent = orig; }, 1800 );
		} );
	}

	function escHtml( str ) {
		return str
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// ── Official Figma MCP section ────────────────────────────────────────────

	const configCode  = document.getElementById( 'wpcodex-figma-config-code' );
	const configHint  = document.getElementById( 'wpcodex-figma-config-hint' );
	const configPaths = document.getElementById( 'wpcodex-figma-config-paths' );
	const copyConfig  = document.getElementById( 'wpcodex-figma-copy-config' );
	const clientTabs  = document.getElementById( 'wpcodex-figma-client-tabs' );

	let activeClient = null;

	function renderClient( slug ) {
		const client = cfg.clients[ slug ];
		if ( ! client || ! configCode ) return;

		activeClient = slug;
		configCode.textContent = client.json;

		if ( configHint ) {
			configHint.textContent = client.hint ?? '';
		}

		if ( configPaths && Array.isArray( client.paths ) && client.paths.length ) {
			configPaths.style.display = '';
			configPaths.innerHTML = '<ul>'
				+ client.paths.map( p => `<li><code>${ escHtml( p ) }</code></li>` ).join( '' )
				+ '</ul>';
		} else if ( configPaths ) {
			configPaths.style.display = 'none';
		}
	}

	// Tab clicks.
	if ( clientTabs ) {
		clientTabs.querySelectorAll( '.wpcodex-client-tab' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				clientTabs.querySelectorAll( '.wpcodex-client-tab' )
					.forEach( t => t.classList.remove( 'is-active' ) );
				btn.classList.add( 'is-active' );
				renderClient( btn.dataset.client );
			} );
		} );

		// Render first tab on load.
		const firstTab = clientTabs.querySelector( '.wpcodex-client-tab' );
		if ( firstTab ) renderClient( firstTab.dataset.client );
	}

	// Copy config snippet.
	if ( copyConfig ) {
		copyConfig.addEventListener( 'click', () => {
			if ( activeClient && cfg.clients[ activeClient ] ) {
				copyText( cfg.clients[ activeClient ].json, copyConfig );
			}
		} );
	}

	// Copy URL button.
	document.querySelectorAll( '.wpcodex-figma-copy-url-btn' ).forEach( btn => {
		btn.addEventListener( 'click', () => copyText( btn.dataset.copy, btn ) );
	} );

	// ── WPCodex Figma Abilities — Enable/Disable toggle ───────────────────────

	const toggle = document.getElementById( 'wpcodex-figma-enabled' );
	const card   = document.getElementById( 'wpcodex-figma-card' );
	const body   = document.getElementById( 'wpcodex-figma-body' );

	if ( toggle ) {
		toggle.addEventListener( 'change', () => {
			const enabled = toggle.checked;
			const label   = toggle.closest( '.wpcodex-toggle' )?.querySelector( '.wpcodex-toggle__label' );

			ajax( cfg.ajaxToggle, cfg.toggleNonce, { enabled: enabled ? '1' : '0' } )
				.then( res => {
					if ( ! res.success ) {
						toggle.checked = ! enabled;
						return;
					}
					if ( label ) label.textContent = enabled ? cfg.l10n.enabled : cfg.l10n.disabled;
					if ( card )  card.classList.toggle( 'is-enabled', enabled );
					if ( body )  body.style.display = enabled ? '' : 'none';
				} )
				.catch( () => { toggle.checked = ! enabled; } );
		} );
	}

	// ── PAT connect modal ─────────────────────────────────────────────────────

	const modal        = document.getElementById( 'wpcodex-figma-modal' );
	const backdrop     = document.getElementById( 'wpcodex-figma-modal-backdrop' );
	const modalClose   = document.getElementById( 'wpcodex-figma-modal-close' );
	const modalCancel  = document.getElementById( 'wpcodex-figma-modal-cancel' );
	const modalSave    = document.getElementById( 'wpcodex-figma-modal-save' );
	const modalSpinner = document.getElementById( 'wpcodex-figma-modal-spinner' );
	const tokenInput   = document.getElementById( 'wpcodex-figma-token-input' );
	const modalError   = document.getElementById( 'wpcodex-figma-modal-error' );
	const modalErrorTxt = document.getElementById( 'wpcodex-figma-modal-error-text' );

	function openModal() {
		if ( tokenInput ) tokenInput.value = '';
		clearModalError();
		if ( modal ) modal.style.display = '';
		tokenInput?.focus();
		document.addEventListener( 'keydown', onEsc );
	}

	function closeModal() {
		if ( modal ) modal.style.display = 'none';
		document.removeEventListener( 'keydown', onEsc );
	}

	function onEsc( e ) {
		if ( e.key === 'Escape' ) closeModal();
	}

	function setModalError( msg ) {
		if ( modalErrorTxt ) modalErrorTxt.textContent = msg;
		if ( modalError )    modalError.style.display = '';
	}

	function clearModalError() {
		if ( modalError )    modalError.style.display = 'none';
		if ( modalErrorTxt ) modalErrorTxt.textContent = '';
	}

	function bindConnectBtn() {
		const btn = document.getElementById( 'wpcodex-figma-connect-btn' );
		if ( btn ) btn.addEventListener( 'click', openModal );
	}
	bindConnectBtn();

	if ( modalClose )  modalClose.addEventListener( 'click', closeModal );
	if ( modalCancel ) modalCancel.addEventListener( 'click', closeModal );
	if ( backdrop )    backdrop.addEventListener( 'click', closeModal );

	if ( tokenInput ) {
		tokenInput.addEventListener( 'keydown', e => {
			if ( e.key === 'Enter' ) modalSave?.click();
		} );
	}

	if ( modalSave ) {
		modalSave.addEventListener( 'click', () => {
			const token = ( tokenInput?.value ?? '' ).trim();
			if ( ! token ) { setModalError( cfg.l10n.tokenRequired ); return; }

			clearModalError();
			modalSave.disabled = true;
			if ( modalSpinner ) modalSpinner.style.display = 'inline-block';
			modalSave.textContent = cfg.l10n.verifying;

			ajax( cfg.ajaxConnect, cfg.connectNonce, { token } )
				.then( res => {
					if ( res.success ) {
						closeModal();
						renderPATStatus( true, res.data?.handle ?? '', res.data?.email ?? '' );
					} else {
						setModalError( res.data ?? cfg.l10n.error );
					}
				} )
				.catch( () => setModalError( cfg.l10n.error ) )
				.finally( () => {
					modalSave.disabled = false;
					if ( modalSpinner ) modalSpinner.style.display = 'none';
					modalSave.textContent = 'Verify & Save';
				} );
		} );
	}

	// ── PAT status render ─────────────────────────────────────────────────────

	const statusWrap = document.getElementById( 'wpcodex-figma-status' );

	function renderPATStatus( connected, handle, email ) {
		if ( ! statusWrap ) return;

		if ( connected ) {
			let nameHtml = '';
			if ( handle ) nameHtml += ` &mdash; ${ escHtml( handle ) }`;
			if ( email )  nameHtml += ` <span class="wpcodex-figma-status__email">(${ escHtml( email ) })</span>`;

			statusWrap.className = 'wpcodex-figma-status wpcodex-figma-status--connected';
			statusWrap.innerHTML =
				'<span class="wpcodex-figma-status__dot"></span>'
				+ `<div class="wpcodex-figma-status__text"><strong>${ cfg.l10n.connected }</strong>${ nameHtml }</div>`
				+ `<button type="button" class="button button-secondary" id="wpcodex-figma-disconnect">${ cfg.l10n.disconnect }</button>`;
			bindDisconnect();
		} else {
			statusWrap.className = 'wpcodex-figma-status wpcodex-figma-status--disconnected';
			statusWrap.innerHTML =
				'<span class="wpcodex-figma-status__dot"></span>'
				+ `<div class="wpcodex-figma-status__text">${ cfg.l10n.notConnected }</div>`
				+ `<button type="button" class="button button-primary" id="wpcodex-figma-connect-btn">${ cfg.l10n.connect }</button>`;
			bindConnectBtn();
		}
	}

	// ── Disconnect ────────────────────────────────────────────────────────────

	function bindDisconnect() {
		const btn = document.getElementById( 'wpcodex-figma-disconnect' );
		if ( ! btn ) return;
		btn.addEventListener( 'click', () => {
			if ( ! window.confirm( cfg.l10n.disconnectConfirm ) ) return;
			btn.disabled = true;
			ajax( cfg.ajaxDisconnect, cfg.disconnectNonce, {} )
				.then( res => {
					if ( res.success ) renderPATStatus( false, '', '' );
					else btn.disabled = false;
				} )
				.catch( () => { btn.disabled = false; } );
		} );
	}
	bindDisconnect();
} );
