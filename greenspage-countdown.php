<?php
/**
 * Plugin Name:       Greenspage Countdown
 * Plugin URI:        https://greenspage.com/plugins/countdown/
 * Description:       A countdown block that is quick to set up and honest about what happens when it runs out. Fixed-date and evergreen modes, country-aware date formats and timezones, optional progress bar, and real expiry behaviour.
 * Version:           1.1.4
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Greenspage Web & SEO
 * Author URI:        https://greenspage.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       greenspage-countdown
 *
 * @package Greenspage_Countdown
 */

defined( 'ABSPATH' ) || exit;

define( 'GPCD_VERSION', '1.1.4' );
define( 'GPCD_FILE', __FILE__ );
define( 'GPCD_DIR', plugin_dir_path( __FILE__ ) );
define( 'GPCD_URL', plugin_dir_url( __FILE__ ) );
define( 'GPCD_GH_REPO', 'GreensPage/greenspage-countdown' );

require_once GPCD_DIR . 'includes/countries.php';
require_once GPCD_DIR . 'includes/patterns.php';
require_once GPCD_DIR . 'includes/updater.php';

/**
 * Attribute schema. Shared by PHP and the editor.
 *
 * @return array
 */
function gpcd_attributes() {
	return array(
		'uid'               => array( 'type' => 'string', 'default' => '' ),
		'mode'              => array( 'type' => 'string', 'default' => 'date' ),
		'targetDate'        => array( 'type' => 'string', 'default' => '' ),
		'country'           => array( 'type' => 'string', 'default' => '' ),
		'timezone'          => array( 'type' => 'string', 'default' => '' ),
		'dateFormat'        => array( 'type' => 'string', 'default' => '' ),
		'timeFormat'        => array( 'type' => 'string', 'default' => 'g:ia' ),
		'evergreenHours'    => array( 'type' => 'number', 'default' => 48 ),
		'evergreenResetDays' => array( 'type' => 'number', 'default' => 0 ),
		'units'             => array( 'type' => 'array', 'default' => array( 'days', 'hours', 'minutes', 'seconds' ) ),
		'labelDays'         => array( 'type' => 'string', 'default' => 'Days' ),
		'labelHours'        => array( 'type' => 'string', 'default' => 'Hours' ),
		'labelMinutes'      => array( 'type' => 'string', 'default' => 'Minutes' ),
		'labelSeconds'      => array( 'type' => 'string', 'default' => 'Seconds' ),
		'introText'         => array( 'type' => 'string', 'default' => '' ),
		'showProgress'      => array( 'type' => 'boolean', 'default' => false ),
		'progressFrom'      => array( 'type' => 'string', 'default' => '' ),
		'onExpire'          => array( 'type' => 'string', 'default' => 'freeze' ),
		'expireMessage'     => array( 'type' => 'string', 'default' => '' ),
		'redirectUrl'       => array( 'type' => 'string', 'default' => '' ),
		'layout'            => array( 'type' => 'string', 'default' => 'boxes' ),
		'accentColor'       => array( 'type' => 'string', 'default' => '' ),
	);
}

/**
 * Register block, scripts and styles.
 */
function gpcd_register() {
	wp_register_script(
		'gpcd-editor',
		GPCD_URL . 'assets/js/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
		GPCD_VERSION,
		true
	);

	wp_localize_script(
		'gpcd-editor',
		'GPCD_DATA',
		array(
			'countries'    => gpcd_editor_country_payload(),
			'allTimezones' => gpcd_all_timezones(),
			'siteTimezone' => wp_timezone_string(),
			'guessCountry' => gpcd_guess_country(),
		)
	);

	wp_register_script(
		'gpcd-frontend',
		GPCD_URL . 'assets/js/frontend.js',
		array(),
		GPCD_VERSION,
		true
	);

	wp_register_style( 'gpcd-style', GPCD_URL . 'assets/css/frontend.css', array(), GPCD_VERSION );
	wp_register_style( 'gpcd-editor-style', GPCD_URL . 'assets/css/editor.css', array( 'gpcd-style' ), GPCD_VERSION );

	// Pattern styling. Loaded on the front end and in the editor so the
	// Launch Hero pattern looks right in both. Inert unless the pattern's
	// classes are present, so the cost on other pages is a tiny cached file.
	wp_register_style( 'gpcd-pattern-launch', GPCD_URL . 'assets/css/pattern-launch.css', array( 'gpcd-style' ), GPCD_VERSION );

	add_action(
		'wp_enqueue_scripts',
		static function () {
			wp_enqueue_style( 'gpcd-pattern-launch' );
		}
	);

	add_action( 'enqueue_block_editor_assets', static function () {
		wp_enqueue_style( 'gpcd-pattern-launch' );
	} );

	register_block_type(
		'greenspage/countdown',
		array(
			'api_version'     => 3,
			'title'           => __( 'Countdown', 'greenspage-countdown' ),
			'category'        => 'widgets',
			'icon'            => 'clock',
			'description'     => __( 'A countdown to a fixed date, or a rolling per-visitor timer.', 'greenspage-countdown' ),
			'keywords'        => array( 'countdown', 'timer', 'launch', 'evergreen', 'deadline' ),
			'supports'        => array(
				'align'  => array( 'wide', 'full' ),
				'anchor' => true,
				'html'   => false,
				'spacing' => array( 'margin' => true, 'padding' => true ),
			),
			'attributes'      => gpcd_attributes(),
			'editor_script'   => 'gpcd-editor',
			'editor_style'    => 'gpcd-editor-style',
			'style'           => 'gpcd-style',
			'view_script'     => 'gpcd-frontend',
			'render_callback' => 'gpcd_render',
		)
	);
}
add_action( 'init', 'gpcd_register' );

