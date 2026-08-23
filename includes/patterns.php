<?php
/**
 * Greenspage Countdown - bundled block patterns.
 *
 * @package Greenspage_Countdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the pattern category and the Launch Hero pattern.
 */
function gpcd_register_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'greenspage',
			array( 'label' => __( 'Greenspage', 'greenspage-countdown' ) )
		);
	}

	// A launch date ~6 weeks out, snapped to a Friday 10:00, so the inserted
	// pattern shows a live countdown immediately instead of 00:00:00:00.
	$seed = gpcd_pattern_seed_date();

	$countdown = array(
		'uid'           => 'launchhero',
		'mode'          => 'date',
		'targetDate'    => $seed,
		'timezone'      => wp_timezone_string(),
		'introText'     => 'Coming {weekday}, {date}',
		'onExpire'      => 'message',
		'expireMessage' => 'We’re live. Take a look around.',
		'accentColor'   => '#244d34',
		'layout'        => 'boxes',
		'showProgress'  => false,
	);

	$block = '<!-- wp:greenspage/countdown ' . wp_json_encode( $countdown ) . ' /-->';

	$content = '<!-- wp:group {"tagName":"section","className":"gp-launch-hero","layout":{"type":"constrained"}} -->'
		. '<section class="wp-block-group gp-launch-hero">'
		. '<!-- wp:heading {"level":1,"className":"gp-launch-title"} -->'
		. '<h1 class="wp-block-heading gp-launch-title">Something big is coming.</h1>'
		. '<!-- /wp:heading -->'
		. '<!-- wp:paragraph {"className":"gp-launch-lead"} -->'
		. '<p class="gp-launch-lead">Say what you are about to launch, and why it matters to the people waiting. Keep it to a sentence or two — the countdown does the rest.</p>'
		. '<!-- /wp:paragraph -->'
		. $block
		. '</section>'
		. '<!-- /wp:group -->';

	register_block_pattern(
		'greenspage/launch-hero',
		array(
			'title'         => __( 'Launch Hero + Countdown', 'greenspage-countdown' ),
			'description'   => __( 'A full-screen coming-soon hero with the Greenspage countdown. Change the date in the countdown block and edit the copy.', 'greenspage-countdown' ),
			'categories'    => array( 'greenspage' ),
			'keywords'      => array( 'countdown', 'launch', 'coming soon', 'hero', 'timer' ),
			'blockTypes'    => array( 'greenspage/countdown' ),
			'viewportWidth' => 1280,
			'content'       => $content,
		)
	);
}
add_action( 'init', 'gpcd_register_patterns', 20 );

/**
 * A sensible default launch moment for the pattern: the second Friday from
 * now at 10:00 in the site timezone, formatted for the datetime-local field.
 *
 * Kept deterministic (no randomness) so previews are stable.
 *
 * @return string
 */
function gpcd_pattern_seed_date() {
	try {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
	} catch ( Exception $e ) {
		return '2026-12-31T10:00';
	}

	$friday = $now->modify( 'friday +1 week' )->setTime( 10, 0 );

	return $friday->format( 'Y-m-d\TH:i' );
}
