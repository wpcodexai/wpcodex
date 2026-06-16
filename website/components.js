/* WPWorker — Shared nav & footer injected into every page */
(function () {
	const page = location.pathname.split('/').pop() || 'index.html';

	// Logo SVG (matches docs/logo.svg — light theme)
	const LOGO_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 120" height="34" style="display:block;"><g transform="translate(15,10)"><polygon points="50,5 90,28 90,72 50,95 10,72 10,28" fill="none" stroke="#3858e9" stroke-width="7" stroke-linejoin="round"/><path d="M35,35 L45,45 L35,55" fill="none" stroke="#3858e9" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/><line x1="55" y1="55" x2="70" y2="55" stroke="#1e1e1e" stroke-width="5" stroke-linecap="round"/><line x1="35" y1="68" x2="60" y2="68" stroke="#1e1e1e" stroke-width="4" stroke-linecap="round"/></g><g transform="translate(130,75)"><text font-family="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif" font-size="50" font-weight="800" fill="#1e1e1e">WP<tspan fill="#3858e9" font-weight="300">Worker</tspan></text></g></svg>`;
	const LOGO_FOOTER_SVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 120" height="34" style="display:block;"><g transform="translate(15,10)"><polygon points="50,5 90,28 90,72 50,95 10,72 10,28" fill="none" stroke="#7b90ff" stroke-width="7" stroke-linejoin="round"/><path d="M35,35 L45,45 L35,55" fill="none" stroke="#7b90ff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/><line x1="55" y1="55" x2="70" y2="55" stroke="rgba(240,237,232,0.75)" stroke-width="5" stroke-linecap="round"/><line x1="35" y1="68" x2="60" y2="68" stroke="rgba(240,237,232,0.75)" stroke-width="4" stroke-linecap="round"/></g><g transform="translate(130,75)"><text font-family="-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif" font-size="50" font-weight="800" fill="#f0ede8">WP<tspan fill="#7b90ff" font-weight="300">Worker</tspan></text></g></svg>`;

	const NAV_LINKS = [
		['index.html', 'Home'],
		['tools.html', 'Tools'],
		['how-it-works.html', 'How it works'],
		['pro.html', 'Pro'],
		['pricing.html', 'Pricing'],
		['security.html', 'Security'],
		['video.html', 'Videos'],
	];

	function navLink(href, label) {
		return `<li><a href="${href}"${href === page ? ' class="active"' : ''}>${label}</a></li>`;
	}
	function mobileLink(href, label) {
		return `<a href="${href}">${label}</a>`;
	}

	const navHTML = `
<nav class="site-nav">
  <div class="container">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo" style="line-height:0;">${LOGO_SVG}</a>
      <ul class="nav-links">${NAV_LINKS.map(([h, l]) => navLink(h, l)).join('')}</ul>
      <div class="nav-actions">
        <a href="docs.html" class="btn btn-outline btn-sm">Docs</a>
        <a href="download.html" class="btn btn-primary btn-sm">Download</a>
      </div>
      <button class="nav-hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
  <div class="mobile-nav" id="mobile-nav">
    ${[...NAV_LINKS, ['download.html', 'Download'], ['docs.html', 'Docs'], ['support.html', 'Support']].map(([h, l]) => mobileLink(h, l)).join('')}
  </div>
</nav>`;

	const FOOTER_HTML = `
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="index.html" class="nav-logo" style="line-height:0;">${LOGO_FOOTER_SVG}</a>
        <p>Give AI agents unrestricted control over WordPress via the MCP protocol.</p>
      </div>
      <div class="footer-col">
        <h5>Resources</h5>
        <a href="docs.html">Docs</a><a href="quickstart.html">Quick Start</a>
        <a href="news.html">News</a><a href="compare.html">Compare</a>
        <a href="changelog.html">Changelog</a><a href="support.html">Support</a>
        <a href="contact.html">Contact</a>
        <a href="https://github.com/wpworkerai/wpworker" target="_blank">GitHub</a>
      </div>
      <div class="footer-col">
        <h5>Product</h5>
        <a href="tools.html">Tools</a><a href="how-it-works.html">How it works</a>
        <a href="pro.html">Pro</a><a href="pricing.html">Pricing</a>
        <a href="security.html">Security</a><a href="video.html">Videos</a>
        <a href="download.html">Download</a>
      </div>
      <div class="footer-col">
        <h5>Legal</h5>
        <a href="privacy-policy.html">Privacy Policy</a>
        <a href="cookie-policy.html">Cookie Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 WPWorker. All rights reserved.</span>
      <div class="footer-socials">
        <a href="https://x.com/wpworker" target="_blank" title="X">
          <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.258 5.63 5.906-5.63Zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
        <a href="https://facebook.com/wpworker" target="_blank" title="Facebook">
          <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.793-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.929-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
        </a>
        <a href="https://github.com/wpworkerai/wpworker" target="_blank" title="GitHub">
          <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844a9.59 9.59 0 0 1 2.504.337c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2z"/></svg>
        </a>
      </div>
    </div>
  </div>
</footer>`;

	// Inject nav before first element in body
	document.body.insertAdjacentHTML('afterbegin', navHTML);
	// Inject footer at end of body
	document.body.insertAdjacentHTML('beforeend', FOOTER_HTML);

	// Mobile nav toggle
	const hamburger = document.getElementById('hamburger');
	const mobileNav = document.getElementById('mobile-nav');
	if (hamburger && mobileNav) {
		hamburger.addEventListener('click', () => {
			mobileNav.classList.toggle('open');
			hamburger.setAttribute('aria-expanded', mobileNav.classList.contains('open'));
		});
	}

	// FAQ accordion
	document.querySelectorAll('.faq-item').forEach(item => {
		item.addEventListener('click', () => {
			const isOpen = item.classList.contains('open');
			document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
			if (!isOpen) item.classList.add('open');
		});
	});

	// Scroll-reveal
	if ('IntersectionObserver' in window) {
		const style = document.createElement('style');
		style.textContent = '.reveal{opacity:0;transform:translateY(28px);transition:opacity .55s ease,transform .55s ease}.reveal.visible{opacity:1;transform:none}';
		document.head.appendChild(style);
		const io = new IntersectionObserver(entries => {
			entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
		}, { threshold: 0.12 });
		document.querySelectorAll('.card,.step,.pricing-card,.news-card,.video-card,.support-card,.tool-card').forEach(el => {
			el.classList.add('reveal'); io.observe(el);
		});
	}
})();