/**
 * Start the GitHub update checker (admin + cron only).
 */
function gpcd_boot_updater() {
	if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		new GPCD_Updater( GPCD_FILE, GPCD_GH_REPO, GPCD_VERSION );
	}
}
add_action( 'init', 'gpcd_boot_updater' );

/**
 * Open this plugin's "Visit plugin site" row link in a new tab.
 *
 * Scoped strictly to this plugin's row and to the Plugin URI link only, so it
 * never alters WordPress admin links globally or other plugins' rows.
 *
 * @param string[] $meta Plugin row meta links (HTML).
 * @param string   $file Plugin basename for the current row.
 * @return string[]
 */
function gpcd_plugin_row_meta( $meta, $file ) {
	if ( plugin_basename( GPCD_FILE ) !== $file ) {
		return $meta;
	}

	foreach ( $meta as $i => $html ) {
		// Only the "Visit plugin site" link points at the product page; the
		// author link points at the site root, so it is left untouched.
		if ( false !== strpos( $html, 'greenspage.com/plugins/countdown' ) ) {
			$meta[ $i ] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://greenspage.com/plugins/countdown/' ),
				esc_html__( 'Visit plugin site', 'greenspage-countdown' )
			);
		}
	}

	return $meta;
}
add_filter( 'plugin_row_meta', 'gpcd_plugin_row_meta', 10, 2 );

/**
 * Resolve the timezone a block should use.
 *
 * @param array $attr Block attributes.
 * @return DateTimeZone
 */
function gpcd_resolve_timezone( $attr ) {
	if ( ! empty( $attr['timezone'] ) ) {
		try {
			return new DateTimeZone( $attr['timezone'] );
		} catch ( Exception $e ) {
			return wp_timezone();
		}
	}

	return wp_timezone();
}

/**
 * Resolve the date format a block should use.
 *
 * @param array $attr Block attributes.
 * @return string
 */
function gpcd_resolve_format( $attr ) {
	if ( ! empty( $attr['dateFormat'] ) ) {
		return $attr['dateFormat'];
	}

	$countries = gpcd_countries();

	if ( ! empty( $attr['country'] ) && isset( $countries[ $attr['country'] ] ) ) {
		return $countries[ $attr['country'] ][1];
	}

	return (string) get_option( 'date_format', 'F j, Y' );
}

/**
 * Swap {date}, {time} and {weekday} tokens in a string.
 *
 * @param string        $text   Raw text.
 * @param DateTimeImmutable|null $target Target moment.
 * @param array         $attr   Block attributes.
 * @return string
 */
function gpcd_tokens( $text, $target, $attr ) {
	if ( '' === $text ) {
		return '';
	}

	if ( ! $target instanceof DateTimeImmutable ) {
		// Evergreen has no shared date, so tokens resolve to nothing rather than a lie.
		return trim( str_replace( array( '{date}', '{time}', '{weekday}' ), '', $text ) );
	}

	return str_replace(
		array( '{date}', '{time}', '{weekday}' ),
		array(
			wp_date( gpcd_resolve_format( $attr ), $target->getTimestamp(), $target->getTimezone() ),
			wp_date( $attr['timeFormat'] ? $attr['timeFormat'] : 'g:ia', $target->getTimestamp(), $target->getTimezone() ),
			wp_date( 'l', $target->getTimestamp(), $target->getTimezone() ),
		),
		$text
	);
}

/**
 * Keep the accent colour to something that can only ever be a colour.
 *
 * Hex, rgb()/rgba(), hsl()/hsla(), a bare keyword, or a CSS custom property
 * reference. Anything else is dropped rather than trusted.
 *
 * @param string $value Raw value.
 * @return string
 */
