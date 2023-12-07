<?php
/***
 * Classes that return HTML Code from SUHV Classes like SuhvClub or SuhvTeam
 * 
 * @author Thomas Hardegger 
 * @version 14.09.2021
 * STATUS: reviewed
 */


class SwissUnihockey_Api_Public {


private static function cacheTime() {
        
  $cbase = 5*60; // 5 Min.
  $tag = date("w");
  //(Sonntag,Montag,Dienstag,Mittwoch,Donnerstag,Freitag,Samstag);
  $cacheValue = array(1*$cbase,6*$cbase,6*$cbase,3*$cbase,3*$cbase,2*$cbase,1*$cbase);
  return($cacheValue[$tag]);
}

/* ------------------------------------------------------------------------------------ */
// Language
private static function translate($group,$value) {
      $data = get_transient( 'SUHV_language_codes');
      //SwissUnihockey_Api_Public::log_me($data->$group->$value);
      return $data->$group->$value;
    }

private static function language_group($group) {
      $data = get_transient( 'SUHV_language_codes');
      //SwissUnihockey_Api_Public::log_me(array($data->$group));
      return $data->$group;
    }
/* ------------------------------------------------------------------------------------ */

private static function nearWeekend() {
               // So Mo Di (Mi=4) Do Fr Sa //
  $dayline = array(0,-1,-2,4,3,2,1);
  $tag = date("w");
  $today = strtotime("today");
  $daytoSunday = $dayline[$tag];
  $sunday = strtotime($daytoSunday." day",$today);
  $saturday = strtotime("-1 day",$sunday);
  $friday = strtotime("-2 day",$sunday);
  $thursday = strtotime("-3 day",$sunday);
  $weekendDays= array("Donnerstag"=>$thursday,"Freitag"=>$friday,"Samstag"=>$saturday,"Sonntag"=>$sunday);

  return($weekendDays);
}

private static function clean_league($game_league, $game_group="") {

    $game_league = str_replace(" Regional", " (".$game_group.") ", $game_league);
    // $game_league = str_replace ("Junioren", "",$game_league);
    $game_league = str_replace ("Juniorinnen", "",$game_league);
    $game_league = str_replace ("/-innen ", "",$game_league);
    $game_league = str_replace ("Herren Aktive", "",$game_league);
    // $game_league = str_replace ("Herren", "",$game_league);
    $game_league = str_replace ("Aktive", "",$game_league);
    $game_league = str_replace ("Schweizer", "",$game_league);
    $game_league = str_replace ("Mobiliar Unihockey Cup", "Cup",$game_league);
    $game_league = str_replace ("Damen Supercup","Damen NLA",$game_league);
    // $game_league = str_replace ("Damen", "",$game_league);
    return($game_league);
}

private static function suhvDown() {

  // return FALSE; // 14.9.2020 enabled 

  $options = get_option( 'SUHV_WP_plugin_options' );
  if (isset($options['SUHV_long_cache']) == 1) {

    $transient = "suhv-api-http-check";
    $allOK = get_transient( $transient );

    if ($allOK == FALSE) {

          $url = 'https://api-v2.swissunihockey.ch/api/games?mode=club&club_id=423403&season=2020';
          $ch = curl_init($url);
          curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
          curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
          curl_setopt($ch, CURLOPT_TIMEOUT,10);
          $response = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          $stringinbodyOK = strpos($response, 'Chur');
          curl_close($ch);
          if ($stringinbodyOK and ($httpcode == 200)) {
            $allOK = TRUE; 
          }
          else {
            $homeurl = home_url();
            if (stripos($homeurl,"www.churunihockey.ch")>0) {
              $transientMail = "suhv-api-http-check-Mail";
              $mailOK = get_transient( $transientMail );
              if (!$mailOK) {
                $mailheaders = 'From: API-Check <'.'thomas@hardegger.com'.'>' . "\r\n";
                $mailheaders .= "MIME-Version: 1.0\r\n";
                $mailheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message = "API Access via HTTP not OK <br />";
                $message .= $url."<br /><br />";
                $message .= "Check now: https://www.churunihockey.ch//wp-content/plugins/suhv-api-for-wp/includes/suhv/php/testAPI.php";
                $checkmail = wp_mail( "logs@hardegger.com", "HTTP Response Check: HTTPCODE ".$httpcode, $message, $mailheaders);
                set_transient( $transientMail, TRUE, 60*60); // nur alle 60 Min. ein Down Mail
              }
            }
            SwissUnihockey_Api_Public::log_me("HTTPCODE: ".$httpcode);
          }
          set_transient( $transient, $allOK, 15*60); // nur alle 15 Min. ein Down Check
    }
  }
  else $allOK = TRUE;
  return !$allOK;

}

/* ---------------------------------------------------------------------------------------------------- */
  public static function api_club_getGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {
    
    $team_ID = NULL;
    $trans_Factor = 1;
    $my_club_name = $club_shortname;
    //SwissUnihockey_Api_Public::log_me($my_club_name);
    $cup = FALSE;
    $transient = "suhv-"."club_getGames".$club_ID.$team_ID.$season.$mode;
    $secure_trans = $transient."Secure";
    $semaphore = $club_ID.$team_ID."club_getGames-Flag";
    $value = get_transient( $transient );
    $flag = get_transient( $semaphore);
    $linkGame_ID = NULL;
    $likkGame_ID_before = NULL;

    if ($flag) $sema_value = "Sema: TRUE"; else  $sema_value = "Sema: FALSE";
    //SwissUnihockey_Api_Public::log_me($sema_value);
    
    if (!$cache) { $value = False; }

    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE;}

    if (($value == False) and ($flag == False)) {

        set_transient( $semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

        $go =  time();
        $api_calls = 0;
        $plugin_options = get_option( 'SUHV_WP_plugin_options' );
        $n_Games = $plugin_options['SUHV_club_games_limit'];
        $tablepress ='';
        if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress"; 


        // SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

        $skip = "<br />";

        $html = "";
        $html_res = "";
        $html_body = "";

        $tage = SwissUnihockey_Api_Public::language_group('Days');
        $heute = SwissUnihockey_Api_Public::translate('Time','heute');
        $gestern = SwissUnihockey_Api_Public::translate('Time','gestern');
        $morgen = SwissUnihockey_Api_Public::translate('Time','morgen');
        $tag = date("w");
        $wochentag = $tage->$tag;

        $api = new SwissUnihockey_Public(); 
        $api_calls++;
        $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
    
    ));

    // Eine Seite retour bei Page-Ende?
    

    $data = $details->data; 
    $startpage = $data->context->page;
    // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 1 page: ".$startpage);

    if ($startpage!=1) { // eine Page retour wenn nicht erste
         $page = $startpage-1;
         $month = date('m');
         if($month == "6") {$page=1;} // during June is the schedule not ready
         $api = new SwissUnihockey_Public(); 
         $api_calls++;
         $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
           'page' => $page
         )); 
        // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 2 page: ".$page);
    }

    $data = $details->data; 

    $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);

    $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
    $header_Leage = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
    $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);
    $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[4]->text);
    $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[5]->text);
    $Cpos = strripos($my_club_name,'Chur');
    if (!is_bool($Cpos)) { $header_Result = "Res.";}
    $club_name = $data->title;
    $games = $data->regions[0]->rows;
    $attributes = $data->regions[0]->rows[0]->cells;
    
    $entries = count($games);
    
    $transient_games = $transient.$tag;
    $last_games = get_transient( $transient_games );
    if ($last_games == FALSE) {
      $last_games = $games;
      set_transient( $transient_games, $last_games, 2*55*60 );
      // echo "<br>Reset Games";60
    }
    $loop = FALSE;
    $tabs = $data->context->tabs;
    if ($tabs == "on") $loop = TRUE;
    $startpage = $data->context->page;
    $page = $startpage;

    $items = 0;
    $today = strtotime("now");
    $startdate = strtotime("-3 days",$today);
    $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;

    if (!$cache) {
      $view_cache = "<br> cache = off / Display: ".$n_Games." Club:".$my_club_name ." / Page:".$page; 
      } else {$view_cache ="";
    }
    
    $Gamedetails = SwissUnihockey_Api_Public::translate("Replacements","Spieldetails");
    $html_head = "<div class=\"suhvIncludes twoRight\">\n\t<table class=\"table nobr\">\n";
    
    $latestDateOfGame = strtotime("1970-01-01");

    error_reporting(E_ALL & ~E_NOTICE);
    while ($loop) {
    $i = 0;
    do {
          $game_id = $games[$i]->link->ids[0];
          $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
          $game_date = $games[$i]->cells[0]->text[0];
          $game_time = $games[$i]->cells[0]->text[1];
          if ($game_time != "???") {
            $game_location_name = $games[$i]->cells[1]->text[0]; 
            $game_location = $games[$i]->cells[1]->text[1]; 
            $game_map_x = $games[$i]->cells[1]->link->x;
            $game_map_y = $games[$i]->cells[1]->link->y;
          }
          else {
            $game_location_name = "";
            $game_location = ""; 
            $game_map_x = "";
            $game_map_y = "";
          }
          $game_league = $games[$i]->cells[2]->text[0];
          $game_group = $games[$i]->cells[2]->text[1]; 
          $game_homeclub = $games[$i]->cells[3]->text[0]; 
          $game_guestclub = $games[$i]->cells[4]->text[0]; 
          $game_result = $games[$i]->cells[5]->text[0];
          $linkGame_ID = $games[$i]->link->ids[0];
          $new_result = $game_result;
          $game_result_add = "";
          if (isset($games[$i]->cells[5]->text[1])) {$game_result_add = $games[$i]->cells[5]->text[1];}
          $game_home_result = substr($game_result,0,stripos($game_result,":"));
          $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
          $site_url = get_site_url();
          $site_display = substr($site_url,stripos($site_url,"://")+3);
        
          //Fehlerkorrektur für vom 7.1.2017 + Php 7.+
          if ($game_date=="today") $game_date="heute";
          if ($game_date=="yesterday") $game_date="gestern";
          if ($game_date=="tomorrow") $game_date="morgen";

          if (($game_date=="heute") or ($game_date=="gestern") or ($game_date=="morgen"))  {
            if ($game_date=="heute")  { 
              $date_of_game = strtotime("today");
              $last_result = $game_result;
            }
            if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
            if ($game_date=="morgen") $date_of_game = strtotime("tomorrow");
          }
          else{
           $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
           $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
          }

          $gameLocationLinkTitle = "";
          if ($game_location) {
            $gameLocationLinkTitle .= $game_location;
            if ($game_location_name) {
              $gameLocationLinkTitle .= " (" . $game_location_name . ")";
            }
          }
          $game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $gameLocationLinkTitle . "\">";
     
          $game_homeDisplay = $game_homeclub;
          $game_guestDisplay = $game_guestclub;

          /* If Cup?
          if (substr_count($game_league,"Cup")>=1) { 
            $cup = TRUE;
          } */

          $special_league = "Junioren/-innen U14/U17 VM";
          $team_one = $my_club_name." I";
          $team_two = $my_club_name." II";
          $league_short = "U14/U17";
          
          $homeClass ="suhv-place";

          if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) { 
            $resultClass = 'suhv-draw';
            $resultHomeClass = 'suhv-home';
            $resultGuestClass = 'suhv-home';
            if ($game_league == $special_league){
                $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
            }
            $game_homeDisplay = $game_league." ".str_replace ($my_club_name,"",$game_homeDisplay);
            $game_guestDisplay = $game_league." ".str_replace ($my_club_name,"",$game_guestDisplay);
          }
          else {
            if ($game_league == $special_league){
                $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
                if ((substr_count($game_homeDisplay,$team_one )>=1) xor (substr_count($game_guestDisplay,$team_two)>=1) ){
                  if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                    $game_league .=" II"; // Angzeige "U14/U17 II"
                   } 
                  else { 
                    if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                      $game_league .=" I"; // Angzeige "U14/U17 I"
                    }
                 } 
                }
                else {
                  if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                    $game_league .=" II"; // Angzeige "U14/U17 II"
                   } 
                  else { 
                    if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                      $game_league .=" I"; // Angzeige "U14/U17 I"
                    }
                 } 
                }
            }

      $game_league = SwissUnihockey_Api_Public::clean_league($game_league, $game_group);

            if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';} else {$resultClass = 'suhv-result';}

            if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                $game_homeDisplay = $game_league; 
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                $game_homeDisplay = $league_short." ".str_replace ($my_club_name,"",$game_homeDisplay);
              $resultHomeClass = 'suhv-home';
              if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            else $resultHomeClass = 'suhv-guest';
            if (substr_count($game_guestDisplay,$my_club_name)>=1) {
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                $game_guestDisplay = $game_league;
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                $game_guestDisplay = $league_short." ".str_replace ($my_club_name,"",$game_guestDisplay);
              $resultGuestClass = 'suhv-home';
              if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            else $resultGuestClass = 'suhv-guest';
          }

          if ($game_result == "")  { 
            $resultClass = 'suhv-result';
          }
          if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))  {
            $resultClass = 'suhv-activ';
            if (substr_count($game_result,"-")!=0) {
              $game_result = "?";
              $resultClass .= ' suhv-wait';
            }
          } 

          if ($game_date == "heute") {
            $game_summary = "-";
            $game_telegramm = "-";
            $game_date = $heute;
          }
          if ($game_date=="morgen") $game_date = $morgen;
          if ($game_date=="gestern") $game_date = $gestern;
          if (($items <= $n_Games)) {

            
            preg_match("/([0-9]{1,2}):([0-9]{1,2})/", $game_time, $match);
            $game_hour    = $match[1];
            $game_minutes = $match[2];

            $mytimestamp = strtotime("+" . $game_hour . "hours", $date_of_game);
            $mytimestamp = strtotime("+" . $game_minutes . "minutes", $mytimestamp);

            if (($mytimestamp >= $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup
              if ($latestDateOfGame != $date_of_game) {
                $latestDateOfGame = $date_of_game;
                $html_body .= "\n<tr class='suhvFatRow'>";
                $tag = date("w", $date_of_game);
                $html_body .= "\n\t<td colspan='5'>" . $tage->$tag . ", " . str_replace(".20", ".", $game_date) . "</td>";
                //$html_body .= "\n\t<td colspan='5'>".str_replace(".20",".",$game_date)."</td>";
                $html_body .= "\n    </tr>";
              }
              $html_body .= "\n<tr><td>" . $game_time . "</td><td>" . $game_homeDisplay . "</td><td>:</td><td>" . $game_guestDisplay . "</td><td>" . $game_maplink . $gameLocationLinkTitle . "</a></td></tr>";
              // $cup = FALSE; // cup only
            }
          }
          else {
            $loop = FALSE;
          }
          $i++; 
          $linkGame_ID_before = $linkGame_ID;
       } while (($i < $entries) and ($loop));

       if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
       else {
         $page++; 
         if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
         $api = new SwissUnihockey_Public(); 
         $api_calls++;
         $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
            'page' => $page
         )); 
         $data = $details->data; 
         $games = $data->regions[0]->rows;
         $entries = count($games);
         // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
      } // end else
    } // end While
      // Report all errors
    error_reporting(E_ALL);
    $html_head .= $html_res;
    // $html_head .= "</tr></thead><tbody>";
    $html .= $html_head;
    $html .= $html_body;
    // $html .= "</tbody>";
    // $html .= "</table>";
    $html .= "\n</table></div>";

    $stop =  time();
    $secs = ($stop- $go);
    //SwissUnihockey_Api_Public::log_me("club_getGames eval-time: ".$secs." secs  api-calls: ". $api_calls);
    //$html2 = str_replace ("</table>","defekt",$html);// for test
    set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
    set_transient( $transient_games, $last_games, 2*60*60 );
    if (($secs<=10) and isset($data))  {
     $safe_html = str_replace (" min.)"," min. cache value)",$html);
     set_transient( $secure_trans, $safe_html, 12*3600); 
    }
  }
  else { 
      $htmlpos = strpos($value,"</table>");
      $len = strlen($value);
      if (($htmlpos) and ($len > 300)) { 
        $html = $value; // Abfrage war OK
        //SwissUnihockey_Api_Public::log_me("API Cache OK pos: ".$htmlpos." len: ".$len);
      }
      else {
        $value = get_transient( $secure_trans ); // letzte gute Version holen bei Time-Out der API 2.0 von Swissunihockey
        //SwissUnihockey_Api_Public::log_me("API Cache Restore!");
        $html = $value; 
      }
  }
  return $html;
}

