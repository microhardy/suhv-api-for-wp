<?php
/**
 * Admin Page for SUHV API-2
 * 
 * @author Thomas Hardegger / based on Code form Jérôme Meier
 * @version  15.11.2019
 * STATUS: Reviewed
*/
/*
Plugin Name: SUHV API-2 Schnittstelle für WordPress
Plugin URI: www.churunihockey.ch
Description: Nutzt da neu API 2.0 von Swissunihockey.ch Basiert auf Lösung von Jérôme Meier http://www.schwarzpunkt.ch 2012
Version: 1.82
Text Domain: SUHV-API-2
Author: Thomas Hardegger
Author URI: www.churunihockey.ch
License: GPL2

----------------------------------------------------------------------------------------
Copyright 2015 Thomas Hardegger (email : websupport@churunihockey.ch)

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
V1.56  20.11.2017  Gamedetails of directcppointsments at current weekend [suhv-api-team-get-gamedetails]
V1.57  10.01.2018  Standby-Result now "vs."
V1.60  21.01.2018  Install a cron aktivation for sending result mails
V1.61  24.02.2018  CurrentGameDetails for support Home-Games
V1.62  25.03.2018  Add highresolution clublogos to Home-Game-Screen
V1.63  22.06.2018  Add Ajax for Home-Games
V1.64  01.09.2018  fix count on Teamtable (PHP 7.2)
V1.65  19.09.2018  fix https converting on clublogos
V1.66  28.09.2018  fix morgen/tomorrow on direkt games
V1.67  30.09.2018  fix mail and set cronlog.txt off - if not churunihockey
V1.68  27.10.2018  fix clean leagues replacements
V1.70  09.01.2019  make loops for group A + B on ranking tables (Junior-Games)
                   sample https://api-v2.swissunihockey.ch/api/rankings?&season=2018&league=12&game_class=34&group=Gruppe+26 
V1.71  25.03.2019  fix draw games-style in team-get-playedgames
V1.72  01.04.2019  New function team-get-directgames
V1.73  01.04.2019  Max Loop now 15 pages on team_getGames & and 25 on league_getGames (limit before 10/20) / team_getPlayedGames start on given page
V1.74  09.01.2019  fix PHP 7.2 Array-Count A + B on ranking tables (Junior-Games)
V1.75  14.10.2019  fix playedGames on draw results
V1.80  12.11.2019  first multi-language version
V1.81  14.11.2019  minor language adds: on Gamedetail Link to swissunihockey and U at small Place-GameTable
V1.82  15.11.2019  fix on team_getGames table - extend table classes with functionnames
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
if ( is_admin() ) {
	require_once SUHV_API_WP_PLUGIN_PATH . 'admin/suhv_api_wp_admin.php';
}

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

function log_me($message) {
      if ( WP_DEBUG === true ) {
          if ( is_array($message) || is_object($message) ) {
              error_log( print_r($message, true) );
          } else {
                error_log( $message );
          }
      }
  }

 //****************************************
 // add custom interval
function cron_add_5minutes( $schedules ) {
  // Adds once every minute to the existing schedules.
    $schedules['every5minutes'] = array(
      'interval' => 60*5,
      'display' => __( 'Once Every 5 Minutes' )
    );
    log_me('new schedule insert every5minutes');
    return $schedules;
}

add_filter( 'cron_schedules', 'cron_add_5minutes' );

// ADD Cron task
 function my_cronjob_suhv_action () {
    // code to execute on cron run
   date_default_timezone_set("Europe/Paris");
   //wp_cache_clear_cache();
   
   if (substr_count( $_SERVER['SERVER_NAME'] ,"churunihockey")>=1) {
     $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
     $txt = $_SERVER['SERVER_NAME']." start: ".date("d.m.Y - H:i:s")."\n";
     fwrite($myfile, $txt);
     fclose($myfile);
   }

   $Suhv_WP_instance = new Suhv_WP();  
   $Suhv_WP_instance->api_club_getGames_Mails();
   $timevalue = 'cronjob running - at '.date("d.m.Y - H:i:s");
   log_me($timevalue);

 } 

// END Add Cron
 
//****************************************

register_activation_hook(__FILE__, 'my_activation');

function my_activation() {
    if (! wp_next_scheduled ( 'my_mail_event' )) {
     wp_schedule_event(time(), 'every5minutes', 'my_mail_event');
    }
    log_me('new cron hook set suhv');
}

add_action('my_mail_event', 'my_cronjob_suhv_action');

//****************************************

register_deactivation_hook(__FILE__, 'my_deactivation');

function my_deactivation() {
  wp_clear_scheduled_hook('my_mail_event');
  log_me('deaktivate cron hook suhv');
}

//****************************************


?>