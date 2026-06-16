/* WPWorker — Shared JS */
(function () {
	'use strict';

	/* ── Mobile nav toggle ── */
	const hamburger = document.getElementById('hamburger');
	const mobileNav = document.getElementById('mobile-nav');
	if (hamburger && mobileNav) {
		hamburger.addEventListener('click', () => {
			mobileNav.classList.toggle('open');
			hamburger.setAttribute('aria-expanded', mobileNav.classList.contains('open'));
		});
	}

	/* ── Active nav link ── */
	const page = location.pathname.split('/').pop() || 'index.html';
	document.querySelectorAll('.nav-links a, .mobile-nav a').forEach(a => {
		if (a.getAttribute('href') === page) a.classList.add('active');
	});

	/* ── FAQ accordion ── */
	document.querySelectorAll('.faq-item').forEach(item => {
		item.addEventListener('click', () => {
			const isOpen = item.classList.contains('open');
			document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
			if (!isOpen) item.classList.add('open');
		});
	});

	/* ── Scroll-reveal ── */
	if ('IntersectionObserver' in window) {
		const style = document.createElement('style');
		style.textContent = '.reveal{opacity:0;transform:translateY(28px);transition:opacity .55s ease,transform .55s ease}.reveal.visible{opacity:1;transform:none}';
		document.head.appendChild(style);

		const io = new IntersectionObserver(entries => {
			entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
		}, { threshold: 0.12 });

		document.querySelectorAll('.card, .step, .pricing-card, .news-card, .video-card, .support-card').forEach(el => {
			el.classList.add('reveal'); io.observe(el);
		});
	}
})();