/* ---------------------------------------------------------------------------------------------------- */
public static function api_club_getPlayedGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {
    
  $team_ID = NULL;
  $trans_Factor = 1;
  $my_club_name = $club_shortname;
  //SwissUnihockey_Api_Public::log_me($my_club_name);
  $cup = FALSE;
  $transient = "suhv-"."club_getPlayedGames".$club_ID.$team_ID.$season.$mode;
  $secure_trans = $transient."Secure";
  $semaphore = $club_ID.$team_ID."club_getPlayedGames-Flag";
  $value = get_transient( $transient );
  $flag = get_transient( $semaphore);
  $linkGame_ID = NULL;
  $likkGame_ID_before = NULL;

  if ($flag) $sema_value = "Sema: TRUE"; else  $sema_value = "Sema: FALSE";
  //SwissUnihockey_Api_Public::log_me($sema_value);
  
  if (!$cache) { $value = False; }

  if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE;}

  if (($value == False) and ($flag == False)) {

    set_transient( $semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

    $go =  time();
    $api_calls = 0;
    $plugin_options = get_option( 'SUHV_WP_plugin_options' );
    $n_Games = $plugin_options['SUHV_club_games_limit'];
    $tablepress ='';
    if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress"; 


    // SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

    $skip = "<br />";

    $html = "";
    $html_res = "";
    $html_body = "";

    $tage = SwissUnihockey_Api_Public::language_group('Days');
    $heute = SwissUnihockey_Api_Public::translate('Time','heute');
    $gestern = SwissUnihockey_Api_Public::translate('Time','gestern');
    $morgen = SwissUnihockey_Api_Public::translate('Time','morgen');
    $tag = date("w");
    $wochentag = $tage->$tag;

    $api = new SwissUnihockey_Public(); 
    $api_calls++;
    $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(

    )); 
    
    // Eine Seite retour bei Page-Ende? 

    $data = $details->data; 
    $startpage = $data->context->page;
    // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 1 page: ".$startpage);
    //echo "Startpage: ".$startpage."<br>";
    if ($startpage!=1) { // eine Page retour wenn nicht erste
         $page = $startpage-1;
         $month = date('m');
         if($month == "6") {$page=1;} // during June is the schedule not ready
         $api = new SwissUnihockey_Public(); 
         $api_calls++;
         $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
           'page' => $page
         )); 
        // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 2 page: ".$page);
    }

    $data = $details->data; 

    $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);

    $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
    $header_Leage = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
    $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);
    $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[4]->text);
    $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[5]->text);
    $Cpos = strripos($my_club_name,'Chur');
    if (!is_bool($Cpos)) { $header_Result = "Res.";}
    $club_name = $data->title;
    $games = $data->regions[0]->rows;
    $attributes = $data->regions[0]->rows[0]->cells;
    
    $entries = count($games);
    
    $transient_games = $transient.$tag;
    $last_games = get_transient( $transient_games );
    if ($last_games == FALSE) {
      $last_games = $games;
      set_transient( $transient_games, $last_games, 2*55*60 );
      // echo "<br>Reset Games";60
    }
    $loop = FALSE;
    $tabs = $data->context->tabs;
    if ($tabs == "on") $loop = TRUE;
    $startpage = $data->context->page;
    $page = $startpage;

    $items = 0;
    $today = strtotime("now");
    $startdate = strtotime("+1 minutes",$today);
    $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;

    if (!$cache) {
      $view_cache = "<br> cache = off / Display: ".$n_Games." Club:".$my_club_name ." / Page:".$page; 
      } else {$view_cache ="";
    }
    
    $Gamedetails = SwissUnihockey_Api_Public::translate("Replacements","Spieldetails");
    $html_head = "<div class=\"suhvIncludes twoRight\">\n\t<table class=\"table nobr\">\n";
    
    $latestDateOfGame = strtotime("1970-01-01");

    error_reporting(E_ALL & ~E_NOTICE);
    while ($loop) {
    $i = $entries - 1;
    do {
          $game_id = $games[$i]->link->ids[0];
          $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
          $game_date = $games[$i]->cells[0]->text[0];
          $game_time = $games[$i]->cells[0]->text[1];
          if ($game_time != "???") {
            $game_location_name = $games[$i]->cells[1]->text[0]; 
            $game_location = $games[$i]->cells[1]->text[1]; 
            $game_map_x = $games[$i]->cells[1]->link->x;
            $game_map_y = $games[$i]->cells[1]->link->y;
          }
          else {
            $game_location_name = "";
            $game_location = ""; 
            $game_map_x = "";
            $game_map_y = "";
          }
          $game_league = $games[$i]->cells[2]->text[0];
          $game_group = $games[$i]->cells[2]->text[1]; 
          $game_homeclub = $games[$i]->cells[3]->text[0]; 
          $game_guestclub = $games[$i]->cells[4]->text[0]; 
          $game_result = $games[$i]->cells[5]->text[0];
          $linkGame_ID = $games[$i]->link->ids[0];
          $new_result = $game_result;
          $game_result_add = "";
          if (isset($games[$i]->cells[5]->text[1])) {
            $game_result_add = "(".$games[$i]->cells[5]->text[1].")";
          }
          $game_home_result = substr($game_result,0,stripos($game_result,":"));
          $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
          $site_url = get_site_url();
          $site_display = substr($site_url,stripos($site_url,"://")+3);
        
          //Fehlerkorrektur für vom 7.1.2017 + Php 7.+
          if ($game_date=="today") $game_date="heute";
          if ($game_date=="yesterday") $game_date="gestern";
          if ($game_date=="tomorrow") $game_date="morgen";

          if (($game_date=="heute") or ($game_date=="gestern") or ($game_date=="morgen"))  {
            if ($game_date=="heute")  { 
              $date_of_game = strtotime("today");
              $last_result = $game_result;
            }
            if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
            if ($game_date=="morgen") $date_of_game = strtotime("tomorrow");
          }
          else{
           $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
           $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
          }

          $gameLocationLinkTitle = "";
          if ($game_location) {
            $gameLocationLinkTitle .= $game_location;
            if ($game_location_name) {
              $gameLocationLinkTitle .= " (" . $game_location_name . ")";
            }
          }
          $game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $gameLocationLinkTitle . "\">";
     
          $game_homeDisplay = $game_homeclub;
          $game_guestDisplay = $game_guestclub;

          /* If Cup?
          if (substr_count($game_league,"Cup")>=1) { 
            $cup = TRUE;
          } */

          $special_league = "Junioren/-innen U14/U17 VM";
          $team_one = $my_club_name." I";
          $team_two = $my_club_name." II";
          $league_short = "U14/U17";
          
          $homeClass ="suhv-place";

          if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) { 
            $resultClass = 'suhv-draw';
            $resultHomeClass = 'suhv-home';
            $resultGuestClass = 'suhv-home';
            if ($game_league == $special_league){
                $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
            }
            $game_homeDisplay = $game_league." ".str_replace ($my_club_name,"",$game_homeDisplay);
            $game_guestDisplay = $game_league." ".str_replace ($my_club_name,"",$game_guestDisplay);
          }
          else {
            if ($game_league == $special_league){
                $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
                if ((substr_count($game_homeDisplay,$team_one )>=1) xor (substr_count($game_guestDisplay,$team_two)>=1) ){
                  if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                    $game_league .=" II"; // Angzeige "U14/U17 II"
                   } 
                  else { 
                    if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                      $game_league .=" I"; // Angzeige "U14/U17 I"
                    }
                 } 
                }
                else {
                  if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                    $game_league .=" II"; // Angzeige "U14/U17 II"
                   } 
                  else { 
                    if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                      $game_league .=" I"; // Angzeige "U14/U17 I"
                    }
                 } 
                }
            }

            $game_league = SwissUnihockey_Api_Public::clean_league($game_league, $game_group);

            if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';} else {$resultClass = 'suhv-result';}

            if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                $game_homeDisplay = $game_league; 
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                $game_homeDisplay = $league_short." ".str_replace ($my_club_name,"",$game_homeDisplay);
              $resultHomeClass = 'suhv-home';
              if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            else $resultHomeClass = 'suhv-guest';
            if (substr_count($game_guestDisplay,$my_club_name)>=1) {
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                $game_guestDisplay = $game_league;
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                $game_guestDisplay = $league_short." ".str_replace ($my_club_name,"",$game_guestDisplay);
              $resultGuestClass = 'suhv-home';
              if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            else $resultGuestClass = 'suhv-guest';
          }

          if ($game_result == "")  { 
            $resultClass = 'suhv-result';
          }
          if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))  {
            $resultClass = 'suhv-activ';
            if (substr_count($game_result,"-")!=0) {
              $game_result = "?";
              $resultClass .= ' suhv-wait';
            }
          } 

          if ($game_date == "heute") {
            $game_summary = "-";
            $game_telegramm = "-";
            $game_date = $heute;
          }
          if ($game_date=="morgen") $game_date = $morgen;
          if ($game_date=="gestern") $game_date = $gestern;
          if (($items <= $n_Games)) {

            if (($date_of_game <= $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup
              if ($latestDateOfGame != $date_of_game) {
                $latestDateOfGame = $date_of_game;
                $html_body .= "\n<tr class='suhvFatRow'>";
                $tag = date("w", $date_of_game);
                $html_body .= "\n\t<td colspan='5'>" . $tage->$tag . ", " . str_replace(".20", ".", $game_date) . "</td>";
                //$html_body .= "\n\t<td colspan='5'>".str_replace(".20",".",$game_date)."</td>";
                $html_body .= "\n    </tr>";
              }

              if ($resultHomeClass == 'suhv-guest') {
								$html_body .= "\n<tr><td>" . $game_guestDisplay . "</td><td>&nbsp;</td><td>" . $game_guest_result . ":" . $game_home_result . $game_result_add . "</td><td>&nbsp;</td><td>" . $game_homeDisplay . "</td></tr>";
							} else {
								$html_body .= "\n<tr><td>" . $game_homeDisplay . "</td><td>&nbsp;</td><td>" . $game_result . $game_result_add . "</td><td>&nbsp;</td><td>" . $game_guestDisplay . "</td></tr>";
							}
              // $cup = FALSE; // cup only
            }
          }
          else {
            $loop = FALSE;
          }
          $i--; 
          $linkGame_ID_before = $linkGame_ID;
       } while (($i >= 0) and ($loop));

       if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
       else {
         $page++; 
         if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
         $api = new SwissUnihockey_Public(); 
         $api_calls++;
         $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
            'page' => $page
         )); 
         $data = $details->data; 
         $games = $data->regions[0]->rows;
         $entries = count($games);
         // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
      } // end else
    } // end While
      // Report all errors
    error_reporting(E_ALL);
    $html_head .= $html_res;
    // $html_head .= "</tr></thead><tbody>";
    $html .= $html_head;
    $html .= $html_body;
    // $html .= "</tbody>";
    // $html .= "</table>";
    $html .= "\n</table></div>";

    $stop =  time();
    $secs = ($stop- $go);
    //SwissUnihockey_Api_Public::log_me("club_getGames eval-time: ".$secs." secs  api-calls: ". $api_calls);
    //$html2 = str_replace ("</table>","defekt",$html);// for test
    set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
    set_transient( $transient_games, $last_games, 2*60*60 );
    if (($secs<=10) and isset($data))  {
     $safe_html = str_replace (" min.)"," min. cache value)",$html);
     set_transient( $secure_trans, $safe_html, 12*3600); 
    }
  } //end If
  else { 
      $htmlpos = strpos($value,"</table>");
      $len = strlen($value);
      if (($htmlpos) and ($len > 300)) { 
        $html = $value; // Abfrage war OK
        //SwissUnihockey_Api_Public::log_me("API Cache OK pos: ".$htmlpos." len: ".$len);
      }
      else {
        $value = get_transient( $secure_trans ); // letzte gute Version holen bei Time-Out der API 2.0 von Swissunihockey
        //SwissUnihockey_Api_Public::log_me("API Cache Restore!");
        $html = $value; 
      }
  }
  return $html;
}

/* ---------------------------------------------------------------------------------------------------- */
 public static function api_club_getGames_Mails($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {
    
    $team_ID = NULL;
    $trans_Factor = 1;
    $my_club_name = $club_shortname;
   
    $cup = FALSE;
    $transient = "suhv-"."club_getGames_Mails".$club_ID.$team_ID.$season.$mode;
    $semaphore = "suhv-"."club_getGames-Flag_Mails".$club_ID.$team_ID;
    $value = get_transient( $transient );
    $flag = get_transient( $semaphore);
    $linkGame_ID = NULL;
    $likkGame_ID_before = NULL;

    if ($flag) $sema_value = "Sema: TRUE"; else  $sema_value = "Sema: FALSE";
    
    if (!$cache) { $value = False; }

    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE;}

    $plugin_options = get_option( 'SUHV_WP_plugin_options' );
    $n_Games = $plugin_options['SUHV_club_games_limit'];
    $e_Mail_From = $plugin_options['SUHV_mail_send_from'];
    $e_Mail_Actual = $plugin_options['SUHV_mail_actual_result'];
    $e_Mail_Result = $plugin_options['SUHV_mail_final_result'];

    if (($value == False) and ($flag == False) and (substr_count($e_Mail_Result,"@" )>=1)) {

	  //**TEST****
	  if (substr_count($_SERVER['SERVER_NAME'] ,"churunihockey")>=1) {
		  $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
		  $txt = "LoopMail: ".date("H:i:s")."\n";
		  fwrite($myfile, $txt);
		  fclose($myfile);
	  }

      set_transient( $semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

      $go =  time();
      $api_calls = 0;
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress"; 

      //SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames_Mails', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

      $mailheaders = 'From: Spielresultate <'.$e_Mail_From.'>' . "\r\n";
      $mailheaders .= "MIME-Version: 1.0\r\n";
      $mailheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
      $skip = "<br />";

      $html = "";
      $html_res = "";
      $html_body = "";
      $mail_subjekt ="";

      $tage = SwissUnihockey_Api_Public::language_group('Days');
      $Gamedetails = SwissUnihockey_Api_Public::translate("Replacements","Spieldetails");
      $tag = date("w");
      $wochentag = $tage->$tag;

      $api = new SwissUnihockey_Public(); 
      $api_calls++;
      $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
        
      )); 
      
      // Eine Seite retour bei Page-Ende? 

      $data = $details->data; 
      $startpage = $data->context->page;

      if ($startpage!=1) { // eine Page retour wenn nicht erste
           $page = $startpage-1;
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
             'page' => $page
           )); 
      }

      $data = $details->data; 
      $header_DateTime = $data->headers[0]->text;
      $header_Location = $data->headers[1]->text;
      $header_Leage = $data->headers[2]->text;
      $header_Home = $data->headers[3]->text;
      $header_Guest = $data->headers[4]->text;
      $header_Result = $data->headers[5]->text;
      $Cpos = strripos($my_club_name,'Chur');
      if (!is_bool($Cpos)) { $header_Result = "Res.";}
      $club_name = $data->title;
      $games = $data->regions[0]->rows;
      $attributes = $data->regions[0]->rows[0]->cells;
      
      $entries = count($games);
      
      $transient_games = $transient."games";
      $last_games = get_transient( $transient_games );
      if ($last_games == FALSE) {
        $last_games = $games;
        set_transient( $transient_games, $last_games, 2*55*60 );
      }
      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == "on") $loop = TRUE;
      $startpage = $data->context->page;
      $page = $startpage;

      $items = 0;
      $today = strtotime("now");
      $startdate = strtotime("-3 days",$today);
      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;
      
    
      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = 0;
      do {
            $game_id = $games[$i]->link->ids[0];
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date = $games[$i]->cells[0]->text[0];
            $game_time = $games[$i]->cells[0]->text[1];
            if ($game_time != "???") {
              $game_location_name = $games[$i]->cells[1]->text[0]; 
              $game_location = $games[$i]->cells[1]->text[1]; 
              $game_map_x = $games[$i]->cells[1]->link->x;
              $game_map_y = $games[$i]->cells[1]->link->y;
            }
            else {
              $game_location_name = "";
              $game_location = ""; 
              $game_map_x = "";
              $game_map_y = "";
            }
            $game_league = $games[$i]->cells[2]->text[0]; 
            $game_homeclub = $games[$i]->cells[3]->text[0]; 
            $game_guestclub = $games[$i]->cells[4]->text[0]; 
            $game_result = $games[$i]->cells[5]->text[0];
            $linkGame_ID = $games[$i]->link->ids[0];
            $new_result = $game_result;
            $game_result_add = "";
            if (isset($games[$i]->cells[5]->text[1])) {$game_result_add = $games[$i]->cells[5]->text[1];}
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
            $site_url = get_site_url();
            $site_display = substr($site_url,stripos($site_url,"://")+3);
          
            //Fehlerkorrektur für vom 7.1.2017
            if ($game_date=="today") $game_date="heute";
            if ($game_date=="yesterday") $game_date="gestern";

            if (($game_date=="heute") or ($game_date=="gestern"))  {
              if ($game_date=="heute")  { 
                $date_of_game = strtotime("today");
                $last_result = $last_games[$i]->cells[5]->text[0];
              }
              if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
            }
            else{
             $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;

            $special_league = "Junioren/-innen U14/U17 VM";
            $team_one = $my_club_name." I";
            $team_two = $my_club_name." II";
            $league_short = "U14/U17";
            

            if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) { 
              if ($game_league == $special_league){
                  $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
              }
              $game_homeDisplay = $game_league." ".str_replace ($my_club_name,"",$game_homeDisplay);
              $game_guestDisplay = $game_league." ".str_replace ($my_club_name,"",$game_guestDisplay);
            }
            else {
              if ($game_league == $special_league){
                  $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
                  if ((substr_count($game_homeDisplay,$team_one )>=1) xor (substr_count($game_guestDisplay,$team_two)>=1) ){
                    if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                      $game_league .=" II"; // Angzeige "U14/U17 II"
                     } 
                    else { 
                      if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                        $game_league .=" I"; // Angzeige "U14/U17 I"
                      }
                   } 
                  }
                  else {
                    if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                      $game_league .=" II"; // Angzeige "U14/U17 II"
                     } 
                    else { 
                      if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                        $game_league .=" I"; // Angzeige "U14/U17 I"
                      }
                   } 
                  }
              }

			  $game_league = SwissUnihockey_Api_Public::clean_league($game_league);


              if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                  $game_homeDisplay = $game_league; 
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                  $game_homeDisplay = $league_short." ".str_replace ($my_club_name,"",$game_homeDisplay);
              }
              if (substr_count($game_guestDisplay,$my_club_name)>=1) {
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                  $game_guestDisplay = $game_league;
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                  $game_guestDisplay = $league_short." ".str_replace ($my_club_name,"",$game_guestDisplay);
              }
            }

            if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))  {
              if (substr_count($game_result,"-")!=0) {
                $game_result = "❓";
              }
            } 

            if ($game_date == "heute") {
              $game_summary = "-";
              $game_telegramm = "-";

              if ( ($new_result != $last_result) and (substr_count($new_result,"*")!=0) and ($new_result!="") and (substr_count($e_Mail_Actual,"@" )>=1)) {
              
               $last_games[$i] = $games[$i];
               $message = $game_location_name." (".$game_location."): <strong>".$new_result."</strong> im Spiel ".$game_homeDisplay." vs. ".$game_guestDisplay.$skip;
               $message .= "Spielbeginn: ".$game_time." aktuelle Zeit: ".strftime("%H:%M").$skip;
               $message .= "".$game_homeclub." vs. ". $game_guestclub."".$skip; 

               $message .=  $skip."Diese Meldung wurde Dir durch <a href=\"".$site_url."\">".$site_display."</a> zugestellt.".$skip; 
               if ((substr_count($new_result,"0:0")!=0)) {
                 SwissUnihockey_Api_Public::log_me('Spielstart-Mail');
                 $checkmail = wp_mail( $e_Mail_Actual, "Spielstart: ".$game_league." * ".$game_homeclub." vs. ". $game_guestclub.' '.$new_result, $message, $mailheaders);
               }
               else {
                 SwissUnihockey_Api_Public::log_me('Zwischenresultat-Mail');
                 $checkmail = wp_mail( $e_Mail_Actual, "Zwischenresultat: ".$game_league." * ".$game_homeclub." vs. ". $game_guestclub.' '.$new_result, $message, $mailheaders);
               }

              }
              else {
              }
              if ( ($new_result != $last_result) and (substr_count($new_result,"*")==0) and ($new_result!="") and (substr_count($new_result,"-")==0) and (substr_count($e_Mail_Result,"@" )>=1) ){
                $api_game = new SwissUnihockey_Public(); 
                $details_game = $api_game->gameDetailsSummary($game_id, array()); 
                $response_type = $details_game->type;
                if ($response_type =="table") {
                  $game_summary = $details_game->data->regions[0]->rows[0]->cells[0]->text[0];
                  $game_sumdetail = $details_game->data->regions[0]->rows[0]->cells[1]->text[0];
                  $game_telegramm = $details_game->data->regions[0]->rows[0]->cells[2]->text[0].$skip.$details_game->data->regions[0]->rows[0]->cells[2]->text[1];
                  $last_games[$i] = $games[$i];
                  $message = $game_location_name." (".$game_location."): <strong>".$new_result."</strong> ".$game_result_add." im Spiel ".$game_homeclub." vs. ".$game_guestclub.$skip;
                  $message .= $skip."<a href=\"".$game_detail_link."\" title=\"".$Gamedetails."\" >Matchtelegramm:</a>".$skip.$game_summary.$skip.$game_sumdetail.$skip.$game_telegramm.$skip; 
                  $message .=  $skip."Diese Meldung wurde Dir durch <a href=\"".$site_url."\">".$site_display."</a> zugestellt.".$skip; 
                  SwissUnihockey_Api_Public::log_me('Schluss-Resultat-Mail');
                  $checkmail = wp_mail( $e_Mail_Result, "Schluss-Resultat: ".$game_league." - ".$game_homeclub." vs. ".$game_guestclub.' '.$new_result, $message, $mailheaders);
    //**TEST****
	                if (substr_count($_SERVER['SERVER_NAME'] ,"churunihockey")>=1) {
    			           $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
    			           $datatxt = "Schluss-Resultat: ".$game_league." - ".$game_homeclub." vs. ".$game_guestclub.' '.$new_result;
    			           $txt = "Mail-Data: ".date("d.m.Y - H:i:s")." ".$datatxt."\n";
    			           fwrite($myfile, $txt);
    			           fclose($myfile);
	    			      }
	                  // WP-Super-Cache löschen
	           		if( function_exists('wp_cache_clear_cache')) {
	                  wp_cache_clear_cache();
	           		}

                }
              }
            }

            if (($items <= $n_Games)) {
              if (($date_of_game > $startdate) and ($linkGame_ID_before != $linkGame_ID)) {  //  and $cup
 
                if (($game_result != "")) {
                }
                else  $items++;
               }
            }
            else {
              $loop = FALSE;
            }
            $i++; 
            $linkGame_ID_before = $linkGame_ID;
         } while (($i < $entries) and ($loop));

         if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
           // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);

      $stop =  time();
      $secs = ($stop- $go);
      //SwissUnihockey_Api_Public::log_me("club_getGames eval-time: ".$secs." secs  api-calls: ". $api_calls);
      //$html2 = str_replace ("</table>","defekt",$html);// for test
    /**TEST****
        if (substr_count($_SERVER['SERVER_NAME'] ,"churunihockey")>=1) {
           $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
           $datatxt = "club_getGames_Mails eval-time: ".$secs." secs  api-calls: ". $api_calls;
           $txt = "Data: ".date("d.m.Y - H:i:s")." ".$datatxt."\n";
           fwrite($myfile, $txt);
           fclose($myfile);
        }
    //**TEST****/
      set_transient( $transient, $games, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor-10 ); // innerloop (cron) 10 sec. shorter
      set_transient( $transient_games, $last_games, 2*60*60);

    } //end If
    else {
    /**TEST****
      if (substr_count($_SERVER['SERVER_NAME'] ,"churunihockey")>=1) {
        if (!(substr_count($e_Mail_Result,"@" )>=1)) {
           $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
           $datatxt = "club_getGames_Mails #NO E-Mail set#";
           $txt = "Info: ".date("d.m.Y - H:i:s")." ".$datatxt."\n";
           fwrite($myfile, $txt);
           fclose($myfile);
        }
      }
    //**TEST****/
    }


    return;
  }

/* ---------------------------------------------------------------------------------------------------- */
  public static function api_getDirectGames($season, $club_ID, $club_shortname, $game_ID, $mode, $cache) {
    
    $trans_Factor = 1;
    $meetings = 5;
    $my_club_name = $club_shortname;
    //SwissUnihockey_Api_Public::log_me($my_club_name);
    $cup = FALSE;
    $transient = "suhv-"."getDirectGames".$club_ID.$game_ID.$season.$mode;
    $secure_trans = $transient."Secure";
    $value = get_transient( $transient );
    //$semaphore = $club_ID.$game_ID."getDirectGamesSemaphore";
    $flag = FALSE;
    //$flag = get_transient( $semaphore);
    $linkGame_ID = NULL;
    $likkGame_ID_before = NULL;

    //echo "Game ID ".$game_ID."<br>";

    if ($flag) $sema_value = "Sema: TRUE"; else  $sema_value = "Sema: FALSE";
    //SwissUnihockey_Api_Public::log_me($sema_value);
    
    if (!$cache) { $value = False; }

    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE;}

    if (($value == False) and ($flag == False)) {

      // set_transient( $semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

      $go =  time();
      $api_calls = 0;
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress"; 


      // SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'game_ID' =>   $game_ID, 'mode' => $mode));

      $skip = "<br />";

      $html = "";
      $html_res = "";
      $html_body = "";

      $tage = SwissUnihockey_Api_Public::language_group('Days');
      $tag = date("w");
      $wochentag = $tage->$tag;

      $api = new SwissUnihockey_Public(); 
      $api_calls++;
      $details = $api->directGames( array(
        'season'=>$season,
        'amount'=>$meetings,
        'game_id'=>$game_ID
      )); 
      

      $data = $details->data; 
      $games_count = $data->context->amount;
      $games = $data->regions[0]->rows;
      // SwissUnihockey_Api_Public::log_me("getDirectGames api-calls:". $api_calls." games-count".$games_count);
      //echo "Games count ".$games_count."<br>";
      $title = str_replace ("Direktbegegnungen", SwissUnihockey_Api_Public::translate("Replacements","Direktbegegnungen"),$data->title);
      $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->short);
      $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->short);
      $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->short);
      $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);

      $entries = $games_count;
      
      $transient_games = $transient.$tag;
      $last_games = get_transient( $transient_games );
      if ($last_games == FALSE) {
        $last_games = $games;
        set_transient( $transient_games, $last_games, 2*55*60 );
        // echo "<br>Reset Games";60
      }
      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == 'on') $loop = TRUE;

      $items = 0;
      $today = strtotime("now");
      $startdate = strtotime("-3 days",$today);
      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;

      if (!$cache) {
         $view_cache = "<br> cache = off / Games: ".$games_count." Club: ".$my_club_name; 
        } else {$view_cache ="";
      }
      
      
      if ((substr_count($games[0]->cells[2]->text[0],$my_club_name)>=1)) $match_tile =  $games[0]->cells[2]->text[0]." vs ".$games[0]->cells[1]->text[0];
      else $match_tile = $games[0]->cells[1]->text[0]." vs ".$games[0]->cells[2]->text[0];

      $html_head = "<table class=\"suhv-table suhv-getDirectGames".$tablepress."\">\n";
      $html_head .= "<caption>".$title.": ".$match_tile."</caption>";
      $html_head .= "<thead><tr><th class=\"suhv-date\">".$header_DateTime.
      "</th><th class=\"suhv-opponent\">".$header_Home.
      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>".
      "</th><th class=\"suhv-opponent\">".$header_Result."</th>";

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = $games_count-1;
      do {
            $game_id = $games[$i]->id;
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date_time = $games[$i]->cells[0]->text[0];
            $game_homeclub = $games[$i]->cells[1]->text[0]; 
            $game_guestclub = $games[$i]->cells[2]->text[0]; 
            $game_result = $games[$i]->cells[3]->text[0];

            $Cpos = strripos($my_club_name,'Chur');
            $splitresult = explode(':',$game_result);
            $home_result = $splitresult[0];
            $guest_result = $splitresult[1];
            $new_result = $game_result;

            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;
            
            $homeClass ="suhv-place";
            $resultHomeClass = $homeClass;
            $resultGuestClass = $homeClass;
            $resultClass = "suhv-draw";

            if ($home_result > $guest_result){ 
              $resultHomeClass = 'suhv-home';
              $resultGuestClass = 'suhv-guest';
              if ((substr_count($game_homeclub,$my_club_name)>=1)) $resultClass = 'suhv-win'; else $resultClass = 'suhv-lose';
            }
            if ($guest_result > $home_result) { 
              $resultHomeClass = 'suhv-guest';
              $resultGuestClass = 'suhv-home';
              if ((substr_count($game_guestclub,$my_club_name)>=1)) $resultClass = 'suhv-win'; else $resultClass = 'suhv-lose';
            }
            
            if (($items <= $games_count)) {
                $html_body .= "<tr". ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"suhv-datetime\">".str_replace(".20",".",$game_date_time).
                "</td><td class=\"".$resultHomeClass."\">".$game_homeclub.
                "</td><td class=\"".$resultGuestClass."\">".$game_guestclub.
                "</td><td class=\"".$resultClass."\">"."<a href=\"".$game_detail_link."\" title=\"".$Spieldetails."\" >".$game_result."</a>";
                $html_body .= "</td></tr>";
            }
            else {
              $loop = FALSE;
            }
            $items++;
            $i--; 
            $linkGame_ID_before = $linkGame_ID;
         } while (($i >= 0) and ($loop));

         $loop = FALSE; 
        
      } // end While
        // Report all errors
      error_reporting(E_ALL);
      $html_head .= $html_res;
      $html_head .= "</tr></thead><tbody>";
      $html .= $html_head;
      $html .= $html_body;
      $html .= "</tbody>";
      $html .= "</table>";
      $stop =  time();
      $secs = ($stop- $go);

      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
      set_transient( $transient_games, $last_games, 2*60*60 );
      if (($secs<=10) and isset($data))  {
       $safe_html = str_replace (" min.)"," min. cache value)",$html);
       set_transient( $secure_trans, $safe_html, 12*3600); 
      }
    } //end If
    else { 
        $htmlpos = strpos($value,"</table>");
        $len = strlen($value);
        if (($htmlpos) and ($len > 300)) { 
          $html = $value; // Abfrage war OK
          //SwissUnihockey_Api_Public::log_me("API Cache OK pos: ".$htmlpos." len: ".$len);
        }
        else {
          $value = get_transient( $secure_trans ); // letzte gute Version holen bei Time-Out der API 2.0 von Swissunihockey
          //SwissUnihockey_Api_Public::log_me("API Cache Restore!");
          $html = $value; 
        }
    }
    return $html;
  }


