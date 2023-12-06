<?php
/***
 * Suhv_WP Klasse
 * 
 * @author Thonmas Hardegger / new API / based on Jérôme Meier, schwarzpunkt meier 2012
 * @version 27.12.2020
 * @todo Auf neue API umschreiben / die Funktionen bebehalten
 * STATUS: reviewed
 */

class SuhvException extends RuntimeException {}
 
if ( !class_exists( 'Suhv_WP' ) ) {
	
class Suhv_WP {
	private $options; // WordPress Options Array
	private $language_path = SUHV_API_WP_PLUGIN_PATH . "includes/suhv/language/default.json"; // languege folder
    private $language_data = NULL; // languege data
	private $season; // Saison
	private $club_id = 423403; // Chur Unihockey
	private $club_shortname = "Chur"; // Shortname
	private $home_location = NULL;  // Heimspiel-Ort
	private $team_id = 429283; // Herren NLA-Team or change by pagevar
	private $team_id_nla = 429283; // keep it - Herren NLA-Team 
	private $league_id = 21; // Leage-ID "Junioren/-innen U14/U17 Vollmeisterschaft" / Saison 2016717
	private $league_class = 49; // Game Class "Junioren/-innen U14/U17 Vollmeisterschaft" / Saison 2016717
	private $league_group = "Gruppe 3"; // Gruppe mit Chur U14 / Saison 2016717
	private $league_round = 90666; // mitChur U14 / Saison 2016717
	private $player_id = NULL; // Sandro Breu 420235
	private $game_id = 940355; // Game Derby Chur vs. Malans
	private $sponsor_name = NULL; // "Churunihockey"
	private $sponsor_sub  = NULL; // "Der Club für Gross und Klein";
	private $sponsor_logo = NULL; // "http://www.churunihockey.ch/wp-content/uploads/2013/08/ChurUnihockeyLogoSlide_460x368.png";
	private $sponsor_link = NULL; // "http:/www.churunihockey.ch";
	private $sponsor_link_title = NULL; // "www.churunihockey";
	private $use_cache = True;
	private $css_path = NULL;
	private $live_game_id = NULL;
	private $live_home_logo = NULL;
	private $live_guest_logo = NULL;
	private $live_refresh = 15; // 12 Refresh Page alle 12 Sekunden
	private $live_auto_reload = 60 ; // Reload alle 60 Sekunden 
	private $promo_link_left = "https://www.churunihockey.ch/promo/image-left.jpg"; // Liveticker Site - Live OSB Site
	private $promo_link_middle = "https://www.churunihockey.ch/promo/image-middle.jpg";
	private $promo_link_right = "https://www.churunihockey.ch/promo/image-right.jpg"; 


		
	public function __construct()  {
		/* ------------------------------------------------------------------------------------ */
		// new API 2
		add_shortcode ( 'suhv-api-club-get-games', array( &$this, 'api_club_getGames' ) );  // Display next Games of the Club
		add_shortcode ( 'suhv-api-club-get-playedgames', array( &$this, 'api_club_getPlayedGames' ) );
		add_shortcode ( 'suhv-api-club-get-homegames', array( &$this, 'api_club_getHomeGames' ) );  // Display next HomeGames of the Club

        add_shortcode ( 'suhv-api-club-get-cupgames', array( &$this, 'api_club_getCupGames' ) );  //*depreciated Display next Cup-Games of the Club

		add_shortcode ( 'suhv-api-league-get-games', array( &$this, 'api_league_getGames' ) ); // Display all Games of the Leage
		add_shortcode ( 'suhv-api-league-get-weekend-games', array( &$this, 'api_league_getWeekendGames' ) ); // Display all Games of the Leage of nearest Weekend

		add_shortcode ( 'suhv-api-team-get-games', array( &$this, 'api_team_getGames' ) );  
		add_shortcode ( 'suhv-api-team-get-playedgames', array( &$this, 'api_team_getPlayedGames' ) );
		add_shortcode ( 'suhv-api-get-directgames', array( &$this, 'api_getDirectGames' ) );  
		add_shortcode ( 'suhv-api-get-team-table', array( &$this, 'api_getTeamTable' ) );
		add_shortcode ( 'suhv-api-get-team-rank', array( &$this, 'api_getTeam_Rank' ) );
		add_shortcode ( 'suhv-api-nla-team-get-table', array( &$this, 'api_nla_team_getTable' ) ); //*depreciated
		add_shortcode ( 'suhv-api-get-team-table_nla', array( &$this, 'api_getTeamTable_nla' ) ); //*depreciated
		add_shortcode ( 'suhv-api-get-team-rank_nla', array( &$this, 'api_getTeam_Rank_nla' ) ); //*depreciated
		add_shortcode ( 'suhv-api-club-get-weekend-games', array( &$this, 'api_club_getWeekend_Games' ) );
		add_shortcode ( 'suhv-api-club-get-weekend-games_for_LiveGames', array( &$this, 'api_club_getWeekend_Games_for_LiveGames' ) );
		add_shortcode ( 'suhv-api-club-get-currentgamedetails', array( &$this, 'api_club_getCurrentGameDetails' ) );  
		add_shortcode ( 'suhv-api-club-get-mini-games', array( &$this, 'api_club_getMiniGames' ) );
		add_shortcode ( 'suhv-api-club-get-mini-results', array( &$this, 'api_club_getMiniResults' ) );
		add_shortcode ( 'suhv-api-team-get-gamedetails', array( &$this, 'api_team_getGameDetails' ) );  
		add_shortcode ( 'suhv-api-get-player', array( &$this, 'api_get_Player' ) );

		// for testing & debug
		add_shortcode ( 'suhv-api-show-params', array( &$this, 'api_show_params' ) );
		add_shortcode ( 'suhv-api-show-vars', array( &$this, 'api_show_vars' ) );
		add_shortcode ( 'suhv-api-show-processing', array( &$this, 'api_show_processing' ) );
		add_shortcode ( 'suhv-api-reload-processing', array( &$this, 'api_reload_processing' ) );

        //************************************************************************************

		/* ------------------------------------------------------------------------------------ */
		// Action-Hooks
		add_action ( 'wp_head', array( &$this, 'check_post_meta' ) );
		add_action ( 'wp_footer', array( &$this, 'suhv_check_update' ) );
		add_filter( 'cron_schedules', 'cron_add_1minutes' );
 	
		$this->options = get_option( 'SUHV_WP_plugin_options' );
		if ( isset( $this->options['SUHV_club_id'] ) ) $this->club_id = $this->options['SUHV_club_id'];
		if ( isset( $this->options['SUHV_default_club_shortname'] ) ) $this->club_shortname = $this->options['SUHV_default_club_shortname'];
		if ( ( isset( $this->options['SUHV_default_team_id'] ) ) && ( $this->options['SUHV_default_team_id'] != "" ) )	{ $this->team_id = $this->options['SUHV_default_team_id']; $this->team_id_nla = $this->team_id;}
		if ( isset( $this->options['SUHV_league_id'] ) ) $this->league_id = $this->options['SUHV_league_id'];
		if ( isset( $this->options['SUHV_round_id'] ) ) $this->league_round = $this->options['SUHV_round_id'];
		if ( isset( $this->options['SUHV_class_id'] ) ) $this->league_class = $this->options['SUHV_class_id'];
		if ( isset( $this->options['SUHV_group_id'] ) ) $this->league_group = $this->options['SUHV_group_id'];
		if ( isset( $this->options['SUHV_default_home_location'] ) ) $this->home_location = $this->options['SUHV_default_home_location']; 
	
		/* ------------------------------------------------------------------------------------ */
		// Stylesheet verlinken wenn selektiert in Admin
		if (isset( $this->options['SUHV_css_file'] )) 
		  if (substr_count($this->options['SUHV_css_file'],".css")>0) {
		    $this->css_path = SUHV_API_WP_PLUGIN_URL . "includes/suhv/styles/".$this->options['SUHV_css_file'];
		    // SwissUnihockey_Api_Public::log_me("CSS:".$this->css_path);
 		    add_action( 'wp_enqueue_scripts', array( $this, 'my_scripts_suhv' ) );
 	      }

 	     // Language verlinken wenn selektiert in Admin
	    if (isset( $this->options['SUHV_language_file'] )) 
	      if (substr_count($this->options['SUHV_language_file'],".json")>0) {
	        $this->language_path = SUHV_API_WP_PLUGIN_PATH . "includes/suhv/language/".$this->options['SUHV_language_file'];
	      }
	    else $this->language_path = SUHV_API_WP_PLUGIN_PATH . "includes/suhv/language/default.json";
	    $data = file_get_contents($this->language_path);
	    if ($data === false) {
            SwissUnihockey_Api_Public::log_me("ERROR read".$this->language_path);
        }
	    $this->language_data = json_decode($data);
	    set_transient( 'SUHV_language_codes',$this->language_data,0);
	    //SwissUnihockey_Api_Public::log_me("language FULL:".$this->language_path);
	    //SwissUnihockey_Api_Public::log_me("language FILE:".$this->options['SUHV_language_file']);
	    //SwissUnihockey_Api_Public::log_me(array($this->language_data));

 		// aktuelle Saison ermitteln
        $this->season = date('Y');
	    if (date('m') < 6) {
		  $this->season = $this->season - 1;
        }
        //SwissUnihockey_Api_Public::log_me($this->options);
	    if(isset($this->options['SUHV_cache']) == 1) {
        	 $this->use_cache = TRUE; }
        else { $this->use_cache = False;}
        	 
    }	
	

	/* ------------------------------------------------------------------------------------ */
    public function my_scripts_suhv() {
    /*** Register global styles & scripts.*/
      wp_register_style('suhv-api-style-css', $this->css_path);
      wp_enqueue_style('suhv-api-style-css');
  
    }


	/* ------------------------------------------------------------------------------------ */
	// SUHV-Club erstellen
	private function set_club( $club_id = NULL ){
		if ( $club_id != NULL )
		  $this->club_id = $club_id;
		// echo "<p class='error suhv'>Club ".$this->club_id."<br></p>";
		
	}

	private function set_club_shortname( $club_shortname = NULL ){
		if ( $club_shortname != NULL )
		  $this->club_shortname = $club_shortname;
		
	}
	
	/* ----------------------------------------------------------------------------------- */
	// Funktion: SUHV Team erstellen 
	private function set_team( $team_id = NULL ) {

         if ( $team_id != NULL )
		   $this->team_id = $team_id;
		 // echo "<p class='error suhv'>Team: ".$this->team_id."<br></p>";
		
	}
	
	/* ------------------------------------------------------------------------------------ */
	// Funktion: SUHV League erstellen
	private function set_league($league_id = NULL){
	  if ( $league_id  != NULL )
 	    $this->league_id = $league_id;
	}
	// Funktion: SUHV League Round erstellen
	private function set_round($league_round = NULL){
	  if ( $league_round  != NULL )
 	    $this->league_round = $league_round;
	}
	// Funktion: SUHV League Class erstellen
	private function set_class($league_class = NULL){
	  if ( $league_class  != NULL )
 	    $this->league_class = $league_class;
	}
	// Funktion: SUHV League Class erstellen
	private function set_group($league_group = NULL){
	  if ( $league_group  != NULL )
 	    $this->league_group = $league_group;
	}
	// Funktion: SUHV Player ID erstellen
	private function set_player($player_id = NULL){
	  if ( $player_id  != NULL )
 	    $this->player_id = $player_id;
	}
	private function set_game($game_id = NULL){
	  if ( $game_id  != NULL )
 	    $this->game_id = $game_id;
	}
	/* ------------------------------------------------------------------------------------ */
	// Funktion: Sponsor Werte
	private function set_sponsor_name($sponsor_name = NULL){
	  if ( $sponsor_name  != NULL )
 	    $this->sponsor_name = $sponsor_name;
	}
	private function set_sponsor_sub($sponsor_sub = NULL){
	  if ( $sponsor_sub  != NULL )
 	    $this->sponsor_sub = $sponsor_sub;
	}
	private function set_sponsor_logo($sponsor_logo = NULL){
	  if ( $sponsor_logo  != NULL )
 	    $this->sponsor_logo = $sponsor_logo;
	}
	private function set_sponsor_link($sponsor_link = NULL){
	  if ( $sponsor_link  != NULL )
 	    $this->sponsor_link = $sponsor_link;
	}
	private function set_sponsor_link_title($sponsor_link_title = NULL){
	  if ( $sponsor_link_title  != NULL )
 	    $this->sponsor_link_title = $sponsor_link_title;
	}
	/* ------------------------------------------------------------------------------------ */
	// Funktion: Sponsor Werte
	private function set_live_game_id($live_game_id = NULL){
	  if ( $live_game_id  != NULL )
 	    $this->live_game_id = $live_game_id;
	}
	private function set_promo_link_left($promo_link_left = NULL){
	  if ( $promo_link_left  != NULL )
 	    $this->promo_link_left = $promo_link_left;
	}
	private function set_promo_link_middle($promo_link_middle  = NULL){
	  if ( $promo_link_middle  != NULL )
 	    $this->promo_link_middle = $promo_link_middle;
	}
	private function set_promo_link_right($promo_link_right = NULL){
	  if ( $promo_link_right != NULL )
 	    $this->promo_link_right = $promo_link_right;
	}
	private function set_Live_home_logo($live_home_logo  = NULL){
	  if ( $live_home_logo  != NULL )
 	    $this->live_home_logo = $live_home_logo;
	}
	private function set_live_guest_logo($live_guest_logo = NULL){
	  if ( $live_guest_logo != NULL )
 	    $this->live_guest_logo = $live_guest_logo;
	}
	private function set_live_refresh($live_refresh = NULL){
	  if ( $live_refresh != NULL )
 	    $this->live_refresh = $live_refresh;
	}
	private function set_live_auto_reload($live_auto_reload = NULL){
	  if ( $live_auto_reload!= NULL )
 	    $this->live_auto_reload = $live_auto_reload;
	}

    private function set_home_location( $home_location = NULL ){
		if ( $home_locatione != NULL )
		  $this->home_location = $home_location;
	}
	
	
	/* ------------------------------------------------------------------------------------ */
	// Funktion: Überprüfung der Meta-Values eines Post 
	function check_post_meta(){
		// Ändert Club, wenn eine Club-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Club ID', true ) != "" ) {
			$this->set_club( get_post_meta( get_the_ID(), 'SUHV Club ID', true ) );
		}
		// Ändert Club-Shortname, wenn ein Club-Shortname im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Club Shortname', true ) != "" ) {
			$this->set_club_shortname( get_post_meta( get_the_ID(), 'SUHV Club Shortname', true ) );
		}
		// Ändert Team, wenn eine Team-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Team ID', true ) != "" ) {
		 $this->set_team( get_post_meta( get_the_ID(), 'SUHV Team ID', true ) );
		}
		// Ändert Game, wenn eine Game-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Game ID', true ) != "" ) {
		 $this->set_game( get_post_meta( get_the_ID(), 'SUHV Game ID', true ) );
		}
		//Liga
		// Ändert Liega, wenn eine Liega-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV League ID', true ) != "" ) {
		 $this->set_league( get_post_meta( get_the_ID(), 'SUHV League ID', true ) );
		}
		// Ändert Liega-Runde, wenn eine Runden-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Round ID', true ) != "" ) {
		 $this->set_round( get_post_meta( get_the_ID(), 'SUHV Round ID', true ) );
		}
		// Ändert Liega-Klasse, wenn eine Klassen-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Class ID', true ) != "" ) {
		 $this->set_class( get_post_meta( get_the_ID(), 'SUHV Class ID', true ) );
		}
		// Ändert Liega-Gruppe, wenn eine Gruppen-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Group ID', true ) != "" ) {
		 $this->set_group( get_post_meta( get_the_ID(), 'SUHV Group ID', true ) );
		}
		// Ändert Player-ID, wenn eine Player-ID im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'SUHV Player ID', true ) != "" ) {
		  $this->set_player( get_post_meta( get_the_ID(), 'SUHV Player ID', true ) );
		}
		//Sponsor
		// Ändert den Sponsorwert, wenn ein Sponsor-Wert im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'Sponsor Name', true ) != "" ) {
		  $this->set_sponsor_name( get_post_meta( get_the_ID(), 'Sponsor Name', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Sponsor Sub', true ) != "" ) {
		  $this->set_sponsor_sub( get_post_meta( get_the_ID(), 'Sponsor Sub', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Sponsor Logo', true ) != "" ) {
		  $this->set_sponsor_logo( get_post_meta( get_the_ID(), 'Sponsor Logo', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Sponsor Link', true ) != "" ) {
		  $this->set_sponsor_link( get_post_meta( get_the_ID(), 'Sponsor Link', true ) );
		}
	    if ( get_post_meta( get_the_ID(), 'Sponsor Link Title', true ) != "" ) {
		  $this->set_sponsor_link_title( get_post_meta( get_the_ID(), 'Sponsor Link Title', true ) );
		}
		//Live Game Site
		// Ändert den Sponsorwert, wenn ein Promi-Wert im Post-Meta-Feld eingegeben wurde.
		if ( get_post_meta( get_the_ID(), 'Live Game ID', true ) != "" ) {
		  $this->set_live_game_id( get_post_meta( get_the_ID(), 'Live Game ID', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Promo Link Left', true ) != "" ) {
		  $this->set_promo_link_left( get_post_meta( get_the_ID(), 'Promo Link Left', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Promo Link Middle', true ) != "" ) {
		  $this->set_promo_link_middle( get_post_meta( get_the_ID(), 'Promo Link Middle', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Promo Link Right', true ) != "" ) {
		  $this->set_promo_link_right( get_post_meta( get_the_ID(), 'Promo Link Right', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Home Logo', true ) != "" ) {
		  $this->set_live_home_logo( get_post_meta( get_the_ID(), 'Home Logo', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Guest Logo', true ) != "" ) {
		  $this->set_Live_guest_logo( get_post_meta( get_the_ID(), 'Guest Logo', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Live Refresh', true ) != "" ) {
		  $this->set_live_refresh( get_post_meta( get_the_ID(), 'Live Refresh', true ) );
		}
		if ( get_post_meta( get_the_ID(), 'Live Auto Reload', true ) != "" ) {
		  $this->set_live_auto_reload( get_post_meta( get_the_ID(), 'Live Auto Reload', true ) );
		}
	}
	

	/* ------------------------------------------------------------------------------------ */
	// ??
	// Funktion: Updatet das SUHV-Team-Objekt in der Datenbank, sofern nötig
	function suhv_check_update(){
		if ( isset ( $this->team ) ) {
			echo "<p class='error suhv'>Kein Default Team vorhanden!</p>";
		}
	}	
	
	/* ------------------------------------------------------------------------------------ */
	// Funktion: Log-Daten ausgeben
	public function get_log(){
	if ( current_user_can('activate_plugins') ) // only for Administrators
     echo '<pre style="clear: both; width: 80%; padding: 2% 10%; margin: 0 auto; background-color: rgb(234,90,90)"><strong>Logdaten des SUHV Frameworks</strong><br>----------------------------<br>', 
     print_r( SuhvApiManager::getInstance()->getLog() ), '</pre>';
    }
   
	// *********************************************************************************************
	//
	// API 2.0
	//
	//
	function api_club_getGames($atts){
		//new2*
		extract(shortcode_atts(array(
		"club_id" => NULL ,
		), $atts));
		//echo " - club:".$club_id;
		if ( $club_id != NULL )
			$this->set_club( $club_id );
		else
			if ( !isset ( $this->club ) ) $this->set_club();

		//echo "api_club_getGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getGames($season, $club_ID, $club_shortname,  $team_ID, $mode, $cache );
	}

	function api_club_getGames_Mails(){
		if ( !isset ( $this->club ) ) $this->set_club();
		//echo "api_club_getGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getGames_Mails($season, $club_ID, $club_shortname,  $team_ID, $mode, $cache );
	}

    function api_team_getGameDetails($atts){
    	//*new
	    extract(shortcode_atts(array(
	     "start_date" => '17.09.2015',
         "end_date" => '18.09.2015',
         "game_id" => NULL ,
         "team_id" => NULL ,
        ), $atts));
        if ( $team_id != NULL )
		 $this->set_team( $team_id );
		else
		 if ( !isset ( $this->team ) ) $this->set_team();
        if ( !isset ( $this->team_id ) ) $this->set_team();
		if ( !isset ( $this->club_id ) ) $this->set_club();
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_team_getGameDetails($season, $club_ID, $club_shortname, $team_ID, $mode, $start_date, $end_date, $game_id, $cache );
	}

	function api_getDirectGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
	//echo "api_getDirectGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $game_ID = $this->game_id;
 	    $mode = "direct";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_getDirectGames($season, $club_ID, $club_shortname, $game_ID, $mode, $cache );
	}
	
	function api_club_getPlayedGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
	//echo "api_club_getPlayedGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getPlayedGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}

	function api_club_getCupGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
	    //echo "api_club_getCupGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getCupGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}

	function api_club_getMiniGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getMiniGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}

	function api_club_getMiniResults(){
		if ( !isset ( $this->club ) ) $this->set_club();
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getMiniResults($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}

    function api_league_getGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
		//echo "api_league_getGames";
		$season = $this->season;
		$league_id = $this->league_id;
 	    $league_class = $this->league_class;
 	    $league_group = $this->league_group;
 	    $league_round = $this->league_round;
 	    $mode = "list";
 	    $cache = $this->use_cache;
 	    // echo "season: ".$season." - league:".$league_id." - game_class:".$league_class." - group:".$league_group." - round:".$league_round."<br>";
 	    return SwissUnihockey_Api_Public::api_league_getGames($season, $league_id, $league_class, $league_group, $league_round, $mode, $cache );
	}

    function api_league_getWeekendGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
	// echo "api_league_getWeekendGames";
		$season = $this->season;
		$league_id = $this->league_id;
 	    $league_class = $this->league_class;
 	    $league_group = $this->league_group;
 	    $league_round = $this->league_round;
 	    $mode = "list";
 	    $cache = $this->use_cache;
 	    // echo "season: ".$season." - league:".$league_id." - game_class:".$league_class." - group:".$league_group." - round:".$league_round."<br>";
 	    return SwissUnihockey_Api_Public::api_league_getWeekend($season, $league_id, $league_class, $league_group, $league_round, $mode, $cache );
	}

	function api_team_getGames( $atts ){
	//new*
        extract(shortcode_atts(array(
	     "team_id" => NULL ,
        ), $atts));
        if ( $team_id != NULL )
		 $this->set_team( $team_id );
		else
		 if ( !isset ( $this->team ) ) $this->set_team();
		if ( !isset ( $this->club ) ) $this->set_club();
		//echo "api_club_getGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
 	    $cache = $this->use_cache;
 	    //echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
 	    return SwissUnihockey_Api_Public::api_team_getGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}

    
	function api_team_getPlayedGames( $atts){
		//new*
        extract(shortcode_atts(array(
	     "team_id" => NULL ,
        ), $atts));
        //echo " - team:".$team_id;
        if ( $team_id != NULL )
         $this->set_team( $team_id );
        else
		 if ( !isset ( $this->team ) ) $this->set_team();
		if ( !isset ( $this->club ) ) $this->set_club();
		//echo "api_club_getGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
 	    $cache = $this->use_cache;
 	    //echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
 	    return SwissUnihockey_Api_Public::api_team_getPlayedGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}
    
	function api_team_getHomeGames(){
		if ( !isset ( $this->club ) ) $this->set_club();
		//echo "api_team_getHomeGames";
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
 	    $cache = $this->use_cache;
 	    //echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
 	    return SwissUnihockey_Api_Public::api_team_getHomeGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache );
	}

	function api_getNLATable( $team_id = NULL ){
		if ( $team_id != NULL )
		 $this->set_team( $team_id );
		else
 		 if ( !isset ( $this->team ) ) $this->set_team();
 	    if ( !isset ( $this->club ) ) $this->set_club();
 	    $season = $this->season;
 	    $club_ID = $this->club_id;
 	    $team_ID = NULL;
 	    $mode = NULL;
 	    $cache = $this->use_cache;
        // echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
		return SwissUnihockey_Api_Public::api_getTeamTable($season, $club_ID, $team_ID, $mode, $cache );
	}


	function api_getTeamTable( $atts ){
	//new*
		extract(shortcode_atts(array(
	     "team_id" => NULL ,
        ), $atts));
        //echo " - team:".$team_id;
		if ( $team_id != NULL )
		 $this->set_team( $team_id );
		else
 		 if ( !isset ( $this->team ) ) $this->set_team();
 	    if ( !isset ( $this->club ) ) $this->set_club();
 	    $season = $this->season;
 	    $club_ID = $this->club_id;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
 	    $cache = $this->use_cache;
        //echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
		return SwissUnihockey_Api_Public::api_getTeamTable($season, $club_ID, $team_ID, $mode, $cache );
	}

	
	function api_getTeam_Rank( $atts ){
	//new*
        extract(shortcode_atts(array(
	     "team_id" => NULL ,
        ), $atts));
        // echo " - team:".$team_id;
		if ( $team_id != NULL )
		    $this->set_team( $team_id );
		else
 		if ( !isset ( $this->team ) ) $this->set_team();
 	    if ( !isset ( $this->club ) ) $this->set_club();
 	    $season = $this->season;
 	    $club_ID = $this->club_id;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
 	    $cache = $this->use_cache;
        //echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
		return SwissUnihockey_Api_Public::api_getTeamRank($season, $club_ID, $team_ID, $mode, $cache );	
	}

    function api_getTeamTable_nla( $team_id = NULL ){
		if ( $team_id != NULL )
		 $this->set_team( $team_id );
		else
 		if ( !isset ( $this->team ) ) $this->set_team();
 	    if ( !isset ( $this->club ) ) $this->set_club();
 	    $season = $this->season;
 	    $club_ID = $this->club_id;
 	    $team_ID = $this->team_id_nla;
 	    $mode = "team";
 	    $cache = $this->use_cache;
        //echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
		return SwissUnihockey_Api_Public::api_getTeamTable($season, $club_ID, $team_ID, $mode, $cache );
	}

	
	function api_getTeam_Rank_nla( $team_id = NULL ){
		if ( $team_id != NULL )
		 $this->set_team( $team_id );
		else
 		if ( !isset ( $this->team ) ) $this->set_team();
 	    if ( !isset ( $this->club ) ) $this->set_club();
 	    $season = $this->season;
 	    $club_ID = $this->club_id;
 	    $team_ID = $this->team_id_nla;
 	    $mode = "team";
 	    $cache = $this->use_cache;
        // echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
		return SwissUnihockey_Api_Public::api_getTeamRank($season, $club_ID, $team_ID, $mode, $cache );	
	}

    function api_club_getWeekend_Games($atts){
	// new2*
	    extract(shortcode_atts(array(
	     "start_date" => '01.09.2015',
         "end_date" => '02.09.2015',
         "club_id" => NULL ,
        ), $atts));
        //echo "weekend - club:".$club_id;
        if ( $club_id != NULL )
         $this->set_club( $club_id );
        else
		 if ( !isset ( $this->club ) ) $this->set_club();

		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    // echo "season: ".$season." - club:".$club_ID." - team:".$team_ID;
 	    return SwissUnihockey_Api_Public::api_club_getWeekendGames($season, $club_ID, $club_shortname, $team_ID, $mode, $start_date, $end_date, $cache );
	}

	function api_club_getWeekend_Games_for_LiveGames($atts){
	    extract(shortcode_atts(array(
	     "away" => NULL ,
	     "start_date" => '01.09.2015',
         "end_date" => '02.09.2015',
        ), $atts));
  
		if ( !isset ( $this->club ) ) $this->set_club();
		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $cache = $this->use_cache;
 	    return SwissUnihockey_Api_Public::api_club_getWeekendGames_for_LiveGames($season, $club_ID, $club_shortname, $team_ID, $mode, $start_date, $end_date, $cache, $away );
	}

	function api_club_getCurrentGameDetails($atts){
        extract(shortcode_atts(array(
	     "game_id" => NULL ,
        ), $atts));

        if ( ($game_id < 100000) and ($game_id > 0) and ($game_id != NULL)) $game_id = 897372;
        if ( !isset ( $this->team_id ) ) $this->set_team();
		if ( !isset ( $this->club_id ) ) $this->set_club();
		if ( !isset ( $this->game_id ) ) $this->set_live_game_id();
		if ( $game_id == NULL ) $game_id = $this->live_game_id ;
		if ( ($game_id == 0)) $game_id = NULL;
        if ( !isset ( $this->live_home_logo) ) $this->set_live_home_logo();
        if ( !isset ( $this->live_guest_logo) ) $this->set_live_guest_logo();
        if ( !isset ( $this->live_refresh) ) $this->set_live_refresh();

		$season = $this->season;
 	    $club_ID = $this->club_id;
 	    $club_shortname = $this->club_shortname;
 	    $team_ID = $this->team_id;
 	    $mode = "club";
 	    $promo_left = $this->promo_link_left;
 	    $promo_middle = $this->promo_link_middle;
 	    $promo_right = $this->promo_link_right;
 	    $home_logo = $this->live_home_logo;
 	    $guest_logo = $this->live_guest_logo;
 	    $live_refresh = $this->live_refresh;
 	    return SwissUnihockey_Api_Public::api_club_getCurrentGameDetails($season, $club_ID, $club_shortname, $team_ID, $mode, $game_id, $home_logo, $guest_logo, $promo_left, $promo_middle, $promo_right, $live_refresh);
	}


	function api_get_Player ($player_id = NULL ){
		//echo "<p class='error suhv'>THE ID ".$player_id."<br></p>";
		if ( $player_id != NULL )
		 $this->set_player( $player_id );
		else
 		if ( !isset ( $this->player_id ) ) $this->set_player();
		$player_id = $this->player_id ;
		//echo "<p class='error suhv'>THE ID - ".$player_id."<br></p>";
 	    $sponsor_name = $this->sponsor_name;
 	    $sponsor_sub = $this->sponsor_sub;
 	    $sponsor_logo = $this->sponsor_logo;
 	    $sponsor_link = $this->sponsor_link;
 	    $sponsor_link_title = $this->sponsor_link_title;
 	    $cache = $this->use_cache;
		return SwissUnihockey_Api_Public::api_getPlayer($player_id, $sponsor_name, $sponsor_sub, $sponsor_logo, $sponsor_link, $sponsor_link_title, $cache );
	}

	function api_show_params(){
 		if ( !isset ( $this->team ) ) $this->set_team();
 	    if ( !isset ( $this->club ) ) $this->set_club();
 	    $season = $this->season;
 	    $club_ID = $this->club_id;
 	    $team_ID = $this->team_id;
 	    $mode = "team";
		return SwissUnihockey_Api_Public::api_show_params($season, $club_ID, $team_ID, $mode);	
	}

	function api_show_vars(){
		echo 'Club-ID: '.$this->club_id.'<br>';
		echo 'Club-Shortname: '.$this->club_id.'<br>';
		echo 'Team-ID '.$this->team_id.'<br>';
		echo 'Player-ID: '.$this->player_id.'<br>';
		echo 'Sponsor Name: '.$this->sponsor_name.'<br>';
	    echo 'Sponsor Sub: '.$this->sponsor_sub.'<br>';
	    echo 'Sponsor Logo: '.$this->sponsor_logo.'<br>';
	    echo 'Sponsor Link: '.$this->sponsor_link.'<br>';
	    echo 'Sponsor Link Title: '.$this->sponsor_link_title.'<br>';
	    echo 'Saison: '.$this->season.'<br>';
		echo 'Liga ID: '.$this->league_id.'<br>';
 	    echo 'Liga Class: '.$this->league_class.'<br>';
 	    echo 'Liga Gruppe: '.$this->league_group.'<br>';
 	    echo 'Liga Runde: '.$this->league_round.'<br>';
	}

    function api_show_processing(){
	  echo 'processing...';
      $moment = "<img src=\"https://www.churunihockey.ch/picture_library/cu/icons/processing.gif\" title=\"Moment bitte!\">";
      echo $moment;
      flush(); 
    }

    function api_reload_processing(){
      if ( !isset ( $this->live_auto_reload ) ) $this->set_live_auto_reload();
      $live_auto_reload = $this->live_auto_reload;
      if ( $live_auto_reload <= 9) $live_auto_reload = 20;
      $reload_time = $live_auto_reload*1000;
	  echo "Last update: ".date("H:i s")."&Prime; auto-reload every ".$live_auto_reload." seconds...";
      $moment = "<img src=\"https://www.churunihockey.ch/picture_library/cu/icons/processing.gif\" title=\"Moment bitte!\">\n"
      ."<script type=\"text/javascript\">\n"
	  ."<!--\n"
	  ."setTimeout(function(){location.reload();},".$reload_time.");\n"
	  ."--></script>\n";
      echo $moment."<br />";
      flush(); 
    }
	

} // End class suhv_WP
	
function Suhv_WP_init() {
	 global $Suhv_WP;
	 date_default_timezone_set("Europe/Paris");
	 $Suhv_WP = new Suhv_WP(); 	
 }


add_action('plugins_loaded', 'Suhv_WP_init');

// Add Cron

	function log_cron($message){

	    if ((substr_count( $_SERVER['SERVER_NAME'] ,"churunihockey")>=1) or (substr_count( $_SERVER['SERVER_NAME'] ,"localhost")>=1)) {
	     date_default_timezone_set("Europe/Paris");
	     if ( (idate("H") == 7) and ((idate("i") % 25) == 0) ) {
            $myfile = fopen("cronlog.txt", "w") or die("Unable to open file!");
            $message = $_SERVER['SERVER_NAME']." Log cleared: ".date("d.m.Y - H:i:s");
         }
	     else $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
	     fwrite($myfile, $message."\n");
	     fclose($myfile);
	   }
	}


	 //****************************************
	 // add custom interval
	function cron_add_1minutes( $schedules ) {
	  // Adds once every minute to the existing schedules.
	    $schedules['every1minutes'] = array(
	      'interval' => 60*1,
	      'display' => __( 'Every 1 minutes' )
	    );
	    SwissUnihockey_Api_Public::log_me('new schedule insert every1minutes');
	    SwissUnihockey_Api_Public::log_me($_SERVER['SERVER_NAME']);
	    return $schedules;
	}


	// ADD Cron task
	function my_cronjob_suhv_action () {
	    // code to execute on cron run
	  date_default_timezone_set("Europe/Paris");
	   
	  //log_cron($_SERVER['SERVER_NAME']." - Plugin-Version: ".SUHV_API_WP_VERSION." start: ".date("d.m.Y - H:i:s")." minute:".idate("i")." day:".idate("w"));
	  $run = false;
	  $Suhv_WP_instance = new Suhv_WP(); 
	  if ((idate("i") % 5) == 0) { // only every 5 minutes
	    $Suhv_WP_instance->api_club_getGames_Mails();
	    log_cron("GameMail called: ".date("H:i:s"));
	    $run = true;
	  }
	  if ((idate("w") == 0) or (idate("w") >= 4) or ((idate("i") % 10) == 0)) { // on sunday oder saturday - else all 10 minutes
	    $args = array();
	    $Suhv_WP_instance->api_club_getCurrentGameDetails($args);
	    log_cron("LiveGame called: ".date("H:i:s"));
	    $run = true;
	  }
	  if ($run) {
	    log_cron($_SERVER['SERVER_NAME'].': cronjob running - at '.date("d.m.Y - H:i:s")." minute:".idate("i")." day:".idate("w")." - Plugin-Version: ".SUHV_API_WP_VERSION);
	  }
	} 


	//****************************************

	register_activation_hook(__FILE__, 'my_activation');

	function my_activation() {
	    $args = array();
	    if (! wp_next_scheduled ( 'my_suhv_event', $args )) {
	     wp_schedule_event(time(), 'every1minutes', 'my_suhv_event', $args );
	    }
	    SwissUnihockey_Api_Public::log_me('new cron hook set suhv');
	}

	add_action('my_suhv_event', 'my_cronjob_suhv_action');

	//****************************************

	register_deactivation_hook(__FILE__, 'my_deactivation');

	function my_deactivation() {
	  wp_clear_scheduled_hook('my_suhv_event');
	  SwissUnihockey_Api_Public::log_me('deaktivate cron hook suhv');
	}

	//****************************************

	// END Add Cron
	 

} // End if ( !class_exists( 'Suhv_WP' ) )
else echo "<p class='error suhv'>Es besteht eine Kollision mit einer anderen Klasse welche ebenfalls Suhv_WP heisst!</p>";