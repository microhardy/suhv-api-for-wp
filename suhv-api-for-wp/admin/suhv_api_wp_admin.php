<?php
/**
 * Admin Page for SUHV API
 * @author Thonmas Hardegger / new API 2.0 / based on Jérôme Meier / old API
 * @version 29.12.2020
 * STATUS: reviewed
 */

if ( !class_exists( 'Suhv_WP_Options' ) ) {
 class SUHV_WP_Options {
 	
 	public $options;
 	
  public function __construct()
 	{
 	  $this->register_settings_and_fields();
 		$this->options = get_option( 'SUHV_WP_plugin_options' );
 		if (($this->options['SUHV_api_key'] == "" ) or ($this->options['SUHV_api_secret'] == ""))
 		 add_action('admin_notices', array( $this, 'admin_notice_api_key' ));
 	}
 	
  public function admin_notice_api_key()
  {
   echo '<div class="error fade">
          <p><strong>Die SUHV API muss konfiguriert werden. Gehe zu den <a href="' . admin_url( 'options-general.php?page=' . SUHV_API_WP_PLUGIN_DIRNAME  . '/admin/suhv_api_wp_admin.php' ) . '">Einstellungen</a> um den SUHV API Key + Secret zu setzen.</strong></p>
         </div>';
  }

 	
 	public static function add_menu_page()
 	{
 	  add_options_page( 'SUHV API', 'SUHV API', 'administrator', __FILE__, array( 'SUHV_WP_Options', 'display_options_page') );	
 	}
  
 	public function display_options_page	()
 	{
 	 ?>   
   <div class='wrap'>
    <?php screen_icon(); ?>
    <h2>Einstellungen &#8250; SUHV API</h2>
    <form method='post' action='options.php'>
     <?php 
 				  settings_fields( 'SUHV_WP_plugin_options' ); // WP Security hidden Fields
 					do_settings_sections( __FILE__ );
 				?>
    <h3>Schnelleinstieg</h3>
    <p>Für eine ausführliche Beschreibung des Plugins besuche die <a href='http://suhv.churunihockey.ch' title='Swiss Unihockey API 2.0 für WordPress'>Plugin-Website</a> von <a href='mailto:websupport@churunihockey.ch' title='websupport'>Thomas Hardegger</a></p>
     <?php 
 					do_settings_sections( __FILE__ . "_description" );
 				?>
     <p class='submit'><input name='submit' type='submit' value='Änderungen übernehmen' class='button-primary' /></p>
    </form>
   </div>
    <?php
 	}
 	
 	public function register_settings_and_fields()
 	{
 		register_setting( 'SUHV_WP_plugin_options', 'SUHV_WP_plugin_options' ); //3rd param = optional callback
 		add_settings_section( 'SUHV_main_section', '', array( $this, 'SUHV_main_section_cb' ), __FILE__ ); //id, title of section, cb, page
 		add_settings_field( 'SUHV_api_key', 'SUHV API Key', array( $this, 'SUHV_api_key_setting' ), __FILE__,  'SUHV_main_section'); // name, title, function to input, ?, section
    add_settings_field( 'SUHV_api_secret', 'SUHV API Secret', array( $this, 'SUHV_api_secret_setting' ), __FILE__,  'SUHV_main_section'); // name, title, function to input, ?, section
 		add_settings_field( 'SUHV_club_id', 'SUHV Club ID', array( $this, 'SUHV_club_id_setting' ), __FILE__,  'SUHV_main_section'); // name, title, function to input, ?, section
 		add_settings_field( 'SUHV_default_team_id', 'SUHV Team ID', array( $this, 'SUHV_default_team_id_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_default_club_shortname', 'SUHV Club Shortname', array( $this, 'SUHV_default_club_shortname_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_css_file', 'SUHV CSS File laden', array( $this, 'SUHV_css_file_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_language_file', 'SUHV language', array( $this, 'SUHV_language_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_css_tablepress', 'SUHV Tablepress-Support', array( $this, 'SUHV_css_tablepress_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_cache', 'SUHV Cache aktivieren', array( $this, 'SUHV_cache_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_long_cache', 'SUHV Long Cache', array( $this, 'SUHV_long_cache_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_club_games_limit', 'SUHV Games Limit', array( $this, 'SUHV_club_games_limits_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_mail_actual_result', 'SUHV Email Actual', array( $this, 'SUHV_mail_actual_result_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_mail_final_result', 'SUHV Email Result', array( $this, 'SUHV_mail_final_result_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_mail_send_from', 'SUHV Email From', array( $this, 'SUHV_mail_send_from_setting' ), __FILE__,  'SUHV_main_section');
    add_settings_field( 'SUHV_default_home_location', 'Heimspiel Ort', array( $this, 'SUHV_default_home_location' ), __FILE__,  'SUHV_main_section');
 		//add_settings_field( 'SUHV_log', 'SUHV Logdaten anzeigen', array( $this, 'SUHV_log_setting' ), __FILE__,  'SUHV_main_section');
 		add_settings_section( 'SUHV_description_section', '', array( $this, 'SUHV_description_section_cb' ), __FILE__ . "_description" ); //id, title of section, cb, page
 		add_settings_field( 'SUHV_shortcode_club', 'SUHV Club', array( $this, 'SUHV_shortcodes_club' ), __FILE__ . "_description",  'SUHV_description_section');
 		add_settings_field( 'SUHV_shortcode_team', 'SUHV Team', array( $this, 'SUHV_shortcodes_team' ), __FILE__ . "_description",  'SUHV_description_section');
 		add_settings_field( 'SUHV_shortcode_league', 'SUHV League', array( $this, 'SUHV_shortcodes_league' ), __FILE__ . "_description",  'SUHV_description_section');

 		add_settings_field( 'SUHV_shortcode_widget', 'Shortcodes in Widgets', array( $this, 'SUHV_shortcodes_widget' ), __FILE__ . "_description",  'SUHV_description_section');
 	}
 	
 	public function SUHV_main_section_cb()
 	{
 	 //optional	
 	}
 	public function SUHV_description_section_cb()
 	{
 	 echo "<p>Für den schnellen Einstieg findest du nachfolgend alle verfügbaren Shortcodes. Diese können direkt im Texteditor aufgerufen werden.</p>";
 	}
 	
 	// API Key  & Secret
 	public function SUHV_api_key_setting()
 	{
 		echo "<input name='SUHV_WP_plugin_options[SUHV_api_key]' style='width:300px' type='text' value='" . $this->options['SUHV_api_key'] . "' /><br>";
 		echo "<span class='description'>Für den Zugriff auf das swiss unihockey API wird der API Key benötigt. Infos <a href='https://api-v2.swissunihockey.ch/api/doc' title='Swiss Unihockey API Registration'>hier</a>. Die Weitergabe an Drittpersonen ist untersagt.</span>";
 	}
  public function SUHV_api_secret_setting()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_api_secret]' style='width:300px' type='text' value='" . $this->options['SUHV_api_secret'] . "' /><br>";
    echo "<span class='description'>Für den Zugriff auf das swiss unihockey API wird das API Secret benötigt. Infos <a href='https://api-v2.swissunihockey.ch/api/doc' title='Swiss Unihockey API Registration'>hier</a>. Die Weitergabe an Drittpersonen ist untersagt.</span>";
  }
  
 	
 	// Club ID
 	public function SUHV_club_id_setting()
 	{
 		echo "<input name='SUHV_WP_plugin_options[SUHV_club_id]' type='text' value='" . $this->options['SUHV_club_id'] . "' /><br>";
 		echo "<span class='description'>Standard Club ID. Kann durch Verwendung der benutzerdefinierten Felder innerhalb einer Seite bzw. eines Beitrages angepasst werden. (Feldname = 'SUHV Club ID')</span>";
 	}
 	
 	// Default Team ID
 	public function SUHV_default_team_id_setting()
 	{
 		echo "<input name='SUHV_WP_plugin_options[SUHV_default_team_id]' type='text' value='" . $this->options['SUHV_default_team_id'] . "' /><br>";
 		echo "<span class='description'>Standard Team ID. Kann durch Verwendung der benutzerdefinierten Felder innerhalb Seite bzw. eines Beitrages (temporär) angepasst werden. (Feldname = 'SUHV Team ID')</span>";
 	}

    // Default Club Shortname
  public function SUHV_default_club_shortname_setting()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_default_club_shortname]' type='text' value='" . $this->options['SUHV_default_club_shortname'] . "' /><br>";
    echo "<span class='description'>Clubname Kurzbezeichung Ort. Beipiel 'Chur' oder 'piranha chur'.</span>";
  }


  // Anzeige der Anzahl Spiele 
  public function SUHV_club_games_limits_setting()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_club_games_limit]' style='width:50px' type='text' value='" . $this->options['SUHV_club_games_limit'] . "' />";
    echo "<span class='description'> Anzahl n Games in der Club Games Anzeige. (Beispiel: 10)</span>";
 
   }
  // Mail für Zwischenresultate / Schlussresultate
  public function SUHV_mail_actual_result_setting()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_mail_actual_result]' style='width:300px' type='text' value='" . $this->options['SUHV_mail_actual_result'] . "' />";
    echo "<span class='description'> Mailadresse für Zwischenresultate</span>";
 
  }

  public function SUHV_mail_final_result_setting()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_mail_final_result]' style='width:300px' type='text' value='" . $this->options['SUHV_mail_final_result'] . "' />";
    echo "<span class='description'> Mailadresse für Endresultate</span>";
 
  }
  public function SUHV_mail_send_from_setting()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_mail_send_from]' style='width:300px' type='text' value='" . $this->options['SUHV_mail_send_from'] . "' />";
    echo "<span class='description'> Mailadresse des Absenders</span>";
  }

 	
 	// Default CSS loading?
 	//public function SUHV_css_setting()
 	//{echo "<label><input name='SUHV_WP_plugin_options[SUHV_css]' type='checkbox' value='1' " . checked( 1, $this->options['SUHV_css'], false ) . "/> Ja, das Standard-CSS bitte laden.</label><br>";}

  // Default CSS File ?
  public function SUHV_css_file_setting()
  {    
      $filesearch = SUHV_API_WP_PLUGIN_PATH."includes/suhv/styles/*.css";
      $fpath = SUHV_API_WP_PLUGIN_PATH."includes/suhv/styles/";
      $len = strlen($fpath);
      $css_files[]="- nein -";
      $files=glob($filesearch);
      foreach ($files as $file) {
        $css_files[]= substr($file,$len);
      }
      echo "<select name='SUHV_WP_plugin_options[SUHV_css_file]' value='" . $this->options['SUHV_css_file'] . "' />";
      //echo "<select>";
      foreach ($css_files as $option) {
        echo '<option value="' . $option . '" id="' . $option . '"', $this->options['SUHV_css_file'] == $option ? ' selected="selected"' : '', '>', $option , '</option>';
      }
      echo "</select>";

  }

  // Default language File ?
  public function SUHV_language_setting()
  {    
      $filesearch = SUHV_API_WP_PLUGIN_PATH."includes/suhv/language/*.json";
      $fpath = SUHV_API_WP_PLUGIN_PATH."includes/suhv/language/";
      $len = strlen($fpath);
      $language_files[]="- default -";
      $files=glob($filesearch);
      foreach ($files as $file) {
        $language_files[]= substr($file,$len);
      }
      echo "<select name='SUHV_WP_plugin_options[SUHV_language_file]' value='" . $this->options['SUHV_language_file'] . "' />";
      //echo "<select>";
      foreach ($language_files as $option) {
        echo '<option value="' . $option . '" id="' . $option . '"', $this->options['SUHV_language_file'] == $option ? ' selected="selected"' : '', '>', $option , '</option>';
      }
      echo "</select>";

  }

  // Add Tablepress Class?
  public function SUHV_css_tablepress_setting()
  {
    echo "<label><input name='SUHV_WP_plugin_options[SUHV_css_tablepress]' type='checkbox' value='1' " . checked( 1, $this->options['SUHV_css_tablepress'], false ) . "/> Ja, Tablepress-Class einfügen</label><br>";
  }

  // Default Cache setting?
  public function SUHV_cache_setting()
  {
    echo "<label><input name='SUHV_WP_plugin_options[SUHV_cache]' type='checkbox' value='1' " . checked( 1, $this->options['SUHV_cache'], false ) . "/> Ja, Cache einschalten. (Bitte nur zu Testzwecken ausschalten)</label><br>";
  }

    public function SUHV_long_cache_setting()
  {
    echo "<label><input name='SUHV_WP_plugin_options[SUHV_long_cache]' type='checkbox' value='1' " . checked( 1, $this->options['SUHV_long_cache'], false ) . "/>Langzeit-Cache (Falls swissunihockey überlastet)</label><br>";
  }
  
 	// Default Club Homegame Location
  public function SUHV_default_home_location()
  {
    echo "<input name='SUHV_WP_plugin_options[SUHV_default_home_location]' type='text' value='" . $this->options['SUHV_default_home_location'] . "' /> ";
    echo "<span class='description'>Heimspielaustragungsort. Beipiele: 'Chur' oder 'Maienfeld' oder 'Zuchwil'.</span>";
  }

 	// Default Logfiles?
 	public function SUHV_log_setting()
 	{
 		echo "<label><input name='SUHV_WP_plugin_options[SUHV_log]' type='checkbox' value='1' " . checked( 1, $this->options['SUHV_log'], false ) . "/> Ja, die Log-Daten des SUHV-Frameworks am Seitenende anzeigen (nur für WordPress-Administratoren sichtbar).</label><br>";
 	}
 	
 	public function SUHV_shortcodes_club()
 	{
 	echo "[suhv-api-club-get-games<span class='description'>] Die nächsten n Spiele des Vereins (Anzahl n Spiele in den Einstellungen festlegen) </span><br>"; 
    echo "[suhv-api-club-get-games <span class='description'>club_id=\"423403\"] Optional \"club_id\" z.B. für anderen Club</span><br>"; 
    echo "[suhv-api-club-get-cupgames]* <span class='description'>Alle Cup-Spiele des NLA-Teams (*depreciated/nicht mehr verwenden)</span><br>"; 
    echo "[suhv-api-club-get-weekend-games start_date=\"19.09.2020\" end_date=\"20.09.2020\"]<span class='description'> Spiele des Clubs am Wochenende</span><br>";
    echo "[suhv-api-club-get-weekend-games <span class='description'>club_id=\"423403\"] Spiele des Clubs oder Club mit ID am aktuellen Wochenende (Mittwoch bis Dienstag)</span><br>";
    echo "[suhv-api-club-get-currentgamedetails]<span class='description'> Details der aktuellen Direktbegegnung</span><br>";
    echo "Bestimmende Variablen: 'SUHV Club ID'<br>";
    echo "[suhv-api-get-directgames]<span class='description'> Letzte Direktbegegnung</span><br>";
    echo "Bestimmende Variablen: 'SUHV Game ID'";

 	}
 	
 	public function SUHV_shortcodes_team()
 	{
 		echo "[suhv-api-team-get-games] <span class='description'>Nächste Spiele des Teams</span><br>"; 
    echo "[suhv-api-team-get-games <span class='description'>team_id=\"429283\"]</span><br>";
 		echo "[suhv-api-team-get-playedgames] <span class='description'>Gespielte Spiele des Teams</span><br>";
    echo "[suhv-api-team-get-playedgames team_id=\"429283\"] <span class='description'></span><br>";
    echo "[suhv-api-team-get-gamedetails] <span class='description'>Details der Direktbegegnungen des Teams am aktuellen Wochenende <strong>(Mittwoch bis Dienstag)</strong></span><br>";
    echo "[suhv-api-team-get-gamedetails game_id=\"963116\"] <span class='description'>Details der Direktbegegnung des Spiels mit ID</span><br>";
    echo "[suhv-api-team-get-gamedetails team_id=\"429283\"] <span class='description'>Details der Direktbegegnung des Teams (new)</span><br>";
 		echo "[suhv-api-get-team-table] <span class='description'>Tabelle des Teams</span><br>"; 
    echo "[suhv-api-get-team-table team_id=\"429283\"] <span class='description'></span><br>";
 		echo "[suhv-api-get-team-rank] <span class='description'>Rangliste in Liga des Teams</span><br>";	
    echo "[suhv-api-get-team-rank team_id=\"429283\"] <span class='description'></span><br>";
 		echo "[suhv-api-nla-team-get-table] <span class='description'>Tabelle des NLA Teams (*depreciated/nicht mehr verwenden)</span><br>"; 
 		echo "[suhv-api-get-team-table_nla] <span class='description'>Tabelle des NLA Teams (*depreciated/nicht mehr verwenden)</span><br>"; 
    echo "[suhv-api-get-team-rank_nla] <span class='description'>Rangliste des NLA Teams (*depreciated/nicht mehr verwenden)</span><br>";
    echo "Bestimmende Variablen oder Seitenvariablen: 'SUHV Club ID' & 'SUHV Team ID'";
 	}
 	
 	public function SUHV_shortcodes_league()
 	{
 		echo "[suhv-api-league-get-games] <span class='description'>Alle Meisterschaftsrunden der Liga</span><br>"; 
    echo "[suhv-api-league-get-weekend-games] <span class='description'>Alle Spiele der Liga am aktuellen Wochenende</span><br>"; 
    echo "Bestimmende Seitenvariablen: 'SUHV League ID' & 'SUHV Round ID' & 'SUHV Class ID' & 'SUHV Group ID'<br>";
    echo "Hinweise zu IDs: ";
    echo "<span class='description'><a href=\"https://www.swissunihockey.ch/de/league-group-detail/\">Liga-IDs auf swissunihockey</a></span><br>";
 	}
 	
 	public function SUHV_shortcodes_widget()
 	{
 		echo "Achtung, nicht alle WordPress-Templates unterstützen Shortcodes in Widgets. Füge folgendes in dein functions.php hinzu, falls du damit Probleme hast:	<span class='description'>add_filter('widget_text', 'do_shortcode');</span><br>";
 	}
 
 	
 } // END Class SUHV_WP_Options
} // END if Class SUHV_WP_Options Exists

add_action('admin_menu', function() {
  SUHV_WP_Options::add_menu_page();
});

add_action( 'admin_init', function(){
 new SUHV_WP_Options(); 
});