/* ---------------------------------------------------------------------------------------------------- */
  public static function api_club_getCupGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {
    
    $team_ID = NULL;
    $trans_Factor = 10;
    $my_club_name = $club_shortname;
    //SwissUnihockey_Api_Public::log_me($my_club_name);
    $cup = FALSE;
    $transient = "suhv-"."club_getCupGames".$club_ID.$team_ID.$season.$mode;
    $secure_trans = $transient."Secure";
    //$semaphore = $club_ID.$team_ID."club_getGames-Flag";
    $value = get_transient( $transient );
    //$flag = get_transient( $semaphore);
    $flag = FALSE;
    $linkGame_ID = NULL;
    $likkGame_ID_before = NULL;

    if ($flag) $sema_value = "Sema: TRUE"; else  $sema_value = "Sema: FALSE";
    //SwissUnihockey_Api_Public::log_me($sema_value);
    
    if (!$cache) { $value = False; }
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if (($value == False) and ($flag == False)) {

      //set_transient( $semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

      $go =  time();
      $api_calls = 0;
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $n_Games = $plugin_options['SUHV_club_games_limit'];
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";

      // SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

      $skip = "<br />";

      $html = "";
      $html_res = "";
      $html_body = "";

      $tage = SwissUnihockey_Api_Public::language_group('Days');
      $tag = date("w");
      $wochentag = $tage->$tag;

      $api = new SwissUnihockey_Public(); 
      $api_calls++;
      $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
       
      )); 
      
      // Eine Seite retour bei Page-Ende? 

      $data = $details->data; 
      $startpage = $data->context->page;
      // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 1 page: ".$startpage);

      if ($startpage!=1) { // eine Page retour wenn nicht erste
           $page = $startpage-1;
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
          // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 2 page: ".$page);
      }

      $data = $details->data; 
      $header_DateTime = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[0]->text);
      $header_Location = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[1]->text);
      $header_Leage = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[2]->text);
      $header_Home = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[3]->text);
      $header_Guest = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[4]->text);
      $header_Result = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[5]->text);
      //$header_Result = "Res.";
      $club_name = $data->title;
      $games = $data->regions[0]->rows;
      $attributes = $data->regions[0]->rows[0]->cells;
      
      $entries = count($games);
      
      $transient_games = $transient.$tag;
      $last_games = get_transient( $transient_games );
      if ($last_games == FALSE) {
        $last_games = $games;
        set_transient( $transient_games, $last_games, 2*60*60 );
        // echo "<br>Reset Games";
      }
      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == "on") $loop = TRUE;
      $startpage = $data->context->page;
      $page = $startpage;

      $items = 0;
      $today = strtotime("now");
      $startdate = strtotime("-3 days",$today);
      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;

      if (!$cache) {
         $view_cache = "<br> cache = off / Display: ".$n_Games; 
         // $view_cache =."<br>emails:<br> ".$e_Mail_Actual."<br>".$e_Mail_Result."<br>".$e_Mail_From;
        } else {$view_cache ="";
      }
      
      $html_head = "<table class=\"suhv-table suhv-club-getCupGames".$tablepress."\">\n";
      //$html_head .= "<caption>".$data->title."<br>".$wochentag.strftime(" - %H:%M")."  (".$cTime." min.)".$view_cache."</caption>";
      $html_head .= "<thead><tr><th class=\"suhv-date\">".$header_DateTime.
      "</th><th class=\"suhv-place\">".$header_Location.
      "</th><th class=\"suhv-opponent\">".$header_Home.
      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>";

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = 0;
      do {
            $game_id = $games[$i]->link->ids[0];
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date = $games[$i]->cells[0]->text[0];
            $game_time = $games[$i]->cells[0]->text[1];
            if ($game_time != "???") {
              $game_location_name = $games[$i]->cells[1]->text[0]; 
              $game_location = $games[$i]->cells[1]->text[1]; 
              $game_map_x = $games[$i]->cells[1]->link->x;
              $game_map_y = $games[$i]->cells[1]->link->y;
            }
            else {
              $game_location_name = "";
              $game_location = ""; 
              $game_map_x = "";
              $game_map_y = "";
            }
            $game_league = $games[$i]->cells[2]->text[0]; 
            $game_homeclub = $games[$i]->cells[3]->text[0]; 
            $game_guestclub = $games[$i]->cells[4]->text[0]; 
            $game_result = $games[$i]->cells[5]->text[0];
            $linkGame_ID = $games[$i]->link->ids[0];
            $new_result = $game_result;
            $game_result_add = "";
            if (isset($games[$i]->cells[5]->text[1])) {$game_result_add = $games[$i]->cells[5]->text[1];}
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
            $site_url = get_site_url();
            $site_display = substr($site_url,stripos($site_url,"://")+3);
          
            //Fehlerkorrektur für vom 7.1.2017 + Php 7.+
            if ($game_date=="today") $game_date="heute";
            if ($game_date=="yesterday") $game_date="gestern";
            if ($game_date=="tomorrow") $game_date="morgen";

            if (($game_date=="heute") or ($game_date=="gestern") or ($game_date=="morgen"))  {
              if ($game_date=="heute")  { 
                $date_of_game = strtotime("today");
                $last_result = $game_result;
              }
              if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
              if ($game_date=="morgen") $date_of_game = strtotime("tomorrow");
            }
            else{
             $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;

            // If Cup?
            if (substr_count($game_league,"Cup")>=1) { 
              $cup = TRUE;
            }

            $special_league = "Junioren/-innen U14/U17 VM";
            $team_one = $my_club_name." I";
            $team_two = $my_club_name." II";
            $league_short = "U14/U17";
            if ($game_league == $special_league){
                $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
                if ((substr_count($game_homeDisplay,$team_one )>=1) xor (substr_count($game_guestDisplay,$team_two)>=1) ){
                  if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                    $game_league .=" II"; // Angzeige "U14/U17 II"
                   } 
                  else { 
                    if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                      $game_league .=" I"; // Angzeige "U14/U17 I"
                    }
                 } 
                }
                else {
                  if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                    $game_league .=" II"; // Angzeige "U14/U17 II"
                   } 
                  else { 
                    if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                      $game_league .=" I"; // Angzeige "U14/U17 I"
                    }
                 } 
                }
            }

			$game_league = SwissUnihockey_Api_Public::clean_league($game_league);

            $homeClass ="suhv-place";
            if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';} else {$resultClass = 'suhv-result';}

            if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                $game_homeDisplay = $game_league; 
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                $game_homeDisplay = $league_short." ".str_replace ($my_club_name,"",$game_homeDisplay);
              $resultHomeClass = 'suhv-home';
              if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            else $resultHomeClass = 'suhv-guest';
            if (substr_count($game_guestDisplay,$my_club_name)>=1) {
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                $game_guestDisplay = $game_league;
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                $game_guestDisplay = $league_short." ".str_replace ($my_club_name,"",$game_guestDisplay);
              $resultGuestClass = 'suhv-home';
              if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            else $resultGuestClass = 'suhv-guest';

            if ($game_result == "")  { 
              $resultClass = 'suhv-result';
            }
            if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))  {
              $resultClass = 'suhv-activ';
              if (substr_count($game_result,"-")!=0) {
                $game_result = "❓";
                $resultClass .= ' suhv-wait';
              }
            } 
            /* no email */
            if (($items <= $n_Games)) {
               if (($date_of_game > $startdate) and ($linkGame_ID_before != $linkGame_ID) and $cup) {
                $html_body .= "<tr". ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"suhv-datetime\">".str_replace(".20",".",$game_date).", ".$game_time.
                "</td><td class=\"".$homeClass."\">".$game_maplink.$game_location."</a>".
                "</td><td class=\"".$resultHomeClass."\">".$game_homeDisplay.
                "</td><td class=\"".$resultGuestClass."\">".$game_guestDisplay;
                if (($game_result != "")) {
                  $html_res = "<th class=\"suhv-result\">".$header_Result."</th>"; 
                  $html_body .= "</td><td class=\"".$resultClass."\"><a href=\"".$game_detail_link."\" title=\"Spieldetails\" >"."<strong>".$game_result."</strong><br>". $game_result_add."</a></td>";
                }
                else  $items++;
                $html_body .= "</tr>";
               }
            }
            else {
              $loop = FALSE;
            }
            $i++; 
            $cup = FALSE;
            $linkGame_ID_before = $linkGame_ID;
         } while (($i < $entries) and ($loop));

         if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
             'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
           // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);
      $html_head .= $html_res;
      $html_head .= "</tr></thead><tbody>";
      $html .= $html_head;
      $html .= $html_body;
      $html .= "</tbody>";
      $html .= "</table>";
      $stop =  time();
      $secs = ($stop- $go);
      //SwissUnihockey_Api_Public::log_me("club_getGames eval-time: ".$secs." secs  api-calls: ". $api_calls);
      //$html2 = str_replace ("</table>","defekt",$html);// for test
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
      set_transient( $transient_games, $last_games, 2*60*60 );
      if (($secs<=10) and isset($data))  {
       $safe_html = str_replace (" min.)"," min. cache value)",$html);
       set_transient( $secure_trans, $safe_html, 12*3600); 
      } // end do
    } //end If
    else { 
        $htmlpos = strpos($value,"</table>");
        $len = strlen($value);
        if (($htmlpos) and ($len > 300)) { 
          $html = $value; // Abfrage war OK
          //SwissUnihockey_Api_Public::log_me("API Cache OK pos: ".$htmlpos." len: ".$len);
        }
        else {
          $value = get_transient( $secure_trans ); // letzte gute Version holen bei Time-Out der API 2.0 von Swissunihockey
          //SwissUnihockey_Api_Public::log_me("API Cache Restore!");
          $html = $value; 
        }
    } //end else
    return $html;
  }

  public static function api_club_getWeekendGames($season, $club_ID, $club_shortname, $team_ID, $mode, $start_date, $end_date, $cache) {
    date_default_timezone_set("Europe/Paris");
    $my_club_name = $club_shortname;
    $linkGame_ID = NULL;
    $linkGame_ID_before = NULL;

  
    $date_parts = explode(".", $start_date); // dd.mm.yyyy in german
    $start_date_us = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
    //echo "<br>start:", date("d.m.Y", $start_date_us) . "<br>";
    $date_parts = explode(".", $end_date); // dd.mm.yyyy in german
    $end_date_us = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
    //echo "<br>end:", date("d.m.Y", $end_date_us) . "<br>";
    $weekend = FALSE;
    $date_description = "Spiele vom";
    if (strpos($start_date,"2015")>0) {
      $weekend = TRUE;
      $weekendDays = SwissUnihockey_Api_Public::nearWeekend();
      $start_date_us = $weekendDays["Freitag"];
      $end_date_us = $weekendDays["Sonntag"];
      $date_description = "Spiele vom Wochenende";
      $start_date = date("d.m.Y",$start_date_us);
      $end_date = date("d.m.Y",$end_date_us);
    }

    $team_ID = NULL;
    $trans_Factor = 2;
    $transient = "suhv-"."club_getWeekendGames".$club_ID.$team_ID.$season.$mode.$start_date.$end_date."test";
    $value = get_transient( $transient );

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }
    
    if ($value == False) {

      //SwissUnihockey_Api_Public::log_me(array('function' => 'club_getWeekendGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode, 'startdate' => $start_date, 'enddate' => $end_date));

      $html = "";
      $html_res = "";
      $html_body = "";
      $url = home_url();


      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";
      $n_Games = 40;

      $tage = SwissUnihockey_Api_Public::language_group('Days');
      $heute = SwissUnihockey_Api_Public::translate('Time','heute');
      $gestern = SwissUnihockey_Api_Public::translate('Time','gestern');
      $morgen = SwissUnihockey_Api_Public::translate('Time','morgen');
      $tag = date("w");
      $wochentag = $tage->$tag;

      $tag = date("w",$start_date_us);
      $start_tag = $tage->$tag;
      $tag = date("w",$end_date_us);
      $end_tag = $tage->$tag;

      $api = new SwissUnihockey_Public(); 
      $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
       
      )); 
      
      // Eine Seite retour bei Page-Ende? 

      $data = $details->data; 
      $startpage = $data->context->page;

      if ($startpage!=1) { // eine Page retour wenn nicht erste
           $page = $startpage-1;
           $api = new SwissUnihockey_Public(); 
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
             'page' => $page
       )); 
      }

      $data = $details->data; 
      $header_DateTime = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[0]->text);
      $header_Location = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[1]->text);
      $header_Leage = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[2]->text);
      $header_Home = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[3]->text);
      $header_Guest = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[4]->text);
      $header_Result = SwissUnihockey_Api_Public::translate('ClubGames',$data->headers[5]->text);
      //$header_Result = "Res.";

      $club_name = str_replace("Spielübersicht ","",$data->title);
      $club_name = substr($club_name,0,strpos($club_name,",")); // ohne Saison...
      $games = $data->regions[0]->rows;
      $attributes = $data->regions[0]->rows[0]->cells;
      
      $entries = count($games);

      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == "on") $loop = TRUE;
      $startpage = $data->context->page;
      $page = $startpage;

      $items = 0;
      $today = strtotime("now");
      $startdate = strtotime("-1 days",$start_date_us);
      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;
      $homeClass ="suhv-place";

      $date_description = SwissUnihockey_Api_Public::translate("Replacements",$date_description);
      $Spielevom = SwissUnihockey_Api_Public::translate("Replacements","Spiele vom");
      $bis = SwissUnihockey_Api_Public::translate("Replacements","bis");
      $Gamedetails = SwissUnihockey_Api_Public::translate("Replacements","Spieldetails");
      $html_head = "<table class=\"suhv-table suhv-club-getWeekendGames".$tablepress."\">\n";
      if ($weekend) 
        $html_head .= "<caption>".$club_name."</br>".$date_description." ".$start_tag." ".$start_date." ".$bis." ".$end_tag." ".$end_date."</caption>";
      else
        $html_head .= "<caption>".$club_name."</br>".$Spielevom." ".$start_tag." ".$start_date." ".$bis." ".$end_tag." ".$end_date."</caption>";
      $html_head .= "<thead><tr><th class=\"suhv-date\">".$header_DateTime.
      "</th><th class=\"suhv-place\">".$header_Location.
      "</th><th class=\"suhv-opponent\">".$header_Home.
      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>";

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = 0;
      do {
            $game_id = $games[$i]->link->ids[0];
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date = $games[$i]->cells[0]->text[0];
            $game_time = $games[$i]->cells[0]->text[1];
            if ($game_time != "???") {
              $game_location_name = $games[$i]->cells[1]->text[0]; 
              $game_map_x = $games[$i]->cells[1]->link->x;
              $game_map_y = $games[$i]->cells[1]->link->y;
            }
            else {
              $game_location_name = "";
              $game_map_x = "";
              $game_map_y = "";
            }
            $game_location = $games[$i]->cells[1]->text[1]; 
            $game_league = $games[$i]->cells[2]->text[0]; 
            $game_homeclub = $games[$i]->cells[3]->text[0]; 
            $game_guestclub = $games[$i]->cells[4]->text[0]; 
            $game_result = $games[$i]->cells[5]->text[0];
            $linkGame_ID = $games[$i]->link->ids[0];
            $new_result = $game_result;
            $game_result_add = "";
            if (isset($games[$i]->cells[5]->text[1])) {$game_result_add = $games[$i]->cells[5]->text[1];}
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
            $site_url = get_site_url();
            $site_display = substr($site_url,stripos($site_url,"://")+3);
          
            //Fehlerkorrektur für vom 7.1.2017 + Php 7.+
            if ($game_date=="today") $game_date="heute";
            if ($game_date=="yesterday") $game_date="gestern";
            if ($game_date=="tomorrow") $game_date="morgen";

            if (($game_date=="heute") or ($game_date=="gestern") or ($game_date=="morgen"))  {
              if ($game_date=="heute")  { 
                $date_of_game = strtotime("today");
                $last_result = $game_result;
              }
              if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
              if ($game_date=="morgen") $date_of_game = strtotime("tomorrow");
            }
            else{
             $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;

            /* If Cup?
            if (substr_count($game_league,"Cup")>=1) { 
              $cup = TRUE;
            } */

            $special_league = "Junioren/-innen U14/U17 VM";
            $team_one = $my_club_name." I";
            $team_two = $my_club_name." II";
            $league_short = "U14/U17";
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) { 
              $resultClass = 'suhv-draw';
              $resultHomeClass = 'suhv-home';
              $resultGuestClass = 'suhv-home';
              if ($game_league == $special_league){
                  $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
              }
              $game_homeDisplay = $game_league." ".str_replace ($my_club_name,"",$game_homeDisplay);
              $game_guestDisplay = $game_league." ".str_replace ($my_club_name,"",$game_guestDisplay);
            }
            else {
              if ($game_league == $special_league){
                  $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
                  if ((substr_count($game_homeDisplay,$team_one )>=1) xor (substr_count($game_guestDisplay,$team_two)>=1) ){
                    if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                      $game_league .=" II"; // Angzeige "U14/U17 II"
                     } 
                    else { 
                      if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                        $game_league .=" I"; // Angzeige "U14/U17 I"
                      }
                   } 
                  }
                  else {
                    if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                      $game_league .=" II"; // Angzeige "U14/U17 II"
                     } 
                    else { 
                      if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                        $game_league .=" I"; // Angzeige "U14/U17 I"
                      }
                   } 
                  }
              }

			  $game_league = SwissUnihockey_Api_Public::clean_league($game_league);

              if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';} else {$resultClass = 'suhv-result';}

              if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                  $game_homeDisplay = $game_league; 
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                  $game_homeDisplay = $league_short." ".str_replace ($my_club_name,"",$game_homeDisplay);
                $resultHomeClass = 'suhv-home';
                if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
              }
              else $resultHomeClass = 'suhv-guest';
              if (substr_count($game_guestDisplay,$my_club_name)>=1) {
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                  $game_guestDisplay = $game_league;
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                  $game_guestDisplay = $league_short." ".str_replace ($my_club_name,"",$game_guestDisplay);
                $resultGuestClass = 'suhv-home';
                if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
              }
              else $resultGuestClass = 'suhv-guest';
            }

            if ($game_result == "")  { 
              $resultClass = 'suhv-result';
            }
            if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))  {
              $resultClass = 'suhv-activ';
              if (substr_count($game_result,"-")!=0) {
                $game_result = "❓";
                $resultClass .= ' suhv-wait';
              }
            } 
            
            if ($game_date == "heute") {
              $game_summary = "-";
              $game_telegramm = "-";
              $game_date = $heute;
            }
            if ($game_date=="morgen") $game_date = $morgen;
            if ($game_date=="gestern") $game_date = $gestern;

            if (($items <= $n_Games) and ($date_of_game <= $end_date_us)) {
              if (($date_of_game > $startdate) and ($date_of_game <= $end_date_us) and ($linkGame_ID_before != $linkGame_ID) ) {  //   and $cup
                $html_body .= "<tr". ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"suhv-datetime\">"."<a href=\"".$game_detail_link."\" title=\"".$Gamedetails."\" >".str_replace(".20",".",$game_date).", ".$game_time."</a>".
                "</td><td class=\"".$homeClass."\">".$game_maplink.$game_location."</a>".
                "</td><td class=\"".$resultHomeClass."\">".$game_homeDisplay.
                "</td><td class=\"".$resultGuestClass."\">".$game_guestDisplay;
                if (($game_result != "")) {
                  $html_res = "<th class=\"suhv-result\">".$header_Result."</th>"; 
                  $html_body .= "</td><td class=\"".$resultClass."\"><a href=\"".$game_detail_link."\" title=\"".$Gamedetails."\" >".$game_result."<br>". $game_result_add."</a></td>";
                }
                else  $items++;
                $html_body .= "</tr>";
                // $cup = FALSE; // cup only
               }
            }
            else {
              $loop = FALSE;
            }
            $i++; 
            $linkGame_ID_before = $linkGame_ID;
         } while (($i < $entries) and ($loop));

         if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
           // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);

      $html_head .= $html_res;
      $html_head .= "</tr></thead><tbody>";
      $html .= $html_head;
      $html .= $html_body;
      $html .= "</tbody>";
      $html .= "</table>";
     
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
      //output some debug string
      // error_log( 'api_club_getGames:');
      //error_log( print_r(strftime("%A - %H:%M")) );
    } //end If
    else { $html = $value; }
    return $html;
  }
  /* ---------------------------------------------------------------------------------------------------- */
   public static function api_club_getWeekendGames_for_LiveGames($season, $club_ID, $club_shortname, $team_ID, $mode, $start_date, $end_date, $cache, $away) {
    
    date_default_timezone_set("Europe/Paris");
    $my_club_name = $club_shortname;
    $linkGame_ID = NULL;
    $linkGame_ID_before = NULL;
    $live_games = TRUE;

    if ($away != NULL) { $away = TRUE; $gametype = "Away";} else { $away = FALSE; $gametype = "Home";}

    //**TEST****
    if (substr_count($_SERVER['SERVER_NAME'] ,"churunihockey")>=1) {
      $myfile = fopen("cronlog.txt", "a+") or die("Unable to open file!");
      $txt = "LiveGamesDetails: ".date("d.m.Y - H:i:s")."\n";
      fwrite($myfile, $txt);
      fclose($myfile);
    }
  
    $date_parts = explode(".", $start_date); // dd.mm.yyyy in german
    $start_date_us = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
    //echo "<br>start:", date("d.m.Y", $start_date_us) . "<br>";
    $date_parts = explode(".", $end_date); // dd.mm.yyyy in german
    $end_date_us = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
    //echo "<br>end:", date("d.m.Y", $end_date_us) . "<br>";
    $weekend = FALSE;
    $date_description = "Spiele vom ".$start_date;
    if (strpos($start_date,"2015")>0) {
      $weekend = TRUE;
      $weekendDays = SwissUnihockey_Api_Public::nearWeekend();
      $start_date_us = $weekendDays["Freitag"];
      $end_date_us = $weekendDays["Sonntag"];
      $date_description = "Spiele heute";
      $start_date = date("d.m.Y",$start_date_us);
      $end_date = date("d.m.Y",$end_date_us);
    }

    $team_ID = NULL;
    $trans_Factor = 2;
    $transient = "suhv-"."club_getWeekendGames_for_LiveGames".$club_ID.$team_ID.$season.$mode.$start_date.$end_date.$gametype;
    $value = get_transient( $transient );

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }
    
    if ($value == False) {

      //SwissUnihockey_Api_Public::log_me(array('function' => 'club_getWeekendGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode, 'startdate' => $start_date, 'enddate' => $end_date));

      $html = "";
      $html_res = "";
      $html_body = "";
      $url = home_url();


      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";
      $n_Games = 40;

      $tage = SwissUnihockey_Api_Public::language_group('Days');
      $tag = date("w");
      $wochentag = $tage->$tag;

      $tag = date("w",$start_date_us);
      $start_tag = $tage->$tag;
      $tag = date("w",$end_date_us);
      $end_tag = $tage->$tag;

      $api = new SwissUnihockey_Public(); 
      $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
       
      )); 
      
      // Eine Seite retour bei Page-Ende? 

      $data = $details->data; 
      $startpage = $data->context->page;

      if ($startpage!=1) { // eine Page retour wenn nicht erste
           $page = $startpage-1;
           $api = new SwissUnihockey_Public(); 
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
             'page' => $page
       )); 
      }

      $data = $details->data; 
      $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);
      $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
      $header_Leage = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
      $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);
      $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[4]->text);
      $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[5]->text);
      $Gamedetails = SwissUnihockey_Api_Public::translate("Replacements","Spieldetails");
      //$header_Result = "Res.";

      $club_name = $data->title;
      $games = $data->regions[0]->rows;
      $attributes = $data->regions[0]->rows[0]->cells;
      
      $entries = count($games);

      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == "on") $loop = TRUE;
      $startpage = $data->context->page;
      $page = $startpage;

      $items = 0;
      $today = strtotime("now");
      $startdate = strtotime("-1 days",$start_date_us);
      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;
      $homeClass ="suhv-place";
      $html_head = "<table class=\"suhv-table suhv-club-getWeekendGames-for-LiveGames".$tablepress."\">\n";
      if ($weekend) 
        $html_head .= "<caption>".$date_description.date(" d.m.Y")." - Filter: ".$gametype."</caption>";
      else
        $html_head .= "<caption>"."Spiele vom ".$start_tag." ".$start_date." bis ".$end_tag." ".$end_date."</caption>";
      $html_head .= "<thead><tr><th class=\"suhv-date\">".$header_DateTime.
      "</th><th class=\"suhv-place\">".$header_Location.
      "</th><th class=\"suhv-opponent\">".$header_Home." / ".$header_Guest."</th>";
      if ($live_games) $html_head .= "</th><th class=\"suhv-livegames\">"."Game-Screen-Link"."</th>";

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = 0;
      do {
            $heutetag = FALSE;
            $game_id = $games[$i]->link->ids[0];
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date = $games[$i]->cells[0]->text[0];
            $game_time = $games[$i]->cells[0]->text[1];
            if ($game_time != "???") {
              $game_location_name = $games[$i]->cells[1]->text[0]; 
              $game_location = $games[$i]->cells[1]->text[1]; 
              $game_map_x = $games[$i]->cells[1]->link->x;
              $game_map_y = $games[$i]->cells[1]->link->y;
            }
            else {
              $game_location_name = "";
              $game_map_x = "";
              $game_map_y = "";
            }
            $game_location = $games[$i]->cells[1]->text[1]; 
            $game_league = $games[$i]->cells[2]->text[0]; 
            $game_homeclub = $games[$i]->cells[3]->text[0]; 
            $game_guestclub = $games[$i]->cells[4]->text[0]; 
            $game_result = $games[$i]->cells[5]->text[0];
            $linkGame_ID = $games[$i]->link->ids[0];
            $new_result = $game_result;
            $game_result_add = "";
            if (isset($games[$i]->cells[5]->text[1])) {$game_result_add = $games[$i]->cells[5]->text[1];}
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
            $site_url = get_site_url();
            $site_display = substr($site_url,stripos($site_url,"://")+3);
          
            //Fehlerkorrektur für vom 7.1.2017
            if ($game_date=="today") $game_date="heute";
            if ($game_date=="yesterday") $game_date="gestern";

            if (($game_date=="heute") or ($game_date=="gestern"))  {
              if ($game_date=="heute")  { 
                $date_of_game = strtotime("today");
                $last_result = $last_games[$i]->cells[5]->text[0];
                $heutetag = TRUE;
              }
              if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
            }
            else{
             $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;

            /* If Cup?
            if (substr_count($game_league,"Cup")>=1) { 
              $cup = TRUE;
            } */

            $special_league = "Junioren/-innen U14/U17 VM";
            $team_one = $my_club_name." I";
            $team_two = $my_club_name." II";
            $league_short = "U14/U17";
              if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) { 
              $resultClass = 'suhv-draw';
              $resultHomeClass = 'suhv-home';
              $resultGuestClass = 'suhv-home';
              if ($game_league == $special_league){
                  $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
              }
              $game_homeDisplay = $game_league." ".str_replace ($my_club_name,"",$game_homeDisplay);
              $game_guestDisplay = $game_league." ".str_replace ($my_club_name,"",$game_guestDisplay);
            }
            else {
              if ($game_league == $special_league){
                  $game_league = str_replace ($special_league,$league_short,$game_league); //new ab 2016
                  if ((substr_count($game_homeDisplay,$team_one )>=1) xor (substr_count($game_guestDisplay,$team_two)>=1) ){
                    if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                      $game_league .=" II"; // Angzeige "U14/U17 II"
                     } 
                    else { 
                      if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                        $game_league .=" I"; // Angzeige "U14/U17 I"
                      }
                   } 
                  }
                  else {
                    if ((substr_count($game_homeDisplay,$team_two )>=1) or (substr_count($game_guestDisplay,$team_two)>=1) ){
                      $game_league .=" II"; // Angzeige "U14/U17 II"
                     } 
                    else { 
                      if ((substr_count($game_homeDisplay,$team_one )>=1) or (substr_count($game_guestDisplay,$team_one)>=1) ){
                        $game_league .=" I"; // Angzeige "U14/U17 I"
                      }
                   } 
                  }
              }

			  $game_league = SwissUnihockey_Api_Public::clean_league($game_league);

              if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';} else {$resultClass = 'suhv-result';}

              if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                  $game_homeDisplay = $game_league; 
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                  $game_homeDisplay = $league_short." ".str_replace ($my_club_name,"",$game_homeDisplay);
                $resultHomeClass = 'suhv-home';
                if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
              }
              else $resultHomeClass = 'suhv-guest';
              if (substr_count($game_guestDisplay,$my_club_name)>=1) {
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) xor (substr_count($game_guestDisplay,$my_club_name)>=1))
                  $game_guestDisplay = $game_league;
                if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($game_guestDisplay,$my_club_name)>=1)) 
                  $game_guestDisplay = $league_short." ".str_replace ($my_club_name,"",$game_guestDisplay);
                $resultGuestClass = 'suhv-home';
                if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
              }
              else $resultGuestClass = 'suhv-guest';
            }

            if ($game_result == "")  { 
              $resultClass = 'suhv-result';
            }
            if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))  {
              $resultClass = 'suhv-activ';
              if (substr_count($game_result,"-")!=0) {
                $game_result = "❓";
                $resultClass .= ' suhv-wait';
              }
            } 
            /* no email*/
            if (substr_count($game_location,"Chur")!=0) { $home_run = TRUE; $home_link = "Heimspiel-Link";}
            else { $home_run = FALSE; $home_link = "Gastspiel-Link"; }
          
            if (($items <= $n_Games) and ($date_of_game <= $end_date_us) ) {
              if (($date_of_game > $startdate) and ($date_of_game <= $end_date_us) and ($linkGame_ID_before != $linkGame_ID) and $heutetag and ($home_run or $away)) {  //   and $cup
                $html_body .= "<tr". ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"suhv-datetime\">"."<a href=\"".$game_detail_link."\" title=\"".$Gamedetails."\" >".str_replace(".20",".",$game_date).", ".$game_time."</a>".
                "</td><td class=\"".$homeClass."\">".$game_location.
                "</td><td>".$game_homeDisplay." vs ".$game_guestDisplay;
                if (($game_result != "") and FALSE ) {
                  $html_res = "<th class=\"suhv-result\">".$header_Result."</th>"; 
                  $html_body .= "</td><td class=\"".$resultClass."\"><a href=\"".$game_detail_link."\" title=\"Matchtelegramm Swissunihockey\" >".$game_result."<br>". $game_result_add."</a></td>";
                }
                else  $items++;
                if ($live_games) {
                  $home_live_url = "<a href=\"".$url."/homegame/"."?game_id=".$game_id."\">".$home_link." - ".$game_league."</a>";
                  $html_body .= "<td class=\"suhv-livegames\">".$home_live_url."</td>";
                }
                $html_body .= "</tr>";
                // $cup = FALSE; // cup only
               }
            }
            else {
              $loop = FALSE;
            }
            $i++; 
            $linkGame_ID_before = $linkGame_ID;
         } while (($i < $entries) and ($loop));

         if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
           // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);

      $html_head .= $html_res;
      $html_head .= "</tr></thead><tbody>";
      $html .= $html_head;
      $html .= $html_body;
      $html .= "</tbody>";
      $html .= "</table>";
     
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
      //output some debug string
      // error_log( 'api_club_getGames:');
      //error_log( print_r(strftime("%A - %H:%M")) );
    } //end If
    else { $html = $value; }
    return $html;
  }
