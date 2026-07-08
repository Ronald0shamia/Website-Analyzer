<?php
/**
 * IP Helper.
 *
 * @package WebsiteAnalyzer\Helpers
 */

namespace WebsiteAnalyzer\Helpers;

/**
 * Utility methods for IP address handling.
 */
class IpHelper {

	/**
	 * Get the real visitor IP address.
	 *
	 * Checks common proxy headers before falling back to REMOTE_ADDR.
	 *
	 * @return string
	 */
	public static function get_ip(): string {
		$headers = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// X-Forwarded-For can contain multiple IPs; take the first.
				if ( str_contains( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
