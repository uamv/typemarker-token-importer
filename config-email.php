<?php

$reporting = require( TYPEMARKER_DIR_PATH . 'config-report.php' );

if ( $email_message ) {
   $message = stripslashes( $email_message );
   $message = wp_kses_post( wpautop( wptexturize( $message ) ) );
   $reporting['message'] = $message;
} else {
   $reporting['message'] = '';
}

$image = get_option( 'options_typemarker_reports_email_image' );

if ( $image == 'header_image' ) {
   $reporting['email_image'] = $reporting['header_image'];
   $reporting['email_image_css'] = 'max-width: 100%;';
} else if ( $image == 'logo' ) {
   $reporting['email_image'] = $reporting['logo'];
   $reporting['email_image_css'] = 'max-width: 50%; margin-bottom: 2.5em;';
} else {
   $reporting['email_image'] = null;
}

return $reporting;