/* ---------------------------------------------------------------------------------------------------- */
  public static function api_club_getCurrentGameDetails($season, $club_ID, $club_shortname, $team_ID, $mode, $live_game_id, $live_home_logo, $live_guest_logo, $promo_left, $promo_middle, $promo_right, $live_refresh) {
    

    date_default_timezone_set("Europe/Paris");
    $my_club_name = $club_shortname;
    $linkGame_ID = NULL;
    $linkGame_ID_before = NULL;
    $homeGameOverride = FALSE; 
    $homeaway = NULL;
    $parameter = NULL;
    $homeGameFound = FALSE;
    $loop = FALSE;
    $date_of_game = strtotime("today");

    function get_club_logo ($club_Name,$small_logo) {

      $transient = "suhv-"."get_club_logos_from_JSON_list".$live_game_id;
      $logos = get_transient($transient);

      if ($logos == False) {

        $file = $_SERVER['DOCUMENT_ROOT']."/club-logos/clubindex.json";
        if (strpos($file,"xampp")) {
          $file = "C:/xampp2/apps/churunihockey/htdocs/club-logos/clubindex.json";
        }
        $logofile = fopen($file, "r") or die("Unable to open file!");
        $logojson = fgets($logofile);
        fclose($file);

        $logodata = json_decode($logojson);
        $refresh = $logodata->refresh;
        $logos = $logodata->logos;

        $logo_index =  array_search($club_Name,$logos->club_Name);
        $logo = $logos->club_Logo[$logo_index];
        
        set_transient( $transient, $logos, $refresh ); // 4h cache time - set on json
      }
      else {  
        $logo_index =  array_search($club_Name,$logos->club_Name);
        $logo = $logos->club_Logo[$logo_index];
      }

      if ($logo_index !== FALSE) 
        return $logo;
      else 
        return $small_logo;

    } // end get_club_logo

    if (isset($_GET['game_id'])) $parameter = $_GET['game_id']; // Seite/?gameid=916641
    if (isset($_GET['homeaway'])) $homeaway = $_GET['homeaway']; // Seite/?gameid=916641&homeaway=1  / override homegames 
    if (($homeaway != NULL) and ($parameter != NULL)) $homeGameOverride = TRUE; 

    if ($parameter != NULL) {
      // echo "<p class='error suhv'>Parameter GAME ID: ".$parameter."<br></p>\n";
      if (ctype_digit($parameter)) $live_game_id = $parameter;
    } 

    if ($live_game_id != NULL) { $game_id = 0; }
      // echo "<p class='error suhv'>GAME ID: ".$live_game_id."<br></p>\n";
    else
      echo "<p class='error suhv'>Aktuelle Zeit: ".date("H:i")."<br></p>\n";

    $value = False;
    //if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }
    
    if ($value == False) {

      //SwissUnihockey_Api_Public::log_me(array('function' => 'club_getCurrentGameDetails', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode, 'promo_left' => $promo_left, 'promo_middle' => $promo_middle, 'promo_right' => $promo_right));

      $html = "";
      $html_res = "";
      $html_body = "";
  
      $highlight_class = " livematch-change";

      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $homegame_location = $plugin_options['SUHV_default_home_location'];
      //echo "<p class='error suhv'>Heimspiel-Ort: ".$homegame_location."<br></p>\n";

      $n_Games = 20;

      $go =  time();
      $api_calls = 1;

      if ($live_game_id == NULL) {  // shortway if set
     
        $api_calls = 0;
        $api = new SwissUnihockey_Public(); $api_calls++;
        $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array()); 

        // Eine Seite retour bei Page-Ende? 

        $data = $details->data; 


        $startpage = $data->context->page;

        if ($startpage!=1) { // eine Page retour wenn nicht erste
             $page = $startpage-1;
             $api = new SwissUnihockey_Public(); $api_calls++;
             $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
               'page' => $page
         )); 
        }

        $data = $details->data; 

        //SwissUnihockey_Api_Public::log_me($details);

        $data = $details->data; 
        $header_DateTime = $data->headers[0]->text;
        $header_Location = $data->headers[1]->text;
        // $header_Leage = $data->headers[2]->text;
        $header_Home = $data->headers[2]->text;
        $header_Guest = $data->headers[3]->text;
        $header_Result = $data->headers[4]->text;
        //$header_Result = "Res.";
        $club_name = $data->title;
        $games = $data->regions[0]->rows;
        $attributes = $data->regions[0]->rows[0]->cells;
        
        $entries = count($games);

        $tabs = $data->context->tabs;
        if ($tabs == "on") $loop = TRUE;
        $startpage = $data->context->page;
        $page = $startpage;

      }
      else $loop = TRUE;

      $items = 0;
      $now = strtotime("now");
   
      $homeClass ="suhv-place";
      $html_head = "";

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = 0;
      do {
            if ($live_game_id == NULL)  // shortway if set
              $game_id = $games[$i]->link->ids[0];
            else $game_id = $live_game_id;

            $api_game = new SwissUnihockey_Public(); $api_calls++;
            $details_game = $api_game->gameDetails($game_id, array()); 

            $data_game = $details_game->data;

            $game_homeclub = $details_game->data->regions[0]->rows[0]->cells[1]->text[0];   
            $game_guestclub = $details_game->data->regions[0]->rows[0]->cells[3]->text[0];
            $game_homeclub = preg_replace('/ I{1,3}/', '' ,$game_homeclub); // reduce Chur Unihockey I,  Chur Unihockey II to Chur Unihockey
            $game_guestclub = preg_replace('/ I{1,3}/','' ,$game_guestclub); // reduce HC Rychenberg Winterthur I, II, III to HC Rychenberg Winterthur

            $home_logo = $details_game->data->regions[0]->rows[0]->cells[0]->image->url;
            $home_logo_alt = $details_game->data->regions[0]->rows[0]->cells[0]->image->alt;
            $home_logo = get_club_logo ($game_homeclub,$home_logo);
            if ($live_home_logo !== NULL) $home_logo = $live_home_logo;

            $guest_logo = $details_game->data->regions[0]->rows[0]->cells[2]->image->url;
            $guest_logo_alt = $details_game->data->regions[0]->rows[0]->cells[2]->image->alt;
            $guest_logo = get_club_logo ($game_guestclub,$guest_logo);
            if ($live_guest_logo !== NULL) $guest_logo = $live_guest_logo;
      
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date = $details_game->data->regions[0]->rows[0]->cells[5]->text[0];
            $game_date_org = $game_date;
            $game_time = $details_game->data->regions[0]->rows[0]->cells[6]->text[0];
            if ($game_time != "???") {
              $game_location_name = $details_game->data->regions[0]->rows[0]->cells[1]->text[0]; 
              $game_location = $details_game->data->regions[0]->rows[0]->cells[1]->text[1]; 
            }
            else {
              $game_location_name = "";
              $game_location = ""; 
            }
            $game_location = $details_game->data->regions[0]->rows[0]->cells[7]->text[0];
            $game_subtitle = $details_game->data->subtitle; 
            $game_result = $details_game->data->regions[0]->rows[0]->cells[4]->text[0];
            $game_result = str_replace("*","", $game_result);
            $game_adds = $details_game->data->regions[0]->rows[0]->cells[4]->text[1];
          
            //Fehlerkorrektur für vom 7.1.2017 + Php 7.+
            if ($game_date=="today") $game_date="heute";
            if ($game_date=="yesterday") $game_date="gestern";
            if ($game_date=="tomorrow") { $game_date="morgen"; $loop = FALSE;}

            if (($game_date=="heute") or ($game_date=="gestern") or ($game_date=="morgen"))  {
              if ($game_date=="heute")  { 
                $date_of_game = strtotime("today");
                $last_result = $game_result;
              }
              if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
              if ($game_date=="morgen") {$date_of_game = strtotime("tomorrow"); $loop = FALSE;}
            }
            else{
             $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }
            $time_parts = explode(":", $game_time); // hh.MM.SS in german
            $date_of_game = $date_of_game + ($time_parts[0] *60*60) + $time_parts[1]*60;
            $start_of_game = $date_of_game - 3*60*60; // vorlauf 3 h
            $end_of_game = $date_of_game + 2*60*60 + 30*60; // + spielzeit brutto 2.5h


            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;

            if (substr_count($game_location,$homegame_location)!=0) { $home_run = TRUE; }
            else  { $home_run = FALSE; }

            
            $game_result_title = $game_result;
            if ($game_result == "")  { 
              $game_result = "vs";
              $game_adds = "Start:<br />".$game_time." Uhr"."<div class=\"livematch-team-count-timer\" id=\"running_clock\"></div>";    
            }
            $homeGoals = "<div>\n<ul>\n";
            $awayGoals = "<div>\n<ul>\n";
            $homeGameFound = FALSE;

            //SwissUnihockey_Api_Public::log_me($items." ".$game_date." : ".$game_homeclub." vs ".$game_guestclub);

            if (($items <= $n_Games)) {

              // $home_run = TRUE; // for TEST only
              if ($live_game_id != NULL) { $game_id = $live_game_id; } // shortway if set

              if (($start_of_game <= $now) and ($end_of_game >= $now) and $home_run or ($game_id == $live_game_id )) {  

                $home_logo = get_club_logo ($game_homeclub,$home_logo);

                $api_game_home = new SwissUnihockey_Public(); $api_calls++;
                $details_game_home = $api_game_home->gameDetailsTeam($game_id, array('team' => "home"));
                $rows_home = $details_game_home->data->regions[0]->rows;
                
                $homeGameFound = TRUE;
                $homeTimeSet = FALSE;
                $homeTime = 0;
                $maxCount = 10;
                $homeCount = 0;
                foreach ($rows_home as $events) {
                 if ( ((substr_count($events->cells[1]->text[0],"Torschütze")!=0) or (substr_count($events->cells[1]->text[0],"Eigentor")!=0)) and ($homeCount < $maxCount) ) {
                   $homeCount++;
                   if (substr_count($events->cells[1]->text[0],"Eigentor")!=0) $TorShooter = "Eigentor Gast"; else $TorShooter = $events->cells[3]->text[0];
                   if ($homeCount == $maxCount) $TorShooter = "...";
                   if (strpos($TorShooter," (") > 1) $TorShooter = substr($TorShooter,0,strpos($TorShooter," ("));
                   $homeGoals .= "<li>".$events->cells[0]->text[0]."&nbsp;&nbsp;".$TorShooter."</li>\n";
                   if (!$homeTimeSet) {
                     $homeTime = intval(str_replace(":","",$events->cells[0]->text[0]));
                     $homeTimeSet = TRUE;
                   }
                 }
                }
                $homeGoals .= "</ul>\n</div>\n";

                $api_game_away = new SwissUnihockey_Public(); $api_calls++;
                $details_game_away = $api_game_away->gameDetailsTeam($game_id, array('team' => "away")); 
                $rows_away = $details_game_away->data->regions[0]->rows;
                  

                $awayTimeSet = FALSE;
                $awayTime = 0;
                $awayCount = 0;
                foreach ($rows_away as $events) {
                 if ( ((substr_count($events->cells[1]->text[0],"Torschütze")!=0) or (substr_count($events->cells[1]->text[0],"Eigentor")!=0)) and ($awayCount < $maxCount) ) {
                   $awayCount++;
                   if (substr_count($events->cells[1]->text[0],"Eigentor")!=0) $TorShooter = "Eigentor Heim"; else $TorShooter = $events->cells[3]->text[0];
                   if ($awayCount == $maxCount) $TorShooter = "...";
                   if (strpos($TorShooter," (") > 1) $TorShooter = substr($TorShooter,0,strpos($TorShooter," ("));
                   $awayGoals .= "<li>".$events->cells[0]->text[0]."&nbsp;&nbsp;".$TorShooter."</li>\n";
                   if (!$awayTimeSet) {
                     $awayTime = intval(str_replace(":","",$events->cells[0]->text[0]));
                     $awayTimeSet = TRUE;
                   }
                 }
                }
                $awayGoals .= "</ul>\n</div>\n";
               
                $home_class = "";
                $guest_class = "";
                
                if ($homeTimeSet and $awayTimeSet) {
                  if ($homeTime > $awayTime) $home_class = $highlight_class;
                  else $guest_class = $highlight_class;
                }
                else {
                  if ($homeTimeSet) $home_class = $highlight_class;
                  if ($awayTimeSet) $guest_class = $highlight_class;
                }

               //echo "<p class='error suhv'>Spiel-ID: ".$game_id." Spielstart: ".$game_time."<br></p>\n";
               echo "<p class='error suhv'>Spiel-ID: ".$game_id." / ".$game_location." / ".$game_subtitle." / Spielstart: ".$game_date_org." ".$game_time."<br> Display-Start: ".date("H:i",$start_of_game)." / Display-Ende: ".date("H:i",$end_of_game)."<br>\n".$game_homeclub." vs ".$game_guestclub."<br></p>\n";

               $html_body .= "<div class=\"livematch-promo-time\"><p>".date("H:i")."</p></div>\n".
               "<div class=\"livematch-detail\">\n".
               "<div class=\"livematch-info\">\n".
                //"<div class=\"livematch-headline\">".$game_homeclub." vs ".$game_guestclub."</div>\n".
                "<div class=\"livematch-result\">".
                  "<div class=\"livematch-team-home".$home_class."\">"."<img title=\"".$game_homeclub."\" alt=\"".$game_homeclub."\" src=\"".$home_logo."\" ><br /><div class=\"livematch-homeplayers\">".$homeGoals."</div></div>\n".
                  "<div class=\"livematch-team-count\">"."".$game_result."<div class=\"livematch-team-count-add\">".$game_adds."</div></div>\n".
                  "<div class=\"livematch-team-guest".$guest_class."\">"."<img title=\"".$game_guestclub."\" alt=\"".$game_guestclub."\" src=\"".$guest_logo."\" ><br /><div class=\"livematch-guestplayers\">".$awayGoals."</div></div>\n".
                "</div>\n".
               // "<div class=\"livematch-location\">".$game_maplink.$game_location." / ".$game_location_name."</a></div>".
               // "<div class=\"livematch-promo\">\n".
                  // "<div class=\"livematch-promo-left\"><img src=\"".$promo_left."\"></div>\n". //
               //   "<div class=\"livematch-promo-middle\"><img src=\"".$promo_middle."\"></div>\n".
                  // "<div class=\"livematch-promo-right\"><img src=\"".$promo_right."\"></div>\n". //
               // "</div>\n".
               "</div></div>\n";
               $loop = FALSE; // nur das aktuelle Game
               }
              else $items++;
            }
            else {
              $loop = FALSE;
            }
            $i++; 
            $linkGame_ID_before = $linkGame_ID;
         } while (($i < $entries) and ($loop));

         if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); $api_calls++;
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
           //SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);

      $html_head .= $html_res;
      $html .= $html_head;
      $html .= $html_body;

   
      //output some debug string
      // error_log( 'api_club_getGames:');
      //error_log( print_r(strftime("%A - %H:%M")) );
    } //end If
    else { $html = $value; }

    $stop = time();
    $eval_time = $stop-$go;
    $performance = round(($eval_time/$api_calls),1);
    $perf_level = "<strong>BAD!</strong>";
    if ($performance < 2) $perf_level = "unsteady";
    if ($performance < 1) $perf_level = "OK";
    else echo "<p class='error suhv'>API-Calls: ".$api_calls." / Duration: ".$eval_time." Secs / Performance: ".$performance." ".$perf_level."<br></p>\n";

    $test_games = array ("916641","919145","920003","919985");

    if  ( ($homeGameFound or $homeGameOverride) and ((($date_of_game >= strtotime('yesterday')) or ($date_of_game == strtotime('today'))) or in_array($game_id, $test_games)) ) {

      $path ="games/";
      $url = home_url();
      if (substr_count($game_location,$homegame_location)!=0) { $home_run = TRUE; $hometitle = "Heimspiel ";} else { $home_run = FALSE; $hometitle = "Gastspiel ";}

      if ( $home_run ) { 
	      $myfile = fopen($path."/index.live", "w") or die("Unable to open file!");
	      fwrite($myfile, $html);
	      fclose($myfile);
      }

      /* Save File with Game-ID 
      $myfile = fopen($path."/".$game_id.".live", "w") or die("Unable to open file!");
      fwrite($myfile, $html);
      fclose($myfile);

      /* save every File
            $myfile = fopen($path."/".$game_id.date("_Y_m_d-H-i").".live", "w") or die("Unable to open file!");
            fwrite($myfile, $html);
            fclose($myfile);
      */
      if (in_array($game_id, $test_games)) echo "<p class='error suhv'>TEST für Beameranzeige!<br></p>\n";
    }

    else { 
      echo "<p class='error suhv'>Kein aktuelles Spiel in den nächsten 3 Stunden! - Heimspielort: ".$homegame_location."<br></p>\n";
      //$html = ""; 
    }

    return $html;
  }

