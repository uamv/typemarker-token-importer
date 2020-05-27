<?php
/*
Plugin Name: Typemarker
Plugin URI: https://typemarker.com
Description: Allow custom branding of MainWP dashboard and professional reporting
Version: 1.2.0
Author: Typewheel
Author URI: http://typewheel.xyz
License: GPL-2.0+

Branding My MainWP Dashboard was built to allow further customization of the MainWP dashboard and provide premium Pro Reports

------------------------------------------------------------------------
Copyright 2020 Typewheel, LLC

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

See http://www.gnu.org/licenses.
*/

/**
 * Define plugins globals.
 */

define( 'TYPEMARKER_VERSION', '1.2.0' );
define( 'TYPEMARKER_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'TYPEMARKER_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'TYPEMARKER_STORE_URL', 'https://typemarker.com' );

use Typemarker\PHPColors;

/**
 * Get instance of class if in admin.
 */

if ( is_admin() ) {
	Typemarker::get_instance();
}

/**
 * Typemarker Class
 *
 *
 * @package Typemarker
 * @author  uamv
 */
class Typemarker {

	/*---------------------------------------------------------------------------------*
	 * Attributes
	 *---------------------------------------------------------------------------------*/

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Dashboard specific options
	 *
	 * @var      array
	 */
	protected $dashboard = array();

	/**
	 * Report specific options
	 *
	 * @var      array
	 */
	protected $reports = array();

	/**
	 * EDD plugin status.
	 *
	 * @var      array
	 */
	public $plugin = false;

   /**
	 * Installed templates
	 *
	 * @var      array
	 */
	public $templates = [];

   /**
   * Available tokens
   *
   * @var      array
   */
   public $tokens = array();

   /**
   * Available sites
   *
   * @var      array
   */
   public $sites = array();

	/*---------------------------------------------------------------------------------*
	 * Consturctor
	 *---------------------------------------------------------------------------------*/

	/**
	 * Initialize the plugin by setting localization, filters, and administration functions.
	 */
	private function __construct() {

		// include libraries
		if ( ! class_exists( 'ACF' ) )
		    require_once( TYPEMARKER_DIR_PATH . 'includes/acf/acf.php' );

      require_once( 'includes/EDD_Client/EDD_Client_Init.php' );
      $this->plugin = new EDD_Client_Init( __FILE__, TYPEMARKER_STORE_URL );

      if ( ! class_exists( 'Color' ) )
		    require_once( TYPEMARKER_DIR_PATH . 'includes/color.php' );

       // check available tokens, sites, and templates
       add_action( 'admin_init', [ $this, 'initialize_variables' ] );

       // add tokens used in custom pro reports
       add_action( 'admin_init', [ $this, 'add_tokens' ] );

		// hide acf management
		add_filter( 'acf/settings/show_admin', '__return_false' );

		// register setting page and field group
		add_action( 'acf/init', [ $this, 'register_acf_field_group' ], 20 );
      add_action( 'acf/render_field/key=field_5e46a627a22f2', [ $this, 'template_teaser' ] );
		add_action( 'acf/init', [ $this, 'add_acf_options_page' ], 20 );

      // require license to use token importer
      if ( $this->plugin->is_premium() ) {
         add_action( 'admin_menu', [ $this, 'add_menu_pages' ], 99 );
      }

      // set report paper size
      add_filter( 'mainwp_pro_reports_pdf_paper_format', function( $format ) {

         $format = get_field( 'typemarker_reports_pdf_paper_format', 'option' ) ? get_field( 'typemarker_reports_pdf_paper_format', 'option' ) : 'a4';

         return $format;

      });

      // set report paper orientation
      add_filter( 'mainwp_pro_reports_pdf_paper_orientation', function( $orientation ) {

         $orientation = get_field( 'typemarker_reports_pdf_paper_orientation', 'option' ) ? get_field( 'typemarker_reports_pdf_paper_orientation', 'option' ) : 'a4';

         return $orientation;

      });

		add_action( 'admin_print_styles', [ $this, 'add_stylesheets_and_javascript' ], 100 );

      // add_action( 'admin_footer', [ $this, 'test_colors' ] ); // for use in development of new reports

	} // end constructor

	/*---------------------------------------------------------------------------------*
	 * Public Functions
	 *---------------------------------------------------------------------------------*/

