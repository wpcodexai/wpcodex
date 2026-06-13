/**
 * Copy-to-clipboard for the Connect page prompt textarea.
 *
 * @file  src/admin/components/copy-button.js
 * @since 1.0.0
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const btn = document.getElementById( 'wpcodex-copy-prompt' );
	if ( ! btn ) return;

	const targetId = btn.getAttribute( 'data-target' ) ?? '';
	const textarea = document.getElementById( targetId );
	const originalText = btn.textContent ?? '';

	btn.addEventListener( 'click', async () => {
		if ( ! textarea ) return;

		try {
			if ( navigator.clipboard ) {
				await navigator.clipboard.writeText( textarea.value );
			} else {
				textarea.select();
				document.execCommand( 'copy' );
			}
			btn.textContent = window.wpcodexData?.i18n?.saved ?? 'Copied!';
			btn.disabled = true;
			setTimeout( () => {
				btn.textContent = originalText;
				btn.disabled = false;
			}, 2000 );
		} catch {
			btn.textContent = window.wpcodexData?.i18n?.error ?? 'Error';
			setTimeout( () => {
				btn.textContent = originalText;
			}, 2000 );
		}
	} );
} );
