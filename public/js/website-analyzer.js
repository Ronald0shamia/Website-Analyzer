(function () {
	'use strict';

	const config = window.WebsiteAnalyzerConfig || {};

	class WebsiteAnalyzer {
		constructor(root) {
			this.root = root;
			this.form = root.querySelector('[data-role="form"]');
			this.urlInput = root.querySelector('[data-role="url"]');
			this.submitButton = root.querySelector('[data-role="submit"]');
			this.message = root.querySelector('[data-role="message"]');
			this.progress = root.querySelector('[data-role="progress"]');
			this.progressBar = root.querySelector('[data-role="progress-bar"]');
			this.results = root.querySelector('[data-role="results"]');
			this.checks = root.querySelector('[data-role="checks"]');
			this.score = root.querySelector('[data-role="score"]');
			this.domain = root.querySelector('[data-role="domain"]');
			this.timestamp = root.querySelector('[data-role="timestamp"]');
			this.currentResult = null;

			this.form.addEventListener('submit', (event) => this.handleSubmit(event));
			this.root.querySelectorAll('[data-export]').forEach((button) => {
				button.addEventListener('click', () => this.export(button.dataset.export));
			});
		}

		async handleSubmit(event) {
			event.preventDefault();

			let target;

			try {
				target = this.normalizeUrl(this.urlInput.value);
			} catch (error) {
				this.showMessage(error.message, 'error');
				return;
			}

			this.setLoading(true);
			this.showMessage(config.i18n?.analyzing || 'Analyse laeuft...', 'info');
			this.setProgress(8);

			try {
				const result = await this.analyze(target);
				this.currentResult = result;
				this.render(result);
				this.recordUsage(target.href);
				this.showMessage('Analyse abgeschlossen. Ergebnisse werden nur in dieser Browser-Sitzung angezeigt.', 'success');
			} catch (error) {
				this.showMessage(error.message || config.i18n?.error || 'Die URL konnte nicht analysiert werden.', 'error');
			} finally {
				this.setLoading(false);
				this.setProgress(100);
				window.setTimeout(() => {
					this.progress.hidden = true;
					this.setProgress(0);
				}, 600);
			}
		}

		normalizeUrl(value) {
			const trimmed = String(value || '').trim();

			if (!trimmed) {
				throw new Error('Bitte gib eine URL ein.');
			}

			const withProtocol = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
			const url = new URL(withProtocol);

			if (!['http:', 'https:'].includes(url.protocol)) {
				throw new Error('Es sind nur HTTP- und HTTPS-URLs erlaubt.');
			}

			return url;
		}

		async analyze(url) {
			const startedAt = new Date();
			const checks = [];
			const timings = await this.measureNetwork(url.href);
			this.setProgress(22);

			const htmlResult = await this.tryReadHtml(url.href);
			this.setProgress(48);

			const security = this.analyzeSecurity(url, htmlResult.headers);
			const seo = this.analyzeSeo(url, htmlResult.document);
			const mobile = this.analyzeMobile(htmlResult.document);
			const accessibility = this.analyzeAccessibility(htmlResult.document);
			const technical = this.analyzeTechnical(url, timings, htmlResult);
			const indexability = this.analyzeIndexability(url, htmlResult.document);
			const performance = this.analyzePerformance(timings, htmlResult);
			const loading = this.analyzeLoading(timings);

			checks.push(loading, performance, mobile, seo, indexability, security, accessibility, technical);

			const scoreable = checks.filter((check) => check.score !== null);
			const score = scoreable.length
				? Math.round(scoreable.reduce((sum, check) => sum + check.score, 0) / scoreable.length)
				: 0;

			this.setProgress(82);

			return {
				url: url.href,
				domain: url.hostname,
				timestamp: startedAt.toISOString(),
				score,
				checks,
				notes: [
					'Die Analyse wird im Browser ausgefuehrt.',
					'Bei fremden Domains koennen Browser-Sicherheitsregeln einzelne HTML- und Header-Pruefungen einschraenken.',
					'Analyseergebnisse werden nicht gespeichert und sind nach einem Reload weg.',
				],
			};
		}

		async measureNetwork(url) {
			const started = performance.now();
			const controller = new AbortController();
			const timeout = window.setTimeout(() => controller.abort(), 12000);

			try {
				await fetch(url, {
					method: 'GET',
					mode: 'no-cors',
					cache: 'no-store',
					credentials: 'omit',
					signal: controller.signal,
				});

				return {
					reachable: true,
					duration: Math.round(performance.now() - started),
					error: '',
				};
			} catch (error) {
				return {
					reachable: false,
					duration: Math.round(performance.now() - started),
					error: error.name === 'AbortError' ? 'Timeout nach 12 Sekunden' : error.message,
				};
			} finally {
				window.clearTimeout(timeout);
			}
		}

		async tryReadHtml(url) {
			const controller = new AbortController();
			const timeout = window.setTimeout(() => controller.abort(), 10000);

			try {
				const response = await fetch(url, {
					method: 'GET',
					mode: 'cors',
					cache: 'no-store',
					credentials: 'omit',
					signal: controller.signal,
				});
				const contentType = response.headers.get('content-type') || '';
				const headers = Object.fromEntries(response.headers.entries());
				const text = contentType.includes('text/html') ? await response.text() : '';
				const document = text ? new DOMParser().parseFromString(text, 'text/html') : null;

				return {
					readable: Boolean(document),
					status: response.status,
					headers,
					document,
					error: '',
				};
			} catch (error) {
				return {
					readable: false,
					status: 0,
					headers: {},
					document: null,
					error: error.name === 'AbortError' ? 'HTML-Pruefung per CORS hat zu lange gedauert.' : 'HTML ist browserseitig nicht auslesbar.',
				};
			} finally {
				window.clearTimeout(timeout);
			}
		}

		analyzeLoading(timings) {
			if (!timings.reachable) {
				return this.check('Ladezeit', 'critical', 0, 'Die Website konnte im Browser nicht erreicht werden.', [
					timings.error || 'Netzwerkfehler',
				]);
			}

			if (timings.duration < 1200) {
				return this.check('Ladezeit', 'good', 95, `${timings.duration} ms gemessen.`, [
					'Sehr schnelle erste Antwort im Browser.',
				]);
			}

			if (timings.duration < 3000) {
				return this.check('Ladezeit', 'warning', 72, `${timings.duration} ms gemessen.`, [
					'Ladezeit ist akzeptabel, bietet aber Optimierungspotenzial.',
				]);
			}

			return this.check('Ladezeit', 'critical', 38, `${timings.duration} ms gemessen.`, [
				'Erste Antwort ist langsam oder wurde durch Netzwerkbedingungen verzoegert.',
			]);
		}

		analyzePerformance(timings, htmlResult) {
			const details = [];
			let score = timings.reachable ? 75 : 20;

			if (timings.duration > 2500) {
				score -= 25;
				details.push('Gemessene Antwortzeit liegt ueber 2,5 Sekunden.');
			}

			if (htmlResult.document) {
				const scripts = htmlResult.document.querySelectorAll('script[src]').length;
				const styles = htmlResult.document.querySelectorAll('link[rel~="stylesheet"]').length;
				const images = htmlResult.document.querySelectorAll('img').length;

				if (scripts > 20) {
					score -= 10;
					details.push(`${scripts} externe Skripte gefunden.`);
				}

				if (styles > 8) {
					score -= 8;
					details.push(`${styles} Stylesheets gefunden.`);
				}

				if (images > 30) {
					score -= 8;
					details.push(`${images} Bilder gefunden.`);
				}

				if (!details.length) {
					details.push('HTML-Struktur enthaelt keine auffaelligen Asset-Mengen.');
				}
			} else {
				score = Math.min(score, 60);
				details.push('Asset-Anzahl konnte wegen Browser-Zugriffsbeschraenkungen nicht geprueft werden.');
			}

			return this.check('Performance', this.statusFromScore(score), score, 'Browserbasierte Performance-Heuristik.', details);
		}

		analyzeMobile(document) {
			if (!document) {
				return this.check('Mobile-Optimierung', 'limited', null, 'Viewport und Responsive-Metadaten konnten nicht ausgelesen werden.', [
					'Die Zielseite erlaubt browserseitig keinen HTML-Zugriff per CORS.',
				]);
			}

			const viewport = document.querySelector('meta[name="viewport"]');
			const hasResponsiveViewport = viewport && /width\s*=\s*device-width/i.test(viewport.getAttribute('content') || '');
			const score = hasResponsiveViewport ? 92 : 45;

			return this.check('Mobile-Optimierung', this.statusFromScore(score), score, hasResponsiveViewport ? 'Responsiver Viewport gefunden.' : 'Kein sauberer responsiver Viewport gefunden.', [
				hasResponsiveViewport ? viewport.getAttribute('content') : 'Empfohlen: width=device-width, initial-scale=1',
			]);
		}

		analyzeSeo(url, document) {
			if (!document) {
				return this.check('SEO', 'limited', null, 'SEO-Metadaten konnten nicht ausgelesen werden.', [
					'Title, Meta Description, Canonical und Heading-Struktur sind ohne HTML-Zugriff nicht pruefbar.',
				]);
			}

			let score = 100;
			const details = [];
			const title = document.querySelector('title')?.textContent.trim() || '';
			const description = document.querySelector('meta[name="description"]')?.getAttribute('content')?.trim() || '';
			const canonical = document.querySelector('link[rel="canonical"]')?.getAttribute('href') || '';
			const h1Count = document.querySelectorAll('h1').length;

			if (title.length < 10 || title.length > 70) {
				score -= 18;
				details.push('Title-Laenge ist nicht optimal.');
			} else {
				details.push('Title-Laenge wirkt solide.');
			}

			if (description.length < 50 || description.length > 170) {
				score -= 18;
				details.push('Meta Description fehlt oder ist nicht optimal lang.');
			} else {
				details.push('Meta Description ist vorhanden.');
			}

			if (h1Count !== 1) {
				score -= 16;
				details.push(`${h1Count} H1-Ueberschriften gefunden.`);
			} else {
				details.push('Genau eine H1-Ueberschrift gefunden.');
			}

			if (!canonical) {
				score -= 10;
				details.push('Canonical-Link fehlt.');
			} else if (!canonical.includes(url.hostname)) {
				score -= 10;
				details.push('Canonical-Link zeigt auf eine andere Domain.');
			} else {
				details.push('Canonical-Link ist vorhanden.');
			}

			return this.check('SEO', this.statusFromScore(score), Math.max(score, 0), 'Onpage-SEO-Pruefung anhand lesbarer HTML-Daten.', details);
		}

		analyzeIndexability(url, document) {
			const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(`site:${url.hostname}`)}`;

			if (!document) {
				return this.check('Google-Indexierung', 'limited', null, 'Google-Indexierung kann rein browserbasiert nicht verlaesslich abgefragt werden.', [
					`Manuelle Site-Abfrage: ${searchUrl}`,
					'Robots- und Meta-Robots-Pruefung erfordert lesbare HTML- oder Robots-Daten.',
				]);
			}

			const robots = document.querySelector('meta[name="robots"]')?.getAttribute('content') || '';
			const isNoIndex = /noindex/i.test(robots);
			const score = isNoIndex ? 25 : 78;

			return this.check('Google-Indexierung', this.statusFromScore(score), score, isNoIndex ? 'Meta Robots enthaelt noindex.' : 'Kein noindex in den lesbaren Metadaten gefunden.', [
				`Manuelle Site-Abfrage: ${searchUrl}`,
				robots ? `Robots-Meta: ${robots}` : 'Kein Robots-Meta-Tag gefunden.',
			]);
		}

		analyzeSecurity(url, headers) {
			let score = url.protocol === 'https:' ? 72 : 30;
			const details = [url.protocol === 'https:' ? 'HTTPS wird verwendet.' : 'HTTPS wird nicht verwendet.'];
			const headerNames = Object.keys(headers || {}).map((header) => header.toLowerCase());
			const expectedHeaders = [
				'content-security-policy',
				'strict-transport-security',
				'x-content-type-options',
				'referrer-policy',
			];

			if (!headerNames.length) {
				return this.check('Sicherheit', url.protocol === 'https:' ? 'limited' : 'critical', url.protocol === 'https:' ? null : score, 'Security-Header sind browserseitig nicht auslesbar.', details.concat([
					'Viele Zielseiten erlauben keine Header-Inspektion per CORS.',
				]));
			}

			expectedHeaders.forEach((header) => {
				if (headerNames.includes(header)) {
					score += 6;
					details.push(`${header} vorhanden.`);
				} else {
					score -= 7;
					details.push(`${header} fehlt.`);
				}
			});

			return this.check('Sicherheit', this.statusFromScore(score), Math.max(0, Math.min(score, 100)), 'HTTPS- und Security-Header-Pruefung.', details);
		}

		analyzeAccessibility(document) {
			if (!document) {
				return this.check('Barrierefreiheit', 'limited', null, 'Accessibility-Heuristiken konnten nicht ausgefuehrt werden.', [
					'Alt-Texte, Sprache und Formularlabels sind ohne HTML-Zugriff nicht pruefbar.',
				]);
			}

			let score = 100;
			const details = [];
			const images = Array.from(document.querySelectorAll('img'));
			const imagesWithoutAlt = images.filter((image) => !image.hasAttribute('alt')).length;
			const htmlLang = document.documentElement.getAttribute('lang');
			const inputs = Array.from(document.querySelectorAll('input, select, textarea'));
			const unlabeledInputs = inputs.filter((input) => {
				const id = input.getAttribute('id');
				const ariaLabel = input.getAttribute('aria-label') || input.getAttribute('aria-labelledby');
				return !ariaLabel && (!id || !document.querySelector(`label[for="${CSS.escape(id)}"]`));
			}).length;

			if (!htmlLang) {
				score -= 15;
				details.push('HTML lang-Attribut fehlt.');
			} else {
				details.push(`HTML lang="${htmlLang}" gefunden.`);
			}

			if (imagesWithoutAlt > 0) {
				score -= Math.min(30, imagesWithoutAlt * 4);
				details.push(`${imagesWithoutAlt} Bilder ohne alt-Attribut.`);
			} else {
				details.push('Alle lesbaren Bilder haben ein alt-Attribut.');
			}

			if (unlabeledInputs > 0) {
				score -= Math.min(25, unlabeledInputs * 5);
				details.push(`${unlabeledInputs} Formularfelder ohne erkennbares Label.`);
			} else {
				details.push('Keine ungelabelten Formularfelder gefunden.');
			}

			return this.check('Barrierefreiheit', this.statusFromScore(score), Math.max(score, 0), 'Grundlegende Accessibility-Heuristik.', details);
		}

		analyzeTechnical(url, timings, htmlResult) {
			let score = timings.reachable ? 78 : 15;
			const details = [];

			if (htmlResult.status >= 400) {
				score -= 40;
				details.push(`HTTP-Status ${htmlResult.status}.`);
			} else if (htmlResult.status > 0) {
				details.push(`HTTP-Status ${htmlResult.status}.`);
			} else {
				details.push('HTTP-Status konnte nicht gelesen werden.');
			}

			if (url.pathname.includes('//')) {
				score -= 8;
				details.push('URL-Pfad enthaelt doppelte Slashes.');
			}

			if (htmlResult.document) {
				const brokenInternalAnchors = Array.from(htmlResult.document.querySelectorAll('a[href^="#"]')).filter((anchor) => {
					const target = anchor.getAttribute('href').slice(1);
					return target && !htmlResult.document.getElementById(target);
				}).length;

				if (brokenInternalAnchors) {
					score -= Math.min(20, brokenInternalAnchors * 4);
					details.push(`${brokenInternalAnchors} interne Sprungmarken ohne Ziel gefunden.`);
				} else {
					details.push('Keine defekten internen Sprungmarken gefunden.');
				}
			} else {
				details.push(htmlResult.error || 'HTML-Pruefung eingeschraenkt.');
			}

			return this.check('Technische Fehler', this.statusFromScore(score), Math.max(0, Math.min(score, 100)), 'Technische Browser-Pruefung.', details);
		}

		check(label, status, score, summary, details) {
			return { label, status, score, summary, details };
		}

		statusFromScore(score) {
			if (score >= 80) {
				return 'good';
			}

			if (score >= 55) {
				return 'warning';
			}

			return 'critical';
		}

		render(result) {
			this.results.hidden = false;
			this.score.textContent = String(result.score);
			this.domain.textContent = result.domain;
			this.timestamp.textContent = new Intl.DateTimeFormat(document.documentElement.lang || 'de-DE', {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(new Date(result.timestamp));
			this.checks.textContent = '';

			result.checks.forEach((check) => {
				const article = document.createElement('article');
				article.className = `website-analyzer__check is-${check.status}`;

				const header = document.createElement('div');
				header.className = 'website-analyzer__check-header';

				const title = document.createElement('h3');
				title.textContent = check.label;

				const badge = document.createElement('span');
				badge.className = 'website-analyzer__badge';
				badge.textContent = this.statusLabel(check.status, check.score);

				const summary = document.createElement('p');
				summary.textContent = check.summary;

				const list = document.createElement('ul');
				check.details.forEach((detail) => {
					const item = document.createElement('li');
					item.textContent = detail;
					list.appendChild(item);
				});

				header.append(title, badge);
				article.append(header, summary, list);
				this.checks.appendChild(article);
			});
		}

		statusLabel(status, score) {
			if (status === 'limited') {
				return 'Eingeschraenkt';
			}

			const prefix = {
				good: 'Gut',
				warning: 'Pruefen',
				critical: 'Kritisch',
			}[status] || 'Info';

			return score === null ? prefix : `${prefix} · ${score}/100`;
		}

		recordUsage(url) {
			if (!config.ajaxUrl || !config.nonce) {
				return;
			}

			const body = new URLSearchParams({
				action: 'website_analyzer_record_usage',
				nonce: config.nonce,
				url,
			});

			fetch(config.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body,
				credentials: 'same-origin',
			}).catch(() => {});
		}

		export(type) {
			if (!this.currentResult) {
				return;
			}

			if (type === 'json') {
				this.download('website-analyzer.json', 'application/json', JSON.stringify(this.currentResult, null, 2));
				return;
			}

			if (type === 'csv') {
				this.download('website-analyzer.csv', 'text/csv;charset=utf-8', this.toCsv(this.currentResult));
				return;
			}

			if (type === 'pdf') {
				this.download('website-analyzer.pdf', 'application/pdf', this.toPdf(this.currentResult), true);
			}
		}

		toCsv(result) {
			const rows = [
				['Kategorie', 'Status', 'Score', 'Zusammenfassung', 'Details'],
				...result.checks.map((check) => [
					check.label,
					check.status,
					check.score === null ? '' : check.score,
					check.summary,
					check.details.join(' | '),
				]),
			];

			return rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\r\n');
		}

		toPdf(result) {
			const lines = [
				'Website Analyzer Report',
				`URL: ${result.url}`,
				`Domain: ${result.domain}`,
				`Score: ${result.score}/100`,
				`Zeitpunkt: ${result.timestamp}`,
				'',
				...result.checks.flatMap((check) => [
					`${check.label}: ${this.statusLabel(check.status, check.score)}`,
					check.summary,
					...check.details.map((detail) => `- ${detail}`),
					'',
				]),
			];

			return this.buildSimplePdf(lines);
		}

		buildSimplePdf(lines) {
			const escapePdf = (value) => String(value)
				.replace(/[^\x09\x0A\x0D\x20-\x7E]/g, '?')
				.replace(/\\/g, '\\\\')
				.replace(/\(/g, '\\(')
				.replace(/\)/g, '\\)');
			const content = ['BT', '/F1 12 Tf', '50 790 Td', '14 TL'];
			let lineCount = 0;

			lines.forEach((line) => {
				const chunks = String(line).match(/.{1,82}/g) || [''];
				chunks.forEach((chunk) => {
					if (lineCount > 0) {
						content.push('T*');
					}
					content.push(`(${escapePdf(chunk)}) Tj`);
					lineCount += 1;
				});
			});
			content.push('ET');

			const stream = content.join('\n');
			const objects = [
				'1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
				'2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
				'3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
				'4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
				`5 0 obj << /Length ${stream.length} >> stream\n${stream}\nendstream endobj`,
			];
			let pdf = '%PDF-1.4\n';
			const offsets = [0];

			objects.forEach((object) => {
				offsets.push(pdf.length);
				pdf += `${object}\n`;
			});

			const xref = pdf.length;
			pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
			offsets.slice(1).forEach((offset) => {
				pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
			});
			pdf += `trailer << /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;

			return pdf;
		}

		download(filename, type, content, binary = false) {
			const blob = new Blob([content], { type });
			const url = URL.createObjectURL(blob);
			const link = document.createElement('a');

			link.href = url;
			link.download = filename;
			link.click();
			window.setTimeout(() => URL.revokeObjectURL(url), 1000);

			if (binary) {
				return;
			}
		}

		setLoading(isLoading) {
			this.submitButton.disabled = isLoading;
			this.urlInput.disabled = isLoading;
			this.progress.hidden = !isLoading;
		}

		setProgress(value) {
			this.progressBar.style.width = `${value}%`;
		}

		showMessage(text, type) {
			this.message.textContent = text;
			this.message.dataset.state = type;
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('[data-website-analyzer]').forEach((root) => {
			new WebsiteAnalyzer(root);
		});
	});
})();
