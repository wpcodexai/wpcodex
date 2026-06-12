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

document.addEventListener( 'DOMContentLoaded', () => {
	// Auto-grow textarea 
	const textarea = document.querySelector( '.wpcodex-skill-editor' );
	if ( textarea ) {
		const autoGrow = () => {
			textarea.style.height = 'auto';
			textarea.style.height = textarea.scrollHeight + 'px';
		};
		textarea.addEventListener( 'input', autoGrow );
		autoGrow(); // Run once on load.
	}

	// Slug validation 
	const nameInput = document.getElementById( 'skill_name' );
	const form      = nameInput?.closest( 'form' );

	if ( nameInput && form && ! nameInput.readOnly ) {
		// Auto-slugify as user types.
		nameInput.addEventListener( 'input', () => {
			nameInput.value = nameInput.value
				.toLowerCase()
				.replace( /[^a-z0-9-]/g, '-' )
				.replace( /-{2,}/g, '-' );
		} );

		form.addEventListener( 'submit', ( e ) => {
			const val = nameInput.value.trim();
			if ( ! /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test( val ) ) {
				e.preventDefault();
				nameInput.focus();
				nameInput.setCustomValidity(
					'Skill name must be lowercase and hyphen-separated (e.g. my-skill-name).'
				);
				nameInput.reportValidity();
			} else {
				nameInput.setCustomValidity( '' );
			}
		} );
	}
} );