	/**
	 * Return an instance of this class.
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		} // end if

		return self::$instance;

	} // end get_instance

   public function initialize_variables() {

      $tokens = MainWP_Pro_Reports_DB::get_instance()->get_tokens();
      $this->tokens = array();
      foreach ( $tokens as $token ) {
         $this->tokens[ $token->token_name ] = $token->id;
      }

      $site_sql = MainWP_DB::Instance()->query( MainWP_DB::Instance()->getSQLSearchWebsitesForCurrentUser( null ) );
      $this->sites = array();
      while ( $site_sql && ( $site = @MainWP_DB::fetch_object( $site_sql ) ) ) {
         $this->sites[ $site->url ] = $site->id;
      }

      $templates = array_values( MainWP_Pro_Reports::get_instance()->get_template_files() );

      foreach ( $templates as $template ) {
         if ( strpos( $template, 'typemarker' ) !== false ) {
            $this->templates[] = $template;
         }
      }

      // get and set styling options
		$this->dashboard = array(
			'color_primary' => get_field( 'typemarker_dashboard_color_theme_primary', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_theme_primary', 'option' ) ) : $this->color( '#7fb100' ),
			'color_secondary' => get_field( 'typemarker_dashboard_color_theme_secondary', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_theme_secondary', 'option' ) ) : $this->color( '#e0e1e2' ),
			'color_base' => get_field( 'typemarker_dashboard_color_theme_base', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_theme_base', 'option' ) ) : $this->color( '#1c1d1b' ),
			'color_good' => get_field( 'typemarker_dashboard_color_accent_good', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_accent_good', 'option' ) ) : $this->color( '#7fb100' ),
			'color_attention' => get_field( 'typemarker_dashboard_color_accent_attention', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_accent_attention', 'option' ) ) : $this->color( '#fbbd08' ),
			'color_critical' => get_field( 'typemarker_dashboard_color_accent_critical', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_accent_critical', 'option' ) ) : $this->color( '#a00000' ),
			'color_black_pseudo' => get_field( 'typemarker_dashboard_color_grayscale_black_pseudo', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_grayscale_black_pseudo', 'option' ) ) : $this->color( '#070707' ),
			'color_gray' => get_field( 'typemarker_dashboard_color_grayscale_gray', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_grayscale_gray', 'option' ) ) : $this->color( '#aeaea8' ),
			'color_white_pseudo' => get_field( 'typemarker_dashboard_color_grayscale_white_pseudo', 'option' ) ? $this->color( get_field( 'typemarker_dashboard_color_grayscale_white_pseudo', 'option' ) ) : $this->color( '#fafafa' ),
			'logo' => get_field( 'typemarker_dashboard_logo', 'option' ) ? wp_get_attachment_url( get_field( 'typemarker_dashboard_logo', 'option' ) ) : null,
			'hide' => array(
				'tooltips' => get_field( 'typemarker_dashboard_hide', 'option' ) ? in_array( 'Tooltips', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'screenoptions' => get_field( 'typemarker_dashboard_hide', 'option' ) ? in_array( 'Screen Options', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'documentation' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Documentation', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'account' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Account', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'posts' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Posts', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'pages' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Pages', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'users' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Users', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'settings' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Settings', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'status' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Status', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
				'logo' => get_field( 'typemarker_dashboard_hide', 'option') ? in_array( 'Logo', get_field( 'typemarker_dashboard_hide', 'option' ) ) : false,
			),
		);

      $this->reports = array(
         'color_scheme' => get_field( 'typemarker_reports_color_scheme', 'option' ) ? get_field( 'typemarker_reports_color_scheme', 'option' ) : 'report'
      );

   }

   public function template_teaser( $field ) {

      echo 'You currently have ' . sprintf( _n( '%s custom template', '%s custom templates', count( $this->templates ), 'typemarker' ), count( $this->templates ) ) . ' available. Find more at <a href="https://typemarker.com">typemarker.com</a>.';

   }

   // generate color variations
   public function generate_color_variant_css( $color, $name ) {

      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-lightest: #' . $color->mix( $this->dashboard['color_white_pseudo']->getHex(), -80 ) . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-lighter: #' . $color->mix( $this->dashboard['color_white_pseudo']->getHex(), -70 ) . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-light: #' . $color->mix( $this->dashboard['color_white_pseudo']->getHex(), -50 ) . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . ': #' . $color->getHex() . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-dark: #' . $color->mix( $this->dashboard['color_black_pseudo']->getHex(), 80 ) . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-darker: #' . $color->mix( $this->dashboard['color_black_pseudo']->getHex(), 70 ) . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-darkest: #' . $color->mix( $this->dashboard['color_black_pseudo']->getHex(), 50 ) . ';';
      echo '--typemarker-color-' . str_replace( '_', '-', $name ) . '-contrast: #' . $this->hex_color_contrast( $color ) . ';';

   }

   // generate color variations
   public function color_variants( $color ) {

      $colors = array(
         'lightest' => '#' . $color->mix( 'ffffff', -88 ),
         'lighter' => '#' . $color->mix( 'ffffff', -30 ),
         'light' => '#' . $color->mix( 'ffffff', 40 ),
         'color' => '#' . $color->getHex(),
         'dark' => '#' . $color->mix( '000000', 60 ),
         'darker' => '#' . $color->mix( '000000', 0 ),
         'darkest' => '#' . $color->mix( '000000', -48 ),
         'lightest-contrast' => '#' . $this->hex_color_contrast( $this->color( $color->mix( 'ffffff', -88 ) ) ),
         'lighter-contrast' => '#' . $this->hex_color_contrast( $this->color( $color->mix( 'ffffff', 0 ) ) ),
         'light-contrast' => '#' . $this->hex_color_contrast( $this->color( $color->mix( 'ffffff', 50 ) ) ),
         'color-contrast' => '#' . $this->hex_color_contrast( $color ),
         'dark-contrast' => '#' . $this->hex_color_contrast( $this->color( $color->mix( '000000', 50 ) ) ),
         'darker-contrast' => '#' . $this->hex_color_contrast( $this->color( $color->mix( '000000', 0 ) ) ),
         'darkest-contrast' => '#' . $this->hex_color_contrast( $this->color( $color->mix( '000000', -88 ) ) ),
      );

      return $colors;

   }

   // generate color variations
   public function dashboard_colors() {

      $colors = array(
         'primary' => $this->color_variants( $this->dashboard['color_primary'], 'primary' ),
         'secondary' => $this->color_variants( $this->dashboard['color_secondary'], 'secondary' ),
         'base' => $this->color_variants( $this->dashboard['color_base'], 'base' ),
         'black' => $this->color_variants( $this->dashboard['color_black_pseudo'], 'black' ),
         'gray' => $this->color_variants( $this->dashboard['color_gray'], 'gray' ),
         'white' => $this->color_variants( $this->dashboard['color_white_pseudo'], 'white' ),
         'good' => $this->color_variants( $this->dashboard['color_good'], 'good' ),
         'attention' => $this->color_variants( $this->dashboard['color_attention'], 'attention' ),
         'critical' => $this->color_variants( $this->dashboard['color_critical'], 'critical' ),
      );

      return $colors;

   }

   public function set_color( $colors, $sources ) {

      switch ( $this->reports['color_scheme'] ) {
         case 'dashboard':
            $source = $sources[1];
            break;

         case 'client':
            $source = $sources[2];
            break;

         default:
            $source = $sources[0];
            break;
      }

      return $colors[ $source[0] ][ $source[1] ];

   }

	// Print styles to header
	public function add_stylesheets_and_javascript() {

		?>

		<script id="typemarker" type="text/javascript">

			var typemarkerHideMenu = [];

			document.addEventListener("DOMContentLoaded",function(){

				<?php if ( $this->dashboard['hide']['posts'] ) { echo 'typemarkerHideMenu.push("PostBulkManage");'; } ?>
				<?php if ( $this->dashboard['hide']['pages'] ) { echo 'typemarkerHideMenu.push("PageBulkManage");'; } ?>
				<?php if ( $this->dashboard['hide']['users'] ) { echo 'typemarkerHideMenu.push("UserBulkManage");'; } ?>
				<?php if ( $this->dashboard['hide']['settings'] ) { echo 'typemarkerHideMenu.push("Settings");'; } ?>
				<?php if ( $this->dashboard['hide']['status'] ) { echo 'typemarkerHideMenu.push("ServerInformation");'; } ?>

				for ( var typemarkerHidden of typemarkerHideMenu ) {
					document.querySelector('#mainwp-main-menu a[href*="admin.php?page='+typemarkerHidden+'"]').parentElement.remove();
				}

			});

		</script>

		<style>
			:root {
				--typemarker-white-pseudo: #<?php echo $this->dashboard['color_white_pseudo']->getHex() ?>;
				--typemarker-black-pseudo: #<?php echo $this->dashboard['color_black_pseudo']->getHex() ?>;
            <?php
               $this->generate_color_variant_css( $this->dashboard['color_primary'], 'primary' );
               $this->generate_color_variant_css( $this->dashboard['color_secondary'], 'secondary' );
               $this->generate_color_variant_css( $this->dashboard['color_base'], 'base' );
               $this->generate_color_variant_css( $this->dashboard['color_good'], 'good' );
               $this->generate_color_variant_css( $this->dashboard['color_attention'], 'attention' );
               $this->generate_color_variant_css( $this->dashboard['color_critical'], 'critical' );
            ?>
				--typemarker-color-base-contrast-opacity: #<?php echo $this->hex_color_contrast( $this->dashboard['color_base'] ); ?>88;
			}

			body.mainwp-ui a {
			    color: var(--typemarker-color-primary-darkest);
			}

			<?php
				echo '.typemarker-start-hidden';
				if ( $this->dashboard['hide']['tooltips'] ) { echo ',body.mainwp-ui *[data-tooltip]::after,body.mainwp-ui *[data-tooltip]::before,#mainwp-pro-reports-template-selection-info,#mainwp-pro-reports-email-template-selection-info'; }
				if ( $this->dashboard['hide']['screenoptions'] ) { echo ',a[onclick*="screen-options-modal"]'; }
				if ( $this->dashboard['hide']['documentation'] ) { echo ',#mainwp-help-sidebar'; }
				if ( $this->dashboard['hide']['account'] ) { echo ',a[href*="mainwp.com/my-account"]'; }
				if ( $this->dashboard['hide']['logo'] ) { echo ',#mainwp-logo'; }
				echo '{display: none!important;}';
			?>

			body.mainwp-ui .mainwp-nav-wrap,
			body.mainwp-ui .ui.inverted.menu {
				background-color: var(--typemarker-color-base);
			}

			<?php if ( $this->dashboard['logo'] !== null ) : ?>

				body.mainwp-ui #mainwp-logo {
					background-image: url('<?php echo $this->dashboard['logo']; ?>');
					background-size: contain;
					background-repeat: no-repeat;
					background-position: center;
				}

				@media screen and ( max-width: 768px ) {
					body.mainwp-ui .mainwp-nav-wrap {
						padding-bottom: 15px;
					}
					body.mainwp-ui #mainwp-logo {
						background-position: 15px 15px;
						background-size: 125px;
					}
				}

				body.mainwp-ui #mainwp-logo {
					height: 40px;
				}
				body.mainwp-ui #mainwp-logo img {
					display: none;
				}
			<?php endif; ?>

			body.mainwp-ui .ui.vertical.menu > .item {
			    cursor: pointer;
			}

			body.mainwp-ui .ui.inverted.menu .item, .ui.inverted.menu .item>a:not(.ui) {
				color: var(--typemarker-color-base-contrast);
			}
			body.mainwp-ui .ui.vertical.inverted.menu .menu .item, .ui.vertical.inverted.menu .menu .item a:not(.ui) {
				color: var(--typemarker-color-base-contrast-opacity);
			}
			body.mainwp-ui .ui.vertical.inverted.menu .item .menu .link.item:hover, .ui.vertical.inverted.menu .item .menu a.item:hover {
				color: var(--typemarker-color-base-contrast);
			}

			body.mainwp-ui .ui.vertical.menu .item a.title b {
			    font-weight: normal;
			}

			body.mainwp-ui #mainwp-top-header .mainwp-page-title {
				font-size: 135%;
			}

			body.mainwp-ui .ui.button {
				background-color: var(--typemarker-color-secondary);
				color: var(--typemarker-color-secondary-contrast);
			}
			body.mainwp-ui .ui.button:hover {
			    background-color: var(--typemarker-color-secondary-dark);
			}

			body.mainwp-ui .ui.green.button,
			body.mainwp-ui .ui.green.buttons .button {
			    background-color: var(--typemarker-color-primary);
			    color: var(--typemarker-color-primary-contrast);
			    border-color: var(--typemarker-color-primary);
			}

			.ui.inverted.green.segment {
			    background-color: var(--typemarker-color-primary) !important;
			    color: var(--typemarker-color-primary-contrast);
			}

			body.mainwp-ui .ui.green.button:hover,
			body.mainwp-ui .ui.green.buttons .button:hover {
				background-color: var(--typemarker-color-primary-dark);
			}

			body.mainwp-ui .ui.basic.green.button, .ui.basic.green.buttons .button {
				color: var(--typemarker-color-primary) !important;
				box-shadow: inset 0 0 0 1px var(--typemarker-color-primary) !important;
			}
			body.mainwp-ui .ui.basic.green.button:hover, .ui.basic.green.buttons .button:hover {
				color: var(--typemarker-color-primary-darker) !important;
				box-shadow: inset 0 0 0 1px var(--typemarker-color-primary-darker) !important;
			}

			body.mainwp-ui .ui.grid>.column:not(.row), .ui.grid>.row>.column {
			    padding-left: 8px;
			    padding-right: 8px;
			}

			body.mainwp-ui .mainwp-widget {
			    padding: 23px;
			}

			body.mainwp-ui  .ui.segment {
			    overflow: hidden;
			}

			body.mainwp-ui  .ui.segment > .ui.grid:first-child {
			    border-bottom: 1px solid #eee;
			    margin-bottom: 10px;
			}

			body.mainwp-ui .ui.message {
			    border-radius: 5px;
			    margin-bottom: 16px;
			}
         body.mainwp-ui .ui.message.info {
            background-color: var(--typemarker-color-secondary-lighter);
         }

			body.mainwp-ui #mainwp-add-new-buttons .ui.button {
			    background-color: var(--typemarker-color-secondary);
			    color: var(--typemarker-color-secondary-contrast);
			}

			body.mainwp-ui #mainwp-add-new-buttons .ui.button:hover {
			    background-color: var(--typemarker-color-secondary-dark);
			}

			body.mainwp-ui h3.ui.header .sub.header {
			    margin-top: 10px;
			}

			body.mainwp-ui .ui.section.divider {
			    margin-top: 0;
			    margin-bottom: 0;
			}

			body.mainwp-ui .ui.green.menu .active.item,
			body.mainwp-ui .ui.menu .green.active.item {
				border-color: var(--typemarker-color-primary) !important;
				color: var(--typemarker-color-primary) !important;
			}

			body.mainwp-ui .ui.green.message {
				background-color: var(--typemarker-color-primary-lightest);
				box-shadow: 0 0 0 1px var(--typemarker-color-primary-lighter) inset, 0 0 0 0 transparent;
			}

			body.mainwp-ui .ui.green.message .header {
				color: var(--typemarker-color-primary-darkest);
			}

			body.mainwp-ui .ui.green.label,
			body.mainwp-ui .ui.green.labels .label {
			    background-color: var(--typemarker-color-good) !important;
			    border-color: var(--typemarker-color-good) !important;
			}

			body.mainwp-ui .ui.basic.green.label {
				background-color: #fff !important;
				border-color: var(--typemarker-color-good-darker) !important;
				color: var(--typemarker-color-good-darker) !important;
			}
			i.inverted.bordered.green.icon,
			i.inverted.circular.green.icon,
			i.inverted.green.icon {
				background-color: var(--typemarker-color-good) !important;
			}
			.ui.green.statistic>.value,
			.ui.green.statistics .statistic>.value,
			.ui.statistics .green.statistic>.value {
				color: var(--typemarker-color-good);
			}
			.aum_monitors_list .up,
			.statistics_table .up,
			.aum_upm_status.up,
			.aum_upm_color_info.up,
			.ui.green.progress .bar {
				background-color: var(--typemarker-color-good) !important;
				color: var(--typemarker-color-good-contrast);
			}

			i.green.icon,
         .ui.green.progress.success > label {
				color: var(--typemarker-color-good) !important;
			}

			body.mainwp-ui .ui.yellow.label,
			body.mainwp-ui .ui.yellow.labels .label {
			    background-color: var(--typemarker-color-attention) !important;
			    border-color: var(--typemarker-color-attention) !important;
			}
			body.mainwp-ui .ui.basic.yellow.label {
				background-color: #fff !important;
				border-color: var(--typemarker-color-attention-darker) !important;
				color: var(--typemarker-color-attention-darker) !important;
			}
			i.inverted.bordered.yellow.icon,
			i.inverted.circular.yellow.icon,
			i.inverted.yellow.icon {
				background-color: var(--typemarker-color-attention) !important;
			}
			.ui.yellow.statistic>.value,
			.ui.yellow.statistics .statistic>.value,
			.ui.statistics .yellow.statistic>.value {
				color: var(--typemarker-color-attention);
			}
			.aum_monitors_list .started,
			.statistics_table .started,
			.aum_upm_status.started,
			.aum_upm_color_info.started,
			.aum_monitors_list .seems_down,
			.statistics_table .seems_down,
			.aum_upm_status.seems_down,
			.aum_upm_color_info.seems_down {
				background-color: var(--typemarker-color-attention) !important;
				color: var(--typemarker-color-attention-contrast);
			}

			body.mainwp-ui .ui.red.label,
			body.mainwp-ui .ui.red.labels .label {
			    background-color: var(--typemarker-color-critical) !important;
			    border-color: var(--typemarker-color-critical) !important;
			}
			body.mainwp-ui .ui.basic.red.label {
				background-color: #fff !important;
				border-color: var(--typemarker-color-critical-darker) !important;
				color: var(--typemarker-color-critical-darker) !important;
			}
			i.inverted.bordered.red.icon,
			i.inverted.circular.red.icon,
			i.inverted.red.icon {
				background-color: var(--typemarker-color-critical) !important;
			}
			.ui.red.statistic>.value,
			.ui.red.statistics .statistic>.value,
			.ui.statistics .red.statistic>.value {
				color: var(--typemarker-color-critical);
			}
			.aum_monitors_list .down,
			.statistics_table .down,
			.aum_upm_status.down,
			.aum_upm_color_info.down{
				background-color: var(--typemarker-color-critical) !important;
				color: var(--typemarker-color-critical-contrast);
			}
			.ui.table td.error,
			.ui.table tr.error {
				background-color: var(--typemarker-color-critical-lightest) !important;
				color: var(--typemarker-color-critical) !important;
			}
			.ui.selectable.table tr.error:hover,
			.ui.selectable.table tr:hover td.error,
			.ui.table tr td.selectable.error:hover {
				background-color: var(--typemarker-color-critical-lighter) !important;
				color: var(--typemarker-color-critical) !important;
			}

			body.mainwp-ui .mainwp-ui .ui.toggle.checkbox input:checked ~ .box::before,
			body.mainwp-ui .mainwp-ui .ui.toggle.checkbox input:checked ~ label::before,
			body.mainwp-ui .ui.toggle.checkbox input:checked~.box:before,
			body.mainwp-ui .ui.toggle.checkbox input:checked~label:before {
			    background-color: var(--typemarker-color-primary) !important;
			}

			body.mainwp-ui .ui.form input:not([type]),
			.ui.form input[type=date],
			.ui.form input[type=datetime-local],
			.ui.form input[type=email], .ui.form input[type=file],
			.ui.form input[type=number], .ui.form input[type=password],
			.ui.form input[type=search], .ui.form input[type=tel],
			.ui.form input[type=text],
			.ui.form input[type=time],
			.ui.form input[type=url],
			.ui.selection.dropdown {
			    border: 1px solid rgba(34,36,38,.25);
			}

			body.mainwp-ui .ui.button {
			    /* color: rgba(0,0,0,.6); */
			}

			body.mainwp-ui #mainwp-top-header {
			    padding-left: 22px;
			    z-index: 1000;
			}

			body.mainwp-ui #mainwp-main-menu > .item:hover {
			    /* background: #191e23; */
			}

			body.mainwp-ui #mainwp-main-menu > .item:hover > a:not(.ui) {
			    /* color: #00b9eb; */
			}

			body.mainwp-ui .ui.positive.button, .ui.positive.buttons .button {
			    /*background-color: #006799;*/
			    color: #fff;
			}

			body.mainwp-ui #mainwp-message-zone {
			    margin-bottom: 30px;
			}

			body.mainwp-ui .ui.green.message, .ui.yellow.message {
			    color: #000;
			}

		</style>

	<?php }

	// Return color object for each color
	private function color( $hex ) {

		return new Typemarker\Mexitek\PHPColors\Color( $hex );

	}

	// Return contrasting text color for hex color
	public function hex_color_contrast( $color ) {

		if ( $color->isDark() ) {
			return $this->dashboard['color_white_pseudo']->getHex();
		} else {
			return $this->dashboard['color_black_pseudo']->getHex();
		}

	}

	// Register ACF options page
	public function add_acf_options_page() {

		if( function_exists( 'acf_add_options_page' ) ) {

         acf_add_options_page( array(
				'page_title' 	=> 'Typemarker',
				'menu_title'	=> 'Typemarker',
				'menu_slug' 	=> 'typemarker',
				'capability'    => 'manage_options',
            'position'     => 3,
            'icon_url'     => 'dashicons-art',
            'redirect'     => false
			) );

		}

	}

   // Register ACF options page
   public function add_menu_pages() {

      add_menu_page(
         'Typemarker Token Importer',
         'Token Importer',
         'manage_options',
         'typemarker-token-importer',
         [ $this, 'render_token_importer_page' ],
         'dashicons-upload',
         3
      );

   }

	// Register ACF fields for settings page
	public function register_acf_field_group() {

      require_once( TYPEMARKER_DIR_PATH . 'assets/acf-field-groups.php' );

	}

   public function render_token_importer_page() { ?>

      <div class="wrap">
         <h1>Import Tokens to MainWP Dashboard</h1>

         <!-- Form -->
         <form method='post' action='<?= $_SERVER['REQUEST_URI']; ?>' enctype='multipart/form-data'>
            <input type="file" name="token_file_import" >
            <input type="submit" name="token_import" value="Import" class="button button-primary">
         </form>

         <?php

         // Import CSV
         if( isset( $_POST['token_import'] ) ) {

            // File extension
            $extension = pathinfo( $_FILES['token_file_import']['name'], PATHINFO_EXTENSION );

            // If file extension is 'csv'
            if( ! empty( $_FILES['token_file_import']['name'] ) && $extension == 'csv' ) {

               $csvFile = fopen( $_FILES['token_file_import']['tmp_name'], 'r' ); // Open file in read mode

               $header = fgetcsv( $csvFile ); // process header row before looping through csv data
               array_shift( $header ); // remove Site header
               array_shift( $header ); // remove Type header
               $tokenNames = $header; // leaves token names we assign to var

               // clean up token headers
               foreach ( $tokenNames as &$token ) {
                  $token = trim( $token, ' []' );
               }

               $processed = array();
               $row = 1;

               // Read the csv file
               while( ( $csvData = fgetcsv( $csvFile ) ) !== FALSE ) {

                  $csvData = array_map( "utf8_encode", $csvData );

                  $processed[ $row ]['data'] = $csvData; // grab data we'll be processing
                  $site = trim( array_shift( $csvData ) ); // remove site column and assign to var
                  $tokenType = trim( array_shift( $csvData ) ); // remove token type column and assign to var

                  // Skip row if length doesn't match header length
                  if ( count( $csvData ) != count( $header ) ) {
                     $processed[ $row ]['status'] = 'fail-columns';
                     $row++;
                     continue;
                  }
                  // Skip row if site doesn't exist or is invalid
                  if ( ! array_key_exists( $site, $this->sites ) ) {
                     $processed[ $row ]['status'] = 'fail-site';
                     $row++;
                     continue;
                  }

                  // check token type and process
                  if ( $tokenType == 'Pro Reports' ) {

                     $expunge = MainWP_Pro_Reports_DB::get_instance()->delete_site_tokens( null, (int) $this->sites[ $site ] ); // remove all existing pro report tokens from site

                     // loop through the columns of csv row
                     foreach ( $csvData as $key => $tokenValue ) {

                        // if available token, then let's add value from csv
                        if ( isset( $this->tokens[ $tokenNames[ $key ] ] ) ) {

                           $imported = MainWP_Pro_Reports_DB::get_instance()->add_token_site( (int) $this->tokens[ $tokenNames[ $key ] ], $tokenValue, (int) $this->sites[ $site ] );

                           if ( $imported !== false ) {
                              $processed[ $row ]['status'][ $key ] = 'success';
                           } else {
                              $processed[ $row ]['status'][ $key ] = $tokenNames[ $key ];
                           }

                        } else {

                           $processed[ $row ]['status'][ $key ] = $tokenNames[ $key ];

                        }

                     }

                  } else {

                     $processed[ $row ]['status'] = 'fail-type';

                  }

                  $row++; // increment the row for our reporting records

               }

               // get and report count for imported and failed tokens
               $count_imported_tokens = 0;
               $count_failed_tokens = 0;

               foreach ( $processed as $row => $result ) {
                  if ( is_array( $result['status'] ) ) {
                     foreach ( $result['status'] as $key => $status ) {
                        $status == 'success' ? $count_imported_tokens++ : $count_failed_tokens++;
                     }
                  } else {
                     $count_failed_tokens = $count_failed_tokens + count( $tokenNames );
                  }
               }

               echo "<h3 style='color: green;'>Total tokens imported : " . $count_imported_tokens . "</h3>";

               if ( $count_failed_tokens > 0 ) {
                  echo "<h3 style='color: red;'>Failed to import : " . $count_failed_tokens . "</h3>";
               }

               // render the results table ?>

               <table class="wp-list-table widefat fixed striped">
                  <thead>
                     <tr>
                        <th style="width: 50px;">Status</th>
                        <th style="width: 50px;">Row #</th>
                        <th>Site</th>
                        <th>Failure</th>
                        <th>Data</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php
                     foreach ( $processed as $row => $result ) {

                        // remove site and token columns from data, assigning site
                        $site = array_shift( $result['data'] );
                        array_shift( $result['data'] );

                        // set error message for failure type
                        if ( ! is_array( $result['status'] ) ) {

                           switch ( $result['status'] ) {
                              case 'fail-columns':
                              $error = 'Column Mismatch';
                              break;
                              case 'fail-site':
                              $error = 'Invalid Site';
                              break;
                              case 'fail-type':
                              $error = 'Invalid Token Type';
                              break;
                              default:
                              $error = 'Error';
                              break;
                           }

                           echo '<tr class="fail-row">';
                              echo '<td><span class="dashicons dashicons-warning" style="color:red;"></span></td>';
                              echo '<td>' . $row . '</td>';
                              echo '<td>' . $site . '</td>';
                              echo '<td>' . $error . '</td>';
                              echo '<td>' . implode( ',', $result['data'] ) . '</td>';
                           echo '</tr>';

                        } else {

                           // get token success count
                           $tokenResult = array_count_values( $result['status'] );
                           // get the token failures
                           $tokenFailures = array_diff( $result['status'], array( 'success' ) );

                           if ( $tokenResult['success'] == count( $result['status'] ) ) {
                              echo '<tr>';
                                 echo '<td><span class="dashicons dashicons-yes" style="color:green;"></span></td>';
                                 echo '<td>' . $row . '</td>';
                                 echo '<td>' . $site . '</td>';
                                 echo '<td></td>';
                                 echo '<td>' . implode( ',', $result['data'] ) . '</td>';
                              echo '</tr>';
                           } else {
                              echo '<tr class="fail-tokens">';
                                 echo '<td><span class="dashicons dashicons-warning" style="color:orange;"></span></td>';
                                 echo '<td>' . $row . '</td>';
                                 echo '<td>' . $site . '</td>';
                                 echo '<td>Token Failures:<br />' . implode( '<br />', $tokenFailures ) . '</td>';
                                 echo '<td>' . implode( ',', $result['data'] ) . '</td>';
                              echo '</tr>';
                           }

                        }
                     }
                     ?>
                  </tbody>
               </table>

               <?php

            } else {

               echo "<h3 style='color: red;'>Please choose a .csv file.</h3>";

            }

         }

         echo '</div>';

      }

   public function add_tokens() {

      $tokens = array(
         'client.logo.url' => 'Display client logo image via full URL.',
         'client.icon.url' => 'Display client icon image via full URL.',
         'client.site.domain' => 'Display site domain w/o http(s):// protocol',
         'client.contact.nickname' => 'Display the client contact nickname',
         'client.brand.color.primary' => 'Primary brand color for client (hex)',
         'client.brand.color.accent' => 'Brand color accent for client (hex)'
      );

      require_once( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'mainwp-pro-reports-extension/class/class-mainwp-pro-reports-db.php' );

      foreach ( $tokens as $name => $description ) {
         $token = array(
            'token_name' => $name,
            'token_description' => $description
         );
         MainWP_Pro_Reports_DB::get_instance()->add_token( $token );
      }

   }

   public function test_colors() {

      $colors = [
         'primary' => $this->dashboard['color_primary'],
         'secondary' => $this->dashboard['color_secondary'],
         'base' => $this->dashboard['color_base'],
         'good' => $this->dashboard['color_good'],
         'attention' => $this->dashboard['color_attention'],
         'critical' => $this->dashboard['color_critical'],
         'white' => $this->dashboard['color_white_pseudo'],
         'gray' => $this->dashboard['color_gray'],
         'black' => $this->dashboard['color_black_pseudo'],
         'brand' => $this->color('#e1b653')
      ];

      echo '<div id="mex-color" style="width: 90%; display: grid; grid-auto-flow: column; float: right;">';
      foreach ($colors as $name => $color) {

         $mex = $this->color_variants($color);

         echo '<div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['lightest'] . ';color: ' . $mex['lightest-contrast'] . ';">' . $name . '-lightest</div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['lighter'] . ';color: ' . $mex['lighter-contrast'] . ';">' . $name . '-lighter</div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['light'] . ';color: ' . $mex['light-contrast'] . ';">' . $name . '-light</div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['color'] . ';color: ' . $mex['color-contrast'] . ';">' . $name . '-color</div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['dark'] . ';color: ' . $mex['dark-contrast'] . ';">' . $name . '-dark</div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['darker'] . ';color: ' . $mex['darker-contrast'] . ';">' . $name . '-darker</div>';
            echo '<div style="padding: .5em; height: 30px;background-color: ' . $mex['darkest'] . ';color: ' . $mex['darkest-contrast'] . ';">' . $name . '-darkest</div>';
         echo '</div>';

      }
      echo '</div>';

   }

}
