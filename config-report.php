<?php

if ( ! defined( 'ABSPATH' ) )
   exit;

/******************************
* LOAD PREREQUISITES
*********/

use Typemarker\Mexitek\PHPColors;

require_once( TYPEMARKER_DIR_PATH . 'includes/color.php' );

$marker = Typemarker::get_instance();

/******************************
* CONFIGURE MAINWP TEMPLATING
*********/

$config_tokens = array(
   0 => '[hide-if-empty]',
   1 => '', // show report data
   2 => '[hide-section-data]'
);

// instantiate show/hide values for sections
$default_config = array(
   'wp-update' => 0,
   'plugins-updates' => 0,
   'themes-updates' => 0,
   'uptime' => 0,
   'security' => 0,
   'backups' => 0,
   'ga' => 0,
   'matomo' => 0,
   'pagespeed' => 0,
   'maintenance' => 0
);

$showhide_values = @json_decode( $report->showhide_sections, 1 );
if ( ! is_array( $showhide_values ) ) $showhide_values = array();

$showhide_values = array_merge($default_config, $showhide_values);

// set report content
$heading = $report->heading;
$intro = $report->intro;
$intro = nl2br($intro); // to fix
$outro = $report->outro;
$outro = nl2br($outro); // to fix

if ( ! empty( $report->logo_id ) ) {
   if ( ! empty( get_option( 'options_typemarker_reports_email_images_as' ) ) && get_option( 'options_typemarker_reports_email_images_as' ) == 'data' ) {
      $logo_path = get_attached_file( (int) $report->logo_id, true );
      $logo_type = pathinfo( $logo_path, PATHINFO_EXTENSION );
      $logo_file = file_get_contents( $logo_path );
      $logo_base64 = base64_encode( $logo_file );
      $logo = 'data:image/' . $logo_type . ';base64,' . $logo_base64;
   } else {
      $logo = wp_get_attachment_url( $report->logo_id );
   }
} else {
   $logo = '';
}

if ( ! empty( $report->header_image_id ) ) {
   if ( ! empty( get_option( 'options_typemarker_reports_email_images_as' ) ) && get_option( 'options_typemarker_reports_email_images_as' ) == 'data' ) {
      $header_image_path = get_attached_file( (int) $report->header_image_id, true );
      $header_image_type = pathinfo( $header_image_path, PATHINFO_EXTENSION );
      $header_image_file = file_get_contents( $header_image_path );
      $header_image_base64 = base64_encode( $header_image_file );
      $header_image = 'data:image/' . $header_image_type . ';base64,' . $header_image_base64;
   } else {
      $header_image = wp_get_attachment_url( $report->header_image_id );
   }
} else {
   $header_image = '';
}

/******************************
* CONFIGURE TYPEMARKER TEMPLATING
*********/

$site_id = ( ! is_null( $website ) && ! empty( $website ) ) ? $website['id'] : 0; // set id of the site being processed

$environment = [ 'template' => __FILE__, 'site' => $site_id ]; // set environment variables to be used in filters

$css = file_get_contents( TYPEMARKER_DIR_PATH . 'assets/css/report.css' );

$colors = $marker->dashboard_colors();

// instantiate proper report colors
$report_color['background'] = empty( $report->background_color ) ? '#ffffff' : $report->background_color;
$report_color['background'] = new Typemarker\Mexitek\PHPColors\Color( $report_color['background'] );
$report_color['text'] = empty( $report->text_color ) ? '#000000' : $report->text_color;
$report_color['text'] = new Typemarker\Mexitek\PHPColors\Color( $report_color['text'] );
$report_color['accent'] = empty( $report->accent_color ) ? '#e1b653' : $report->accent_color;
$report_color['accent'] = new Typemarker\Mexitek\PHPColors\Color( $report_color['accent'] );