/* ---------------------------------------------------------------------------------------------------- */
  public static function api_team_getGameDetails($season, $club_ID, $club_shortname, $team_ID, $mode, $start_date, $end_date, $game_id, $cache) {
    
    date_default_timezone_set("Europe/Paris");
    $my_club_name = $club_shortname;
    $linkGame_ID = NULL;
    $linkGame_ID_before = NULL;
    $gameID_fix = FALSE;
  
    $date_parts = explode(".", $start_date); // dd.mm.yyyy in german
    $start_date_us = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
    //echo "<br>start:", date("d.m.Y", $start_date_us) . "<br>";
    $date_parts = explode(".", $end_date); // dd.mm.yyyy in german
    $end_date_us = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
    //echo "<br>end:", date("d.m.Y", $end_date_us) . "<br>";
    $weekend = FALSE;
    $date_description = "Spiele-Details vom ".$start_date;
    if (strpos($start_date,"2015")>0) {
      $weekend = TRUE;
      $weekendDays = SwissUnihockey_Api_Public::nearWeekend();
      $start_date_us = $weekendDays["Donnerstag"];  // Normal Freitag - Sonntag
      $end_date_us = $weekendDays["Sonntag"];
      $date_description = "Spiele-Details vom Wochenende";
      $start_date = date("d.m.Y",$start_date_us);
      $end_date = date("d.m.Y",$end_date_us);
    }

    //$team_ID = NULL;
    $trans_Factor = 1;
    $transient = "suhv-"."club_getGameDetails".$club_ID.$team_ID.$season.$mode.$start_date.$end_date."GameDetails".$game_id;
    $value = get_transient( $transient );

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }
    
    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGameDetails 2', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode, 'startdate' => $start_date, 'enddate' => $end_date));

      $html = "";
      $html_res = "";
      $html_body = "";
  

      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";
      $n_Games = 40;

      $tage = SwissUnihockey_Api_Public::language_group('Days');
      $tag = date("w");
      $wochentag = $tage->$tag;

      $tag = date("w",$start_date_us);
      $start_tag = $tage->$tag;
      $tag = date("w",$end_date_us);
      $end_tag = $tage->$tag;

      if ($game_id == NULL) {

        $api = new SwissUnihockey_Public(); 
        $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(

        )); 

        // Eine Seite retour bei Page-Ende? 

        $data = $details->data; 
        $startpage = $data->context->page;

        if ($startpage!=1) { // eine Page retour wenn nicht erste
             $page = $startpage-1;
             $api = new SwissUnihockey_Public(); 
             $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
               'page' => $page
         )); 
        }

        
    // SwissUnihockey_Api_Public::log_me($details);

        $data = $details->data; 
        $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);
        $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
        // $header_Leage = $data->headers[2]->text;
        $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
        $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);
        $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[4]->text);
        //$header_Result = "Res.";

        $heute = SwissUnihockey_Api_Public::translate('Time','heute');
        $gestern = SwissUnihockey_Api_Public::translate('Time','gestern');
        $morgen = SwissUnihockey_Api_Public::translate('Time','morgen');

        $club_name = $data->title;
        $games = $data->regions[0]->rows;
        $attributes = $data->regions[0]->rows[0]->cells;
        
        $entries = count($games);
        $loop = FALSE;
        $tabs = $data->context->tabs;
        if ($tabs == "on") $loop = TRUE;
        $startpage = $data->context->page;
        $page = $startpage;
      }
      else { $loop = TRUE; $gameID_fix = TRUE;}
      $items = 0;
      $today = strtotime("now");
      $startdate = strtotime("-1 days",$start_date_us);
      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;
   
      $homeClass ="suhv-place";
      $html_head = "";

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
      $i = 0;
      do {
            if (!$gameID_fix) $game_id = $games[$i]->link->ids[0]; 

            $api_game = new SwissUnihockey_Public(); 
            $details_game = $api_game->gameDetails($game_id, array()); 

            $data_game = $details_game->data;
      
            $home_logo = $details_game->data->regions[0]->rows[0]->cells[0]->image->url;
            $home_logo_alt = $details_game->data->regions[0]->rows[0]->cells[0]->image->alt;
    
            $guest_logo = $details_game->data->regions[0]->rows[0]->cells[2]->image->url;
            $guest_logo_alt = $details_game->data->regions[0]->rows[0]->cells[2]->image->alt;

            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;

            $game_date = $details_game->data->regions[0]->rows[0]->cells[5]->text[0];
            $game_time = $details_game->data->regions[0]->rows[0]->cells[6]->text[0];
            if ($game_time != "???") {
              $game_location_name = $details_game->data->regions[0]->rows[0]->cells[7]->text[0];
              $game_location = $details_game->data->regions[0]->rows[0]->cells[7]->text[0];
              $game_map_x = $details_game->data->regions[0]->rows[0]->cells[7]->link->x;
              $game_map_y = $details_game->data->regions[0]->rows[0]->cells[7]->link->y;
            }
            else {
              $game_location_name = "";
              $game_location = ""; 
              $game_map_x = "";
              $game_map_y = "";
            }

            $game_homeclub = $details_game->data->regions[0]->rows[0]->cells[1]->text[0];
            $game_guestclub = $details_game->data->regions[0]->rows[0]->cells[3]->text[0];
            if ($gameID_fix) $game_result = $details_game->data->regions[0]->rows[0]->cells[4]->text[0];
            else $game_result = $games[$i]->cells[4]->text[0];
            $linkGame_ID = $games[$i]->link->ids[0];
            $new_result = $game_result;
            $game_result_add = $details_game->data->regions[0]->rows[0]->cells[4]->text[1];
            if (isset($games[$i]->cells[4]->text[1])) {$game_result_add = $games[$i]->cells[4]->text[1];}
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));
            $site_url = get_site_url();
            $site_display = substr($site_url,stripos($site_url,"://")+3);
          
            //Fehlerkorrektur für vom 7.1.2017 + Php 7.+
            if ($game_date=="today") $game_date="heute";
            if ($game_date=="yesterday") $game_date="gestern";
            if ($game_date=="tomorrow") $game_date="morgen";

            if (($game_date=="heute") or ($game_date=="gestern") or ($game_date=="morgen"))  {
              if ($game_date=="heute")  { 
                $date_of_game = strtotime("today");
                $last_result = $game_result;
              }
              if ($game_date=="gestern") $date_of_game = strtotime("yesterday");
              if ($game_date=="morgen") $date_of_game = strtotime("tomorrow");
            }
            else{
             $date_parts = explode(".", $game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";

            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;

            if ($game_result == "")  { 
              $game_result = "vs";
            }

            if (($items <= $n_Games) and ($date_of_game <= $end_date_us)) {

              if (($date_of_game > $startdate) and ($date_of_game <= $end_date_us) and ($linkGame_ID_before != $linkGame_ID) or $gameID_fix) {  
              
                 if ($game_date == "heute") $game_date = $heute;
                 if ($game_date=="morgen") $game_date = $morgen;
                 if ($game_date=="gestern") $game_date = $gestern;

                 $html_body .= "<div class=\"match-detail suhv-team-getGameDetails\">".
                 "<div class=\"match-info\">".
                 "<div class=\"match-headline\">"."<a href=\"".$game_detail_link."\" title=\"Matchtelegramm auf Swissunihockey\" >".$game_homeclub." vs ".$game_guestclub."</a></div><div class=\"match-datetime\">".$game_date." - ".$game_time."</div>".
                  "<div class=\"match-result\">".
                    "<div class=\"match-team-home\">"."<img title=\"".$game_homeclub."\" alt=\"".$game_homeclub."\" src=\"".$home_logo."\" >"."</div>".
                    "<div class=\"match-team-count\">"."<br />".$game_result."<br />"."<div class=\"match-team-count-add\">".$game_result_add."</div></div>".
                    "<div class=\"match-team-guest\">"."<img title=\"".$game_guestclub."\" alt=\"".$game_guestclub."\" src=\"".$guest_logo."\" >"."</div>".
                  "</div>".
                  "<div class=\"match-location\">".$game_maplink.$game_location_name."</a></div>".
                 "</div></div>";
                }
              else $items++;
            }
            else {
              $loop = FALSE;
            }
            $i++; 
            $linkGame_ID_before = $linkGame_ID;
         } while (($i < $entries) and ($loop));

         if (($data->slider->next == NULL) or $gameID_fix) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); 
           $api_calls++;
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
           // SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);

      $html_head .= $html_res;
      $html .= $html_head;
      $html .= $html_body;

   
     
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
      //output some debug string
      // error_log( 'api_club_getGames:');
      //error_log( print_r(strftime("%A - %H:%M")) );
    } //end If
    else { $html = $value; }
    return $html;
  }
/* ---------------------------------------------------------------------------------------------------- */
 public static function api_team_getPlayedGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {

    $transient = "suhv-"."getPlayedGames".$club_ID.$team_ID.$season.$mode;
    $value = get_transient( $transient );
    $trans_Factor = 1;
    $my_club_name = $club_shortname;
    
    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'team_getPlayedGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

      $html = "";
      $html_res = "";
      $html_body = "";

      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";
      
      // echo "<br>".$season."<br>".$clubId;
      // do not start on 'page' => 1
      $api = new SwissUnihockey_Public(); 
      $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
     
      ));  

      if (isset($details->data)) {
        $data = $details->data; 
        $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);
        $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
        $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
        $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);
        $header_Guest = "Gegner";
        $header_Guest = SwissUnihockey_Api_Public::translate('Games',$header_Guest);
        $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[4]->text);
        //$header_Result = "Resultat";
  
        $club_name = $data->title;
        $games = $data->regions[0]->rows;
        $attributes = $data->regions[0]->rows[0]->cells;
      }
      
      $homeClass ="suhv-place";
      $entries = count($games);

      $title = str_replace ("Spielübersicht", SwissUnihockey_Api_Public::translate("Replacements","Spielübersicht"),$data->title);
      $title = str_replace ("Saison", SwissUnihockey_Api_Public::translate("Replacements","Saison"),$title);
      $Gamedetails = SwissUnihockey_Api_Public::translate("Replacements","Spieldetails");
      /*$html_head = "<table class=\"suhv-table suhv-team-getPlayedGames".$tablepress."\">\n";
      $html_head .= "<caption>".$title."</caption>";
      $html_head .= "<thead><tr><th class=\"suhv-date\">".$header_DateTime.
      "</th><th class=\"suhv-place\">".$header_Location.
      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>"; */

      $html_head = "<table>\n<tr><th class=\"suhv-date\">Datum</th><th class=\"suhv-result\">Resultat</th><th class=\"suhv-opponent\">Gegner</th></tr>\n";


      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == "on") $loop = TRUE;
      $startpage = $data->context->page;
      $page = $startpage;

      error_reporting(E_ALL & ~E_NOTICE);
      while ($loop) {
        $i = $entries - 1;
      do {
            $game_id = $games[$i]->link->ids[0];
            $game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=".$game_id;
            $game_date = $games[$i]->cells[0]->text[0];
            $game_time = $games[$i]->cells[0]->text[1];
            $game_location_name = $games[$i]->cells[1]->text[0]; 
            $game_location = $games[$i]->cells[1]->text[1]; 
            $game_map_x = $games[$i]->cells[1]->link->x;
            $game_map_y = $games[$i]->cells[1]->link->y; 
            $game_homeclub = $games[$i]->cells[2]->text[0]; 
            $game_guestclub = $games[$i]->cells[3]->text[0]; 
            $game_result = $games[$i]->cells[4]->text[0];
            $game_result_add = $games[$i]->cells[4]->text[1];
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;
            $game_league = str_replace ("Junioren", "",$game_league);
	        $resultClass = 'suhv-result';
            
	        if ((substr_count($game_homeDisplay,$my_club_name)>=1) and (substr_count($club_name,$game_homeclub)>=1)){ 
	            $game_Opponent = $game_guestDisplay; 
              $resultToDisplay = $game_home_result . ":" . $game_guest_result . $game_result_add;
						
	            if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
	        }
	        if ((substr_count($game_guestDisplay,$my_club_name)>=1) and (substr_count($club_name,$game_guestclub)>=1)){
	            $game_Opponent = $game_homeDisplay;
	            $resultToDisplay = $game_guest_result . ":" . $game_home_result . $game_result_add;
						if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
	        }
            if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';}

            if ($game_result == "")  { 
              $resultClass = 'suhv-result';
              $items++;
            }
            if (($game_date=="heute") and ((substr_count($game_result,"*")!=0) or (substr_count($game_result,"-")!=0)))   {
              $resultClass = 'suhv-activ';
            } 

            if ($game_result != "") {
              if ($game_date == "heute" or $game_date == "gestern") {
                $html_body .= "\n<tr" . ($i % 2 == 1 ? ' class="alt"' : '') . ">" . "<td class=\"" . $header_DateTime . "\">" . $game_date . substr($game_date, 8, 2) . "</td>";
              } else {
                $html_body .= "\n<tr" . ($i % 2 == 1 ? ' class="alt"' : '') . ">" . "<td class=\"" . $header_DateTime . "\">" . substr($game_date, 0, 6) . substr($game_date, 8, 2) . "</td>";
              }
  
              $html_body .= "<td class=\"suhv-result\">" . $resultToDisplay . "</td>";
              $html_body .= "<td class=\"suhv-opponent\">" . $game_Opponent . "</td></tr>";
  
            } else {
              //$loop = FALSE;
            }
            $i--; 

         } while (($i >= 0) and ($loop));

         if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
         else {
           $page++; 
           if ($page >= ($startpage+10)) $loop = FALSE; // Don't Loop always. // max 10 Pages
           $api = new SwissUnihockey_Public(); 
           $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
              'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $entries = count($games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);
      // $html_head .= $html_res;
      // $html_head .= "</tr></thead><tbody>";
      $html .= $html_head . "\n\n";
      $html .= $html_body;
      // $html .= "</tbody>";
      $html .= "</table>";

      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor);
    }
    else { $html = $value; }
    return $html;
      
  }
