=== Website Analyzer ===
Contributors: website-analyzer
Tags: seo, performance, accessibility, security, analyzer
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browserbasierte Website-Analyse mit Exporten und datensparsamen Nutzungsstatistiken.

== Description ==

Website Analyzer stellt den Shortcode [website_analyzer] bereit. Benutzer geben eine URL ein und erhalten browserbasierte Checks zu Ladezeit, Performance, Mobile-Optimierung, SEO, Google-Indexierung, Sicherheit, Barrierefreiheit und technischen Fehlern.

Die Analyseergebnisse werden nicht serverseitig gespeichert. Nach einem Reload sind die Frontend-Ergebnisse weg. Im Dashboard werden nur Nutzungszeitpunkt, Nutzungshaeufigkeit und analysierte Domains gespeichert.

== Installation ==

1. Plugin-Ordner in wp-content/plugins/website-analyzer ablegen.
2. Plugin im WordPress-Dashboard aktivieren.
3. Shortcode [website_analyzer] in eine Seite einfuegen.

== Notes ==

Da die Analyse browserbasiert laeuft, koennen fremde Domains einzelne HTML- oder Header-Pruefungen durch CORS/Same-Origin-Sicherheitsregeln einschraenken.