// instantiate client brand colors
$brand_color['primary'] = MainWP_Pro_Reports_DB::get_instance()->get_tokens_by( 'token_name', 'client.brand.color.primary', $site_id );
$brand_color['primary'] = empty( $brand_color['primary']->site_token->token_value ) ? get_option( 'options_typemarker_reports_color_token_client_brand_color_primary_default' ) : $brand_color['primary']->site_token->token_value;
$brand_color['primary'] = empty( $brand_color['primary'] ) ? $report_color['accent'] : $brand_color['primary'];
$brand_color['primary'] = new Typemarker\Mexitek\PHPColors\Color( $brand_color['primary'] );
$brand_color['accent'] = MainWP_Pro_Reports_DB::get_instance()->get_tokens_by( 'token_name', 'client.brand.color.accent', $site_id );
$brand_color['accent'] = empty( $brand_color['accent']->site_token->token_value ) ? get_option( 'options_typemarker_reports_color_token_client_brand_color_accent_default' ) : $brand_color['accent']->site_token->token_value;
$brand_color['accent'] = empty( $brand_color['accent'] ) ? $report_color['text'] : $brand_color['accent'];
$brand_color['accent'] = new Typemarker\Mexitek\PHPColors\Color( $brand_color['accent'] );

// instantiate usable color array
$plus_colors = array(
      // report colors
      'background' => $marker->color_variants( $report_color['background'] ),
      'text' => $marker->color_variants( $report_color['text'] ),
      'accent' => $marker->color_variants( $report_color['accent'] ),
      // token colors
      'client-primary' => $marker->color_variants( $brand_color['primary'] ),
      'client-accent' => $marker->color_variants( $brand_color['accent'] )
);
// dashboard colors
$colors = array_merge( $colors, $plus_colors );

// set plugin preferences
$analytics = empty( get_option( 'options_typemarker_reports_analytics' ) ) ? 'google' : get_option( 'options_typemarker_reports_analytics' );

if ( $analytics == 'google' ) {
   $analytics = is_plugin_active( 'mainwp-google-analytics-extension/mainwp-google-analytics-extension.php' ) ? 'google' : false;
} elseif ( $analytics == 'matomo' ) {
   $analytics = is_plugin_active( 'mainwp-piwik-extension/mainwp-piwik-extension.php' ) ? 'matomo' : false;
}

$security = empty( get_option( 'options_typemarker_reports_security' ) ) ? 'sucuri' : get_option( 'options_typemarker_reports_security' );

if ( $security == 'sucuri' ) {
   $security = is_plugin_active( 'mainwp-sucuri-extension/mainwp-sucuri-extension.php' ) ? 'sucuri' : false;
} elseif ( $security == 'wordfence' ) {
   $security = is_plugin_active( 'mainwp-wordfence-extension/mainwp-wordfence-extension.php' ) ? 'wordfence' : false;
}

if ( is_plugin_active( 'mainwp-backwpup-extension/mainwp-backwpup-extension.php' )
   || is_plugin_active( 'mainwp-backupwordpress-extension/mainwp-backupwordpress-extension.php' )
   || is_plugin_active( 'mainwp-buddy-extension/mainwp-buddy-extension.php' )
   || is_plugin_active( 'mainwp-updraftplus-extension/mainwp-updraftplus-extension.php' )
   || is_plugin_active( 'mainwp-timecapsule-extension/mainwp-timecapsule-extension.php' )
) {
   $backups = true;
} else {
   $backups = false;
}

// set final branding options
$agency_url = get_option( 'options_typemarker_reports_business_url' );
$cta_text = get_option( 'options_typemarker_reports_cta_text' );
$cta_url = get_option( 'options_typemarker_reports_cta_url' );
$footer_text = get_option( 'options_typemarker_reports_footer_text' );

/******************************
* SEND CONFIGURATION TO THE PDF & EMAIL CONFIG FILES
*********/

$reporting = array(
   'marker' => $marker,
   'analytics' => $analytics,
   'security' => $security,
   'backups' => $backups,
   'css' => $css,
   'colors' => $colors,
   'logo' => $logo,
   'header_image' => $header_image,
   'agency_url' => $agency_url,
   'cta_text' => $cta_text,
   'cta_url' => $cta_url,
   'footer_text' => $footer_text,
   'heading' => $heading,
   'intro' => $intro,
   'outro' => $outro,
   'config_tokens' => $config_tokens,
   'showhide_values' => $showhide_values,
   'from' => $report->femail
);
return $reporting;
