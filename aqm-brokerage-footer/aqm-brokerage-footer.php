<?php
/**
 * Plugin Name: AQM Brokerage Identification
 * Description: Renders the RECO-required brokerage identification line on every page. Theme-independent, so it survives the Elementor exit.
 * Version:     1.4.0
 * Author:      A. Q. Mufti
 * Plugin URI:  https://github.com/AQMufti/aqm-brokerage-footer
 * License:     GPL-2.0-or-later
 *
 * WHY THIS EXISTS
 *
 * RECO Bulletin 5.1 requires a registrant's advertising to clearly and
 * prominently identify the brokerage, using the exact name registered with
 * RECO and carrying the descriptor "Brokerage". It applies to websites, not
 * just print. A database scan on 4 Sep 2026 found the brokerage named only in
 * the body copy of eight posts and two listing pages - in no header, footer or
 * template - which means it was absent from the homepage and from most of the
 * site.
 *
 * WHY A MUST-USE PLUGIN RATHER THAN THE FOOTER TEMPLATE
 *
 * The Elementor footer template is being removed. A compliance line that
 * vanishes halfway through a theme rebuild is worse than one that was never
 * added, because nobody notices its absence. This hooks wp_footer, so it
 * renders under Elementor, under Twenty Twenty-Five, and under whatever comes
 * after.
 *
 * ON STYLING
 *
 * RECO's guidance singles out tiny grey text under a large personal logo as
 * inviting scrutiny. So this is set at 0.875rem and inherits the surrounding
 * text colour rather than being forced to a pale grey. If it needs to be
 * larger, make it larger - do not make it smaller.
 *
 * ALSO AVAILABLE AS  [aqm_brokerage]  if you would rather place it by hand.
 */

defined( 'ABSPATH' ) || exit;

/*
 * THE UPDATER IS CONSTRUCTED FIRST, DELIBERATELY.
 *
 * In 1.1.0 and 1.2.0 the conversion guard ran BEFORE this block and returned
 * early, so AQM_Updater was never constructed - which removed the "Check for
 * updates" link from this plugin's row and left no way to update it except a
 * manual zip upload. A guard that disables the thing that would have fixed the
 * guard is a trap. Registering the updater first costs nothing (it only adds
 * filters) and keeps the plugin repairable however badly the rest goes wrong.
 */
define( 'AQM_BROKERAGE_FILE', __FILE__ );
define( 'AQM_BROKERAGE_VERSION', '1.4.0' );
define( 'AQM_BROKERAGE_GITHUB_REPO', 'AQMufti/aqm-brokerage-footer' );

// Shared GitHub-release updater - identical mechanism in every AQM plugin.
require_once __DIR__ . '/aqm-updater.php';
new AQM_Updater(
	__FILE__,
	AQM_BROKERAGE_VERSION,
	AQM_BROKERAGE_GITHUB_REPO,
	'AQM Brokerage Identification',
	'Renders the RECO-required brokerage identification line on every page.'
);

/*
 * THE CONVERSION GUARDS ARE GONE - 8 Sep 2026, and they are not coming back.
 *
 * This file carried two of them: one testing whether the old must-use copy was
 * still on disk, and one testing class_exists( 'AQM_Brokerage_ID' ) before
 * loading on. The second could NEVER be false, and it broke the plugin.
 *
 * PHP hoists unconditional top-level function and class declarations when a
 * file is included - they exist before the file's first statement runs. So by
 * the time that guard was evaluated, AQM_Brokerage_ID was already
 * defined BY THIS FILE, a few lines below. The guard returned every single
 * time, and nothing after it ever executed: no add_action, no add_shortcode,
 * no admin screen. The functions existed; none of them were ever hooked.
 *
 * That is why the RECO brokerage line was missing from the site, and why the
 * reviews stopped rendering, from the moment these plugins were converted.
 *
 * The file_exists() guard went too, because it cannot help either: if a
 * must-use copy declared these same symbols, PHP would fatal on the redeclare
 * as this file was included, long before any runtime check could return. The
 * only guard that would work is wrapping the whole file in
 * if ( ! class_exists( 'AQM_Brokerage_ID' ) ) - which is what AQM Form Spam
 * Guard does. The must-use copies are deleted and archived, so nothing here
 * needs guarding at all.
 */


