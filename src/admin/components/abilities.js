/**
 * Abilities Hub — enhance toggle experience.
 *
 * @file  src/admin/components/abilities.js
 * @since 1.0.0
 *
 * The checkboxes already submit a form via onchange. This module adds
 * optimistic UI (immediate card class swap) and a brief loading state.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const hub = document.getElementById( 'wpcodex-abilities-settings' );
	if ( ! hub ) return;

	hub.querySelectorAll( '.wpcodex-toggle input[type="checkbox"]' ).forEach( ( checkbox ) => {
		checkbox.addEventListener( 'change', () => {
			const card = checkbox.closest( '.wpcodex-ability-card' );
			if ( ! card ) return;

			const isNowEnabled = checkbox.checked;

			// Optimistic UI — swap class immediately before form submits.
			card.classList.toggle( 'is-enabled',  isNowEnabled );
			card.classList.toggle( 'is-disabled', ! isNowEnabled );

			const label = card.querySelector( '.wpcodex-toggle__label' );
			if ( label ) {
				label.textContent = isNowEnabled
					? ( window.wpcodexData?.i18n?.enabled  ?? 'Enabled' )
					: ( window.wpcodexData?.i18n?.disabled ?? 'Disabled' );
			}
		} );
	} );
} );
