<?php
/**
 * Website Analyzer Service.
 *
 * Orchestrates all server-side analysis checks.
 *
 * @package WebsiteAnalyzer\API
 */

namespace WebsiteAnalyzer\API;

use WebsiteAnalyzer\Admin\Settings;

/**
 * Fetches and analyzes a given URL from the server side.
 * Browser-based metrics (LCP, FCP, CLS, etc.) are collected via JavaScript.
 */
class WebsiteAnalyzerService {

	/**
	 * Target URL.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * HTTP response data.
	 *
	 * @var array<string, mixed>
	 */
	private array $response = [];

	/**
	 * Constructor.
	 *
	 * @param string $url Target URL.
	 */
	public function __construct( string $url ) {
		$this->url = esc_url_raw( $url );
	}

	/**
	 * Run all analysis checks.
	 *
	 * @return array<string, mixed>
	 * @throws \Exception On request failure.
	 */
	public function analyze(): array {
		$this->fetch_url();

		return [
			'url'           => $this->url,
			'timestamp'     => time(),
			'http'          => $this->analyze_http(),
			'headers'       => $this->analyze_headers(),
			'seo'           => $this->analyze_seo(),
			'security'      => $this->analyze_security(),
			'robots'        => $this->check_robots_txt(),
			'sitemap'       => $this->check_sitemap(),
			'html_meta'     => $this->extract_html_meta(),
		];
	}

