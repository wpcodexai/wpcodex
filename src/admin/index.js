/**
 * WPCodex Admin — entry point.
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
import './../scss/admin.scss';
import './components/copy-button.js';
import './components/notices.js';
import './components/abilities.js';
import './components/skills.js';
import './components/configuration.js';
