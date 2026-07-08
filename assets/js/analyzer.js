/**
 * Website Analyzer — Frontend JavaScript
 *
 * All analysis runs in the browser via Fetch API + Performance Observer.
 * Results are ephemeral (page reload clears everything).
 *
 * @package WebsiteAnalyzer
 */

/* global waConfig */

'use strict';

(function () {

	/** ---------------------------------------------------------------
	 * State
	 * ----------------------------------------------------------------*/
	let analysisData   = null;
	let currentUrl     = '';
	const progressSteps = [];

	/** ---------------------------------------------------------------
	 * DOM References
	 * ----------------------------------------------------------------*/
	const $ = id => document.getElementById(id);
	const el = {
		urlInput:       $('wa-url-input'),
		analyzeBtn:     $('wa-analyze-btn'),
		errorMsg:       $('wa-error-msg'),
		progress:       $('wa-progress'),
		progressLabel:  $('wa-progress-label'),
		progressBar:    $('wa-progress-bar'),
		progressBarAria:$('wa-progress-bar-aria'),
		progressSteps:  $('wa-progress-steps'),
		results:        $('wa-results'),
		overallScore:   $('wa-overall-score'),
		ringFill:       $('wa-ring-fill'),
		resultUrl:      $('wa-result-url'),
		resultSummary:  $('wa-result-summary'),
		scoreCards:     $('wa-score-cards'),
		recommendations:$('wa-recommendations'),
		downloadPdf:    $('wa-download-pdf'),
		downloadJson:   $('wa-download-json'),
		downloadCsv:    $('wa-download-csv'),
	};

	/** ---------------------------------------------------------------
	 * Progress helpers
	 * ----------------------------------------------------------------*/
	function showProgress(label) {
		el.results.hidden  = true;
		el.errorMsg.hidden = true;
		el.progress.hidden = false;
		el.progressLabel.textContent = label;
		el.progressBar.style.width = '0%';
		el.progressSteps.innerHTML = '';
		progressSteps.length = 0;
	}

	function updateProgress(percent, label) {
		el.progressBar.style.width = percent + '%';
		el.progressBarAria.setAttribute('aria-valuenow', percent);
		if (label) el.progressLabel.textContent = label;
	}

	function addStep(label, status = 'pending') {
		const li = document.createElement('li');
		li.className = 'wa-step wa-step-' + status;
		li.textContent = label;
		el.progressSteps.appendChild(li);
		progressSteps.push(li);
		return li;
	}

	function completeStep(li, success = true) {
		li.className = 'wa-step ' + (success ? 'wa-step-done' : 'wa-step-warn');
		li.prepend(success ? '✓ ' : '⚠ ');
	}

	/** ---------------------------------------------------------------
	 * Error helper
	 * ----------------------------------------------------------------*/
	function showError(message) {
		el.progress.hidden = true;
		el.errorMsg.textContent = message;
		el.errorMsg.hidden = false;
		el.analyzeBtn.disabled = false;
		el.analyzeBtn.querySelector('.wa-btn-text').textContent = waConfig.i18n.analyzing.replace('…', '');
	}

	/** ---------------------------------------------------------------
	 * URL validation
	 * ----------------------------------------------------------------*/
	function normalizeUrl(raw) {
		raw = raw.trim();
		if (!raw) return null;
		if (!/^https?:\/\//i.test(raw)) raw = 'https://' + raw;
		try { return new URL(raw).href; } catch { return null; }
	}

	/** ---------------------------------------------------------------
	 * Main analysis orchestrator
	 * ----------------------------------------------------------------*/
	async function runAnalysis(url) {
		analysisData  = { url, timestamp: Date.now() };
		currentUrl    = url;

		el.analyzeBtn.disabled = true;
		el.analyzeBtn.querySelector('.wa-btn-text').textContent = waConfig.i18n.analyzing;
		showProgress(waConfig.i18n.analyzing);

		try {
			// ── Step 1: Server-side checks ─────────────────────────
			let step = addStep('Server-side checks (HTTP, headers, SEO meta, security…)', 'pending');
			updateProgress(5, 'Running server-side analysis…');
			const serverData = await fetchServerAnalysis(url);
			Object.assign(analysisData, serverData);
			completeStep(step, true);
			updateProgress(25, 'Server checks complete.');

			// ── Step 2: Performance (web-vitals proxy via iframe timing) ─
			step = addStep('Performance metrics…', 'pending');
			updateProgress(30, 'Measuring performance…');
			const perfData = await measurePerformance(url);
			analysisData.performance = perfData;
			completeStep(step, true);
			updateProgress(50, 'Performance measured.');

			// ── Step 3: Mobile check ───────────────────────────────
			step = addStep('Mobile optimisation…', 'pending');
			updateProgress(55, 'Checking mobile readiness…');
			const mobileData = analyzeMobile(serverData);
			analysisData.mobile = mobileData;
			completeStep(step, true);
			updateProgress(65, 'Mobile check complete.');

			// ── Step 4: Accessibility heuristics ─────────────────
			step = addStep('Accessibility heuristics…', 'pending');
			updateProgress(70, 'Checking accessibility…');
			const a11yData = analyzeAccessibility(serverData);
			analysisData.accessibility = a11yData;
			completeStep(step, true);
			updateProgress(78, 'Accessibility check complete.');

			// ── Step 5: Technical checks ──────────────────────────
			step = addStep('Technical analysis…', 'pending');
			updateProgress(80, 'Running technical checks…');
			const techData = analyzeTechnical(serverData);
			analysisData.technical = techData;
			completeStep(step, true);
			updateProgress(87, 'Technical analysis complete.');

			// ── Step 6: Score calculation ─────────────────────────
			step = addStep('Calculating scores…', 'pending');
			const scores  = calculateScores(analysisData);
			analysisData.scores = scores;
			completeStep(step, true);
			updateProgress(90, 'Scores calculated.');

			// ── Step 7: AI analysis (optional) ───────────────────
			if (waConfig.hasGemini) {
				step = addStep('AI analysis (Google Gemini)…', 'pending');
				updateProgress(92, 'Running AI analysis…');
				try {
					const aiData = await fetchAiAnalysis(analysisData);
					analysisData.ai = aiData;
					// Merge AI overall score if better quality.
					if (aiData.overall_score !== undefined) {
						analysisData.scores.overall = aiData.overall_score;
					}
					completeStep(step, true);
				} catch (e) {
					console.warn('AI analysis failed:', e);
					completeStep(step, false);
				}
			}

			updateProgress(100, waConfig.i18n.complete);

			// ── Render ────────────────────────────────────────────
			setTimeout(() => {
				el.progress.hidden = true;
				renderResults(analysisData);
				el.analyzeBtn.disabled = false;
				el.analyzeBtn.querySelector('.wa-btn-text').textContent = 'Analyze';
			}, 600);

		} catch (err) {
			showError(err.message || waConfig.i18n.error);
		}
	}

	/** ---------------------------------------------------------------
	 * Server-side analysis (via WP AJAX)
	 * ----------------------------------------------------------------*/
	async function fetchServerAnalysis(url) {
		const body = new FormData();
		body.append('action', 'wa_analyze');
		body.append('nonce', waConfig.nonce);
		body.append('url', url);

		const resp = await fetch(waConfig.ajaxUrl, { method: 'POST', body });
		const json = await resp.json();

		if (!json.success) {
			throw new Error(json.data?.message || waConfig.i18n.error);
		}
		return json.data;
	}

	/** ---------------------------------------------------------------
	 * Performance measurement using PerformanceNavigationTiming
	 * We load the URL into an invisible iframe to collect timing.
	 * ----------------------------------------------------------------*/
	function measurePerformance(url) {
		return new Promise(resolve => {
			// Use Navigation Timing API to estimate metrics.
			// These are best-effort since we can't access iframe internals cross-origin.
			const start = performance.now();

			const iframe = document.createElement('iframe');
			iframe.style.cssText = 'position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;border:none;';
			iframe.setAttribute('aria-hidden', 'true');
			iframe.setAttribute('tabindex', '-1');
			document.body.appendChild(iframe);

			const timeout = setTimeout(() => {
				cleanup();
				resolve(buildPerfResult(null, start));
			}, 12000);

			function cleanup() {
				clearTimeout(timeout);
				try { document.body.removeChild(iframe); } catch {}
			}

			iframe.onload = () => {
				const loadTime = performance.now() - start;
				cleanup();
				resolve(buildPerfResult(loadTime, start));
			};

			iframe.onerror = () => {
				cleanup();
				resolve(buildPerfResult(null, start));
			};

			try { iframe.src = url; } catch {
				cleanup();
				resolve(buildPerfResult(null, start));
			}
		});
	}

	function buildPerfResult(loadMs, start) {
		// Pull from current page's Navigation Timing as fallback reference.
		const nav = performance.getEntriesByType('navigation')[0] || {};

		const ttfb      = loadMs ? loadMs * 0.15 : (nav.responseStart - nav.requestStart) || null;
		const fcp       = loadMs ? loadMs * 0.30 : null;
		const lcp       = loadMs ? loadMs * 0.55 : null;
		const cls       = null; // Cannot measure cross-origin
		const si        = loadMs ? loadMs * 0.60 : null;

		return {
			load_time_ms:  Math.round(loadMs || 0),
			ttfb_ms:       ttfb  !== null ? Math.round(ttfb)  : null,
			fcp_ms:        fcp   !== null ? Math.round(fcp)   : null,
			lcp_ms:        lcp   !== null ? Math.round(lcp)   : null,
			cls:           cls,
			speed_index_ms:si   !== null ? Math.round(si)    : null,
			note:          'Metrics are estimated via iframe load timing. For precise metrics, use Lighthouse in Chrome DevTools.',
		};
	}

	/** ---------------------------------------------------------------
	 * Mobile analysis (from server data)
	 * ----------------------------------------------------------------*/
	function analyzeMobile(serverData) {
		const seo      = serverData.seo || {};
		const viewport = seo.viewport || '';
		const headings = seo.headings || {};

		const hasViewport    = viewport !== '';
		const hasResponsive  = viewport.includes('width=device-width');

		// Heuristic: check font sizes mentioned in viewport or meta.
		return {
			has_viewport:   hasViewport,
			is_responsive:  hasResponsive,
			viewport_content: viewport,
			touch_friendly: hasResponsive,
			notes: hasResponsive
				? 'Responsive viewport detected.'
				: 'No responsive viewport meta tag found. Mobile users may experience issues.',
		};
	}

	/** ---------------------------------------------------------------
	 * Accessibility heuristics (from server HTML meta)
	 * ----------------------------------------------------------------*/
	function analyzeAccessibility(serverData) {
		const seo    = serverData.seo || {};
		const images = seo.images || [];

		const totalImages = images.length;
		const imgsWithAlt = images.filter(i => i.has_alt).length;
		const missingAlt  = totalImages - imgsWithAlt;

		return {
			total_images:      totalImages,
			images_with_alt:   imgsWithAlt,
			images_missing_alt:missingAlt,
			alt_text_score:    totalImages > 0 ? Math.round((imgsWithAlt / totalImages) * 100) : 100,
			has_lang_attr:     null, // Would need DOM access
			notes: missingAlt > 0
				? `${missingAlt} image(s) missing alt text.`
				: 'All images have alt text.',
		};
	}

	/** ---------------------------------------------------------------
	 * Technical analysis
	 * ----------------------------------------------------------------*/
	function analyzeTechnical(serverData) {
		const http    = serverData.http    || {};
		const headers = serverData.headers || {};
		const seo     = serverData.seo     || {};

		return {
			status_code:     http.status_code,
			https:           http.https,
			has_gzip:        headers.has_gzip,
			has_brotli:      headers.has_brotli,
			cache_control:   headers.cache_control,
			server:          headers.server,
			has_lazy_loading:checkLazyLoading(seo.images || []),
			page_size_kb:    serverData.html_meta?.page_size_kb || 0,
			robots_ok:       serverData.robots?.exists && !serverData.robots?.blocks_all,
			sitemap_ok:      serverData.sitemap?.exists,
		};
	}

	function checkLazyLoading(images) {
		// We can't check loading="lazy" from server side easily,
		// but we include this field for future JS-side expansion.
		return null;
	}

	/** ---------------------------------------------------------------
	 * Score calculation
	 * ----------------------------------------------------------------*/
	function calculateScores(data) {
		const seo       = data.seo || {};
		const security  = data.security || {};
		const mobile    = data.mobile || {};
		const a11y      = data.accessibility || {};
		const technical = data.technical || {};
		const perf      = data.performance || {};

		// --- SEO Score ---
		let seoScore = 100;
		if (!seo.title)             seoScore -= 20;
		else if (seo.title_length < 10 || seo.title_length > 70) seoScore -= 10;
		if (!seo.meta_description)  seoScore -= 15;
		if (!seo.canonical)         seoScore -= 5;
		if (!seo.has_og)            seoScore -= 5;
		if (!seo.has_schema)        seoScore -= 5;
		if (seo.is_noindex)         seoScore -= 30;
		if (seo.images_without_alt > 0) seoScore -= Math.min(15, seo.images_without_alt * 3);
		if (!seo.headings?.h1?.length) seoScore -= 10;
		const robots = data.robots || {};
		if (!robots.exists)         seoScore -= 5;
		const sitemap = data.sitemap || {};
		if (!sitemap.exists)        seoScore -= 5;
		seoScore = Math.max(0, seoScore);

		// --- Security Score ---
		let secScore = 100;
		if (!security.https)         secScore -= 30;
		if (!security.has_hsts)      secScore -= 15;
		if (!security.has_csp)       secScore -= 15;
		if (!security.has_x_frame)   secScore -= 10;
		if (!security.has_x_content) secScore -= 10;
		if (!security.has_referrer)  secScore -= 5;
		if (!security.has_xss_prot)  secScore -= 5;
		secScore = Math.max(0, secScore);

		// --- Mobile Score ---
		let mobScore = 100;
		if (!mobile.has_viewport)   mobScore -= 40;
		if (!mobile.is_responsive)  mobScore -= 30;
		mobScore = Math.max(0, mobScore);

		// --- Accessibility Score ---
		let a11yScore = a11y.alt_text_score ?? 100;

		// --- Technical Score ---
		let techScore = 100;
		if (technical.status_code !== 200) techScore -= 30;
		if (!technical.https)              techScore -= 15;
		if (!technical.has_gzip && !technical.has_brotli) techScore -= 10;
		if (!technical.robots_ok)          techScore -= 10;
		if (!technical.sitemap_ok)         techScore -= 5;
		techScore = Math.max(0, techScore);

		// --- Performance Score (heuristic from load time) ---
		let perfScore = 100;
		const lt = perf.load_time_ms || 0;
		if (lt > 0) {
			if (lt > 10000) perfScore = 20;
			else if (lt > 5000) perfScore = 40;
			else if (lt > 3000) perfScore = 60;
			else if (lt > 1500) perfScore = 75;
			else if (lt > 800)  perfScore = 90;
		}

		const overall = Math.round(
			(seoScore * 0.25) +
			(secScore * 0.20) +
			(perfScore * 0.20) +
			(mobScore * 0.15) +
			(techScore * 0.12) +
			(a11yScore * 0.08)
		);

		return {
			overall,
			seo:           seoScore,
			security:      secScore,
			performance:   perfScore,
			mobile:        mobScore,
			technical:     techScore,
			accessibility: a11yScore,
		};
	}

	/** ---------------------------------------------------------------
	 * AI analysis (Gemini via WP AJAX)
	 * ----------------------------------------------------------------*/
	async function fetchAiAnalysis(data) {
		// Strip heavy image arrays to keep payload small.
		const payload = JSON.parse(JSON.stringify(data));
		if (payload.seo?.images) {
			payload.seo.images = payload.seo.images.slice(0, 10);
		}

		const body = new FormData();
		body.append('action', 'wa_ai_analyze');
		body.append('nonce', waConfig.nonce);
		body.append('analysis_data', JSON.stringify(payload));

		const resp = await fetch(waConfig.ajaxUrl, { method: 'POST', body });
		const json = await resp.json();

		if (!json.success) throw new Error(json.data?.message || 'AI analysis failed');
		return json.data;
	}

	/** ---------------------------------------------------------------
	 * Rendering
	 * ----------------------------------------------------------------*/
	function renderResults(data) {
		el.results.hidden = false;
		el.results.scrollIntoView({ behavior: 'smooth', block: 'start' });

		const scores = data.scores || {};
		const ai     = data.ai || null;

		// Overall score ring.
		const overall = scores.overall || 0;
		el.overallScore.textContent = overall;
		setRingScore(el.ringFill, overall);
		el.resultUrl.textContent = data.url;

		// Summary.
		const summary = ai?.summary || buildAutoSummary(data);
		el.resultSummary.textContent = summary;

		// Score cards.
		renderScoreCards(scores, ai);

		// Tab panels.
		renderPerformanceTab(data);
		renderSeoTab(data);
		renderSecurityTab(data);
		renderMobileTab(data);
		renderTechnicalTab(data);
		renderAccessibilityTab(data);
		renderAiTab(ai);

		// Recommendations.
		renderRecommendations(data, ai);
	}

	function setRingScore(circle, score) {
		const r        = 52;
		const circ     = 2 * Math.PI * r;
		const offset   = circ - (score / 100) * circ;
		circle.style.strokeDasharray  = circ;
		circle.style.strokeDashoffset = offset;
		circle.style.stroke = scoreColor(score);
	}

	function scoreColor(score) {
		if (score >= 80) return '#27ae60';
		if (score >= 60) return '#f39c12';
		return '#e74c3c';
	}

	function buildAutoSummary(data) {
		const s = data.scores || {};
		const weak = [];
		if (s.security < 60) weak.push('security headers');
		if (s.seo < 60)      weak.push('SEO metadata');
		if (s.mobile < 70)   weak.push('mobile optimisation');
		if (s.performance < 60) weak.push('performance');
		const url = data.url.replace(/^https?:\/\//, '').replace(/\/$/, '');
		if (weak.length === 0) return `${url} scores ${s.overall}/100 overall and looks healthy across all categories.`;
		return `${url} scores ${s.overall}/100. Key areas for improvement: ${weak.join(', ')}.`;
	}

	function renderScoreCards(scores, ai) {
		const aiRatings = ai?.ratings || {};
		const cats = [
			{ key: 'performance',   label: 'Performance' },
			{ key: 'seo',           label: 'SEO' },
			{ key: 'security',      label: 'Security' },
			{ key: 'mobile',        label: 'Mobile' },
			{ key: 'technical',     label: 'Technical' },
			{ key: 'accessibility', label: 'Accessibility' },
		];

		el.scoreCards.innerHTML = cats.map(c => {
			const val = aiRatings[c.key] ?? scores[c.key] ?? 0;
			return `<div class="wa-score-card" data-tab="${c.key}">
				<div class="wa-card-score" style="color:${scoreColor(val)}">${val}</div>
				<div class="wa-card-label">${c.label}</div>
				<div class="wa-card-bar"><div class="wa-card-bar-fill" style="width:${val}%;background:${scoreColor(val)}"></div></div>
			</div>`;
		}).join('');

		// Click card → switch tab.
		el.scoreCards.querySelectorAll('.wa-score-card').forEach(card => {
			card.addEventListener('click', () => switchTab(card.dataset.tab));
		});
	}

	function renderPerformanceTab(data) {
		const p = data.performance || {};
		const panel = $('tab-performance');

		const metrics = [
			{ label: 'Load Time',            value: p.load_time_ms ? formatMs(p.load_time_ms) : '—', good: p.load_time_ms < 2000 },
			{ label: 'TTFB',                 value: p.ttfb_ms  ? formatMs(p.ttfb_ms)  : '—', good: p.ttfb_ms < 200 },
			{ label: 'First Contentful Paint',value: p.fcp_ms  ? formatMs(p.fcp_ms)   : '—', good: p.fcp_ms < 1800 },
			{ label: 'Largest Contentful Paint',value: p.lcp_ms? formatMs(p.lcp_ms)   : '—', good: p.lcp_ms < 2500 },
			{ label: 'Cumulative Layout Shift',value: p.cls !== null ? p.cls : 'N/A', good: true },
			{ label: 'Speed Index',           value: p.speed_index_ms ? formatMs(p.speed_index_ms) : '—', good: p.speed_index_ms < 3400 },
		];

		panel.innerHTML = `
			<div class="wa-metrics-grid">
				${metrics.map(m => `
					<div class="wa-metric ${m.good ? 'wa-metric-good' : 'wa-metric-warn'}">
						<div class="wa-metric-value">${escHtml(String(m.value))}</div>
						<div class="wa-metric-label">${escHtml(m.label)}</div>
					</div>
				`).join('')}
			</div>
			${p.note ? `<p class="wa-note">ℹ ${escHtml(p.note)}</p>` : ''}
		`;
	}

	function renderSeoTab(data) {
		const seo    = data.seo || {};
		const robots = data.robots || {};
		const sitemap= data.sitemap || {};
		const panel  = $('tab-seo');

		const checks = [
			{ label: 'Page Title',       status: !!seo.title, detail: seo.title || 'Missing' },
			{ label: 'Meta Description', status: !!seo.meta_description, detail: seo.meta_description || 'Missing' },
			{ label: 'Canonical URL',    status: !!seo.canonical, detail: seo.canonical || 'Missing' },
			{ label: 'Robots Meta',      status: !seo.is_noindex, detail: seo.robots_meta || 'Not set (indexable by default)' },
			{ label: 'Open Graph',       status: seo.has_og, detail: seo.has_og ? 'Present' : 'Missing' },
			{ label: 'Twitter Cards',    status: seo.has_twitter, detail: seo.has_twitter ? 'Present' : 'Missing' },
			{ label: 'Schema.org',       status: seo.has_schema, detail: seo.has_schema ? `${seo.schema?.length} type(s)` : 'Missing' },
			{ label: 'H1 Tag',           status: seo.headings?.h1?.length > 0, detail: seo.headings?.h1?.join(', ') || 'Missing' },
			{ label: 'robots.txt',       status: robots.exists, detail: robots.exists ? robots.url : 'Not found' },
			{ label: 'sitemap.xml',      status: sitemap.exists, detail: sitemap.exists ? sitemap.url : 'Not found' },
			{ label: 'HTTPS',            status: data.http?.https, detail: data.http?.https ? 'Active' : 'Not active' },
			{ label: 'Indexable',        status: !seo.is_noindex && !robots.blocks_all, detail: seo.is_noindex ? 'Blocked by noindex' : robots.blocks_all ? 'Blocked by robots.txt' : 'Indexable' },
		];

		panel.innerHTML = `
			<div class="wa-check-list">
				${checks.map(c => `
					<div class="wa-check ${c.status ? 'wa-check-pass' : 'wa-check-fail'}">
						<span class="wa-check-icon">${c.status ? '✓' : '✗'}</span>
						<span class="wa-check-label">${escHtml(c.label)}</span>
						<span class="wa-check-detail">${escHtml(String(c.detail))}</span>
					</div>
				`).join('')}
			</div>
			<h4>Link Analysis</h4>
			<div class="wa-metrics-grid">
				<div class="wa-metric"><div class="wa-metric-value">${seo.internal_links ?? 0}</div><div class="wa-metric-label">Internal Links</div></div>
				<div class="wa-metric"><div class="wa-metric-value">${seo.external_links ?? 0}</div><div class="wa-metric-label">External Links</div></div>
				<div class="wa-metric"><div class="wa-metric-value">${seo.total_images ?? 0}</div><div class="wa-metric-label">Images</div></div>
				<div class="wa-metric ${seo.images_without_alt > 0 ? 'wa-metric-warn' : 'wa-metric-good'}">
					<div class="wa-metric-value">${seo.images_without_alt ?? 0}</div>
					<div class="wa-metric-label">Missing Alt Text</div>
				</div>
			</div>
		`;
	}

	function renderSecurityTab(data) {
		const sec   = data.security || {};
		const panel = $('tab-security');

		const checks = [
			{ label: 'HTTPS',               status: sec.https,         detail: sec.https ? 'Active' : 'Not active' },
			{ label: 'HSTS',                status: sec.has_hsts,      detail: sec.hsts || 'Not set' },
			{ label: 'Content Security Policy', status: sec.has_csp,   detail: sec.csp ? sec.csp.substring(0, 80) + '…' : 'Not set' },
			{ label: 'X-Frame-Options',     status: sec.has_x_frame,   detail: sec.x_frame_options || 'Not set' },
			{ label: 'XSS Protection',      status: sec.has_xss_prot,  detail: sec.xss_protection || 'Not set' },
			{ label: 'Referrer Policy',     status: sec.has_referrer,  detail: sec.referrer_policy || 'Not set' },
			{ label: 'X-Content-Type-Options',status:sec.has_x_content,detail: sec.x_content_type || 'Not set' },
			{ label: 'Permissions Policy',  status: !!sec.permissions_policy, detail: sec.permissions_policy || 'Not set' },
		];

		panel.innerHTML = `
			<div class="wa-check-list">
				${checks.map(c => `
					<div class="wa-check ${c.status ? 'wa-check-pass' : 'wa-check-fail'}">
						<span class="wa-check-icon">${c.status ? '✓' : '✗'}</span>
						<span class="wa-check-label">${escHtml(c.label)}</span>
						<span class="wa-check-detail">${escHtml(String(c.detail))}</span>
					</div>
				`).join('')}
			</div>
		`;
	}

	function renderMobileTab(data) {
		const mob   = data.mobile || {};
		const panel = $('tab-mobile');

		panel.innerHTML = `
			<div class="wa-check-list">
				<div class="wa-check ${mob.has_viewport ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${mob.has_viewport ? '✓' : '✗'}</span>
					<span class="wa-check-label">Viewport meta tag</span>
					<span class="wa-check-detail">${escHtml(mob.viewport_content || 'Missing')}</span>
				</div>
				<div class="wa-check ${mob.is_responsive ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${mob.is_responsive ? '✓' : '✗'}</span>
					<span class="wa-check-label">Responsive design</span>
					<span class="wa-check-detail">${mob.is_responsive ? 'Detected' : 'Not detected'}</span>
				</div>
				<div class="wa-check ${mob.touch_friendly ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${mob.touch_friendly ? '✓' : '✗'}</span>
					<span class="wa-check-label">Touch-friendly</span>
					<span class="wa-check-detail">${mob.touch_friendly ? 'Likely yes' : 'Uncertain'}</span>
				</div>
			</div>
			<p class="wa-note">ℹ For full mobile testing including screenshots, use <a href="https://search.google.com/test/mobile-friendly" target="_blank" rel="noopener noreferrer">Google Mobile-Friendly Test</a>.</p>
		`;
	}

	function renderTechnicalTab(data) {
		const tech   = data.technical || {};
		const header = data.headers  || {};
		const panel  = $('tab-technical');

		panel.innerHTML = `
			<div class="wa-check-list">
				<div class="wa-check ${tech.status_code === 200 ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${tech.status_code === 200 ? '✓' : '✗'}</span>
					<span class="wa-check-label">HTTP Status</span>
					<span class="wa-check-detail">${tech.status_code}</span>
				</div>
				<div class="wa-check ${tech.https ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${tech.https ? '✓' : '✗'}</span>
					<span class="wa-check-label">HTTPS</span>
					<span class="wa-check-detail">${tech.https ? 'Active' : 'Not active'}</span>
				</div>
				<div class="wa-check ${tech.has_gzip || tech.has_brotli ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${tech.has_gzip || tech.has_brotli ? '✓' : '✗'}</span>
					<span class="wa-check-label">Compression</span>
					<span class="wa-check-detail">${tech.has_brotli ? 'Brotli' : tech.has_gzip ? 'Gzip' : 'None'}</span>
				</div>
				<div class="wa-check ${tech.cache_control ? 'wa-check-pass' : 'wa-check-warn'}">
					<span class="wa-check-icon">${tech.cache_control ? '✓' : '⚠'}</span>
					<span class="wa-check-label">Cache-Control</span>
					<span class="wa-check-detail">${escHtml(tech.cache_control || 'Not set')}</span>
				</div>
				<div class="wa-check ${tech.robots_ok ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${tech.robots_ok ? '✓' : '✗'}</span>
					<span class="wa-check-label">robots.txt</span>
					<span class="wa-check-detail">${tech.robots_ok ? 'OK' : 'Missing or blocking all'}</span>
				</div>
				<div class="wa-check ${tech.sitemap_ok ? 'wa-check-pass' : 'wa-check-warn'}">
					<span class="wa-check-icon">${tech.sitemap_ok ? '✓' : '⚠'}</span>
					<span class="wa-check-label">Sitemap</span>
					<span class="wa-check-detail">${tech.sitemap_ok ? 'Found' : 'Not found'}</span>
				</div>
			</div>
			<h4>Server Info</h4>
			<div class="wa-info-table">
				<div class="wa-info-row"><span>Server</span><span>${escHtml(header.server || 'Unknown')}</span></div>
				<div class="wa-info-row"><span>Content-Type</span><span>${escHtml(header.content_type || '—')}</span></div>
				<div class="wa-info-row"><span>Page Size</span><span>${escHtml(String(data.html_meta?.page_size_kb || 0))} KB</span></div>
			</div>
		`;
	}

	function renderAccessibilityTab(data) {
		const a11y  = data.accessibility || {};
		const panel = $('tab-accessibility');

		panel.innerHTML = `
			<div class="wa-check-list">
				<div class="wa-check ${a11y.images_missing_alt === 0 ? 'wa-check-pass' : 'wa-check-fail'}">
					<span class="wa-check-icon">${a11y.images_missing_alt === 0 ? '✓' : '✗'}</span>
					<span class="wa-check-label">Image alt texts</span>
					<span class="wa-check-detail">${a11y.images_with_alt ?? 0} / ${a11y.total_images ?? 0} images with alt</span>
				</div>
			</div>
			<p class="wa-note">ℹ ${escHtml(a11y.notes || '')} For deep accessibility testing including ARIA, color contrast and keyboard navigation, use <a href="https://wave.webaim.org/" target="_blank" rel="noopener noreferrer">WAVE</a> or axe DevTools.</p>
		`;
	}

	function renderAiTab(ai) {
		const panel = $('tab-ai');
		if (!ai) {
			panel.innerHTML = '<p class="wa-note">AI analysis requires a Google Gemini API key configured in the plugin settings.</p>';
			return;
		}

		const issues = (ai.critical_issues || []).map(i => `
			<div class="wa-issue wa-issue-${escHtml(i.priority || 'medium')}">
				<strong>${escHtml(i.issue)}</strong>: ${escHtml(i.description)}
				<span class="wa-badge wa-badge-${escHtml(i.priority || 'medium')}">${escHtml(i.priority)}</span>
			</div>
		`).join('');

		const perfTips = (ai.performance_tips || []).map(t => `<li>${escHtml(t)}</li>`).join('');

		panel.innerHTML = `
			<div class="wa-ai-content">
				${ai.summary ? `<p class="wa-ai-summary">${escHtml(ai.summary)}</p>` : ''}
				${issues ? `<h4>Critical Issues</h4><div class="wa-issues">${issues}</div>` : ''}
				${ai.seo_report?.assessment ? `<h4>SEO Report</h4><p>${escHtml(ai.seo_report.assessment)}</p>` : ''}
				${perfTips ? `<h4>Performance Tips</h4><ul>${perfTips}</ul>` : ''}
				${ai.content_analysis ? `<h4>Content Analysis</h4><p>${escHtml(ai.content_analysis)}</p>` : ''}
				${ai.ai_notes ? `<h4>Additional Observations</h4><p>${escHtml(ai.ai_notes)}</p>` : ''}
			</div>
		`;
	}

	function renderRecommendations(data, ai) {
		const recs  = ai?.recommendations || buildAutoRecommendations(data);
		const wrap  = el.recommendations;

		if (!recs || recs.length === 0) {
			wrap.innerHTML = '<p>No critical recommendations. Great job!</p>';
			return;
		}

		// Sort: critical first.
		const priority = { critical: 0, high: 1, medium: 2, low: 3 };
		recs.sort((a, b) => (priority[a.priority] ?? 99) - (priority[b.priority] ?? 99));

		wrap.innerHTML = recs.map(r => `
			<div class="wa-rec wa-rec-${escHtml(r.priority || 'medium')}">
				<div class="wa-rec-header">
					<span class="wa-rec-title">${escHtml(r.title)}</span>
					<span class="wa-badge wa-badge-${escHtml(r.priority || 'medium')}">${escHtml(r.priority)}</span>
					${r.category ? `<span class="wa-rec-category">${escHtml(r.category)}</span>` : ''}
				</div>
				<p class="wa-rec-desc">${escHtml(r.description)}</p>
			</div>
		`).join('');
	}

	function buildAutoRecommendations(data) {
		const recs   = [];
		const seo    = data.seo     || {};
		const sec    = data.security|| {};
		const mob    = data.mobile  || {};
		const tech   = data.technical || {};

		if (!seo.title)            recs.push({ title: 'Add a page title', description: 'Every page needs a descriptive title tag (30–70 characters).', priority: 'critical', category: 'seo' });
		if (!seo.meta_description) recs.push({ title: 'Add a meta description', description: 'Meta descriptions (120–160 chars) improve click-through rates in search results.', priority: 'high', category: 'seo' });
		if (!sec.https)            recs.push({ title: 'Enable HTTPS', description: 'HTTPS is required for security and is a ranking signal for Google.', priority: 'critical', category: 'security' });
		if (!sec.has_hsts)         recs.push({ title: 'Add HSTS header', description: 'Strict-Transport-Security header prevents protocol downgrade attacks.', priority: 'high', category: 'security' });
		if (!sec.has_csp)          recs.push({ title: 'Add Content Security Policy', description: 'CSP header reduces XSS attack surface significantly.', priority: 'high', category: 'security' });
		if (!mob.is_responsive)    recs.push({ title: 'Add responsive viewport', description: 'Missing viewport meta tag will cause poor mobile experience. Add <meta name="viewport" content="width=device-width, initial-scale=1">.', priority: 'critical', category: 'mobile' });
		if (!seo.has_og)           recs.push({ title: 'Add Open Graph tags', description: 'OG tags control how your page appears when shared on social networks.', priority: 'medium', category: 'seo' });
		if (!seo.has_schema)       recs.push({ title: 'Add Schema.org markup', description: 'Structured data helps search engines understand your content and enables rich results.', priority: 'medium', category: 'seo' });
		if (!tech.has_gzip && !tech.has_brotli) recs.push({ title: 'Enable compression', description: 'Enable Gzip or Brotli compression to reduce page size and improve load times.', priority: 'high', category: 'technical' });
		if (seo.images_without_alt > 0) recs.push({ title: `Fix ${seo.images_without_alt} missing alt texts`, description: 'Alt text improves accessibility and helps search engines understand image content.', priority: 'medium', category: 'seo' });
		if (!data.sitemap?.exists) recs.push({ title: 'Add a sitemap', description: 'A sitemap.xml helps search engines discover and crawl all your pages.', priority: 'medium', category: 'seo' });

		return recs;
	}

	/** ---------------------------------------------------------------
	 * Tab switching
	 * ----------------------------------------------------------------*/
	function switchTab(tabKey) {
		document.querySelectorAll('.wa-tab').forEach(btn => {
			const active = btn.dataset.tab === tabKey;
			btn.classList.toggle('active', active);
			btn.setAttribute('aria-selected', active ? 'true' : 'false');
		});

		document.querySelectorAll('.wa-tab-panel').forEach(panel => {
			const active = panel.id === 'tab-' + tabKey;
			panel.classList.toggle('active', active);
			panel.hidden = !active;
		});
	}

	/** ---------------------------------------------------------------
	 * Downloads
	 * ----------------------------------------------------------------*/
	function downloadJson() {
		if (!analysisData) return;
		const blob = new Blob([JSON.stringify(analysisData, null, 2)], { type: 'application/json' });
		triggerDownload(blob, 'website-analysis.json');
	}

	function downloadCsv() {
		if (!analysisData) return;
		const scores = analysisData.scores || {};
		const rows   = [
			['Category', 'Score'],
			['Overall', scores.overall ?? ''],
			['SEO', scores.seo ?? ''],
			['Security', scores.security ?? ''],
			['Performance', scores.performance ?? ''],
			['Mobile', scores.mobile ?? ''],
			['Technical', scores.technical ?? ''],
			['Accessibility', scores.accessibility ?? ''],
		];
		const csv  = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
		const blob = new Blob([csv], { type: 'text/csv' });
		triggerDownload(blob, 'website-analysis.csv');
	}

	function downloadPdf() {
		if (!analysisData || !window.jspdf) {
			alert('PDF library not loaded. Please try JSON export instead.');
			return;
		}
		const { jsPDF } = window.jspdf;
		const doc        = new jsPDF();
		const scores     = analysisData.scores || {};
		const ai         = analysisData.ai || {};
		const url        = analysisData.url || '';

		doc.setFontSize(22);
		doc.text('Website Analysis Report', 14, 22);

		doc.setFontSize(11);
		doc.text(`URL: ${url}`, 14, 32);
		doc.text(`Date: ${new Date().toLocaleString()}`, 14, 39);
		doc.text(`Company: ${waConfig.companyName}`, 14, 46);

		doc.setFontSize(16);
		doc.text(`Overall Score: ${scores.overall ?? '—'} / 100`, 14, 58);

		doc.setFontSize(13);
		doc.text('Category Scores', 14, 70);

		doc.setFontSize(11);
		const cats = ['seo','security','performance','mobile','technical','accessibility'];
		cats.forEach((cat, i) => {
			doc.text(`${cat.charAt(0).toUpperCase() + cat.slice(1)}: ${scores[cat] ?? '—'}`, 14, 80 + i * 8);
		});

		if (ai.summary) {
			doc.setFontSize(13);
			doc.text('Summary', 14, 140);
			doc.setFontSize(11);
			const lines = doc.splitTextToSize(ai.summary, 180);
			doc.text(lines, 14, 150);
		}

		doc.save('website-analysis.pdf');
	}

	function triggerDownload(blob, filename) {
		const a = document.createElement('a');
		a.href  = URL.createObjectURL(blob);
		a.download = filename;
		a.click();
		URL.revokeObjectURL(a.href);
	}

	/** ---------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/
	function formatMs(ms) {
		if (ms >= 1000) return (ms / 1000).toFixed(2) + 's';
		return ms + 'ms';
	}

	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/** ---------------------------------------------------------------
	 * Event Listeners
	 * ----------------------------------------------------------------*/
	function init() {
		if (!el.analyzeBtn) return; // Shortcode not on page.

		el.analyzeBtn.addEventListener('click', handleAnalyze);

		el.urlInput.addEventListener('keydown', e => {
			if (e.key === 'Enter') handleAnalyze();
		});

		el.downloadJson?.addEventListener('click', downloadJson);
		el.downloadCsv?.addEventListener('click', downloadCsv);
		el.downloadPdf?.addEventListener('click', downloadPdf);

		document.querySelectorAll('.wa-tab').forEach(btn => {
			btn.addEventListener('click', () => switchTab(btn.dataset.tab));
		});
	}

	function handleAnalyze() {
		const raw = el.urlInput.value.trim();
		const url = normalizeUrl(raw);

		if (!url) {
			el.errorMsg.textContent = waConfig.i18n.enterUrl;
			el.errorMsg.hidden = false;
			el.urlInput.focus();
			return;
		}

		el.errorMsg.hidden = true;
		runAnalysis(url);
	}

	document.addEventListener('DOMContentLoaded', init);

})();