/* ---------------------------------------------------------------------------------------------------- */
 public static function api_team_getGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {

    $transient = "suhv-"."team_getGames".$club_ID.$team_ID.$season.$mode;
    $value = get_transient( $transient );
    $trans_Factor = 5;
    $my_club_name = $club_shortname;

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'team_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

      $html = "";
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";
      
      // echo "<br>".$season."<br>".$clubId;
      $page = 1;

      $api = new SwissUnihockey_Public(); 

      $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
        
      )); 
      $data = $details->data; 

      //SwissUnihockey_Api_Public::log_me(array('function' => 'league_team_getGames', 'season' => $season, 'club_ID' => $club_ID, 'team_ID' => $team_ID , 'mode' => $mode));


      $loop = FALSE;
      $tabs = $data->context->tabs;
      if ($tabs == "on") $loop = TRUE;
      $page = $data->context->page; 

      $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);
      $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
      $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
      $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[3]->text);
      $header_Guest = "Gegner";
      $header_Guest = SwissUnihockey_Api_Public::translate('Games',$header_Guest);
      $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[4]->text);

      $club_name = $data->title;
      $games = $data->regions[0]->rows;
      $attributes = $data->regions[0]->rows[0]->cells;
  
      $title = str_replace ("Spielübersicht", SwissUnihockey_Api_Public::translate("Replacements","Spielübersicht"),$data->title);
      $title = str_replace ("Saison", SwissUnihockey_Api_Public::translate("Replacements","Saison"),$title);
      $html .= "<table class=\"suhv-table suhv-team-getGames".$tablepress."\">\n";
      // $html .= "<caption>".$title."</caption>";
      $html .= "<thead><tr><th class=\"suhv-date\">Datum";
      $html .= "<th class=\"suhv-time\">Zeit";
      $html .= "</th><th class=\"suhv-opponent\">".$header_Guest;
      $html .= "</th><th class=\"suhv-location\">".$header_Location;
      //if ($header_Result != "")  $html .= "</th><th class=\"suhv-result\">".$header_Result."</th></tr></thead>";
      //else 
        $html .= "</th></tr></thead>";
      $html .= "<tbody>";

      error_reporting(E_ALL & ~E_NOTICE); 
      $i = 0;
      $entries = count($games);
      while ($loop) {
       
        do {

            //$game_date = $games[$i]->cells[0]->text[0];
            $game_date = substr($games[$i]->cells[0]->text[0], 0, 6) . substr($games[$i]->cells[0]->text[0], 8, 2);
            $game_time = $games[$i]->cells[0]->text[1];
            $game_location_name = $games[$i]->cells[1]->text[0]; 
            $game_location = $games[$i]->cells[1]->text[1]; 
            $game_map_x = $games[$i]->cells[1]->link->x;
            $game_map_y = $games[$i]->cells[1]->link->y;
            $game_homeclub = $games[$i]->cells[2]->text[0]; 
            $game_guestclub = $games[$i]->cells[3]->text[0]; 
            $game_result = $games[$i]->cells[4]->text[0];
            $game_result_add = $games[$i]->cells[4]->text[1];
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;
            $game_Diplay = "";
            $homeClass ="suhv-place";
            //$game_league = str_replace ("Herren", "",$game_league);
            
            if ($game_home_result == $game_guest_result) { $resultClass = 'suhv-draw';} else {$resultClass = 'suhv-result';}
            if (substr_count($game_homeDisplay,$my_club_name)>=1) { 
              $game_Display = $game_guestDisplay; 
              if ($game_home_result > $game_guest_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            if (substr_count($game_guestDisplay,$my_club_name)>=1) {
              $game_Display = $game_homeclub;
              if ($game_guest_result > $game_home_result) { $resultClass = 'suhv-win';} else {$resultClass = 'suhv-lose';}
            }
            if ($game_result == "")  {
              $html .= "<tr" . ($i % 2 == 1 ? ' class="alt"' : '') . ">";
              $html .= "<td class=\"" . $header_DateTime . "\">" . $game_date . "</td>";
              $html .= "<td class=\"" . $header_DateTime . "\">" . $game_time . "</td>";
              $html .= "<td>" . $game_Display . "</td>";
              $html .= "<td class=\"" . $homeClass . "\">" . $game_maplink . $game_location_name . " (" . $game_location . ")" . "</a></td>";
              //$html .= "</td><td class=\"suhv-result\"><strong>".$game_result."</strong><br>". $game_result_add."</td>".
              $html .= "</tr>";
            }
            $i++; 

        } while ($i < $entries);

        if ($data->slider->next == NULL) { $loop = FALSE; }// only this loop
        else {
          $page++; 
          if ($page > 15) $loop = FALSE; // Don't Loop always.

          $api = new SwissUnihockey_Public(); 
          $details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
             'page' => $page
           )); 
           $data = $details->data; 
           $games = $data->regions[0]->rows;
           $i = 0;
           $entries = count($games);
        } // end else
      } // end While
        // Report all errors
      error_reporting(E_ALL);
      $html .= "</tbody>";
      $html .= "</table>";
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
    }
    else { $html = $value; }
    return $html;
      
  }


/* ---------------------------------------------------------------------------------------------------- */

  public static function api_getTeamTable($season, $club_ID, $team_ID, $mode, $cache) {

    $transient = "suhv-"."getTeamTable".$club_ID.$team_ID.$season;
    $value = get_transient( $transient );
    $trans_Factor = 5;
    
    // Sample: https://api-v2.swissunihockey.ch/api/rankings?&team_id=427913&club_id=423403&season=2015

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'getTeamTable', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

      $html = "";
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";

      // echo "<br>".$season."-".$club_ID."-".$team_ID;

      $api = new SwissUnihockey_Public();
      $version_info = $api->version();
 
      $details = $api->rankings($season, $club_ID, $team_ID, $mode, array(
        
      )); 

      $data = $details->data; 

      error_reporting(E_ALL & ~E_NOTICE);
      $headerCount = count($data->headers);
      //echo "<br> HEADER-Count".$headerCount;
      $smallTable = FALSE;
      if ($headerCount < 13) $smallTable = TRUE;
      
      $header_Rank = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[0]->text);
      $header_Team =SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[2]->text);
      $header_Sp = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[3]->text);
      if ($smallTable) {
        $header_S = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[5]->text);
        $header_U = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[6]->text);
        $header_N = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[7]->text);
        $header_T = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[8]->text);
        // $header_T = "Tore";
        $header_TD = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[9]->text);
        $header_PQ = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[10]->text);
        $header_P = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[11]->text);
        //$header_P = "Pt.";
      }
      else{
        $header_S = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[5]->text);
        $header_SnV = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[6]->text);
        $header_NnV = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[7]->text);
        $header_N = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[8]->text);
        $header_T = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[9]->text);
        //$header_T = "Tore";
        $header_TD = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[10]->text);
        $header_PQ = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[11]->text);
        $header_P = SwissUnihockey_Api_Public::translate('TeamTable',$data->headers[12]->text);
        //$header_P = "Pt.";
      }
      $Table_title = $data->title;
      //* $rankings = $data->regions[0]->rows;

      $reg = 0; // extension 1.2019 for Junior D.. make two loops if Gruppe A + B

      do {

	      $rankings = $data->regions[$reg]->rows;
	      $capt = false;
	      if ($data->regions[$reg]->text == "A") { 
	      	$regloop = true; 
	      	$capt = true;
	      } 
	      else { 
	      	$regloop = false; 
	      	if ($data->regions[$reg]->text == "") $capt = true; 
	      }
	    
	      if (isset($rankings))
          $entries = count($rankings); 
        else 
          $entries = 1;

	      if (!$cache) {
	         $view_cache = "<br> cache = off / Team-ID: ".$team_ID; 
	        } else {$view_cache ="";
	      }
	     
        $title = str_replace ("Tabelle", SwissUnihockey_Api_Public::translate("Replacements","Tabelle"),$data->title);
        $title = str_replace ("per", SwissUnihockey_Api_Public::translate("Replacements","per"),$title);
        $title_rank = "Rang";
        $title_team = "Team";
        $title_games = "Spiele";
        $title_SoW = "Spiele ohne Wertung";
        $titel_wins = "Siege";
        $title_ties = "Siege nach Verlängerung"; 
        $title_defeats = "Niederlagen nach Verlängerung";
        $title_lost = "Niederlagen";
        $title_scored = "Torverhältnis";
        $title_diff = "Tordifferenz";
        $title_points = "Punkte";
        $title_pointsquotient = "Punktquotient";
        $title_even = "Spiele unentschieden";
        $title_rank= SwissUnihockey_Api_Public::translate("TeamTable",$title_rank);
        $title_team = SwissUnihockey_Api_Public::translate("TeamTable",$title_team);
        $title_games = SwissUnihockey_Api_Public::translate("TeamTable",$title_games);
        $titel_wins = SwissUnihockey_Api_Public::translate("TeamTable",$titel_wins);      
        $title_ties = SwissUnihockey_Api_Public::translate("TeamTable",$title_ties);
        $title_defeats = SwissUnihockey_Api_Public::translate("TeamTable",$title_defeats);
        $title_lost = SwissUnihockey_Api_Public::translate("TeamTable",$title_lost);
        $title_scored = SwissUnihockey_Api_Public::translate("TeamTable",$title_scored);
        $title_diff = SwissUnihockey_Api_Public::translate("TeamTable",$title_diff);
        $title_points = SwissUnihockey_Api_Public::translate("TeamTable",$title_points);
        $title_pointsquotient = SwissUnihockey_Api_Public::translate("TeamTable",$title_pointsquotient);
        $title_even = SwissUnihockey_Api_Public::translate("TeamTable",$title_even);

	      $html .= "<table class=\"suhv-table suhv-getTeamTable".$tablepress."\">";
	      // if ($capt) $html .= "<caption>".$title.$view_cache."</caption>";
	      $html .= "<thead>".       
	        "<tr><th class=\"suhv-rank\"><abbr title=\"".$title_rank."\">".$header_Rank."</abbr>".
	        "</th><th class=\"suhv-team\"><abbr title=\"".$title_team."\">".$header_Team."</abbr>".
	        "</th><th class=\"suhv-games\"><abbr title=\"".$title_games."\">".$header_Sp."</abbr>".
	        "</th><th class=\"suhv-wins\"><abbr title=\"".$titel_wins."\">".$header_S."</abbr>";
	      if ($smallTable) {
	        $html .= "</th><th class=\"suhv-even\"><abbr title=\"".$title_even."\">".$header_U."</abbr>".
	        "</th><th class=\"suhv-lost\"><abbr title=\"".$title_lost."\">".$header_N."</abbr>".
	        "</th><th class=\"suhv-scored\"><abbr title=\"".$title_scored."\">".$header_T."</abbr>".
	        "</th><th class=\"suhv-diff\"><abbr title=\"".$title_diff."\">".$header_TD."</abbr>".
          "</th><th class=\"suhv-points\"><abbr title=\"".$title_pointsquotient."\">".$header_PQ."</abbr>".
	        "</th><th class=\"suhv-points\"><abbr title=\"".$title_points."\">".$header_P."</abbr>";
	      }
	      else{
	        $html .= "</th><th class=\"suhv-ties\"><abbr title=\"".$title_ties."\">".$header_SnV."</abbr>".
	        "</th><th class=\"suhv-defeats\"><abbr title=\"".$title_defeats."\">".$header_NnV."</abbr>".
	        "</th><th class=\"suhv-lost\"><abbr title=\"".$title_lost."\">".$header_N."</abbr>".
	        "</th><th class=\"suhv-scored\"><abbr title=\"".$title_scored."\">".$header_T."</abbr>".
	        "</th><th class=\"suhv-diff\"><abbr title=\"".$title_diff."\">".$header_TD."</abbr>".
	        "</th><th class=\"suhv-points\"><abbr title=\"".$title_pointsquotient."\">".$header_PQ."</abbr>".
          "</th><th class=\"suhv-points\"><abbr title=\"".$title_points."\">".$header_P."</abbr>";
	      }
	      $html .= "</th></tr></thead>";
	      $html .= "<tbody>";
	      

	      $i = 0;

	      do {
	           $ranking_TeamID = $data->regions[$reg]->rows[$i]->data->team->id;
	           $ranking_TeamName = $data->regions[$reg]->rows[$i]->data->team->name;

	           $ranking_Rank = $data->regions[$reg]->rows[$i]->cells[0]->text[0];
	           $ranking_Team = $data->regions[$reg]->rows[$i]->cells[2]->text[0];
	           $ranking_Sp = $data->regions[$reg]->rows[$i]->cells[3]->text[0];
	           if ($smallTable) {
               $ranking_S = $data->regions[$reg]->rows[$i]->cells[5]->text[0];
	             $ranking_U = $data->regions[$reg]->rows[$i]->cells[6]->text[0];
	             $ranking_N = $data->regions[$reg]->rows[$i]->cells[7]->text[0];
	             $ranking_T = $data->regions[$reg]->rows[$i]->cells[8]->text[0];
	             $ranking_TD = $data->regions[$reg]->rows[$i]->cells[9]->text[0];
               $ranking_PQ = $data->regions[$reg]->rows[$i]->cells[10]->text[0];
	             $ranking_P = $data->regions[$reg]->rows[$i]->cells[11]->text[0];
	           }
	           else{
               $ranking_S = $data->regions[$reg]->rows[$i]->cells[5]->text[0];
	             $ranking_SnV = $data->regions[$reg]->rows[$i]->cells[6]->text[0];
	             $ranking_NnV = $data->regions[$reg]->rows[$i]->cells[7]->text[0];
	             $ranking_N = $data->regions[$reg]->rows[$i]->cells[8]->text[0];
	             $ranking_T = $data->regions[$reg]->rows[$i]->cells[9]->text[0];
	             $ranking_TD = $data->regions[$reg]->rows[$i]->cells[10]->text[0];
               $ranking_PQ = $data->regions[$reg]->rows[$i]->cells[11]->text[0];
	             $ranking_P = $data->regions[$reg]->rows[$i]->cells[12]->text[0];
	           }
	           if ($team_ID == $ranking_TeamID) { $tr_class = 'suhv-my-team';} else {$tr_class = '';}

	           $html .= "<tr class=\"".$tr_class.($i % 2 == 1 ? ' alt' : '')."\">".
	           "<td class=\"suhv-rank\">".$ranking_Rank.
	           "</td><td class=\"suhv-team\">".$ranking_Team.
	           "</td><td class=\"suhv-games\">".$ranking_Sp;
	           if ($smallTable) {
	             $html .= "</td><td class=\"suhv-wins\">".$ranking_S.
               "</td><td class=\"suhv-even\">".$ranking_U.
	             "</td><td class=\"suhv-lost\">".$ranking_N.
	             "</td><td class=\"suhv-scored\">".$ranking_T.
	             "</td><td class=\"suhv-diff\">".$ranking_TD.
               "</td><td class=\"suhv-points\">".$ranking_PQ.
	             "</td><td class=\"suhv-points\">".$ranking_P;
	           }
	           else{
	             $html .= "</td><td class=\"suhv-wins\">".$ranking_S.
               "</td><td class=\"suhv-ties\">".$ranking_SnV.
	             "</td><td class=\"suhv-defeats\">".$ranking_NnV.
	             "</td><td class=\"suhv-lost\">".$ranking_N.
	             "</td><td class=\"suhv-scored\">".$ranking_T.
	             "</td><td class=\"suhv-diff\">".$ranking_TD.
               "</td><td class=\"suhv-points\">".$ranking_PQ.
	             "</td><td class=\"suhv-points\">".$ranking_P;
	           }
	           $html .= "</td></tr>"; 
	           $i++; 
	        } while ($i < $entries);
	        // loop if Gruppe A + B
            $reg++;
        } while ($regloop);
        // Report all errors
        error_reporting(E_ALL);

      $html .= "</tbody>";
      $html .= "</table>";

      set_transient( $transient, $html,  SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
    }
    else { $html = $value; }
    return $html;
   
      
    }

    /* ---------------------------------------------------------------------------------------------------- */
  public static function api_getTeamRank($season, $club_ID, $team_ID, $mode, $cache) {

    $transient = "suhv-"."getTeamRank".$club_ID.$team_ID.$season;
    $value = get_transient( $transient );
    $trans_Factor = 3;
    
    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'getTeamRank', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

      $html = "";
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";
  
      // echo "<br>".$season."-".$club_ID."-".$team_ID;

      $api = new SwissUnihockey_Public();
      $version_info = $api->version();
 
      $details = $api->rankings($season, $club_ID, $team_ID, $mode, array(
        
      )); 

      $data = $details->data; 

      $header_Rank = SwissUnihockey_Api_Public::translate('TeamRank',$data->headers[0]->text);
      //$header_Rank = "Rang";
      $header_Team = SwissUnihockey_Api_Public::translate('TeamRank',$data->headers[2]->text);
      $header_ranking_TD = SwissUnihockey_Api_Public::translate("TeamRank",$data->headers[11]->text);
      $header_P = SwissUnihockey_Api_Public::translate('TeamRank',$data->headers[12]->text); // Points
      //$header_P = "Punkte";

      $Table_title = $data->title;
      //* $rankings = $data->regions[0]->rows;
      if (isset($data->regions[0]->rows)) {
          $rankings = $data->regions[0]->rows;
          $entries =  count($rankings);
      }
      else $entries = 0;
      
      $headerCount = count($data->headers);


      if (!$cache) {
         $view_cache = "<br> cache = off / Team-ID: ".$team_ID; 
        } else {$view_cache ="";
      }
     
      $title = str_replace ("Tabelle", SwissUnihockey_Api_Public::translate("Replacements","Tabelle"),$data->title);
      $title = str_replace ("per", SwissUnihockey_Api_Public::translate("Replacements","per"),$title);
      $html .= "<table class=\"suhv-table suhv-getTeamRank".$tablepress."\">";
      $html .= "<caption>".$title.$view_cache."</caption>";
      $html .= "<thead>".
           "<tr><th class=\"suhv-rank\">".$header_Rank.
           "</th><th class=\"suhv-team\">".$header_Team.
           "</th><th class=\"suhv-diff\">".$header_ranking_TD.
           "</th><th class=\"suhv-points\">".$header_P.
           "</th></tr></thead>";
      $html .= "<tbody>";
      
      error_reporting(E_ALL & ~E_NOTICE);

      $smallTable = FALSE;
      if ($headerCount < 10) $smallTable = TRUE;

      $i = 0;
   
      do {
           $ranking_TeamID = $rankings[$i]->data->team->id;
           $ranking_TeamName = $rankings[$i]->data->team->name;

           $ranking_Rank = $rankings[$i]->cells[0]->text[0];
           $ranking_Team = $rankings[$i]->cells[2]->text[0];
           if ($smallTable) {
           	$ranking_TD = $rankings[$i]->cells[8]->text[0];
            $ranking_P = $rankings[$i]->cells[9]->text[0]; //Points
           }
           else {
           	$ranking_TD = $rankings[$i]->cells[11]->text[0];
            $ranking_P = $rankings[$i]->cells[12]->text[0]; // Points
           }
           if ($team_ID == $ranking_TeamID) { $tr_class = 'suhv-my-team';} else {$tr_class = '';}

           $html .= "<tr class=\"".$tr_class.($i % 2 == 1 ? ' alt' : '')."\">".
           "<td class=\"suhv-rank\">".$ranking_Rank.
           "</td><td class=\"suhv-team\">".$ranking_Team.
           "</td><td class=\"suhv-diff\">".$ranking_TD.
           "</td><td class=\"suhv-points\">".$ranking_P.
           "</td></tr>"; 
           $i++; 
        } while ($i < $entries);
        // Report all errors
      error_reporting(E_ALL);

      $html .= "</tbody>";
      $html .= "</table>";
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor);
    }
    else { $html = $value; }
    return $html;
   
  }

