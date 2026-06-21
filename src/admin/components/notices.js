/**
 * Dismissible admin notices.
 *
 * @file  src/admin/components/notices.js
 * @since 1.0.0
 *
 * Delegates to WordPress's built-in notice dismissal; this just handles
 * any custom .allyworker-notice elements that aren't standard .notice divs.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	document
		.querySelectorAll( '.allyworker-notice .notice-dismiss' )
		.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const notice = btn.closest( '.allyworker-notice' );
				if ( notice ) {
					notice.style.transition = 'opacity .2s';
					notice.style.opacity = '0';
					setTimeout( () => notice.remove(), 200 );
				}
			} );
		} );
} );
