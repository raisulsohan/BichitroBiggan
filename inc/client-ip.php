<?php
/**
 * The visitor's address when the site sits behind Cloudflare.
 *
 * Behind a proxy, REMOTE_ADDR is the proxy. Everything the theme keys on the
 * address — the login limiter in security.php, the reader and depth counts in
 * views.php — would then lump every visitor arriving through one Cloudflare
 * edge together: readers undercounted, and a password-guessing bot locking
 * the login for everybody behind that edge, the site's own editors included.
 *
 * Cloudflare sends the real address in CF-Connecting-IP. Anybody can send a
 * header, so it is believed only when the connection itself comes from one of
 * Cloudflare's published ranges. A host that already restores the address
 * (some do) hands over the visitor in REMOTE_ADDR, which matches no Cloudflare
 * range and is used as it is — either way the answer is the visitor.
 *
 * Without Cloudflare in front nothing changes: the header is absent and
 * REMOTE_ADDR is returned untouched.
 *
 * @package BichitroBiggan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloudflare's ranges, as published at cloudflare.com/ips on 27 September 2026.
 *
 * @return string[] CIDR blocks, IPv4 then IPv6.
 */
function bb_cloudflare_ranges() {
	return array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);
}

/**
 * Whether an address falls inside a CIDR block. IPv4 and IPv6 alike.
 *
 * @param string $ip   An address.
 * @param string $cidr A block such as 104.16.0.0/13 or 2606:4700::/32.
 * @return bool
 */
function bb_ip_in_range( $ip, $cidr ) {
	$parts  = explode( '/', $cidr, 2 );
	$subnet = $parts[0];
	$bits   = isset( $parts[1] ) ? (int) $parts[1] : null;

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || ! filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
		return false;
	}

	$ip_bin  = inet_pton( $ip );
	$net_bin = inet_pton( $subnet );

	// An IPv4 address never sits inside an IPv6 block, or the other way round.
	if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
		return false;
	}

	if ( null === $bits ) {
		$bits = strlen( $net_bin ) * 8;
	}

	$bytes = intdiv( $bits, 8 );
	$rest  = $bits % 8;

	if ( substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
		return false;
	}

	if ( 0 === $rest ) {
		return true;
	}

	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;

	return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $net_bin[ $bytes ] ) & $mask );
}

/**
 * Whether a connection comes from Cloudflare.
 *
 * @param string $ip The connecting address.
 * @return bool
 */
function bb_is_cloudflare( $ip ) {
	foreach ( bb_cloudflare_ranges() as $range ) {
		if ( bb_ip_in_range( $ip, $range ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The visitor's address: the real one behind Cloudflare, REMOTE_ADDR otherwise.
 *
 * @return string An address, or '' when the server gives none.
 */
function bb_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$cf     = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';

	if ( '' === $cf || ! filter_var( $cf, FILTER_VALIDATE_IP ) || ! bb_is_cloudflare( $remote ) ) {
		return $remote;
	}

	return $cf;
}