/* ---------------------------------------------------------------------------------------------------- */
 public static function api_league_getGames($season, $league, $game_class, $group, $round, $mode, $cache) {


    $transient = "suhv-"."league_getGames".$league.$game_class.$group.$season.$mode;
    
    $value = get_transient( $transient );
    // $value = FALSE;
   
    $maxloop = 25;
    $loopcnt = 1;
    $trans_Factor = 12;
    $ActivRound = "";
    $lastActivRound = "";
    
    if ($league == 21) {$small = TRUE; } 
    else {$small = FALSE; }

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'league_getGames', 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'group' => $group, 'round' => $round, 'mode' => $mode));

      $html = "";
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";

      $api = new SwissUnihockey_Public(); 

      $details = $api->games(array(
        'season' => $season, 'league' => $league, 'game_class' => $game_class, 'round' => $round, 'mode' => $mode, 'group' => $group, 
      )); 
      $data = $details->data; 


      $loop = FALSE;
      $nextround = $data->slider->next->set_in_context->round;
      //  echo "<br>Nextround:".$nextround."<br>";

      if ($data->slider->next <> NULL) $loop = TRUE;

      $round = $data->context->round;

      $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);
      $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
      $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
      //$header_Home = "Heimteam";
      $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[6]->text);
      //$header_Guest = "Gastteam";
      $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[7]->text);


      $round_Title = $data->slider->text;
      $games = $data->regions[0]->rows;
      $game_location_name = $games[0]->cells[1]->text[0]; 
      $game_map_x = 0;
      $game_map_y = 0;

      if (isset($games[0]->cells[1]->link->x)) {
        $game_map_x = $games[0]->cells[1]->link->x;
        $game_map_y = $games[0]->cells[1]->link->y;
      }

      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;

      $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title=\"".$game_location_name."\">";
     
      $ActivRound = trim(substr($round_Title,0,strpos($round_Title,'/')));
      $ActivRound = str_replace(' ', '_', $ActivRound);
      $LinkActivRound = SwissUnihockey_Api_Public::translate('Replacements','Zur aktuellen Runde');
      $RoundTitle = str_replace('Runde',SwissUnihockey_Api_Public::translate('Replacements','Runde'), $round_Title);

      $html .= '<div class="suhv-round-link"><a id="ActivRound" href="#LastGame">'.__($LinkActivRound,'SUHV-API-2').'</a></div>';
      //$html .= '<div class="suhv-round-link"><a id="ActivRound" href="#LastGame">'.__('Zur aktuellen Runde','SUHV-API-2').'</a></div>';
      $html .= '<div class="suhv-round-anchor"><a id="'.$ActivRound.'"></a></div>';
      if ($small) { $html .= "<h3>".$RoundTitle."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>"; } 
      else  {
        $html .= "<h3>".$RoundTitle."</h3>";
      }
      // $html .= "<h3>".$round_Title."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>";

      $html .= "<table class=\"suhv-table suhv-league-getGames".$tablepress."\">\n";
      // $html .= "<caption>timestamp: ".strftime("%H:%M")." - caching: ".$cTime." min.</caption>";
      // $html .= "<caption><h3>".$round_Title."  (".$game_maplink.$game_location_name."</a>)</h3></caption>";
      $html .= "<thead><tr><th class=\"suhv-league suhv-date\">".$header_DateTime;
      if  (!$small) $html .= "</th><th class=\"suhv-league suhv-place\">".$header_Location;
      $html .= "</th><th class=\"suhv-league suhv-opponent\">".$header_Home;
      $html .= "</th><th class=\"suhv-league suhv-opponent\">".$header_Guest;
      $html .= "</th><th class=\"suhv-league suhv-result\">".$header_Result;
      $html .= "</th></tr></thead><tbody>";

      error_reporting(E_ALL & ~E_NOTICE); 
      $i = 0;
      $entries = count($games);
   
      while ($loop) {
        
        do {

            $game_date_time = $games[$i]->cells[0]->text[0];
            $game_location_name = $games[$i]->cells[1]->text[0]; 
            $game_map_x = $games[$i]->cells[1]->link->x;
            $game_map_y = $games[$i]->cells[1]->link->y;
            $game_homeclub = $games[$i]->cells[2]->text[0]; 
            $game_guestclub = $games[$i]->cells[6]->text[0]; 
            $game_result = $games[$i]->cells[7]->text[0];
            if ($game_result == "") $game_result = "N/A";
  
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;
            $game_Diplay = "";

              $html .= "<tr ". ($i % 2 == 1 ? 'class="alt odd"' : 'class="even"') ."><td class=\"suhv-league suhv-datetime\">".$game_date_time;
              if (!$small) { $html .= "</td><td class=\"suhv-league suhv-place\">".$game_maplink.$game_location_name."</a>"; } 
              $html .= "</td><td class=\"suhv-league suhv-opponent\">".$game_homeclub;
              $html .= "</td><td class=\"suhv-league suhv.opponent\">".$game_guestclub;
              $html .= "</td><td class=\"suhv-league suhv-result\">".$game_result;
              $html .= "</td></tr>";
            if ($game_result!="N/A") $LastActivRound = $ActivRound;
            $i++; 

        } while ($i < $entries);
        $html .= "</tbody>";
        $html .= "</table>"; 
        $loopcnt++;

        if (($data->slider->next == NULL) or ($loopcnt > $maxloop)) { $loop = FALSE; }// only this loop
        else {
          //echo '<br>next loop<br>';
          $api = new SwissUnihockey_Public(); 

          $details = $api->games(array(
            'season' => $season, 'league' => $league, 'game_class' => $game_class, 'round' => $nextround, 'mode' => $mode, 'group' => $group, 
          )); 

          $data = $details->data; 
          $nextround = $data->slider->next->set_in_context->round;
          //    echo "<br>Nextround:".$nextround."<br>";
          $i = 0;
          $entries = count($games);

          $round_Title = $data->slider->text;
          $games = $data->regions[0]->rows;
          $game_location_name = $games[0]->cells[1]->text[0]; 

          $game_map_x = $games[0]->cells[1]->link->x;
          $game_map_y = $games[0]->cells[1]->link->y;

          $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
          $ActivRound = trim(substr($round_Title,0,strpos($round_Title,'/')));
          $ActivRound = str_replace(' ', '_', $ActivRound);
          $RoundTitle = str_replace('Runde',SwissUnihockey_Api_Public::translate('Replacements','Runde'), $round_Title);
          $html .= '<div class="suhv-round-anchor"><a id="'.$ActivRound.'"></a></div>';
          if ($small) { $html .= "<h3>".$RoundTitle."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>"; } 
          else  {
            $html .= "<h3>".$RoundTitle."</h3>";
          }

          $html .= "<table class=\"suhv-table suhv-league".$tablepress."\" >\n";
          $html .= "<thead><tr><th class=\"suhv-league suhv-date\" >".$header_DateTime;
          if (!$small) {$html .= "</th><th class=\"suhv-league  suhv-place\" >".$header_Location;}
          $html .= "</th><th class=\"suhv-league suhv-opponent\" >".$header_Home;
          $html .= "</th><th class=\"suhv-league suhv-opponent\" >".$header_Guest;
          $html .= "</th><th class=\"suhv-league suhv-result\" >".$header_Result;
          $html .= "</th></tr></thead><tbody>";
           
        } // end else
      } // end While
      $html .= '<script>document.getElementById("ActivRound").href = "#'.$LastActivRound.'"</script>';
        // Report all errors
      error_reporting(E_ALL);
    
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
    }
    else { $html = $value; }
    return $html;
      
  }