/*
 * WHY THE DEACTIVATE LINK IS REMOVED
 *
 * As a must-use plugin this could not be switched off, and that was the point:
 * RECO Bulletin 5.1 requires the brokerage line on every page, and a
 * compliance line that disappears without anyone noticing is worse than one
 * that was never added. Converting to a regular plugin buys the shared updater
 * but reintroduces exactly that risk - one stray click on the Plugins screen
 * and the site is non-compliant, silently.
 *
 * So the Deactivate link is removed from this plugin's row. The escape hatch is
 * the same one the must-use version had: delete the plugin folder, or run
 *   wp plugin deactivate aqm-brokerage-footer
 * Both are deliberate acts. A misclick is not.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		unset( $links['deactivate'] );
		$links['aqm-reco'] = '<span style="color:#b32d2e">RECO-required &mdash; do not remove</span>';
		return $links;
	}
);

final class AQM_Brokerage_ID {

	/** Bump if the wording below changes, so caches are easy to reason about. */
	const VERSION = '1.4.0';

	/**
	 * The registered particulars. These are advertising-compliance content, not
	 * decoration - change them only against the brokerage's own records.
	 *
	 * 'name' must be the name registered with RECO. AQ confirmed on 4 Sep 2026
	 * that "A. Q. Mufti" IS the registered name, not a short form of one.
	 */
	private static function particulars() {
		return apply_filters( 'aqm_brokerage_particulars', array(
			'name'      => 'A. Q. Mufti',
			'role'      => 'Sales Representative',
			'brokerage' => 'RE/MAX Real Estate Centre Inc., Brokerage',
			'address'   => '141-1140 Burnhamthorpe Rd. W., Mississauga, ON L5C 4E9',
			'phone'     => '416 908 5600',
			'phone_uri' => '+14169085600',
			'email'     => 'info@aqmuftirealty.com',
		) );
	}

	/** Rendered at most once per request even if the shortcode is also used. */
	private static $done = false;

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_footer' ), 20 );
		add_shortcode( 'aqm_brokerage', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'aqm_copyright', array( __CLASS__, 'copyright' ) );
	}

	/**
	 * The footer copyright line, with the year taken from the clock.
	 *
	 * The Elementor footer had it hardcoded as 2024 and it went stale, which is
	 * what hardcoded years always do. The wording is AQ's, unchanged - the
	 * entity named in it is a legal one and is not mine to edit.
	 *
	 * Rendered through a shortcode rather than typed into the block, because a
	 * shortcode inside a core/paragraph is NOT expanded in a template part -
	 * only a core/shortcode block is.
	 */
	public static function copyright() {
		return esc_html(
			sprintf(
				'Copyright © %s A.Q.Mufti Personal Real Estate Corporation - All Rights Reserved',
				wp_date( 'Y' )
			)
		);
	}

	public static function render_footer() {
		if ( self::$done ) {
			return;
		}
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped below.
	}

	public static function shortcode( $atts = array() ) {
		return self::markup();
	}

	private static function markup() {

		self::$done = true;

		$p = self::particulars();

		$css = '
.aqm-brokerage-id{
	box-sizing:border-box;
	width:100%;
	margin:0;
	padding:1.25rem 1rem;
	font-size:.875rem;
	line-height:1.6;
	text-align:center;
	color:inherit;
	border-top:1px solid currentColor;
	border-color:color-mix(in srgb, currentColor 20%, transparent);
}
.aqm-brokerage-id p{margin:0 0 .35rem}
.aqm-brokerage-id p:last-child{margin-bottom:0}
.aqm-brokerage-id .aqm-brokerage-firm{font-weight:600}
.aqm-brokerage-id a{color:inherit;text-decoration:underline}
@media (max-width:480px){.aqm-brokerage-id{font-size:.8125rem}}
';

		$out  = '<style id="aqm-brokerage-id-css">' . $css . '</style>';
		$out .= '<div class="aqm-brokerage-id" role="contentinfo" aria-label="Brokerage identification">';

		/* Line 1: registrant, role, brokerage. This is the part RECO requires. */
		$out .= '<p>'
			. '<span class="aqm-brokerage-person">' . esc_html( $p['name'] ) . ', ' . esc_html( $p['role'] ) . '</span>'
			. ' &middot; '
			. '<span class="aqm-brokerage-firm">' . esc_html( $p['brokerage'] ) . '</span>'
			. '</p>';

		/* Line 2: how to reach the office. */
		$out .= '<p>'
			. esc_html( $p['address'] )
			. ' &middot; <a href="tel:' . esc_attr( $p['phone_uri'] ) . '">' . esc_html( $p['phone'] ) . '</a>'
			. ' &middot; <a href="mailto:' . esc_attr( $p['email'] ) . '">' . esc_html( $p['email'] ) . '</a>'
			. '</p>';

		$out .= '</div>';

		return $out;
	}
}

AQM_Brokerage_ID::init();