	/**
	 * Fetch the target URL.
	 *
	 * @return void
	 * @throws \Exception On request failure.
	 */
	private function fetch_url(): void {
		$timeout = (int) Settings::get( 'analysis_timeout', 30 );

		$args = [
			'timeout'    => $timeout,
			'user-agent' => 'Mozilla/5.0 (compatible; WebsiteAnalyzer/1.0; +https://example.com)',
			'sslverify'  => true,
			'redirection' => 5,
		];

		$response = wp_remote_get( $this->url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$this->response = [
			'code'     => wp_remote_retrieve_response_code( $response ),
			'headers'  => wp_remote_retrieve_headers( $response )->getAll(),
			'body'     => wp_remote_retrieve_body( $response ),
			'response' => $response,
		];
	}

	/**
	 * Analyze HTTP response.
	 *
	 * @return array<string, mixed>
	 */
	private function analyze_http(): array {
		$code = $this->response['code'];

		return [
			'status_code'  => $code,
			'status_ok'    => $code >= 200 && $code < 300,
			'is_redirect'  => $code >= 300 && $code < 400,
			'final_url'    => $this->url,
			'https'        => str_starts_with( $this->url, 'https://' ),
		];
	}

	/**
	 * Analyze response headers.
	 *
	 * @return array<string, mixed>
	 */
	private function analyze_headers(): array {
		$headers = $this->response['headers'];

		$compression = '';
		if ( isset( $headers['content-encoding'] ) ) {
			$compression = strtolower( $headers['content-encoding'] );
		}

		return [
			'content_type'        => $headers['content-type'] ?? '',
			'content_encoding'    => $compression,
			'has_gzip'            => str_contains( $compression, 'gzip' ),
			'has_brotli'          => str_contains( $compression, 'br' ),
			'cache_control'       => $headers['cache-control'] ?? '',
			'etag'                => $headers['etag'] ?? '',
			'last_modified'       => $headers['last-modified'] ?? '',
			'x_powered_by'        => $headers['x-powered-by'] ?? '',
			'server'              => $headers['server'] ?? '',
			'content_length'      => $headers['content-length'] ?? '',
		];
	}

	/**
	 * Analyze security headers.
	 *
	 * @return array<string, mixed>
	 */
	private function analyze_security(): array {
		$headers = $this->response['headers'];
		$https   = str_starts_with( $this->url, 'https://' );

		return [
			'https'             => $https,
			'hsts'              => $headers['strict-transport-security'] ?? '',
			'csp'               => $headers['content-security-policy'] ?? '',
			'x_frame_options'   => $headers['x-frame-options'] ?? '',
			'xss_protection'    => $headers['x-xss-protection'] ?? '',
			'referrer_policy'   => $headers['referrer-policy'] ?? '',
			'x_content_type'    => $headers['x-content-type-options'] ?? '',
			'permissions_policy'=> $headers['permissions-policy'] ?? '',
			'has_hsts'          => isset( $headers['strict-transport-security'] ),
			'has_csp'           => isset( $headers['content-security-policy'] ),
			'has_x_frame'       => isset( $headers['x-frame-options'] ),
			'has_xss_prot'      => isset( $headers['x-xss-protection'] ),
			'has_referrer'      => isset( $headers['referrer-policy'] ),
			'has_x_content'     => isset( $headers['x-content-type-options'] ),
		];
	}

	/**
	 * Extract SEO-related HTML meta information.
	 *
	 * @return array<string, mixed>
	 */
	private function analyze_seo(): array {
		$body = $this->response['body'];
		if ( empty( $body ) ) {
			return [];
		}

		$dom = new \DOMDocument();
		// Suppress HTML5 parse warnings.
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $body );
		libxml_clear_errors();
		libxml_use_internal_errors( false );

		$xpath = new \DOMXPath( $dom );

		// Title.
		$title_nodes = $xpath->query( '//title' );
		$title       = $title_nodes && $title_nodes->length > 0 ? trim( $title_nodes->item( 0 )->textContent ) : '';

		// Meta description.
		$meta_desc = '';
		$meta_nodes = $xpath->query( '//meta[@name="description"]/@content' );
		if ( $meta_nodes && $meta_nodes->length > 0 ) {
			$meta_desc = trim( $meta_nodes->item( 0 )->nodeValue );
		}

		// Canonical.
		$canonical     = '';
		$canonical_nodes = $xpath->query( '//link[@rel="canonical"]/@href' );
		if ( $canonical_nodes && $canonical_nodes->length > 0 ) {
			$canonical = trim( $canonical_nodes->item( 0 )->nodeValue );
		}

		// Robots meta.
		$robots_meta = '';
		$robots_nodes = $xpath->query( '//meta[@name="robots"]/@content' );
		if ( $robots_nodes && $robots_nodes->length > 0 ) {
			$robots_meta = trim( $robots_nodes->item( 0 )->nodeValue );
		}

		// Viewport.
		$viewport     = '';
		$viewport_nodes = $xpath->query( '//meta[@name="viewport"]/@content' );
		if ( $viewport_nodes && $viewport_nodes->length > 0 ) {
			$viewport = trim( $viewport_nodes->item( 0 )->nodeValue );
		}

		// Headings.
		$headings = [];
		foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $tag ) {
			$nodes = $xpath->query( '//' . $tag );
			$headings[ $tag ] = [];
			if ( $nodes ) {
				foreach ( $nodes as $node ) {
					$headings[ $tag ][] = trim( $node->textContent );
				}
			}
		}

		// Images.
		$images           = [];
		$images_without_alt = 0;
		$img_nodes        = $xpath->query( '//img' );
		if ( $img_nodes ) {
			foreach ( $img_nodes as $img ) {
				$alt    = $img->getAttribute( 'alt' );
				$src    = $img->getAttribute( 'src' );
				$images[] = [
					'src'     => $src,
					'alt'     => $alt,
					'has_alt' => $alt !== '',
				];
				if ( $alt === '' ) {
					$images_without_alt++;
				}
			}
		}

		// Open Graph.
		$og = [];
		$og_nodes = $xpath->query( '//meta[starts-with(@property,"og:")]' );
		if ( $og_nodes ) {
			foreach ( $og_nodes as $node ) {
				$prop      = $node->getAttribute( 'property' );
				$og[ $prop ] = $node->getAttribute( 'content' );
			}
		}