/* ---------------------------------------------------------------------------------------------------- */
 public static function api_league_getWeekend($season, $league, $game_class, $group, $round, $mode, $cache) {


    $weekend = TRUE;
    $weekend_games = FALSE;
    $weekendDays = SwissUnihockey_Api_Public::nearWeekend();
    $start_date_us = $weekendDays["Freitag"];
    $end_date_us = $weekendDays["Sonntag"];
    $start_date = date("d.m.Y",$start_date_us);
    $end_date = date("d.m.Y",$end_date_us);
    $html_head = "";

    $transient = "suhv-"."league_getWeekend".$league.$game_class.$group.$season.$mode;
    
    $value = get_transient( $transient );
    // $value = FALSE;
   
    $maxloop = 20;
    $loopcnt = 1;
    $trans_Factor = 12;
    $ActivRound = "";
    $lastActivRound = "";
    $loop = TRUE;
    

    if ($league == 21) {$small = TRUE; } 
    else {$small = FALSE; }

    if (!$cache) $value = False;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'league_getWeekend', 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'group' => $group, 'round' => $round, 'mode' => $mode));

      $html = "";
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";


      $api = new SwissUnihockey_Public(); 

      // no 'round' => $round,
      $details = $api->games(array(
        'season' => $season, 'league' => $league, 'game_class' => $game_class,  'mode' => $mode, 'group' => $group, 
      )); 
      $data = $details->data; 
      $round = $data->slider->prev->set_in_context->round;

      if (isset($data->slider->next->set_in_context->round)) {
            $nextround = $data->slider->next->set_in_context->round;
            $round = $nextround +1; 
          }
      else $nextround = $round;

      $header_DateTime = SwissUnihockey_Api_Public::translate('Games',$data->headers[0]->text);
      $header_Location = SwissUnihockey_Api_Public::translate('Games',$data->headers[1]->text);
      $header_Home = SwissUnihockey_Api_Public::translate('Games',$data->headers[2]->text);
      //$header_Home = "Heimteam";
      $header_Guest = SwissUnihockey_Api_Public::translate('Games',$data->headers[6]->text);
      //$header_Guest = "Gastteam";
      $header_Result = SwissUnihockey_Api_Public::translate('Games',$data->headers[7]->text);

      $round_Title = $data->slider->text;
      $games = $data->regions[0]->rows;
      $game_location_name = $games[0]->cells[1]->text[0]; 

      $game_map_x = $games[0]->cells[1]->link->x;
      $game_map_y = $games[0]->cells[1]->link->y;

      $game_date_time = $games[0]->cells[0]->text[0];

      $date_of_game = strtotime("today");

      $game_date = substr($game_date_time,0,stripos($game_date_time," "));
      if (($game_date == "heute") or ($game_date == "gestern") or ($game_date=="morgen"))  {
         if ($game_date == "heute") $date_of_game = strtotime("today");
         if ($game_date == "gestern") $date_of_game = strtotime("yesterday");
         if ($game_date == "morgen") $date_of_game = strtotime("tomorrow");
      }
      else{
         $date_parts = explode(".",$game_date); // dd.mm.yyyy in german
         $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
      }

      $cTime = (SwissUnihockey_Api_Public::cacheTime() / 60)*$trans_Factor;

      $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title=\"".$game_location_name."\">";
     
      $ActivRound = trim(substr($round_Title,0,strpos($round_Title,'/')));
      $ActivRound = str_replace(' ', '_', $ActivRound);
      //$html .= '<div class="suhv-round-link"><a id="ActivRound" href="#LastGame">'.__('Zur aktuellen Runde','SUHV-API-2').'</a></div>';
      //$html .= '<div class="suhv-round-anchor"><a id="'.$ActivRound.'"></a></div>';
      if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
        $loop = TRUE;
        if ($small) { $html .= "<h3>".$round_Title."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>"; } 
        else  {
          $html .= "<h3>".$round_Title."</h3>";
        }
        // $html .= "<h3>".$round_Title."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>";
        $date_description = SwissUnihockey_Api_Public::translate("Replacements","Liga-Spiele vom Wochenende");
        $Spielevom = SwissUnihockey_Api_Public::translate("Replacements","Spiele vom");
        $bis = SwissUnihockey_Api_Public::translate("Replacements","bis");
        $html .= "<table class=\"suhv-table suhv-league-getWeekend".$tablepress."\">\n";
        if ($weekend) 
          $html_head .= "<caption>".$date_description." ".$start_date." ".$bis." ".$end_date."</caption>";
        else
          $html_head .= "<caption>".$Spielevom." ".$start_date." ".$bis." ".$end_date."</caption>";
        $html .= "<thead><tr><th class=\"suhv-league suhv-date\">"."Datum, Zeit";
        if  (!$small) {$html .= "</th><th class=\"suhv-league suhv-place\">".$header_Location;}
        $html .= "</th><th class=\"suhv-league suhv-opponent\">".$header_Home;
        $html .= "</th><th class=\"suhv-league suhv-opponent\">".$header_Guest;
        $html .= "</th><th class=\"suhv-league suhv-result\">".$header_Result."</th></tr></thead>";
        $html .= "<tbody>";
      }
      error_reporting(E_ALL & ~E_NOTICE); 
      $i = 0;
      $entries = count($games);
   
      while ($loop) {
        
        do {

            $game_date_time = $games[$i]->cells[0]->text[0];
            $game_location_name = $games[$i]->cells[1]->text[0]; 
            $game_map_x = $games[$i]->cells[1]->link->x;
            $game_map_y = $games[$i]->cells[1]->link->y;
            $game_homeclub = $games[$i]->cells[2]->text[0]; 
            $game_guestclub = $games[$i]->cells[6]->text[0]; 
            $game_result = $games[$i]->cells[7]->text[0];
            if ($game_result == "") $game_result = "N/A";
  
            $game_home_result = substr($game_result,0,stripos($game_result,":"));
            $game_guest_result = substr($game_result,stripos($game_result,":")+1,strlen($game_result));

            $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
       
            $game_homeDisplay = $game_homeclub;
            $game_guestDisplay = $game_guestclub;
            $game_Diplay = "";

            $date_of_game = strtotime("today");
            $game_date = substr($game_date_time,0,stripos($game_date_time," "));
            if (($game_date == "heute") or ($game_date == "gestern") or ($game_date=="morgen"))  {
               if ($game_date == "heute") $date_of_game = strtotime("today");
               if ($game_date == "gestern") $date_of_game = strtotime("yesterday");
               if ($game_date == "morgen") $date_of_game = strtotime("tomorrow");
            }
            else{
               $date_parts = explode(".",$game_date); // dd.mm.yyyy in german
               $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
            }
            if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
              $html .= "<tr ". ($i % 2 == 1 ? 'class="alt odd"' : 'class="even"') ."><td class=\"suhv-league suhv-date\">".$game_date_time;
              if (!$small) { $html .= "</td><td class=\"suhv-league suhv-place\">".$game_maplink.$game_location_name."</a>"; } 
              $html .= "</td><td class=\"suhv-league suhv-opponent\">".$game_homeclub;
              $html .= "</td><td class=\"suhv-league suhv.opponent\">".$game_guestclub;
              $html .= "</td><td class=\"suhv-league suhv-result\">".$game_result;
              $html .= "</td></tr>";
              if ($game_result!="N/A") $LastActivRound = $ActivRound;
            }
            else $i = $entries; // exit
            $i++; 
        } while ($i < $entries);
        if (($date_of_game >= $start_date_us) or ($date_of_game <= $end_date_us)) {
          $html .= "</tbody>";
          $html .= "</table>"; 
          $weekend_games = TRUE;
        }
        $loopcnt++;

        if (($data->slider->next == NULL) or ($loopcnt > $maxloop)) { $loop = FALSE; }// only this loop
        else {
          //echo '<br>next loop<br>';
          $api = new SwissUnihockey_Public(); 

          $details = $api->games(array(
            'season' => $season, 'league' => $league, 'game_class' => $game_class, 'round' => $nextround, 'mode' => $mode, 'group' => $group, 
          )); 

         //SwissUnihockey_Api_Public::log_me(array('function' => 'league_getWeekend_sub', 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'group' => $group, 'round' => $nextround, 'mode' => $mode));

          $data = $details->data; 
          if (isset($data->slider->next->set_in_context->round)) 
            $nextround = $data->slider->next->set_in_context->round;

          $i = 0;
          $entries = count($games);

          $round_Title = $data->slider->text;
          $games = $data->regions[0]->rows;
          $game_location_name = $games[0]->cells[1]->text[0]; 
          $game_date_time = $games[0]->cells[0]->text[0];

          $game_map_x = $games[0]->cells[1]->link->x;
          $game_map_y = $games[0]->cells[1]->link->y;

          $date_of_game = strtotime("today");
          $game_date = substr($game_date_time,0,stripos($game_date_time," "));
          if (($game_date == "heute") or ($game_date == "gestern") or ($game_date=="morgen"))  {
             if ($game_date == "heute") $date_of_game = strtotime("today");
             if ($game_date == "gestern") $date_of_game = strtotime("yesterday");
             if ($game_date == "morgen") $date_of_game = strtotime("tomorrow");
          }
          else{
             $date_parts = explode(".",$game_date); // dd.mm.yyyy in german
             $date_of_game = strtotime($date_parts[2]."-".$date_parts[1]."-".$date_parts[0]);
          }

          if ($date_of_game > $end_date_us) { $loop = FALSE;  // > nicht >= 
          } // exit 

          $game_maplink = "<a href=\"https://maps.google.ch/maps?q=".$game_map_y.",".$game_map_x."\""." target=\"_blank\" title= \"".$game_location_name."\">";
          $ActivRound = trim(substr($round_Title,0,strpos($round_Title,'/')));
          $ActivRound = str_replace(' ', '_', $ActivRound);
          $html .= '<div class="suhv-round-anchor"><a id="'.$ActivRound.'"></a></div>';
          if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
            if ($small) { $html .= "<h3>".$round_Title."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>"; } 
            else  {
              $html .= "<h3>".$round_Title."</h3>";
            }

            $html .= "<table class=\"suhv-table suhv-league".$tablepress."\" >\n";
            $html .= "<thead><tr><th class=\"suhv-league suhv-date\" >"."Datum, Zeit";
            if (!$small) {$html .= "</th><th class=\"suhv-league  suhv-place\" >".$header_Location;}
            $html .= "</th><th class=\"suhv-league suhv-opponent\" >".$header_Home;
            $html .= "</th><th class=\"suhv-league suhv-opponent\" >".$header_Guest;
            $html .= "</th><th class=\"suhv-league suhv-result\" >".$header_Result;
            $html .= "</th></tr></thead><tbody>";
          }
        } // end else
      } // end While
      if (!$weekend_games ) {
         $html .= 'Keine Spiele in dieser Liga am Wochenende '.date("d.m.Y",$start_date_us)." - ".date("d.m.Y",$end_date_us)."<br />";
      }
      
      //$html .= '<script>document.getElementById("ActivRound").href = "#'.$LastActivRound.'"</script>';
        // Report all errors
      error_reporting(E_ALL);
    
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor );
    }
    else { $html = $value; }
    return $html;
      
  }




    /* ---------------------------------------------------------------------------------------------------- */
  public static function api_getPlayer($player_id, $sponsor_name, $sponsor_sub, $sponsor_logo, $sponsor_link, $sponsor_link_title, $cache) {
 
    $transient = "suhv-"."getPlayer".$player_id;
    $value = get_transient( $transient );
    $trans_Factor = 1;

    if (!$cache) $value = False;
    //echo "<br> PLAYER ID : ".$player_id."<br>" ;
    if (SwissUnihockey_Api_Public::suhvDown()){ $value = TRUE; }

    if ($value == False) {

      // SwissUnihockey_Api_Public::log_me(array('function' => 'getPlayer', 'player_id' =>  $player_id, 'sponsor_name' => $sponsor_name, 'sponsor_sub' => $sponsor_sub, 'sponsor_link' => $sponsor_link));

      $html = "";
      $plugin_options = get_option( 'SUHV_WP_plugin_options' );
      $tablepress ='';
      if ((isset( $plugin_options['SUHV_css_tablepress']) == 1)) $tablepress = " tablepress";

      $api = new SwissUnihockey_Public();

      $details = $api->playerDetails($player_id, array(
        
      ));

      $data = $details->data; 
     
      $attributes = $data->regions[0]->rows[0]->cells;

      $player_name = $data->subtitle;
      $image_url = $attributes[0]->image->url;
      $club_name = $attributes[1]->text[0]; 
      $player_nr = $attributes[2]->text[0]; 
      $player_pos = $attributes[3]->text[0]; 
      $player_year =$attributes[4]->text[0]; 
      $player_size = $attributes[5]->text[0];
      $player_weight = $attributes[6]->text[0];


      $html .= "<div class=\"su-spieldaten suhv-getPlayer\">";
      $html .= "<div class=\"su-container su-obj-spielerdetails\">";
      if ($player_id != NULL) {
        $html .= "<div class=\"su-header\"><span class=\"su-value-name\"><h2>".$player_nr." ".$player_name."</h2></span>"; 
      }
      else {
        $html .= "<div class=\"su-header\"><span class=\"su-value-name\"><h2>SUHV Player ID not set!</h2></span>"; 
      }
      $html .= "</div>";
      $html .= "<div class=\"su-row\">";
      $html .= "<div class=\"su-obj-portrait\">";
      $html .= "<span class=\"su-value-portrait\">";
      $html .= "<img src=\"".$image_url."\" alt=\"".$player_name." (Portrait)"."\"></span></div>";
      $html .= "<div class=\"su-obj-details\">";
      $html .= "<table class=\" su-table".$tablepress."\" cellpadding=\"0\" cellspacing=\"0\"><tbody>";
      $html .= "<tr><td class=\"su-strong\">Name:</td><td>".$player_name."</td></tr>";
      $html .= "<tr><td class=\"su-strong\">Nr:</td><td>".$player_nr."</td></tr>";     
      $html .= "<tr><td class=\"su-strong\">Position:</td><td>".$player_pos."</td></tr>";
      $html .= "<tr><td class=\"su-strong\">Jahrgang:</td><td>".$player_year."</td></tr>";
      $html .= "<tr><td class=\"su-strong\">Grösse:</td><td>". $player_size."</td></tr>";
      $html .= "<tr><td class=\"su-strong\">Gewicht:</td><td>". $player_weight."</td></tr>";
      $html .= "</tbody></table>";    
      $html .= "<div class=\"su-site-link\"><a href=\"https://www.swissunihockey.ch/de/player-detail?player_id=".$player_id."\">Spielerstatistik (Swissunihockey)</a></div>";
      $html .= "</div></div><!-- /su-row --></div><!-- /su-container --></div><!-- /su-spieldaten -->";

      if ($sponsor_name != NULL) {
       if ($sponsor_name[0] != '*') {
         $html .= "<h2 class=\"sponsor-header\">Sponsor</h2>";
         $html .= "<div class=\"sponsor-row\">";
         $html .= "<div class=\"sponsor-logo\"><a href=\"".$sponsor_link."\"><img src=\"".$sponsor_logo."\" alt=\"".$sponsor_name."\" /></a></div>";
         $html .= "<div class=\"sponsor-name\"><span><h3>".$sponsor_name."</h3>";
         $html .= "<h4><a href=\"".$sponsor_link."\">".$sponsor_link_title."</a></h4></span></div></div>";
       }
       else {
         $html .= "<h2 class=\"sponsor-header\">persönlicher Sponsor gesucht!</h2>";
         $html .= "<div class=\"sponsor-row\">";
         $html .= "<div class=\"sponsor-logo\"><a href=\"http://www.churunihockey.ch/sponsoring/kontakt-marketing/\"><img src=\"http://www.churunihockey.ch/wp-content/uploads/2013/08/ChurUnihockeyLogoSlide_460x368.png\" alt=\"www.churunihockey.ch\" /></a></div>";
         $html .= "<div class=\"sponsor-name\"><span><h3>- Hier könnte ihre Firma stehen -</h3>";
         $html .= "<h4><a href=\"http://www.churunihockey.ch/sponsoring/kontakt-marketing/\">"."www.churunihockey.ch"."</a></h4></span></div></div>";
       }
      }
      set_transient( $transient, $html, SwissUnihockey_Api_Public::cacheTime()*$trans_Factor);
    }
    else { $html = $value; }
    return $html;
   
      
  }

  public static function api_club_getMiniResults($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
    $my_club_name = $club_shortname;
		$cup                = FALSE;
		$transient          = $club_ID . $team_ID . "club_getMiniResults" . $season . $mode;
		$secure_trans       = $transient . "Secure";
		$semaphore          = $club_ID . $team_ID . "club_getMiniResults-Flag";
		$value              = get_transient($transient);
		$flag               = get_transient($semaphore);
		$linkGame_ID        = NULL;
		$likkGame_ID_before = NULL;

		if ($flag)
			$sema_value = "Sema: TRUE";
		else
			$sema_value = "Sema: FALSE";


		if (!$cache) {
			$value = False;
		}

		$html = "";
		$html .= "<!--api_club_getMiniResults! flag:".$flag." cache:".$cache." value:".$value."-->";

		if (($value == False) and ($flag == False)) {

			set_transient($semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

			$go             = time();
			$api_calls      = 0;
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = $plugin_options['SUHV_club_mini_results_limit'];
			$e_Mail_From    = $plugin_options['SUHV_mail_send_from'];
			$e_Mail_Actual  = $plugin_options['SUHV_mail_actual_result'];
			$e_Mail_Result  = $plugin_options['SUHV_mail_final_result'];

			$html .= "<!--n_Games:".$n_Games."-->";

			$skip = "<br />";

			$html_res  = "";
			$html_body = "";

			$tage      = array(
				"Sonntag",
				"Montag",
				"Dienstag",
				"Mittwoch",
				"Donnerstag",
				"Freitag",
				"Samstag"
			);
			$tag       = date("w");
			$wochentag = $tage[$tag];


			$api = new SwissUnihockey_Public();
			$api_calls++;
			$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array());

			// Eine Seite retour bei Page-Ende? 

			$data      = $details->data;
			$startpage = $data->context->page;

			if ($startpage != 1) { // eine Page retour wenn nicht erste
				$page = $startpage - 1;
				$api  = new SwissUnihockey_Public();
				$api_calls++;
				$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
					'page' => $page
				));
				// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 2 page: ".$page);
			}

			$data            = $details->data;
			$header_DateTime = $data->headers[0]->text;
			$header_Location = $data->headers[1]->text;
			$header_Leage    = $data->headers[2]->text;
			$header_Home     = $data->headers[3]->text;
			$header_Guest    = $data->headers[4]->text;
			$header_Result   = $data->headers[5]->text;
			$Cpos            = strripos($my_club_name, 'Chur');
			if (!is_bool($Cpos)) {
				$header_Result = "Res.";
			}
			$club_name  = $data->title;
			$games      = $data->regions[0]->rows;
			$attributes = $data->regions[0]->rows[0]->cells;

			$entries = count($games);

			$transient_games = $transient . $tag;
			$last_games      = get_transient($transient_games);
			if ($last_games == FALSE) {
				$last_games = $games;
				set_transient($transient_games, $last_games, 2 * 60 * 60);
			}
			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$startdate = strtotime("+3 hours", $today);

			if (!$cache) {
				$view_cache = "<br> cache = off / Display: " . $n_Games . " Club: " . $my_club_name;
			} else {
				$view_cache = "";
			}

			$html_head        = "";
			$latestDateOfGame = strtotime("1970-01-01");

			error_reporting(E_ALL & ~E_NOTICE);
			while ($loop) {
				$i           = $entries - 1;
				$echoedGames = 0;
				do {
					//					echo "\n<!-- game result: " . $game_result . " resutl add: " . $game_result_add . "-->";
					$game_id   = $games[$i]->link->ids[0];
					$game_date = $games[$i]->cells[0]->text[0];
					$game_time = $games[$i]->cells[0]->text[1];
					if ($game_time != "???") {
						$game_location_name = $games[$i]->cells[1]->text[0];
						$game_location      = $games[$i]->cells[1]->text[1];
						$game_map_x         = $games[$i]->cells[1]->link->x;
						$game_map_y         = $games[$i]->cells[1]->link->y;
					} else {
						$game_location_name = "";
						$game_location      = "";
						$game_map_x         = "";
						$game_map_y         = "";
					}
					$game_leage      = $games[$i]->cells[2]->text[0];
					$game_group      = $games[$i]->cells[2]->text[1];
					$game_homeclub   = $games[$i]->cells[3]->text[0];
					$game_guestclub  = $games[$i]->cells[4]->text[0];
					$game_result     = $games[$i]->cells[5]->text[0];
					$linkGame_ID     = $games[$i]->link->ids[0];
					$new_result      = $game_result;
					$game_result_add = "";
					if (isset($games[$i]->cells[5]->text[1])) {
						$game_result_add = $games[$i]->cells[5]->text[1];
					}

					$game_home_result  = substr($game_result, 0, stripos($game_result, ":"));
					$game_guest_result = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));
					$site_url          = get_site_url();
					$site_display      = substr($site_url, stripos($site_url, "://") + 3);

					//Fehlerkorrektur fÃ¼r vom 7.1.2017
					if ($game_date == "today")
						$game_date = "heute";
					if ($game_date == "yesterday")
						$game_date = "gestern";

					if (($game_date == "heute") or ($game_date == "gestern")) {
						if ($game_date == "heute") {
							$date_of_game = strtotime("today");
							$last_result  = $last_games[$i]->cells[5]->text[0];
						}
						if ($game_date == "gestern")
							$date_of_game = strtotime("yesterday");
					} else {
						$date_parts   = explode(".", $game_date); // dd.mm.yyyy in german
						$date_of_game = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
					}


					$gameLocationLinkTitle = "";
					if ($game_location) {
						$gameLocationLinkTitle .= $game_location;
						if ($game_location_name) {
							$gameLocationLinkTitle .= " (" . $game_location_name . ")";
						}
					}

					$game_homeDisplay  = $game_homeclub;
					$game_guestDisplay = $game_guestclub;

					$special_league = "Junioren/-innen U14/U17 VM";
					$team_one       = $my_club_name . " I";
					$team_two       = $my_club_name . " II";
					$league_short   = "U14/U17";

					$game_group = str_replace("Gruppe", "Gr.", $game_group);
                    $game_leage = SwissUnihockey_Api_Public::clean_league($game_leage, $game_group);

					$homeClass = "suhv-place";
					if ($game_home_result == $game_guest_result) {
						$resultClass = 'suhv-draw';
					} else {
						$resultClass = 'suhv-result';
					}

					if (substr_count($game_homeDisplay, $my_club_name) >= 1) {
						if ((substr_count($game_homeDisplay, $my_club_name) >= 1) xor (substr_count($game_guestDisplay, $my_club_name) >= 1))
							$game_homeDisplay = $game_leage;
						if ((substr_count($game_homeDisplay, $my_club_name) >= 1) and (substr_count($game_guestDisplay, $my_club_name) >= 1))
							$game_homeDisplay = $league_short . " " . str_replace($my_club_name, "", $game_homeDisplay);
						$resultHomeClass = 'suhv-home';
						if ($game_home_result > $game_guest_result) {
							$resultClass = 'suhv-win';
						} else {
							$resultClass = 'suhv-lose';
						}
					} else
						$resultHomeClass = 'suhv-guest';
					if (substr_count($game_guestDisplay, $my_club_name) >= 1) {
						if ((substr_count($game_homeDisplay, $my_club_name) >= 1) xor (substr_count($game_guestDisplay, $my_club_name) >= 1))
							$game_guestDisplay = $game_leage;
						if ((substr_count($game_homeDisplay, $my_club_name) >= 1) and (substr_count($game_guestDisplay, $my_club_name) >= 1))
							$game_guestDisplay = $league_short . " " . str_replace($my_club_name, "", $game_guestDisplay);
						$resultGuestClass = 'suhv-home';
						if ($game_guest_result > $game_home_result) {
							$resultClass = 'suhv-win';
						} else {
							$resultClass = 'suhv-lose';
						}
					} else
						$resultGuestClass = 'suhv-guest';

					if ($game_result == "") {
						$resultClass = 'suhv-result';
					}
					if (($game_date == "heute") and ((substr_count($game_result, "*") != 0) or (substr_count($game_result, "-") != 0))) {
						$resultClass = 'suhv-activ';
						if (substr_count($game_result, "-") != 0) {
							$game_result = "â“";
							$resultClass .= ' suhv-wait';
						}
					}
					if ($game_date == "heute") {
						if (($new_result != $last_result) and (substr_count($new_result, "*") != 0) and ($new_result != "") and (substr_count($e_Mail_Actual, "@") >= 1)) {
							$last_games[$i] = $games[$i];
						}
						if (($new_result != $last_result) and (substr_count($new_result, "*") == 0) and ($new_result != "") and (substr_count($new_result, "-") == 0) and (substr_count($e_Mail_Result, "@") >= 1)) {
							$last_games[$i] = $games[$i];
						}
					}
					if ($echoedGames < $n_Games) {
						if (($date_of_game <= $startdate) and ($linkGame_ID_before != $linkGame_ID) and ($game_result != "") and ($game_result != ":") and ($game_result != "[:]") and ($game_result != "-:-") and ($game_result != "[-:-]")) { //  and $cup
							$echoedGames++;
							if ($resultHomeClass == 'suhv-guest') {
								$html_body .= "\n<div>" . $game_guestDisplay . " [" . $game_guest_result . ":" . $game_home_result . $game_result_add . "] " . $game_homeDisplay . "</div>";
							} else {
								$html_body .= "\n<div>" . $game_homeDisplay . " [" . $game_result . $game_result_add . "] " . $game_guestDisplay . "</div>";
							}
						}
					} else {
						$loop = FALSE;
					}
					$i--;
					$linkGame_ID_before = $linkGame_ID;
				} while (($i >= 0) and $loop and ($echoedGames < $n_Games));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10)) {
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					}
					$api = new SwissUnihockey_Public();
					$api_calls++;
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
					$entries = count($games);
				} // end else
			} // end wile
			// Report all errors
			error_reporting(E_ALL);
			$html_head .= $html_res;
			$html .= $html_head;
			$html .= $html_body;

			$stop = time();
			$secs = ($stop - $go);
			//SwissUnihockey_Api_Public::log_me("club_getGames eval-time: ".$secs." secs  api-calls: ". $api_calls);
			//$html2 = str_replace ("</table>","defekt",$html);// for test
			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
			set_transient($transient_games, $last_games, 2 * 60 * 60);
			if (($secs <= 10) and isset($data)) {
				$safe_html = str_replace(" min.)", " min. cache value)", $html);
				set_transient($secure_trans, $safe_html, 12 * 3600);
			}
		} //end If
		else {
			$htmlpos = strpos($value, "</table>");
			$len     = strlen($value);
			$html .= "\n<!-- strlen ist ".strlen($value)." -->";
			if (($htmlpos) and ($len > 300)) {
				$html = $value; // Abfrage war OK
			} else {
				$value = get_transient($secure_trans); // letzte gute Version holen bei Time-Out der API 2.0 von Swissunihockey
				$html  = $value;
			}
		}
		//		$html .= "\n<!-- das wars -->";
		return $html;
	}

	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_club_getMiniGames($season, $club_ID, $club_shortname, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
    $my_club_name = $club_shortname;
		$cup                = FALSE;
		$transient          = $club_ID . $team_ID . "club_getMiniGames" . $season . $mode;
		$secure_trans       = $transient . "Secure";
		$semaphore          = $club_ID . $team_ID . "club_getMiniGames-Flag";
		$value              = get_transient($transient);
		$flag               = get_transient($semaphore);
		$linkGame_ID        = NULL;
		$likkGame_ID_before = NULL;

		if ($flag)
			$sema_value = "Sema: TRUE";
		else
			$sema_value = "Sema: FALSE";
		//SwissUnihockey_Api_Public::log_me($sema_value);

		if (!$cache) {
			$value = False;
		}

		$html         = "";
		//        $html .= "<!-- api_club_getMiniGames!  flag:".$flag." cache:".$cache." value:".$value."-->";

		if (($value == False) and ($flag == False)) {

			set_transient($semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

			$go             = time();
			$api_calls      = 0;
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = $plugin_options['SUHV_club_mini_games_limit'];
			//      echo "n_games: ".$n_Games;
			$e_Mail_From    = $plugin_options['SUHV_mail_send_from'];
			$e_Mail_Actual  = $plugin_options['SUHV_mail_actual_result'];
			$e_Mail_Result  = $plugin_options['SUHV_mail_final_result'];


			// SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

			$mailheaders = 'From: Spielresultate <' . $e_Mail_From . '>' . "\r\n";
			$mailheaders .= "MIME-Version: 1.0\r\n";
			$mailheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
			$skip = "<br />";

			$html_res     = "";
			$html_body    = "";
			$mail_subjekt = "";

			$tage      = array(
				"Sonntag",
				"Montag",
				"Dienstag",
				"Mittwoch",
				"Donnerstag",
				"Freitag",
				"Samstag"
			);
			$tag       = date("w");
			$wochentag = $tage[$tag];

			$api = new SwissUnihockey_Public();
			$api_calls++;
			$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array());

			// Eine Seite retour bei Page-Ende? 

			if (isset($details->data)) {
				$data      = $details->data;
				$startpage = $data->context->page;
			} else {
				$startpage = 1;
			}

			// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 1 page: ".$startpage);

			if ($startpage != 1) { // eine Page retour wenn nicht erste
				$page = $startpage - 1;
				$api  = new SwissUnihockey_Public();
				$api_calls++;
				$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
					'page' => $page
				));
				// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 2 page: ".$page);
			}

			if (isset($details->data)) {
				$data            = $details->data;
				$header_DateTime = $data->headers[0]->text;
				$header_Location = $data->headers[1]->text;
				$header_Leage    = $data->headers[2]->text;
				$header_Home     = $data->headers[3]->text;
				$header_Guest    = $data->headers[4]->text;
				$header_Result   = $data->headers[5]->text;

				$Cpos            = strripos($my_club_name, 'Chur');
				if (!is_bool($Cpos)) {
					$header_Result = "Res.";
				}
				$club_name  = $data->title;
				$games      = $data->regions[0]->rows;
				$attributes = $data->regions[0]->rows[0]->cells;

				$entries = count($games);

				$startpage = $data->context->page;
			}

			$transient_games = $transient . $tag;
			$last_games      = get_transient($transient_games);
			if ($last_games == FALSE) {
				$last_games = $games;
				set_transient($transient_games, $last_games, 2 * 60 * 60);
				// echo "<br>Reset Games";
			}

			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$startdate = strtotime("-3 hours", $today);

			$cTime = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			if (!$cache) {
				$view_cache = "<br> cache = off / Display: " . $n_Games . " Club: " . $my_club_name;
			} else {
				$view_cache = "";
			}

			$html_head = "\n";


			error_reporting(E_ALL & ~E_NOTICE);
			$i           = 0;
			$echoedGames = 0;
			do {
				$game_id         = $games[$i]->link->ids[0];
				$game_date       = $games[$i]->cells[0]->text[0];
				$game_time       = $games[$i]->cells[0]->text[1];
				$game_leage      = $games[$i]->cells[2]->text[0];
				$game_group      = $games[$i]->cells[2]->text[1];
				$game_homeclub   = $games[$i]->cells[3]->text[0];
				$game_guestclub  = $games[$i]->cells[4]->text[0];
				$game_result     = $games[$i]->cells[5]->text[0];
				$linkGame_ID     = $games[$i]->link->ids[0];
				$new_result      = $game_result;
				$game_result_add = "";
				if (isset($games[$i]->cells[5]->text[1])) {
					$game_result_add = $games[$i]->cells[5]->text[1];
				}
				$game_home_result  = substr($game_result, 0, stripos($game_result, ":"));
				$game_guest_result = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));
				$site_url          = get_site_url();
				$site_display      = substr($site_url, stripos($site_url, "://") + 3);

				//Fehlerkorrektur fÃ¼r vom 7.1.2017
				if ($game_date == "today")
					$game_date = "heute";
				if ($game_date == "yesterday")
					$game_date = "gestern";

				if (($game_date == "heute") or ($game_date == "gestern")) {
					if ($game_date == "heute") {
						$date_of_game = strtotime("today");
						$last_result  = $last_games[$i]->cells[5]->text[0];
					}
					if ($game_date == "gestern")
						$date_of_game = strtotime("yesterday");
				} else {
					$date_parts   = explode(".", $game_date); // dd.mm.yyyy in german
					$date_of_game = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
				}

				$game_homeDisplay  = $game_homeclub;
				$game_guestDisplay = $game_guestclub;

				/* If Cup?
					if (substr_count($game_leage,"Cup")>=1) { 
					$cup = TRUE;
					} */

				$special_league = "Junioren/-innen U14/U17 VM";
				$team_one       = $my_club_name . " I";
				$team_two       = $my_club_name . " II";
				$league_short   = "U14/U17";
				if ($game_leage == $special_league) {
					$game_leage = str_replace($special_league, $league_short, $game_leage); //new ab 2016
					if ((substr_count($game_homeDisplay, $team_one) >= 1) xor (substr_count($game_guestDisplay, $team_two) >= 1)) {
						if ((substr_count($game_homeDisplay, $team_two) >= 1) or (substr_count($game_guestDisplay, $team_two) >= 1)) {
							$game_leage .= " II"; // Angzeige "U14/U17 II"
						} else {
							if ((substr_count($game_homeDisplay, $team_one) >= 1) or (substr_count($game_guestDisplay, $team_one) >= 1)) {
								$game_leage .= " I"; // Angzeige "U14/U17 I"
							}
						}
					} else {
						if ((substr_count($game_homeDisplay, $team_two) >= 1) or (substr_count($game_guestDisplay, $team_two) >= 1)) {
							$game_leage .= " II"; // Angzeige "U14/U17 II"
						} else {
							if ((substr_count($game_homeDisplay, $team_one) >= 1) or (substr_count($game_guestDisplay, $team_one) >= 1)) {
								$game_leage .= " I"; // Angzeige "U14/U17 I"
							}
						}
					}
				}
                
                $game_group = str_replace("Gruppe", "Gr.", $game_group);
                $game_leage = SwissUnihockey_Api_Public::clean_league($game_leage, $game_group);

				$homeClass = "suhv-place";
				if ($game_home_result == $game_guest_result) {
					$resultClass = 'suhv-draw';
				} else {
					$resultClass = 'suhv-result';
				}

				if (substr_count($game_homeDisplay, $my_club_name) >= 1) {
					if ((substr_count($game_homeDisplay, $my_club_name) >= 1) xor (substr_count($game_guestDisplay, $my_club_name) >= 1))
						$game_homeDisplay = $game_leage;
					if ((substr_count($game_homeDisplay, $my_club_name) >= 1) and (substr_count($game_guestDisplay, $my_club_name) >= 1))
						$game_homeDisplay = $league_short . " " . str_replace($my_club_name, "", $game_homeDisplay);
					$resultHomeClass = 'suhv-home';
					if ($game_home_result > $game_guest_result) {
						$resultClass = 'suhv-win';
					} else {
						$resultClass = 'suhv-lose';
					}
				} else
					$resultHomeClass = 'suhv-guest';
				if (substr_count($game_guestDisplay, $my_club_name) >= 1) {
					if ((substr_count($game_homeDisplay, $my_club_name) >= 1) xor (substr_count($game_guestDisplay, $my_club_name) >= 1))
						$game_guestDisplay = $game_leage;
					if ((substr_count($game_homeDisplay, $my_club_name) >= 1) and (substr_count($game_guestDisplay, $my_club_name) >= 1))
						$game_guestDisplay = $league_short . " " . str_replace($my_club_name, "", $game_guestDisplay);
					$resultGuestClass = 'suhv-home';
					if ($game_guest_result > $game_home_result) {
						$resultClass = 'suhv-win';
					} else {
						$resultClass = 'suhv-lose';
					}
				} else
					$resultGuestClass = 'suhv-guest';

				if ($game_result == "") {
					$resultClass = 'suhv-result';
				}
				if (($game_date == "heute") and ((substr_count($game_result, "*") != 0) or (substr_count($game_result, "-") != 0))) {
					$resultClass = 'suhv-activ';
					if (substr_count($game_result, "-") != 0) {
						$game_result = "â“";
						$resultClass .= ' suhv-wait';
					}
				}
				if ($game_date == "heute") {
					if (($new_result != $last_result) and (substr_count($new_result, "*") != 0) and ($new_result != "") and (substr_count($e_Mail_Actual, "@") >= 1)) {
						// echo "<br>new-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
						$last_games[$i] = $games[$i];
					} else {
						//echo "<br>old-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
					}
					if (($new_result != $last_result) and (substr_count($new_result, "*") == 0) and ($new_result != "") and (substr_count($new_result, "-") == 0) and (substr_count($e_Mail_Result, "@") >= 1)) {
						// echo "<br>NEUES-Resultat ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
						$last_games[$i] = $games[$i];
					}
				}
				if ($echoedGames < $n_Games) {

					preg_match("/([0-9]{1,2}):([0-9]{1,2})/", $game_time, $match);
					$game_hour    = $match[1];
					$game_minutes = $match[2];

					$mytimestamp = strtotime("+" . $game_hour . "hours", $date_of_game);
					$mytimestamp = strtotime("+" . $game_minutes . "minutes", $mytimestamp);
                    
                    if (($mytimestamp >= $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup
						$html_body .= "\n" . date('d.m', $date_of_game) . "&nbsp;" . $game_time . "&nbsp;" . $game_homeDisplay . "&nbsp;:&nbsp;" . $game_guestDisplay . "<br>";
						$echoedGames++;
					}
				} else {
					break;
				}
				$i++;
				$linkGame_ID_before = $linkGame_ID;
			} while ($i < $entries);
			// Report all errors
			error_reporting(E_ALL);
			$html_head .= $html_res;
			//      $html_head .= "</tr></thead><tbody>";
			$html .= $html_head;
			$html .= $html_body;
			//      $html .= "</tbody>";
			//      $html .= "</table>";
			//      $html .= "</table></div>";
			$html .= "\n";

			$stop = time();
			$secs = ($stop - $go);
			//SwissUnihockey_Api_Public::log_me("club_getGames eval-time: ".$secs." secs  api-calls: ". $api_calls);
			//$html2 = str_replace ("</table>","defekt",$html);// for test
			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
			set_transient($transient_games, $last_games, 2 * 60 * 60);
			if (($secs <= 10) and isset($data)) {
				$safe_html = str_replace(" min.)", " min. cache value)", $html);
				set_transient($secure_trans, $safe_html, 12 * 3600);
			}
		} //end If
		else {
			//        $htmlpos = strpos($value,"</table>");
			$htmlpos = strpos($value, "\n");

			$len = strlen($value);
			if (($htmlpos) and ($len > 300)) {
				$html = $value; // Abfrage war OK
				//SwissUnihockey_Api_Public::log_me("API Cache OK pos: ".$htmlpos." len: ".$len);
			} else {
				$value = get_transient($secure_trans); // letzte gute Version holen bei Time-Out der API 2.0 von Swissunihockey
				//SwissUnihockey_Api_Public::log_me("API Cache Restore!");
				$html  = $value;
			}
		}
		return $html;
	}

/* ---------------------------------------------------------------------------------------------------- */

  // Funktion: Log-Daten in WP-Debug schreiben
  public static function log_me($message) {
      if ( WP_DEBUG === true ) {
          if ( is_array($message) || is_object($message) ) {
              error_log( print_r($message, true) );
          } else {
                error_log( $message );
          }
      }
  }
/* ---------------------------------------------------------------------------------------------------- */
  public static function api_show_params($season, $club_ID, $team_ID, $mode) {

      echo "<br>Season: ".$season." - Club: ".$club_ID." - Team: ".$team_ID;
  }

}




