<?php
/**
 * Country -> date format + shortlist of timezones.
 *
 * Picking a country first means nobody has to scroll a 400-entry timezone list,
 * and it sets a sensible default date format at the same time.
 *
 * @package Greenspage_Countdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * The curated country map.
 *
 * Format: code => array( label, php_date_format, array_of_timezones ).
 *
 * @return array
 */
function gpcd_countries() {
	$map = array(
		'AU' => array( 'Australia', 'j F Y', array( 'Australia/Sydney', 'Australia/Melbourne', 'Australia/Brisbane', 'Australia/Adelaide', 'Australia/Perth', 'Australia/Hobart', 'Australia/Darwin' ) ),
		'NZ' => array( 'New Zealand', 'j F Y', array( 'Pacific/Auckland', 'Pacific/Chatham' ) ),
		'GB' => array( 'United Kingdom', 'j F Y', array( 'Europe/London' ) ),
		'IE' => array( 'Ireland', 'j F Y', array( 'Europe/Dublin' ) ),
		'US' => array( 'United States', 'F j, Y', array( 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles', 'America/Anchorage', 'Pacific/Honolulu' ) ),
		'CA' => array( 'Canada', 'F j, Y', array( 'America/St_Johns', 'America/Halifax', 'America/Toronto', 'America/Winnipeg', 'America/Edmonton', 'America/Vancouver' ) ),
		'ZA' => array( 'South Africa', 'j F Y', array( 'Africa/Johannesburg' ) ),
		'IN' => array( 'India', 'j F Y', array( 'Asia/Kolkata' ) ),
		'SG' => array( 'Singapore', 'j F Y', array( 'Asia/Singapore' ) ),
		'MY' => array( 'Malaysia', 'j F Y', array( 'Asia/Kuala_Lumpur' ) ),
		'HK' => array( 'Hong Kong', 'j F Y', array( 'Asia/Hong_Kong' ) ),
		'JP' => array( 'Japan', 'Y F j', array( 'Asia/Tokyo' ) ),
		'CN' => array( 'China', 'Y F j', array( 'Asia/Shanghai' ) ),
		'KR' => array( 'South Korea', 'Y F j', array( 'Asia/Seoul' ) ),
		'TH' => array( 'Thailand', 'j F Y', array( 'Asia/Bangkok' ) ),
		'ID' => array( 'Indonesia', 'j F Y', array( 'Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura' ) ),
		'PH' => array( 'Philippines', 'F j, Y', array( 'Asia/Manila' ) ),
		'VN' => array( 'Vietnam', 'j F Y', array( 'Asia/Ho_Chi_Minh' ) ),
		'AE' => array( 'United Arab Emirates', 'j F Y', array( 'Asia/Dubai' ) ),
		'SA' => array( 'Saudi Arabia', 'j F Y', array( 'Asia/Riyadh' ) ),
		'IL' => array( 'Israel', 'j F Y', array( 'Asia/Jerusalem' ) ),
		'TR' => array( 'Turkey', 'j F Y', array( 'Europe/Istanbul' ) ),
		'DE' => array( 'Germany', 'j. F Y', array( 'Europe/Berlin' ) ),
		'AT' => array( 'Austria', 'j. F Y', array( 'Europe/Vienna' ) ),
		'CH' => array( 'Switzerland', 'j. F Y', array( 'Europe/Zurich' ) ),
		'FR' => array( 'France', 'j F Y', array( 'Europe/Paris' ) ),
		'BE' => array( 'Belgium', 'j F Y', array( 'Europe/Brussels' ) ),
		'NL' => array( 'Netherlands', 'j F Y', array( 'Europe/Amsterdam' ) ),
		'ES' => array( 'Spain', 'j F Y', array( 'Europe/Madrid', 'Atlantic/Canary' ) ),
		'PT' => array( 'Portugal', 'j F Y', array( 'Europe/Lisbon', 'Atlantic/Azores' ) ),
		'IT' => array( 'Italy', 'j F Y', array( 'Europe/Rome' ) ),
		'GR' => array( 'Greece', 'j F Y', array( 'Europe/Athens' ) ),
		'PL' => array( 'Poland', 'j F Y', array( 'Europe/Warsaw' ) ),
		'CZ' => array( 'Czechia', 'j. F Y', array( 'Europe/Prague' ) ),
		'SE' => array( 'Sweden', 'j F Y', array( 'Europe/Stockholm' ) ),
		'NO' => array( 'Norway', 'j F Y', array( 'Europe/Oslo' ) ),
		'DK' => array( 'Denmark', 'j F Y', array( 'Europe/Copenhagen' ) ),
		'FI' => array( 'Finland', 'j F Y', array( 'Europe/Helsinki' ) ),
		'BR' => array( 'Brazil', 'j F Y', array( 'America/Sao_Paulo', 'America/Manaus', 'America/Fortaleza' ) ),
		'MX' => array( 'Mexico', 'j F Y', array( 'America/Mexico_City', 'America/Tijuana', 'America/Monterrey' ) ),
		'AR' => array( 'Argentina', 'j F Y', array( 'America/Argentina/Buenos_Aires' ) ),
		'CL' => array( 'Chile', 'j F Y', array( 'America/Santiago' ) ),
		'CO' => array( 'Colombia', 'j F Y', array( 'America/Bogota' ) ),
		'NG' => array( 'Nigeria', 'j F Y', array( 'Africa/Lagos' ) ),
		'KE' => array( 'Kenya', 'j F Y', array( 'Africa/Nairobi' ) ),
		'EG' => array( 'Egypt', 'j F Y', array( 'Africa/Cairo' ) ),
	);

	/**
	 * Filter the country list, so a site can add or trim entries.
	 *
	 * @param array $map Country map.
	 */
	return apply_filters( 'gpcd_countries', $map );
}

/**
 * Build the payload handed to the editor script.
 *
 * @return array
 */
function gpcd_editor_country_payload() {
	$out = array();

	foreach ( gpcd_countries() as $code => $row ) {
		$out[] = array(
			'code'      => $code,
			'label'     => $row[0],
			'format'    => $row[1],
			'timezones' => $row[2],
		);
	}

	usort(
		$out,
		static function ( $a, $b ) {
			return strcmp( $a['label'], $b['label'] );
		}
	);

	return $out;
}

/**
 * Every timezone identifier, for the "Other" escape hatch.
 *
 * @return array
 */
function gpcd_all_timezones() {
	return timezone_identifiers_list();
}

/**
 * Resolve the country a block should default to, based on the site timezone.
 *
 * @return string Country code, or empty string when nothing matches.
 */
function gpcd_guess_country() {
	$site_tz = wp_timezone_string();

	foreach ( gpcd_countries() as $code => $row ) {
		if ( in_array( $site_tz, $row[2], true ) ) {
			return $code;
		}
	}

	return '';
}
