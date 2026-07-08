<?php
/**
 * Rate Limiter.
 *
 * @package WebsiteAnalyzer\Helpers
 */

namespace WebsiteAnalyzer\Helpers;

/**
 * Implements rate limiting using WordPress transients.
 */
class RateLimiter {

	/**
	 * Transient prefix.
	 *
	 * @var string
	 */
	const PREFIX = 'wa_rate_';

	/**
	 * Check if the given IP is within the rate limit.
	 *
	 * @param string $ip           IP address.
	 * @param int    $max_per_hour Maximum allowed requests per hour.
	 * @return bool True if allowed, false if rate limited.
	 */
	public function check( string $ip, int $max_per_hour ): bool {
		$key   = self::PREFIX . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= $max_per_hour ) {
			return false;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
		} else {
			// Increment without resetting expiration.
			$remaining = $this->get_transient_remaining( $key );
			set_transient( $key, $count + 1, max( 1, $remaining ) );
		}

		return true;
	}

	/**
	 * Approximate remaining seconds for a transient.
	 *
	 * @param string $key Transient key.
	 * @return int
	 */
	private function get_transient_remaining( string $key ): int {
		global $wpdb;

		$option_name = '_transient_timeout_' . $key;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$timeout = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option_name )
		);

		if ( $timeout === null ) {
			return HOUR_IN_SECONDS;
		}

		return max( 1, (int) $timeout - time() );
	}
}
