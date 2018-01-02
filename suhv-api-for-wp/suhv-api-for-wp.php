<?php
/**
 * Admin Page for SUHV API-2
 * 
 * @author Thomas Hardegger / based on Code form Jérôme Meier
 * @version  10.12.2017
 * STATUS: Reviewed
*/
/*
Plugin Name: SUHV API-2 Schnittstelle für WordPress
Plugin URI: www.churunihockey.ch
Description: Nutzt da neu API 2.0 von Swissunihockey.ch Basiert auf Lösung von Jérôme Meier http://www.schwarzpunkt.ch 2012
Version: 1.56
Text Domain: SUHV-API-2
Author: Thomas Hardegger
Author URI: www.churunihockey.ch
License: GPL2

----------------------------------------------------------------------------------------
Copyright 2015 Thomas Hardegger (email : webmaster@churunihockey.ch)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
----------------------------------------------------------------------------------------
*/

/******************************************************************************
 * NEW Documentation SUHV API 2
 * https://api-v2.swissunihockey.ch/api/doc
 * Beispiel: https://api-v2.swissunihockey.ch/api/games?mode=club&club_id=423403&season=2015 
*****************************************************************************/

/*** Changes ******************************************************************
V1.43  18.03.2017  Remove inline <strong> Style in api_club_getGames -> Output-Style in css
V1.44  22.03.2017  Bug in api_club_getWeekendGames
V1.45  02.04.2017  Bug in api_club_getWeekendGames on GameDate
V1.46  26.06.2017  Support user defined field "SUHV Club Shortname"
V1.47  26.06.2017  Support tablepress for primary table class settings
V1.51  20.09.2017  Smaller effect on Club-Games if Swissunihockey API fails on ClubGames
V1.51  29.10.2017  Smaller effect on Club-Games if Swissunihockey API fails over all functions
V1.53  08.11.2017  Clean Log - fix wp_enqueue_scripts calls
V1.55  20.11.2017  Gamedetails of directcppointsments at current weekend [suhv-api-team-get-gamedetails]
*******************************************************************************/

// Sicherstellen, dass keine Infos ausgegeben werden wenn direkt aufgerufen
if ( !function_exists( 'add_action' ) ) {
	echo 'Hallo!  Ich bin ein Plugin. Viel machen kann ich nicht wenn du mich direkt aufrust :)';
	exit;
}

/* ------------------------------------------------------------------------------------ */
// Konstanten
/* ------------------------------------------------------------------------------------ */
if ( ! defined( 'SUHV_API_WP_VERSION' ) )
 define('SUHV_API_WP_VERSION', '1.0');
 
if ( ! defined( 'SUHV_API_WP_PLUGIN_URL' ) )
 define('SUHV_API_WP_PLUGIN_URL', plugin_dir_url( __FILE__ )); // http://www.churunichockey.ch/wp-content/plugins/suhv-api-for-wp/

 
if ( ! defined( 'SUHV_API_WP_PLUGIN_PATH' ) )
 define('SUHV_API_WP_PLUGIN_PATH', plugin_dir_path( __FILE__ )); // httpdocs/wp-content/plugins/suhv-api-for-wp/
 
if ( ! defined( 'SUHV_API_WP_PLUGIN_BASENAME' ) )
 define( 'SUHV_API_WP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // suhv-api-for-wp/suhv-api-for-wp.php

if ( ! defined( 'SUHV_API_WP_PLUGIN_DIRNAME' ) )
 define( 'SUHV_API_WP_PLUGIN_DIRNAME', dirname( SUHV_API_WP_PLUGIN_BASENAME ) ); // suhv-api-for-wp
    
global $wpdb;

/* ------------------------------------------------------------------------------------ */
// Administrationsbereich (Backend)
/* ------------------------------------------------------------------------------------ */
if ( is_admin() )
	require_once SUHV_API_WP_PLUGIN_PATH . 'admin/suhv_api_wp_admin.php';


/* ------------------------------------------------------------------------------------ */
// Besucherbereich (Frontend)
/* ------------------------------------------------------------------------------------ */
if ( ! is_admin() ){ // Alles wird nur im Frontend ausgeführt

 /* ------------------------------------------------------------------------------------*/
 // SUHV-Konstante API Key mit Plugin-Optionen überschreiben
 /* ------------------------------------------------------------------------------------ */
 	$plugin_options = get_option( 'SUHV_WP_plugin_options' );
 	$api_key = $plugin_options['SUHV_api_key'];
  if (!defined('CFG_SUHV_API_KEY')) define('CFG_SUHV_API_KEY', $api_key);
  if (!defined('CFG_SUHV_CLUB')) define('CFG_SUHV_CLUB', 'Chur Unihockey');
  if (!defined('CFG_SUHV_VERSION')) define('CFG_SUHV_VERSION', 'beta 02');
  
 /* ------------------------------------------------------------------------------------ */
 // SUHV-API--PHP-Framework verlinken
 /* ------------------------------------------------------------------------------------ */
 try{
  require_once SUHV_API_WP_PLUGIN_PATH . '/includes/suhv/php/suhv_api_html_lib.php';
  require_once SUHV_API_WP_PLUGIN_PATH . '/includes/suhv/lib/BusinessServer/vendor/autoload.php';
  require_once SUHV_API_WP_PLUGIN_PATH . '/includes/suhv/lib/BusinessServer/src/SwissUnihockey/Public.php';
  require_once SUHV_API_WP_PLUGIN_PATH . '/includes/suhv/php/suhv_api_wp.class.php';
 }

 catch (SuhvException $ex) {
  echo "<p class='error suhv'>SUHV Error found<br><strong>{$ex->getMessage()}</strong></p>\n";
 }
 
} // End if ( ! is_admin() )

?>