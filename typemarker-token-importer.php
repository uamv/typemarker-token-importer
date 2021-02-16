<?php
/*
Plugin Name: Typemarker Token Importer
Plugin URI: https://typemarker.com
Description: Allow for MainWP Pro Report and Client Report token import via CSV file
Version: 1.0.3
Author: Typewheel
Author URI: http://typewheel.xyz
License: GPL-2.0+

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

define( 'TYPEMARKER_TI_VERSION', '1.0.3' );
define( 'TYPEMARKER_TI_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'TYPEMARKER_TI_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'TYPEMARKER_TI_STORE_URL', 'https://typemarker.com' );

/**
* Get instance of class if in admin.
*/

if ( is_admin() ) {
   Typemarker_TokenImporter::get_instance();
}

/**
* Typemarker_TokenImporter Class
*
*
* @package Typemarker
* @author  uamv
*/
class Typemarker_TokenImporter {

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
   * Constructor
   *---------------------------------------------------------------------------------*/

   /**
   * Initialize the plugin by setting localization, filters, and administration functions.
   */
   private function __construct() {

      // include the EDD Client
      require_once( 'includes/EDD_Client/EDD_Client_Init.php' );
      new EDD_Client_Init( __FILE__, TYPEMARKER_TI_STORE_URL );

      // check available tokens, sites, and templates
      add_action( 'admin_init', [ $this, 'initialize_variables' ] );

      add_action( 'admin_menu', [ $this, 'add_menu_pages' ], 99 );
      add_filter( 'mainwp_main_menu_submenu', [ $this, 'add_submenu_item' ], 10 );

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

   /**
    *  Initialize properties of the object
    */
   public function initialize_variables() {

      // TODO: check that MainWP and Pro Reports is active

      if ( class_exists( 'MainWP_Pro_Reports_DB' ) ) {

         $tokens = MainWP_Pro_Reports_DB::get_instance()->get_tokens();

         foreach ( $tokens as $token ) {
            $this->tokens['pro_reports'][ $token->token_name ] = $token->id;
         }

      }

      if ( class_exists( 'MainWP_CReport_DB' ) ) {

         $tokens = MainWP_CReport_DB::get_instance()->get_tokens();

         foreach ( $tokens as $token ) {
            $this->tokens['client_reports'][ $token->token_name ] = $token->id;
         }

      }

      if ( version_compare( MAINWP_VERSION, '4.1', '>=' ) && class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) {

         $site_sql = MainWP\Dashboard\MainWP_DB::instance()->query( MainWP\Dashboard\MainWP_DB::instance()->get_sql_search_websites_for_current_user( null ) );
         $this->sites = array();
         while ( $site_sql && ( $site = @MainWP\Dashboard\MainWP_DB::fetch_object( $site_sql ) ) ) {
            $this->sites[ $site->url ] = $site->id;
         }

      } elseif ( version_compare( MAINWP_VERSION, '4.0', '<=' ) && class_exists( 'MainWP_DB' ) ) {

         $site_sql = MainWP_DB::Instance()->query( MainWP_DB::Instance()->getSQLSearchWebsitesForCurrentUser( null ) );
         $this->sites = array();
         while ( $site_sql && ( $site = @MainWP_DB::fetch_object( $site_sql ) ) ) {
            $this->sites[ $site->url ] = $site->id;
         }

      }

   } // end initialize_variables

   /**
    *  Add Token Importer menu page to WP admin menu
    */
   public function add_menu_pages() {

      if ( ( class_exists( 'MainWP_Pro_Reports_DB' ) || class_exists( 'MainWP_CReport_DB' ) ) && ( class_exists( 'MainWP_DB' ) || class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) ) {

         add_menu_page(
            'Typemarker Token Importer',
            'Token Importer',
            'manage_options',
            'typemarker-token-importer',
            [ $this, 'render_token_importer_page' ],
            'dashicons-upload',
            4
         );

      }

   } // end add_menu_page

   public function add_submenu_item( $sub_leftmenu ) {

      if ( ( is_plugin_active( 'mainwp-pro-reports-extension/mainwp-pro-reports-extension.php' ) || is_plugin_active( 'mainwp-client-reports-extension/mainwp-client-reports-extension.php' ) ) && ( class_exists( 'MainWP_DB' ) || class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) ) {

         $item = array(
            'Pro Report Token Importer',
            'admin.php?page=typemarker-token-importer',
            'mnage_options'
         );

         if ( is_plugin_active( 'typemarker/typemarker.php' ) ) {

            $sub_leftmenu['typemarker'][] = $item;

         }

         $sub_leftmenu['Extensions'][] = $item;

      }

      return $sub_leftmenu;

   }

   /**
    *  Render the HTML and handle form submission of Token Importer page.
    */
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
                  if ( $tokenType == 'Pro Reports' && class_exists( 'MainWP_Pro_Reports_DB' ) ) {

                     $expunge = MainWP_Pro_Reports_DB::get_instance()->delete_site_tokens( null, (int) $this->sites[ $site ] ); // remove all existing pro report tokens from site

                     // loop through the columns of csv row
                     foreach ( $csvData as $key => $tokenValue ) {

                        // if available token, then let's add value from csv
                        if ( isset( $this->tokens['pro_reports'][ $tokenNames[ $key ] ] ) ) {

                           $imported = MainWP_Pro_Reports_DB::get_instance()->add_token_site( (int) $this->tokens['pro_reports'][ $tokenNames[ $key ] ], $tokenValue, (int) $this->sites[ $site ] );

                           if ( $imported !== false ) {
                              $processed[ $row ]['status'][ $key ] = 'success';
                           } else {
                              $processed[ $row ]['status'][ $key ] = $tokenNames[ $key ];
                           }

                        } else {

                           $processed[ $row ]['status'][ $key ] = $tokenNames[ $key ];

                        }

                     }

                  } else if ( $tokenType == 'Client Reports' && class_exists( 'MainWP_CReport_DB' ) ) {

                     $expunge = MainWP_CReport_DB::get_instance()->delete_site_tokens( null, (int) $this->sites[ $site ] ); // remove all existing pro report tokens from site

                     // loop through the columns of csv row
                     foreach ( $csvData as $key => $tokenValue ) {

                        // if available token, then let's add value from csv
                        if ( isset( $this->tokens['client_reports'][ $tokenNames[ $key ] ] ) ) {

                           $imported = MainWP_CReport_DB::get_instance()->add_token_site( (int) $this->tokens['client_reports'][ $tokenNames[ $key ] ], $tokenValue, (int) $this->sites[ $site ] );

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
                        <th style="width: 100px;">Token Type</th>
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
                        $token_type = array_shift( $result['data'] );

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
                           echo '<td>' . $token_type . '</td>';
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
                              echo '<td>' . $token_type . '</td>';
                              echo '<td>' . $row . '</td>';
                              echo '<td>' . $site . '</td>';
                              echo '<td></td>';
                              echo '<td>' . implode( ',', $result['data'] ) . '</td>';
                              echo '</tr>';
                           } else {
                              echo '<tr class="fail-tokens">';
                              echo '<td><span class="dashicons dashicons-warning" style="color:orange;"></span></td>';
                              echo '<td>' . $token_type . '</td>';
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


   }