function gpcd_safe_color( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^(rgb|rgba|hsl|hsla)\(\s*[0-9a-zA-Z.,%\/\s-]+\)$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^var\(\s*--[a-zA-Z0-9-_]+\s*\)$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^[a-zA-Z]+$/', $value ) ) {
		return $value;
	}

	return '';
}

/**
 * Render the block.
 *
 * @param array    $attr    Attributes.
 * @param string   $content Inner content (unused).
 * @param WP_Block $block   Block instance.
 * @return string
 */
function gpcd_render( $attr, $content = '', $block = null ) {
	$attr = wp_parse_args( $attr, wp_list_pluck( gpcd_attributes(), 'default' ) );

	$tz         = gpcd_resolve_timezone( $attr );
	$evergreen  = 'evergreen' === $attr['mode'];
	$target     = null;
	$target_iso = '';

	if ( ! $evergreen ) {
		if ( empty( $attr['targetDate'] ) ) {
			// Nothing to count to. Render nothing rather than a row of zeroes.
			return '';
		}

		try {
			$target     = new DateTimeImmutable( $attr['targetDate'], $tz );
			$target_iso = $target->format( DateTimeInterface::ATOM );
		} catch ( Exception $e ) {
			return '';
		}
	}

	$progress_iso = '';

	if ( $attr['showProgress'] && ! $evergreen ) {
		if ( ! empty( $attr['progressFrom'] ) ) {
			try {
				$progress_iso = ( new DateTimeImmutable( $attr['progressFrom'], $tz ) )->format( DateTimeInterface::ATOM );
			} catch ( Exception $e ) {
				$progress_iso = '';
			}
		}

		if ( '' === $progress_iso ) {
			$post_id = get_the_ID();
			if ( $post_id ) {
				$progress_iso = (string) get_post_time( DateTimeInterface::ATOM, true, $post_id );
			}
		}
	}

	$uid = $attr['uid'] ? $attr['uid'] : 'gpcd';

	$config = array(
		'mode'      => $evergreen ? 'evergreen' : 'date',
		'target'    => $target_iso,
		'hours'     => (float) $attr['evergreenHours'],
		'resetDays' => (float) $attr['evergreenResetDays'],
		'from'      => $progress_iso,
		'onExpire'  => $attr['onExpire'],
		'redirect'  => $attr['redirectUrl'] ? esc_url_raw( $attr['redirectUrl'] ) : '',
		'uid'       => $uid,
		'units'     => array_values( (array) $attr['units'] ),
	);

	$classes = array( 'gp-cd', 'gp-cd--' . sanitize_html_class( $attr['layout'] ) );

	if ( $attr['showProgress'] ) {
		$classes[] = 'gp-cd--has-progress';
	}

	$wrapper_args = array( 'class' => implode( ' ', $classes ) );

	$accent = gpcd_safe_color( $attr['accentColor'] );

	if ( '' !== $accent ) {
		$wrapper_args['style'] = '--gp-cd-accent:' . $accent . ';';
	}

	$wrapper = get_block_wrapper_attributes( $wrapper_args );

	$labels = array(
		'days'    => $attr['labelDays'],
		'hours'   => $attr['labelHours'],
		'minutes' => $attr['labelMinutes'],
		'seconds' => $attr['labelSeconds'],
	);

	$units = array_values( array_intersect( array( 'days', 'hours', 'minutes', 'seconds' ), (array) $attr['units'] ) );

	if ( empty( $units ) ) {
		$units = array( 'days', 'hours', 'minutes', 'seconds' );
	}

	ob_start();
	?>
	<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> data-gpcd="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
		<?php if ( '' !== trim( (string) $attr['introText'] ) ) : ?>
			<p class="gp-cd__intro"><?php echo esc_html( gpcd_tokens( $attr['introText'], $target, $attr ) ); ?></p>
		<?php endif; ?>

		<div class="gp-cd__units" aria-hidden="true">
			<?php foreach ( $units as $unit ) : ?>
				<div class="gp-cd__unit" data-gpcd-unit="<?php echo esc_attr( $unit ); ?>">
					<span class="gp-cd__num">--</span>
					<span class="gp-cd__label"><?php echo esc_html( $labels[ $unit ] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $attr['showProgress'] ) : ?>
			<div class="gp-cd__progress" role="presentation">
				<span class="gp-cd__bar" data-gpcd-bar></span>
			</div>
		<?php endif; ?>

		<p class="gp-cd__sr" data-gpcd-sr aria-live="polite"></p>

		<?php if ( 'message' === $attr['onExpire'] ) : ?>
			<div class="gp-cd__expired" data-gpcd-expired hidden>
				<?php echo esc_html( gpcd_tokens( $attr['expireMessage'] ? $attr['expireMessage'] : __( 'This has now finished.', 'greenspage-countdown' ), $target, $attr ) ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