		// Twitter Cards.
		$twitter = [];
		$tw_nodes = $xpath->query( '//meta[starts-with(@name,"twitter:")]' );
		if ( $tw_nodes ) {
			foreach ( $tw_nodes as $node ) {
				$name         = $node->getAttribute( 'name' );
				$twitter[ $name ] = $node->getAttribute( 'content' );
			}
		}

		// Schema.org (JSON-LD).
		$schema_data = [];
		$ld_nodes    = $xpath->query( '//script[@type="application/ld+json"]' );
		if ( $ld_nodes ) {
			foreach ( $ld_nodes as $node ) {
				$decoded = json_decode( $node->textContent, true );
				if ( $decoded ) {
					$schema_data[] = $decoded;
				}
			}
		}

		// Links.
		$internal_links = 0;
		$external_links = 0;
		$host           = wp_parse_url( $this->url, PHP_URL_HOST );
		$link_nodes     = $xpath->query( '//a[@href]' );
		if ( $link_nodes ) {
			foreach ( $link_nodes as $link ) {
				$href      = $link->getAttribute( 'href' );
				$link_host = wp_parse_url( $href, PHP_URL_HOST );
				if ( $link_host && $link_host !== $host ) {
					$external_links++;
				} else {
					$internal_links++;
				}
			}
		}

		return [
			'title'              => $title,
			'title_length'       => mb_strlen( $title ),
			'meta_description'   => $meta_desc,
			'meta_desc_length'   => mb_strlen( $meta_desc ),
			'canonical'          => $canonical,
			'robots_meta'        => $robots_meta,
			'viewport'           => $viewport,
			'headings'           => $headings,
			'images'             => $images,
			'images_without_alt' => $images_without_alt,
			'total_images'       => count( $images ),
			'open_graph'         => $og,
			'has_og'             => ! empty( $og ),
			'twitter_card'       => $twitter,
			'has_twitter'        => ! empty( $twitter ),
			'schema'             => $schema_data,
			'has_schema'         => ! empty( $schema_data ),
			'internal_links'     => $internal_links,
			'external_links'     => $external_links,
			'is_noindex'         => str_contains( strtolower( $robots_meta ), 'noindex' ),
		];
	}

	/**
	 * Extract HTML meta for SEO analysis.
	 *
	 * @return array<string, mixed>
	 */
	private function extract_html_meta(): array {
		$body = $this->response['body'];
		$size = strlen( $body );

		return [
			'page_size_bytes' => $size,
			'page_size_kb'    => round( $size / 1024, 2 ),
		];
	}

	/**
	 * Check robots.txt.
	 *
	 * @return array<string, mixed>
	 */
	private function check_robots_txt(): array {
		$parsed   = wp_parse_url( $this->url );
		$base     = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
		$robots_url = $base . '/robots.txt';

		$response = wp_remote_get( $robots_url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $response ) ) {
			return [ 'exists' => false, 'content' => '', 'blocks_all' => false ];
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$content = wp_remote_retrieve_body( $response );

		$blocks_all = false;
		if ( $code === 200 && preg_match( '/User-agent:\s*\*.*?Disallow:\s*\//si', $content ) ) {
			$blocks_all = true;
		}

		return [
			'exists'     => $code === 200,
			'url'        => $robots_url,
			'content'    => wp_strip_all_tags( $content ),
			'blocks_all' => $blocks_all,
		];
	}

	/**
	 * Check sitemap.xml.
	 *
	 * @return array<string, mixed>
	 */
	private function check_sitemap(): array {
		$parsed  = wp_parse_url( $this->url );
		$base    = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
		$urls    = [ '/sitemap.xml', '/sitemap_index.xml', '/sitemap-index.xml' ];

		foreach ( $urls as $path ) {
			$sitemap_url = $base . $path;
			$response    = wp_remote_get( $sitemap_url, [ 'timeout' => 10 ] );
			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				return [
					'exists' => true,
					'url'    => $sitemap_url,
				];
			}
		}

		return [ 'exists' => false, 'url' => '' ];
	}
}
