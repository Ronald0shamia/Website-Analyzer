<?php
/**
 * URL validation helpers for server-side metadata checks.
 *
 * @package WebsiteAnalyzer\Helpers
 */

namespace WebsiteAnalyzer\Helpers;

/**
 * Validates public HTTP(S) URLs before the server fetches them.
 */
class UrlValidator {

	/**
	 * Normalize and validate a submitted URL.
	 *
	 * @param string $url Raw URL.
	 * @return string|\WP_Error
	 */
	public static function normalize_public_url( string $url ): string|\WP_Error {
		$url = trim( $url );

		if ( '' === $url ) {
			return new \WP_Error( 'wa_empty_url', __( 'No URL provided.', 'website-analyzer' ) );
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		$url = esc_url_raw( $url, [ 'http', 'https' ] );
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'wa_invalid_url', __( 'Invalid URL provided.', 'website-analyzer' ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'wa_invalid_url', __( 'Invalid URL provided.', 'website-analyzer' ) );
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return new \WP_Error( 'wa_invalid_scheme', __( 'Only HTTP and HTTPS URLs are allowed.', 'website-analyzer' ) );
		}

		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return new \WP_Error( 'wa_credentials_url', __( 'URLs with credentials are not allowed.', 'website-analyzer' ) );
		}

		if ( ! empty( $parts['port'] ) && ! in_array( (int) $parts['port'], [ 80, 443 ], true ) ) {
			return new \WP_Error( 'wa_blocked_port', __( 'Only standard HTTP and HTTPS ports are allowed.', 'website-analyzer' ) );
		}

		$host = strtolower( rtrim( $parts['host'], '.' ) );
		if ( self::is_blocked_host( $host ) ) {
			return new \WP_Error( 'wa_blocked_host', __( 'This host is not allowed.', 'website-analyzer' ) );
		}

		$ips = self::resolve_host( $host );
		if ( empty( $ips ) ) {
			return new \WP_Error( 'wa_dns_failed', __( 'The host could not be resolved.', 'website-analyzer' ) );
		}

		foreach ( $ips as $ip ) {
			if ( ! self::is_public_ip( $ip ) ) {
				return new \WP_Error( 'wa_private_target', __( 'Private, local, and reserved network targets are blocked.', 'website-analyzer' ) );
			}
		}

		return $url;
	}

	/**
	 * Check whether a hostname itself is blocked.
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	private static function is_blocked_host( string $host ): bool {
		if ( in_array( $host, [ 'localhost', 'localhost.localdomain' ], true ) ) {
			return true;
		}

		if ( str_ends_with( $host, '.local' ) || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.internal' ) ) {
			return true;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return ! self::is_public_ip( $host );
		}

		return false;
	}

	/**
	 * Resolve A and AAAA records.
	 *
	 * @param string $host Hostname.
	 * @return array<int, string>
	 */
	private static function resolve_host( string $host ): array {
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}

		$ips = [];
		$a_records = gethostbynamel( $host );
		if ( is_array( $a_records ) ) {
			$ips = array_merge( $ips, $a_records );
		}

		if ( function_exists( 'dns_get_record' ) ) {
			$aaaa_records = dns_get_record( $host, DNS_AAAA );
			if ( is_array( $aaaa_records ) ) {
				foreach ( $aaaa_records as $record ) {
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = $record['ipv6'];
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $ips ) ) );
	}

	/**
	 * Check if an IP is globally routable.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private static function is_public_ip( string $ip ): bool {
		return false !== filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
