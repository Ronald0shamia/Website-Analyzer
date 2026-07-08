# Website Analyzer — WordPress Plugin

A comprehensive, production-ready WordPress plugin that allows visitors to analyze any public website with AI-powered insights.

---

## Features

- **Frontend shortcode** `[website_analyzer]` — fully AJAX-driven, no page reload
- **Server-side analysis** via `wp_remote_get`: HTTP status, headers, SEO meta, security headers, robots.txt, sitemap
- **Browser-side performance metrics** (load time, TTFB, FCP, LCP estimates via iframe timing)
- **Google Gemini AI integration** — scores, recommendations, SEO report, content analysis
- **Downloadable reports** — PDF (jsPDF), JSON, CSV
- **Admin dashboard** with Chart.js usage chart and top-domains table
- **Statistics table** with date/domain/duration/status/IP — ephemeral analysis data is never stored
- **Rate limiting** per IP using WordPress transients
- **Full i18n/l10n** with German translation included
- **Clean uninstall** via `uninstall.php`

---

## Installation

1. Upload the `website-analyzer` folder to `/wp-content/plugins/`
2. Activate the plugin through **Plugins → Installed Plugins**
3. Go to **Website Analyzer → Settings** and enter your Google Gemini API key
4. Place `[website_analyzer]` on any page or post

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+

---

## Directory Structure

```
website-analyzer/
├── assets/
│   ├── css/
│   │   ├── frontend.css        # Shortcode styles
│   │   └── admin.css           # Admin panel styles
│   └── js/
│       ├── analyzer.js         # Full client-side analysis engine
│       └── admin.js            # Admin interactions
├── includes/
│   ├── Plugin.php              # Bootstrap / singleton
│   ├── Admin/
│   │   ├── AdminMenu.php       # Menu pages + asset enqueue
│   │   └── Settings.php        # WordPress Settings API
│   ├── API/
│   │   ├── AjaxHandler.php     # AJAX endpoints
│   │   ├── WebsiteAnalyzerService.php  # Server-side fetcher
│   │   └── GeminiClient.php    # Google Gemini API client
│   ├── Frontend/
│   │   └── Shortcode.php       # [website_analyzer] shortcode
│   ├── Statistics/
│   │   └── StatisticsManager.php  # DB read/write for stats
│   └── Helpers/
│       ├── RateLimiter.php     # Transient-based rate limiting
│       └── IpHelper.php        # Real IP detection
├── languages/
│   └── website-analyzer-de_DE.po
├── templates/
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── statistics.php
│   │   └── settings.php
│   └── frontend/
│       └── analyzer.php
├── composer.json
├── uninstall.php
└── website-analyzer.php        # Plugin header + autoloader
```

---

## Analysis Categories

| Category       | Source           | Details |
|----------------|------------------|---------|
| Performance    | Browser (iframe) | Load time, TTFB, FCP, LCP, Speed Index estimates |
| SEO            | Server (PHP)     | Title, meta desc, canonical, OG, Twitter Cards, Schema.org, headings, alt texts, robots.txt, sitemap |
| Security       | Server (PHP)     | HTTPS, HSTS, CSP, X-Frame-Options, XSS-Protection, Referrer-Policy, X-Content-Type |
| Mobile         | Derived          | Viewport, responsive design detection |
| Technical      | Server + derived | Status code, compression (Brotli/Gzip), Cache-Control, server info, page size |
| Accessibility  | Derived from SEO | Image alt text coverage |
| AI (Gemini)    | Google Gemini API| Overall score, critical issues, SEO report, performance tips, content analysis |

---

## Privacy

- Analysis results are **never stored** in the database
- Only aggregated statistics are stored: domain, duration, success/error, optional IP
- IP storage can be disabled in **Settings → Privacy**
- All stored data is removed on plugin deletion (`uninstall.php`)

---

## API Key Setup

1. Visit [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Create a free API key for **Gemini 1.5 Flash**
3. Paste it into **Website Analyzer → Settings → Google Gemini API Key**

---

## Filters & Hooks

```php
// Modify analysis timeout
add_filter( 'wa_analysis_timeout', fn() => 60 );

// Hook into after a successful analysis
add_action( 'wa_after_analysis', function( array $data ) {
    // $data contains: domain, duration, success, ip, user_id
} );
```

---

## Changelog

### 1.0.0
- Initial release
