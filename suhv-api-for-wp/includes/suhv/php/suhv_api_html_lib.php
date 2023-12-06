<?php
/***
	 * Classes that return HTML Code from SUHV Classes like SuhvClub or SuhvTeam
	 * 
	 * @author Thomas Hardegger 
	 * @version 12.02.2017
	 * @todo Auf neue API umschreiben / die Funktionen bebehalten
	 * STATUS: Change
	 */


class SwissUnihockey_Api_Public {

	private static function cacheTime() {

		$cbase   = 5 * 60; // 5 Min.
		$tag     = date("w");
		$time    = time();
		$nach16  = mktime(16, 0);
		$vor23   = mktime(23, 0);
		$options = get_option('SUHV_WP_plugin_options');
		if ((isset($options['SUHV_long_cache']) == 1) and (($time > $nach16) and ($time < $vor23))) {
			$cacheValue = array(
				7 * 12 * $cbase,
				7 * 12 * $cbase,
				7 * 12 * $cbase,
				7 * 12 * $cbase,
				7 * 12 * $cbase,
				7 * 12 * $cbase,
				7 * 12 * $cbase
			); // 7 hours 16:00 to 23:00
		} else { //(Sonntag,Montag,Dienstag,Mittwoch,Donnerstag,Freitag,Samstag);
			$cacheValue = array(
				1 * $cbase,
				6 * $cbase,
				6 * $cbase,
				2 * $cbase,
				2 * $cbase,
				2 * $cbase,
				1 * $cbase
			);
		}
		return ($cacheValue[$tag]);
	}

	private static function nearWeekend() {
		// So Mo Di (Mi=4) Do Fr Sa //
		$dayline     = array(
			0,
			-1,
			-2,
			4,
			3,
			2,
			1
		);
		$tag         = date("w");
		$today       = strtotime("today");
		$daytoSunday = $dayline[$tag];
		$sunday      = strtotime($daytoSunday . " day", $today);
		$saturday    = strtotime("-1 day", $sunday);
		$friday      = strtotime("-2 day", $sunday);
		$weekendDays = array(
			"Freitag" => $friday,
			"Samstag" => $saturday,
			"Sonntag" => $sunday
		);

		return ($weekendDays);
	}


	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_club_getGames($season, $club_ID, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
		$my_club_name       = "Chur";
		$cup                = FALSE;
		$transient          = $club_ID . $team_ID . "club_getGames" . $season . $mode;
		$secure_trans       = $transient . "Secure";
		$semaphore          = $club_ID . $team_ID . "club_getGames-Flag";
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

		if (($value == False) and ($flag == False)) {

			set_transient($semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

			$go             = time();
			$api_calls      = 0;
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = $plugin_options['SUHV_club_games_limit'];
			$e_Mail_From    = $plugin_options['SUHV_mail_send_from'];
			$e_Mail_Actual  = $plugin_options['SUHV_mail_actual_result'];
			$e_Mail_Result  = $plugin_options['SUHV_mail_final_result'];


			// SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

			$mailheaders = 'From: Spielresultate <' . $e_Mail_From . '>' . "\r\n";
			$mailheaders .= "MIME-Version: 1.0\r\n";
			$mailheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
			$skip = "<br />";

			$html         = "";
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

			$data = $details->data;

			//      echo "<!---------------------------------------\nData:\n\n". $data ."\n-->";

			$startpage = $data->context->page;
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
				// echo "<br>Reset Games";
			}
			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$tod       = strtotime("today");
			$startdate = strtotime("-2 hours", $today);
			$cTime     = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			if (!$cache) {
				$view_cache = "<br> cache = off / Display: " . $n_Games . " Club: " . $my_club_name;
				// $view_cache =."<br>emails:<br> ".$e_Mail_Actual."<br>".$e_Mail_Result."<br>".$e_Mail_From;
			} else {
				$view_cache = "";
			}



			//      $html_head = "<table class=\"suhv-table suhv-planned-games-full\">\n";
			$html_head = "<div class=\"suhvIncludes twoRight\">\n\t<table class=\"table nobr\">\n";
			//      $html_head .= "<caption>".$data->title."<br>".$wochentag.strftime(" - %H:%M")."  (".$cTime." min.)".$view_cache."</caption>";
			//      $html_head .= "<thead><tr><th class=\"suhv-date\">"."Datum,<br>Zeit".
			//      "</th><th class=\"suhv-place\">".$header_Location.
			//      "</th><th class=\"suhv-opponent\">".$header_Home.
			//      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>";

			$latestDateOfGame = strtotime("1970-01-01");

			error_reporting(E_ALL & ~E_NOTICE);
			while ($loop) {
				$i = 0;
				do {
					$game_id          = $games[$i]->link->ids[0];
					$game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=" . $game_id;
					$game_date        = $games[$i]->cells[0]->text[0];
					$game_time        = $games[$i]->cells[0]->text[1];
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

					//Fehlerkorrektur für vom 7.1.2017
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

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $gameLocationLinkTitle . "\">";

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

					$game_leage = str_replace(" Regional", " (".$game_group.") ", $game_leage);
					$game_leage = str_replace("Junioren U", "Junioren U", $game_leage);
					$game_leage = str_replace("Juniorinnen", "", $game_leage);
					$game_leage = str_replace("Herren Aktive", "", $game_leage);
					$game_leage = str_replace("Aktive", "", $game_leage);
					$game_leage = str_replace("Schweizer", "", $game_leage);

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
							$game_result = "❓";
							$resultClass .= ' suhv-wait';
						}
					}
					if ($game_date == "heute") {
						if (($new_result != $last_result) and (substr_count($new_result, "*") != 0) and ($new_result != "") and (substr_count($e_Mail_Actual, "@") >= 1)) {
							// echo "<br>new-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
							$last_games[$i] = $games[$i];
							$message        = $game_location_name . " (" . $game_location . "): <strong>" . $new_result . "</strong> im Spiel " . $game_homeDisplay . " vs. " . $game_guestDisplay . $skip;
							$message .= "Spielbeginn: " . $game_time . " aktuelle Zeit: " . strftime("%H:%M") . $skip;
							// $message .=  $skip."Info: Page: ".$page." Pos: ".$i." - bisher: ".$last_result.$skip; 
							$message .= $skip . "Diese Meldung wurde Dir durch <a href=\"" . $site_url . "\">" . $site_display . "</a> zugestellt." . $skip;
							if ((substr_count($new_result, "0:0") != 0)) {
								$checkmail = wp_mail($e_Mail_Actual, $game_leage . ' Spielstart: ' . $new_result, $message, $mailheaders);
							} else {
								$checkmail = wp_mail($e_Mail_Actual, $game_leage . ' Zwischenresultat: ' . $new_result, $message, $mailheaders);
							}
							//echo "<br>mail ", $checkmail;
						} else {
							//echo "<br>old-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
						}
						if (($new_result != $last_result) and (substr_count($new_result, "*") == 0) and ($new_result != "") and (substr_count($new_result, "-") == 0) and (substr_count($e_Mail_Result, "@") >= 1)) {
							// echo "<br>NEUES-Resultat ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
							$last_games[$i] = $games[$i];
							$message        = $game_location_name . " (" . $game_location . "): <strong>" . $new_result . "</strong> " . $game_result_add . " im Spiel " . $game_homeDisplay . " vs. " . $game_guestDisplay . $skip;
							//$message .= $skip."Info: Page: ".$page." Pos: ".$i." - bisher: ".$last_result.$skip; 
							$message .= $skip . "Diese Meldung wurde Dir durch <a href=\"" . $site_url . "\">" . $site_display . "</a> zugestellt." . $skip;
							$checkmail = wp_mail($e_Mail_Result, $game_leage . ' Schluss-Resultat: ' . $new_result, $message, $mailheaders);
							//echo "<br>mail ", $checkmail;
						}
					}
					if (($items <= $n_Games)) {
						preg_match("/([0-9]{1,2}):([0-9]{1,2})/", $game_time, $match);
						$game_hour    = $match[1];
						$game_minutes = $match[2];

						$mytimestamp = strtotime("+" . $game_hour . "hours", $date_of_game);
						$mytimestamp = strtotime("+" . $game_minutes . "minutes", $mytimestamp);

						//                $html_body .= "\n<!-- ".strtotime($game_time)." ". $game_time." if (".$mytimestamp." >= ".$startdate.")--><!-- ".date("Y-m-d H:i:s", $mytimestamp)." >= ".date("Y-m-d H:i:s", $startdate)."-->";


						if (($mytimestamp >= $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup

							if ($latestDateOfGame != $date_of_game) {
								$latestDateOfGame = $date_of_game;
								$html_body .= "\n<tr class='suhvFatRow'>";
								$html_body .= "\n\t<td colspan='5'>" . $tage[date('w', $date_of_game)] . ", " . str_replace(".20", ".", $game_date) . "</td>";
								//$html_body .= "\n\t<td colspan='5'>".str_replace(".20",".",$game_date)."</td>";
								$html_body .= "\n    </tr>";
							}

							$html_body .= "\n<tr><td>" . $game_time . "</td><td>" . $game_homeDisplay . "</td><td>:</td><td>" . $game_guestDisplay . "</td><td>" . $game_maplink . $gameLocationLinkTitle . "</a></td></tr>";
							// $cup = FALSE; // cup only
						}
					} else {
						$loop = FALSE;
					}
					$i++;
					$linkGame_ID_before = $linkGame_ID;
				} while (($i < $entries) and ($loop));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10))
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					$api = new SwissUnihockey_Public();
					$api_calls++;
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
					$entries = count($games);
					// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
				} // end else
			} // end While
			// Report all errors
			error_reporting(E_ALL);
			$html_head .= $html_res;
			//      $html_head .= "</tr></thead><tbody>";
			$html .= $html_head;
			$html .= $html_body;
			//      $html .= "</tbody>";
			//      $html .= "</table>";
			$html .= "</table></div>";

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
	public static function api_club_getPlayedGames($season, $club_ID, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
		$my_club_name       = "United Toggenburg";
		$cup                = FALSE;
		$transient          = $club_ID . $team_ID . "club_getPlayedGames" . $season . $mode;
		$secure_trans       = $transient . "Secure";
		$semaphore          = $club_ID . $team_ID . "club_getPlayedGames-Flag";
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

		if (($value == False) and ($flag == False)) {

			set_transient($semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

			$go             = time();
			$api_calls      = 0;
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = $plugin_options['SUHV_club_games_limit'];
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
			$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
				'games_per_page' => 200
			));

			// Eine Seite retour bei Page-Ende? 

			$data      = $details->data;
			$startpage = $data->context->page;
			// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." 1 page: ".$startpage);

			if ($startpage != 1) { // eine Page retour wenn nicht erste
				$page = $startpage - 1;
				$api  = new SwissUnihockey_Public();
				$api_calls++;
				$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
					'games_per_page' => 70,
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
				// echo "<br>Reset Games";
			}
			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$startdate = strtotime("+1 minute", $today);
			$cTime     = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			if (!$cache) {
				$view_cache = "<br> cache = off / Display: " . $n_Games . " Club: " . $my_club_name;
				// $view_cache =."<br>emails:<br> ".$e_Mail_Actual."<br>".$e_Mail_Result."<br>".$e_Mail_From;
			} else {
				$view_cache = "";
			}



			//      $html_head = "<table class=\"suhv-table suhv-planned-games-full\">\n";
			$html_head = "<div class=\"suhvIncludes twoRight\">\n\t<table class=\"table nobr\">\n";
			//      $html_head .= "<caption>".$data->title."<br>".$wochentag.strftime(" - %H:%M")."  (".$cTime." min.)".$view_cache."</caption>";
			//      $html_head .= "<thead><tr><th class=\"suhv-date\">"."Datum,<br>Zeit".
			//      "</th><th class=\"suhv-place\">".$header_Location.
			//      "</th><th class=\"suhv-opponent\">".$header_Home.
			//      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>";

			$latestDateOfGame = strtotime("1970-01-01");

			$html_body .= "\n<!--Übersicht. entries=".$entries.":-->\n";
			for ($i2 = 0; $i2<$entries; $i2++) {
				$game2_date = $games[$i2]->cells[0]->text[0];
				$game2_time = $games[$i2]->cells[0]->text[1];
				$html_body .= "<!-- ".$i2." game_date: ".$game2_date. " game_time: ".$game2_time."-->\n";
			}
			$html_body .= "\n";

			error_reporting(E_ALL & ~E_NOTICE);
			while ($loop) {
				$i = $entries - 1;
				do {
					$game_id          = $games[$i]->link->ids[0];
					$game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=" . $game_id;
					$game_date        = $games[$i]->cells[0]->text[0];
					$game_time        = $games[$i]->cells[0]->text[1];
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
					$game_homeclub   = $games[$i]->cells[3]->text[0];
					$game_guestclub  = $games[$i]->cells[4]->text[0];
					$game_result     = $games[$i]->cells[5]->text[0];
					$linkGame_ID     = $games[$i]->link->ids[0];
					$new_result      = $game_result;
					$game_result_add = "";
					if (isset($games[$i]->cells[5]->text[1])) {
						$game_result_add = "(" . $games[$i]->cells[5]->text[1] . ")";
					}
					$game_home_result  = substr($game_result, 0, stripos($game_result, ":"));
					$game_guest_result = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));
					$site_url          = get_site_url();
					$site_display      = substr($site_url, stripos($site_url, "://") + 3);

					//Fehlerkorrektur für vom 7.1.2017
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

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $gameLocationLinkTitle . "\">";

					$game_homeDisplay  = $game_homeclub;
					$game_guestDisplay = $game_guestclub;

					$special_league = "Junioren/-innen U14/U17 VM";
					$team_one       = $my_club_name . " I";
					$team_two       = $my_club_name . " II";
					$league_short   = "U14/U17";

					$game_leage = str_replace(" Regional", "", $game_leage);
					$game_leage = str_replace("Junioren U", "U", $game_leage);
					$game_leage = str_replace("Juniorinnen", "", $game_leage);
					$game_leage = str_replace("Herren Aktive", "", $game_leage);
					$game_leage = str_replace("Aktive", "", $game_leage);
					$game_leage = str_replace("Schweizer", "", $game_leage);

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
							$game_result = "❓";
							$resultClass .= ' suhv-wait';
						}
					}
					if ($game_date == "heute") {
						if (($new_result != $last_result) and (substr_count($new_result, "*") != 0) and ($new_result != "") and (substr_count($e_Mail_Actual, "@") >= 1)) {
							// echo "<br>new-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
							$last_games[$i] = $games[$i];
							$message        = $game_location_name . " (" . $game_location . "): <strong>" . $new_result . "</strong> im Spiel " . $game_homeDisplay . " vs. " . $game_guestDisplay . $skip;
							$message .= "Spielbeginn: " . $game_time . " aktuelle Zeit: " . strftime("%H:%M") . $skip;
							// $message .=  $skip."Info: Page: ".$page." Pos: ".$i." - bisher: ".$last_result.$skip; 
							$message .= $skip . "Diese Meldung wurde Dir durch <a href=\"" . $site_url . "\">" . $site_display . "</a> zugestellt." . $skip;
							if ((substr_count($new_result, "0:0") != 0)) {
								$checkmail = wp_mail($e_Mail_Actual, $game_leage . ' Spielstart: ' . $new_result, $message, $mailheaders);
							} else {
								$checkmail = wp_mail($e_Mail_Actual, $game_leage . ' Zwischenresultat: ' . $new_result, $message, $mailheaders);
							}
							//echo "<br>mail ", $checkmail;
						} else {
							//echo "<br>old-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
						}
						if (($new_result != $last_result) and (substr_count($new_result, "*") == 0) and ($new_result != "") and (substr_count($new_result, "-") == 0) and (substr_count($e_Mail_Result, "@") >= 1)) {
							// echo "<br>NEUES-Resultat ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
							$last_games[$i] = $games[$i];
							$message        = $game_location_name . " (" . $game_location . "): <strong>" . $new_result . "</strong> " . $game_result_add . " im Spiel " . $game_homeDisplay . " vs. " . $game_guestDisplay . $skip;
							//$message .= $skip."Info: Page: ".$page." Pos: ".$i." - bisher: ".$last_result.$skip; 
							$message .= $skip . "Diese Meldung wurde Dir durch <a href=\"" . $site_url . "\">" . $site_display . "</a> zugestellt." . $skip;
							$checkmail = wp_mail($e_Mail_Result, $game_leage . ' Schluss-Resultat: ' . $new_result, $message, $mailheaders);
							//echo "<br>mail ", $checkmail;
						}
					}
					if (($items <= $n_Games)) {
						if (($date_of_game <= $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup

							if ($latestDateOfGame != $date_of_game) {
								$latestDateOfGame = $date_of_game;
								$html_body .= "\n<tr class='suhvFatRow'>";
								$html_body .= "\n\t<td colspan='5'>" . $tage[date('w', $date_of_game)] . ", " . str_replace(".20", ".", $game_date) . "</td>";
								//$html_body .= "\n\t<td colspan='5'>".str_replace(".20",".",$game_date)."</td>";
								$html_body .= "\n    </tr>";
							}

							if ($resultHomeClass == 'suhv-guest') {
								$html_body .= "\n<tr><td>" . $game_guestDisplay . "</td><td>&nbsp;</td><td>" . $game_guest_result . ":" . $game_home_result . $game_result_add . "</td><td>&nbsp;</td><td>" . $game_homeDisplay . "</td></tr>";
							} else {
								$html_body .= "\n<tr><td>" . $game_homeDisplay . "</td><td>&nbsp;</td><td>" . $game_result . $game_result_add . "</td><td>&nbsp;</td><td>" . $game_guestDisplay . "</td></tr>";
							}

						}
					} else {
						$loop = FALSE;
					}
					$i--;
					$linkGame_ID_before = $linkGame_ID;
				} while (($i >= 0) and ($loop));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10))
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					$api = new SwissUnihockey_Public();
					$api_calls++;
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
					$entries = count($games);
					// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
				} // end else
			} // end While
			// Report all errors
			$html_body .= "\n<!--Nach dem Loop-->\n";

			error_reporting(E_ALL);
			$html_head .= $html_res;
			//      $html_head .= "</tr></thead><tbody>";
			$html .= $html_head;
			$html .= $html_body;
			$html .= "</tbody>";
			//      $html .= "</table>";
			$html .= "</table></div>";

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
	public static function api_club_getMiniResults($season, $club_ID, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
		$my_club_name       = "Chur";
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

					//Fehlerkorrektur für vom 7.1.2017
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
					$game_leage = str_replace(" Regional", "(".$game_group.")", $game_leage);
					$game_leage = str_replace("Junioren U", "U", $game_leage);
					$game_leage = str_replace("Juniorinnen", "", $game_leage);
					$game_leage = str_replace("Herren Aktive", "", $game_leage);
					$game_leage = str_replace("Aktive", "", $game_leage);
					$game_leage = str_replace("Schweizer", "", $game_leage);
					$game_leage = str_replace("Mobiliar Unihockey Cup", "Cup", $game_leage);

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
							$game_result = "❓";
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
	public static function api_club_getMiniGames($season, $club_ID, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
		$my_club_name       = "Chur";
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

				//Fehlerkorrektur für vom 7.1.2017
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

				$game_group = str_replace("Gruppe ", "Gr.", $game_group);
				$game_leage = str_replace(" Regional", " (".$game_group.")", $game_leage);
				$game_leage = str_replace("Junioren U", "U", $game_leage);
				$game_leage = str_replace("Juniorinnen ", "", $game_leage);
				$game_leage = str_replace("Herren Aktive ", "", $game_leage);
				$game_leage = str_replace("Aktive ", "", $game_leage);
				$game_leage = str_replace("Schweizer ", "", $game_leage);

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
						$game_result = "❓";
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


					//                    $html_body .= "\n<!-- " . strtotime($game_time) . " " . $game_time . " if (" . $mytimestamp . " >= " . $startdate . ")--><!-- " . date("Y-m-d H:i:s", $mytimestamp) . " >= " . date("Y-m-d H:i:s", $startdate) . "-->";
					if (($mytimestamp >= $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup
						//                        $html_body .= "<!--  ok -->";

						//    $html_body .= "\n\t<td colspan='4'>".$tage[date('w', $date_of_game)].", ".str_replace(".20",".",$game_date)."</td>";

						//$html_body .= "\n<tr><td>".date('d.m', $date_of_game)."</td><td>".$game_time."</td><td>".$game_homeDisplay."</td><td>:</td><td>".$game_guestDisplay."</td></tr>";

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
	public static function api_club_getHomeGames($season, $club_ID, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 1;
		$my_club_name       = "Chur";
		$cup                = FALSE;
		$transient          = $club_ID . $team_ID . "club_getGames" . $season . $mode;
		$secure_trans       = $transient . "Secure";
		$semaphore          = $club_ID . $team_ID . "club_getGames-Flag";
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

		if (($value == False) and ($flag == False)) {

			set_transient($semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

			$go             = time();
			$api_calls      = 0;
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = $plugin_options['SUHV_club_games_limit'];
			$e_Mail_From    = $plugin_options['SUHV_mail_send_from'];
			$e_Mail_Actual  = $plugin_options['SUHV_mail_actual_result'];
			$e_Mail_Result  = $plugin_options['SUHV_mail_final_result'];


			// SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

			$mailheaders = 'From: Spielresultate <' . $e_Mail_From . '>' . "\r\n";
			$mailheaders .= "MIME-Version: 1.0\r\n";
			$mailheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
			$skip = "<br />";

			$html         = "";
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

			$data      = $details->data;
			$startpage = $data->context->page;
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
				// echo "<br>Reset Games";
			}
			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$startdate = strtotime("-3 days", $today);
			$cTime     = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			if (!$cache) {
				$view_cache = "<br> cache = off / Display: " . $n_Games . " Club: " . $my_club_name;
				// $view_cache =."<br>emails:<br> ".$e_Mail_Actual."<br>".$e_Mail_Result."<br>".$e_Mail_From;
			} else {
				$view_cache = "";
			}



			//      $html_head = "<table class=\"suhv-table suhv-planned-games-full\">\n";
			$html_head = "<div class=\"suhvIncludes twoRight\">\n\t<table class=\"table nobr\">\n";
			//      $html_head .= "<caption>".$data->title."<br>".$wochentag.strftime(" - %H:%M")."  (".$cTime." min.)".$view_cache."</caption>";
			//      $html_head .= "<thead><tr><th class=\"suhv-date\">"."Datum,<br>Zeit".
			//      "</th><th class=\"suhv-place\">".$header_Location.
			//      "</th><th class=\"suhv-opponent\">".$header_Home.
			//      "</th><th class=\"suhv-opponent\">".$header_Guest."</th>";

			$latestDateOfGame = strtotime("1970-01-01");

			error_reporting(E_ALL & ~E_NOTICE);
			while ($loop) {
				$i = 0;
				do {
					$game_id          = $games[$i]->link->ids[0];
					$game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=" . $game_id;
					$game_date        = $games[$i]->cells[0]->text[0];
					$game_time        = $games[$i]->cells[0]->text[1];
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

					//Fehlerkorrektur für vom 7.1.2017
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

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $gameLocationLinkTitle . "\">";

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

					$game_leage = str_replace("Junioren", "", $game_leage);
					$game_leage = str_replace("Juniorinnen", "", $game_leage);
					$game_leage = str_replace("Herren Aktive", "", $game_leage);
					$game_leage = str_replace("Aktive", "", $game_leage);
					$game_leage = str_replace("Schweizer", "", $game_leage);

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
							$game_result = "❓";
							$resultClass .= ' suhv-wait';
						}
					}
					if ($game_date == "heute") {
						if (($new_result != $last_result) and (substr_count($new_result, "*") != 0) and ($new_result != "") and (substr_count($e_Mail_Actual, "@") >= 1)) {
							// echo "<br>new-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
							$last_games[$i] = $games[$i];
							$message        = $game_location_name . " (" . $game_location . "): <strong>" . $new_result . "</strong> im Spiel " . $game_homeDisplay . " vs. " . $game_guestDisplay . $skip;
							$message .= "Spielbeginn: " . $game_time . " aktuelle Zeit: " . strftime("%H:%M") . $skip;
							// $message .=  $skip."Info: Page: ".$page." Pos: ".$i." - bisher: ".$last_result.$skip; 
							$message .= $skip . "Diese Meldung wurde Dir durch <a href=\"" . $site_url . "\">" . $site_display . "</a> zugestellt." . $skip;
							if ((substr_count($new_result, "0:0") != 0)) {
								$checkmail = wp_mail($e_Mail_Actual, $game_leage . ' Spielstart: ' . $new_result, $message, $mailheaders);
							} else {
								$checkmail = wp_mail($e_Mail_Actual, $game_leage . ' Zwischenresultat: ' . $new_result, $message, $mailheaders);
							}
							//echo "<br>mail ", $checkmail;
						} else {
							//echo "<br>old-Result ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
						}
						if (($new_result != $last_result) and (substr_count($new_result, "*") == 0) and ($new_result != "") and (substr_count($new_result, "-") == 0) and (substr_count($e_Mail_Result, "@") >= 1)) {
							// echo "<br>NEUES-Resultat ".$page.$i.": ".$new_result." - bisher: ".$last_result; 
							$last_games[$i] = $games[$i];
							$message        = $game_location_name . " (" . $game_location . "): <strong>" . $new_result . "</strong> " . $game_result_add . " im Spiel " . $game_homeDisplay . " vs. " . $game_guestDisplay . $skip;
							//$message .= $skip."Info: Page: ".$page." Pos: ".$i." - bisher: ".$last_result.$skip; 
							$message .= $skip . "Diese Meldung wurde Dir durch <a href=\"" . $site_url . "\">" . $site_display . "</a> zugestellt." . $skip;
							$checkmail = wp_mail($e_Mail_Result, $game_leage . ' Schluss-Resultat: ' . $new_result, $message, $mailheaders);
							//echo "<br>mail ", $checkmail;
						}
					}
					if (($items <= $n_Games)) {
						if (($date_of_game > $startdate) and ($linkGame_ID_before != $linkGame_ID)) { //  and $cup

							if ($latestDateOfGame != $date_of_game) {
								$latestDateOfGame = $date_of_game;
								$html_body .= "\n<tr class='suhvFatRow'>";
								$html_body .= "\n\t<td colspan='5'>" . $tage[date('w', $date_of_game)] . ", " . str_replace(".20", ".", $game_date) . "</td>";
								//$html_body .= "\n\t<td colspan='5'>".str_replace(".20",".",$game_date)."</td>";
								$html_body .= "\n    </tr>";
							}

							$html_body .= "\n<tr><td>" . $game_time . "</td><td>" . $game_homeDisplay . "</td><td>:</td><td>" . $game_guestDisplay . "</td><td>" . $game_maplink . $gameLocationLinkTitle . "</a></td></tr>";



							/*              $html_body .= "<tr". ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"".$header_DateTime."\">".str_replace(".20",".",$game_date).", ".$game_time.
								"</td><td class=\"".$homeClass."\">".$game_maplink.$game_location."</a>".
								"</td><td class=\"".$resultHomeClass."\">".$game_homeDisplay.
								"</td><td class=\"".$resultGuestClass."\">".$game_guestDisplay;
								if (($game_result != "")) {
								$html_res = "<th class=\"suhv-result\">".$header_Result."</th>"; 
								$html_body .= "</td><td class=\"".$resultClass."\"><a href=\"".$game_detail_link."\" title=\"Spieldetails\" >"."<strong>".$game_result."</strong><br>". $game_result_add."</a></td>";
								}
								else  $items++;
								$html_body .= "</tr>";
								*/



							// $cup = FALSE; // cup only
						}
					} else {
						$loop = FALSE;
					}
					$i++;
					$linkGame_ID_before = $linkGame_ID;
				} while (($i < $entries) and ($loop));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10))
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					$api = new SwissUnihockey_Public();
					$api_calls++;
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
					$entries = count($games);
					// SwissUnihockey_Api_Public::log_me("club_getGames api-calls:". $api_calls." entries: ".$entries." items: ".$items." page: ".$page." n: ".$n_Games);
				} // end else
			} // end While
			// Report all errors
			error_reporting(E_ALL);
			$html_head .= $html_res;
			//      $html_head .= "</tr></thead><tbody>";
			$html .= $html_head;
			$html .= $html_body;
			$html .= "</tbody>";
			//      $html .= "</table>";
			$html .= "</table></div>";

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
	public static function api_club_getCupGames($season, $club_ID, $team_ID, $mode, $cache) {

		$team_ID            = NULL;
		$trans_Factor       = 10;
		$my_club_name       = "Chur";
		$cup                = FALSE;
		$transient          = $club_ID . $team_ID . "club_getCupGames" . $season . $mode;
		$secure_trans       = $transient . "Secure";
		$semaphore          = $club_ID . $team_ID . "club_getCupGames-Flag";
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

		if (($value == False) and ($flag == False)) {

			set_transient($semaphore, TRUE, 5); // Keep out for 10 seconds - no Mail

			$go             = time();
			$api_calls      = 0;
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = $plugin_options['SUHV_club_games_limit'];
			$e_Mail_From    = $plugin_options['SUHV_mail_send_from'];
			$e_Mail_Actual  = $plugin_options['SUHV_mail_actual_result'];
			$e_Mail_Result  = $plugin_options['SUHV_mail_final_result'];


			// SwissUnihockey_Api_Public::log_me(array('function' => 'club_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode));

			$mailheaders = 'From: Spielresultate <' . $e_Mail_From . '>' . "\r\n";
			$mailheaders .= "MIME-Version: 1.0\r\n";
			$mailheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
			$skip = "<br />";

			$html         = "";
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

			$data      = $details->data;
			$startpage = $data->context->page;
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

			$data            = $details->data;
			$header_DateTime = $data->headers[0]->text;
			$header_Location = $data->headers[1]->text;
			$header_Leage    = $data->headers[2]->text;
			$header_Home     = $data->headers[3]->text;
			$header_Guest    = $data->headers[4]->text;
			$header_Result   = $data->headers[5]->text;
			$header_Result   = "Res.";
			$club_name       = $data->title;
			$games           = $data->regions[0]->rows;
			$attributes      = $data->regions[0]->rows[0]->cells;

			$entries = count($games);

			$transient_games = $transient . $tag;
			$last_games      = get_transient($transient_games);
			if ($last_games == FALSE) {
				$last_games = $games;
				set_transient($transient_games, $last_games, 2 * 60 * 60);
				// echo "<br>Reset Games";
			}
			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$startdate = strtotime("-3 days", $today);
			$cTime     = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			if (!$cache) {
				$view_cache = "<br> cache = off / Display: " . $n_Games;
				// $view_cache =."<br>emails:<br> ".$e_Mail_Actual."<br>".$e_Mail_Result."<br>".$e_Mail_From;
			} else {
				$view_cache = "";
			}

			$html_head = "<table class=\"suhv-table suhv-planned-games-full\">\n";
			//$html_head .= "<caption>".$data->title."<br>".$wochentag.strftime(" - %H:%M")."  (".$cTime." min.)".$view_cache."</caption>";
			$html_head .= "<thead><tr><th class=\"suhv-date\">" . "Datum,<br>Zeit" . "</th><th class=\"suhv-place\">" . $header_Location . "</th><th class=\"suhv-opponent\">" . $header_Home . "</th><th class=\"suhv-opponent\">" . $header_Guest . "</th>";

			error_reporting(E_ALL & ~E_NOTICE);
			while ($loop) {
				$i = 0;
				do {
					$game_id          = $games[$i]->link->ids[0];
					$game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=" . $game_id;
					$game_date        = $games[$i]->cells[0]->text[0];
					$game_time        = $games[$i]->cells[0]->text[1];
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

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";

					$game_homeDisplay  = $game_homeclub;
					$game_guestDisplay = $game_guestclub;

					// If Cup?
					if (substr_count($game_leage, "Cup") >= 1) {
						$cup = TRUE;
					}

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

					$game_leage = str_replace("Junioren", "", $game_leage);
					$game_leage = str_replace("Juniorinnen", "", $game_leage);
					$game_leage = str_replace("Herren Aktive", "", $game_leage);
					$game_leage = str_replace("Aktive", "", $game_leage);
					$game_leage = str_replace("Schweizer", "", $game_leage);
					//$game_leage = str_replace ("Damen", "",$game_leage);
					//$game_leage = str_replace ("Herren", "",$game_leage);
					$homeClass  = "suhv-place";
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
							$game_result = "❓";
							$resultClass .= ' suhv-wait';
						}
					}
					/* no email */
					if (($items <= $n_Games)) {
						if (($date_of_game > $startdate) and ($linkGame_ID_before != $linkGame_ID) and $cup) {
							$html_body .= "<tr" . ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"" . $header_DateTime . "\">" . str_replace(".20", ".", $game_date) . ", " . $game_time . "</td><td class=\"" . $homeClass . "\">" . $game_maplink . $game_location . "</a>" . "</td><td class=\"" . $resultHomeClass . "\">" . $game_homeDisplay . "</td><td class=\"" . $resultGuestClass . "\">" . $game_guestDisplay;
							if (($game_result != "")) {
								$html_res = "<th class=\"suhv-result\">" . $header_Result . "</th>";
								$html_body .= "</td><td class=\"" . $resultClass . "\"><a href=\"" . $game_detail_link . "\" title=\"Spieldetails\" >" . "<strong>" . $game_result . "</strong><br>" . $game_result_add . "</a></td>";
							} else
								$items++;
							$html_body .= "</tr>";
						}
					} else {
						$loop = FALSE;
					}
					$i++;
					$cup                = FALSE;
					$linkGame_ID_before = $linkGame_ID;
				} while (($i < $entries) and ($loop));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10))
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					$api = new SwissUnihockey_Public();
					$api_calls++;
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
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
	public static function api_club_getWeekendGames($season, $club_ID, $team_ID, $mode, $start_date, $end_date, $cache) {

		date_default_timezone_set("Europe/Paris");
		$my_club_name       = "Chur";
		$linkGame_ID        = NULL;
		$linkGame_ID_before = NULL;

		$my_club_name = $plugin_options['SUHV_default_club_shortname'];

		$date_parts       = explode(".", $start_date); // dd.mm.yyyy in german
		$start_date_us    = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
		//echo "<br>start:", date("d.m.Y", $start_date_us) . "<br>";
		$date_parts       = explode(".", $end_date); // dd.mm.yyyy in german
		$end_date_us      = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
		//echo "<br>end:", date("d.m.Y", $end_date_us) . "<br>";
		$weekend          = FALSE;
		$date_description = "Spiele vom " . $start_date;
		if (strpos($start_date, "2015") > 0) {
			$weekend          = TRUE;
			$weekendDays      = SwissUnihockey_Api_Public::nearWeekend();
			$start_date_us    = $weekendDays["Samstag"];
			$end_date_us      = $weekendDays["Sonntag"];
			$date_description = "Spiele vom Wochenende";
			$start_date       = date("d.m.Y", $start_date_us);
			$end_date         = date("d.m.Y", $end_date_us);
		}

		$team_ID      = NULL;
		$trans_Factor = 2;
		$transient    = $club_ID . $team_ID . "club_getWeekendGames" . $season . $mode . $start_date . $end_date . "test";
		$value        = get_transient($transient);

		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'club_getWeekendGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' => $mode, 'startdate' => $start_date, 'enddate' => $end_date));

			$html      = "";
			$html_res  = "";
			$html_body = "";

			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];
			$n_Games        = 40;

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
			$tag       = date("w", $start_date_us);
			$start_tag = $tage[$tag];
			$tag       = date("w", $end_date_us);
			$end_tag   = $tage[$tag];

			$api     = new SwissUnihockey_Public();
			$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array());

			// Eine Seite retour bei Page-Ende? 

			$data      = $details->data;
			$startpage = $data->context->page;

			if ($startpage != 1) { // eine Page retour wenn nicht erste
				$page    = $startpage - 1;
				$api     = new SwissUnihockey_Public();
				$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
					'page' => $page
				));
			}

			$data            = $details->data;
			$header_DateTime = $data->headers[0]->text;
			$header_Location = $data->headers[1]->text;
			$header_Leage    = $data->headers[2]->text;
			$header_Home     = $data->headers[3]->text;
			$header_Guest    = $data->headers[4]->text;
			$header_Result   = $data->headers[5]->text;
			//$header_Result = "Res.";
			$club_name       = $data->title;
			$games           = $data->regions[0]->rows;
			$attributes      = $data->regions[0]->rows[0]->cells;

			$entries = count($games);

			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			$items     = 0;
			$today     = strtotime("now");
			$startdate = strtotime("-1 days", $start_date_us);
			$cTime     = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;
			$homeClass = "suhv-place";
			$html_head = "<table class=\"suhv-table suhv-planned-games-full\">\n";
			if ($weekend)
				$html_head .= "<caption>" . $date_description . " " . $start_tag . " " . $start_date . " bis " . $end_tag . " " . $end_date . "</caption>";
			else
				$html_head .= "<caption>" . "Spiele vom " . $start_tag . " " . $start_date . " bis " . $end_tag . " " . $end_date . "</caption>";
			$html_head .= "<thead><tr><th class=\"suhv-date\">" . "Datum,<br>Zeit" . "</th><th class=\"suhv-place\">" . $header_Location . "</th><th class=\"suhv-opponent\">" . $header_Home . "</th><th class=\"suhv-opponent\">" . $header_Guest . "</th>";

			error_reporting(E_ALL & ~E_NOTICE);
			while ($loop) {
				$i = 0;
				do {
					$game_id          = $games[$i]->link->ids[0];
					$game_detail_link = "https://www.swissunihockey.ch/de/game-detail?game_id=" . $game_id;
					$game_date        = $games[$i]->cells[0]->text[0];
					$game_time        = $games[$i]->cells[0]->text[1];
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

					//Fehlerkorrektur für vom 7.1.2017
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

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";

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

					$game_leage = str_replace("Junioren", "", $game_leage);
					$game_leage = str_replace("Juniorinnen", "", $game_leage);
					$game_leage = str_replace("Herren Aktive", "", $game_leage);
					$game_leage = str_replace("Aktive", "", $game_leage);
					$game_leage = str_replace("Schweizer", "", $game_leage);

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
							$game_result = "❓";
							$resultClass .= ' suhv-wait';
						}
					}
					/* no email*/

					if (($items <= $n_Games) and ($date_of_game <= $end_date_us)) {
						if (($date_of_game > $startdate) and ($date_of_game <= $end_date_us) and ($linkGame_ID_before != $linkGame_ID)) { //   and $cup
							$html_body .= "<tr" . ($i % 2 == 1 ? ' class="alt"' : '') . "><td class=\"" . $header_DateTime . "\">" . str_replace(".20", ".", $game_date) . ", " . $game_time . "</td><td class=\"" . $homeClass . "\">" . $game_maplink . $game_location . "</a>" . "</td><td class=\"" . $resultHomeClass . "\">" . $game_homeDisplay . "</td><td class=\"" . $resultGuestClass . "\">" . $game_guestDisplay;
							if (($game_result != "")) {
								$html_res = "<th class=\"suhv-result\">" . $header_Result . "</th>";
								$html_body .= "</td><td class=\"" . $resultClass . "\"><a href=\"" . $game_detail_link . "\" title=\"Spieldetails\" >" . "<strong>" . $game_result . "</strong><br>" . $game_result_add . "</a></td>";
							} else
								$items++;
							$html_body .= "</tr>";
							// $cup = FALSE; // cup only
						}
					} else {
						$loop = FALSE;
					}
					$i++;
					$linkGame_ID_before = $linkGame_ID;
				} while (($i < $entries) and ($loop));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10))
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					$api = new SwissUnihockey_Public();
					$api_calls++;
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
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

			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
			//output some debug string
			// error_log( 'api_club_getGames:');
			//error_log( print_r(strftime("%A - %H:%M")) );
		} //end If
		else {
			$html = $value;
		}
		return $html;
	}
	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_team_getPlayedGames($season, $club_ID, $team_ID, $mode, $cache) {

		$transient    = $club_ID . $team_ID . "getPlayedGames" . $season . $mode;
		$value        = get_transient($transient);
		$trans_Factor = 1;
		$my_club_name = "Chur";

		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'team_getPlayedGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

			$html      = "";
			$html_res  = "";
			$html_body = "";

			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];

			// echo "<br>".$season."<br>".$clubId;

			$api     = new SwissUnihockey_Public();
			$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
				'page' => 1
			));

			if(isset($details->data)) {
				$data = $details->data;

				$header_DateTime = $data->headers[0]->text;
				$header_Location = $data->headers[1]->text;
				$header_Home     = $data->headers[2]->text;
				$header_Guest    = $data->headers[3]->text;
				$header_Guest    = "Gegner";
				$header_Result   = $data->headers[4]->text;
				$header_Result   = "Resultat";
				$club_name       = $data->title;
				$games           = $data->regions[0]->rows;
				$attributes      = $data->regions[0]->rows[0]->cells;
			}


			$homeClass = "suhv-place";
			$entries   = count($games);

			$html_head = "<table>\n<tr><th class=\"suhv-date\">Datum</th><th class=\"suhv-result\">Resultat</th><th class=\"suhv-opponent\">Gegner</th></tr>\n";




			$loop = FALSE;
			$tabs = $data->context->tabs;
			if ($tabs = "on")
				$loop = TRUE;
			$startpage = $data->context->page;
			$page      = $startpage;

			error_reporting(E_ALL & ~E_NOTICE);

			$i = count($games) - 1;
			$html_head .= "<!-- " . $i . " games-->";
			$html_head .= "<!-- tabs: " . $tabs . " -->";
			$html_head .= "<!-- loop: " . $loop . " -->";
			while ($loop) {
				do {
					$html_head .= "<!-- i " . $i . " -->";
					$game_id            = $games[$i]->link->ids[0];
					$game_detail_link   = "https://www.swissunihockey.ch/de/game-detail?game_id=" . $game_id;
					$game_date          = $games[$i]->cells[0]->text[0];
					$game_time          = $games[$i]->cells[0]->text[1];
					$game_location_name = $games[$i]->cells[1]->text[0];
					$game_location      = $games[$i]->cells[1]->text[1];
					$game_map_x         = $games[$i]->cells[1]->link->x;
					$game_map_y         = $games[$i]->cells[1]->link->y;
					$game_homeclub      = $games[$i]->cells[2]->text[0];
					$game_guestclub     = $games[$i]->cells[3]->text[0];
					$game_result        = $games[$i]->cells[4]->text[0];
					$html_head .= "<!-- game_id " . $game_id . " -->";
					$html_head .= "<!-- game_homeclub " . $game_homeclub . " -->";
					$html_head .= "<!-- game_result " . $game_result . " -->";
					$game_result_add   = $games[$i]->cells[4]->text[1];
					$game_home_result  = substr($game_result, 0, stripos($game_result, ":"));
					$game_guest_result = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";

					$game_homeDisplay = $game_homeclub;

					$game_guestDisplay = $game_guestclub;
					$game_leage        = str_replace("Junioren", "", $game_leage);
					//$game_leage = str_replace ("Herren", "",$game_leage);

					if ($game_home_result == $game_guest_result) {
						$resultClass = 'suhv-draw';
					} else {
						$resultClass = 'suhv-result';
					}
					if ((substr_count($game_homeDisplay, $my_club_name) >= 1) and (substr_count($club_name, $game_homeclub) >= 1)) {
						$game_Opponent   = $game_guestDisplay;
						$resultToDisplay = $game_home_result . ":" . $game_guest_result . $game_result_add;
						if ($game_home_result > $game_guest_result) {
							$resultClass = 'suhv-win';
						} else {
							$resultClass = 'suhv-lose';
						}
					}
					if ((substr_count($game_guestDisplay, $my_club_name) >= 1) and (substr_count($club_name, $game_guestclub) >= 1)) {
						$game_Opponent   = $game_homeDisplay;
						$resultToDisplay = $game_guest_result . ":" . $game_home_result . $game_result_add;
						if ($game_guest_result > $game_home_result) {
							$resultClass = 'suhv-win';
						} else {
							$resultClass = 'suhv-lose';
						}
					}

					if ($game_result == "") {
						$resultClass = 'suhv-result';
						$items++;
					}
					if (($game_date == "heute") and ((substr_count($game_result, "*") != 0) or (substr_count($game_result, "-") != 0))) {
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
						$html_body .= "<!-- set loop to false :( game_result: '" . $game_result . "' game_date: '" . $game_date . "' -->";
						//              $loop = FALSE;
					}
					$i--;

					$html_head .= "<!-- end of loop: " . $i . " - " . $loop . " -->";

				} while (($i >= 0) and ($loop));

				if ($data->slider->next == NULL) {
					$loop = FALSE;
				} // only this loop
				else {
					$page++;
					if ($page >= ($startpage + 10))
						$loop = FALSE; // Don't Loop always. // max 10 Pages
					$api     = new SwissUnihockey_Public();
					$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array(
						'page' => $page
					));
					$data    = $details->data;
					$games   = $data->regions[0]->rows;
					$entries = count($games);
				} // end else
			} // end While
			// Report all errors
			error_reporting(E_ALL);
			//      $html_head .= $html_res;
			//      $html_head .= "</tr></thead><tbody>";
			$html .= $html_head . "\n\n";
			$html .= $html_body;
			//      $html .= "</tbody>";
			$html .= "</table>";

			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		return $html;

	}
	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_team_getGames($season, $club_ID, $team_ID, $mode, $cache) {

		$transient    = $club_ID . $team_ID . "team_getGames" . $season . $mode;
		$value        = get_transient($transient);
		$trans_Factor = 5;
		$my_club_name = "Chur";

		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'team_getGames', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

			$html           = "";
			$plugin_options = get_option('SUHV_WP_plugin_options');
			$my_club_name   = $plugin_options['SUHV_default_club_shortname'];

			// echo "<br>".$season."<br>".$clubId;
			$page = 1;

			$api = new SwissUnihockey_Public();

			$details = $api->clubGames($season, $club_ID, $team_ID, $mode, array());
			$data    = $details->data;

			SwissUnihockey_Api_Public::log_me(array(
				'function' => 'league_team_getGames',
				'season' => $season,
				'club_ID' => $club_ID,
				'team_ID' => $team_ID,
				'mode' => $mode
			));


			$page            = $data->context->page;
			$header_DateTime = $data->headers[0]->text;
			$header_Location = $data->headers[1]->text;
			$header_Home     = $data->headers[2]->text;
			$header_Guest    = $data->headers[3]->text;
			$header_Guest    = "Gegner";
			$header_Result   = $data->headers[4]->text;
			$club_name       = $data->title;
			$games           = $data->regions[0]->rows;
			$attributes      = $data->regions[0]->rows[0]->cells;


			$html .= "<table class=\"suhv-table \">\n";
			//      $html .= "<caption>".$data->title."</caption>";
			$html .= "<thead><tr><th class=\"suhv-date\">" . "Datum";
			$html .= "</th><th class=\"suhv-time\">Zeit";
			$html .= "</th><th class=\"suhv-opponent\">" . $header_Guest;
			$html .= "</th><th class=\"suhv-location\">" . $header_Location;
			//$game_result = $games[0]->cells[4]->text[0];
			//if ($game_result != "")  
			//   $html .= "</th><th class=\"suhv-result\">".$header_Result."</th></tr></thead>";
			$html .= "</th></tr></thead>";
			$html .= "<tbody>";

			error_reporting(E_ALL & ~E_NOTICE);
			$i       = 0;
			$entries = count($games);

			do {

				$game_date          = substr($games[$i]->cells[0]->text[0], 0, 6) . substr($games[$i]->cells[0]->text[0], 8, 2);
				$game_time          = $games[$i]->cells[0]->text[1];
				$game_location_name = $games[$i]->cells[1]->text[0];
				$game_location      = $games[$i]->cells[1]->text[1];
				$game_map_x         = $games[$i]->cells[1]->link->x;
				$game_map_y         = $games[$i]->cells[1]->link->y;
				$game_homeclub      = $games[$i]->cells[2]->text[0];
				$game_guestclub     = $games[$i]->cells[3]->text[0];
				$game_result        = $games[$i]->cells[4]->text[0];
				$game_result_add    = $games[$i]->cells[4]->text[1];
				$game_home_result   = substr($game_result, 0, stripos($game_result, ":"));
				$game_guest_result  = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));

				$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";

				$game_homeDisplay  = $game_homeclub;
				$game_guestDisplay = $game_guestclub;
				$game_Diplay       = "";
				$homeClass         = "suhv-place";
				//$game_leage = str_replace ("Herren", "",$game_leage);

				if ($game_home_result == $game_guest_result) {
					$resultClass = 'suhv-draw';
				} else {
					$resultClass = 'suhv-result';
				}
				if (substr_count($game_homeDisplay, $my_club_name) >= 1) {
					$game_Display = $game_guestDisplay;
					if ($game_home_result > $game_guest_result) {
						$resultClass = 'suhv-win';
					} else {
						$resultClass = 'suhv-lose';
					}
				}
				if (substr_count($game_guestDisplay, $my_club_name) >= 1) {
					$game_Display = $game_homeclub;
					if ($game_guest_result > $game_home_result) {
						$resultClass = 'suhv-win';
					} else {
						$resultClass = 'suhv-lose';
					}
				}
				if ($game_result == "") {
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

			// Report all errors
			error_reporting(E_ALL);
			$html .= "</tbody>";
			$html .= "</table>";
			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		return $html;

	}
	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_getTeamTable($season, $club_ID, $team_ID, $mode, $cache) {

		$transient    = $club_ID . $team_ID . "getTeamTable" . $season;
		$value        = get_transient($transient);
		$trans_Factor = 5;
		// Sample: https://api-v2.swissunihockey.ch/api/rankings?&team_id=427913&club_id=423403&season=2015


		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'getTeamTable', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

			$html = "";

			// echo "<br>".$season."-".$club_ID."-".$team_ID;

			$api          = new SwissUnihockey_Public();
			$version_info = $api->version();

			$details = $api->rankings($season, $club_ID, $team_ID, $mode, array());

			$data = $details->data;

			error_reporting(E_ALL & ~E_NOTICE);
			$headerCount = count($data->headers);



			//echo "<br> HEADER-Count".$headerCount;
			$smallTable  = FALSE;
			if ($headerCount < 13)
				$smallTable = TRUE;

			$header_Rank = $data->headers[0]->text;
			$header_Team = $data->headers[2]->text;
			$header_Sp   = $data->headers[3]->text;
			/*            $header_gSp    = $data->headers[4]->text;
				$header_SoW = $data->headers[5]->text; */
			$header_S  = $data->headers[5]->text;
			if ($smallTable) {
				$header_U  = $data->headers[6]->text;
				$header_N  = $data->headers[7]->text;
				$header_T  = $data->headers[8]->text;
				$header_T  = "Tore";
				$header_TD = $data->headers[9]->text;
				$header_PQ  = $data->headers[10]->text;
				$header_P  = $data->headers[11]->text;
			} else {
				$header_SnV = $data->headers[6]->text;
				$header_NnV = $data->headers[7]->text;
				$header_N   = $data->headers[8]->text;
				$header_T   = $data->headers[9]->text;
				$header_T   = "Tore";
				$header_TD  = $data->headers[10]->text;
				$header_PQ   = $data->headers[11]->text;
				$header_P   = $data->headers[12]->text;
			}
			$Table_title = $data->title;

			if (false && !$cache) {
				$view_cache = "<br> cache = off / Team-ID: " . $team_ID;
			} else {
				$view_cache = "";
			}
			$html .= "<table class=\"suhv-table\">";
			//$html .= "<caption>".$data->title.$view_cache."</caption>";
			/*			<th class=\"suhv-gamesplayed\"><abbr title=\"gespielte Spiele\">" . $header_gSp . "</abbr>" . "</th>
				<th class=\"suhv-gameswithout\"><abbr title=\"Spiele ohne Wertung\">" . $header_SoW . "</abbr>" . "</th> */

			$html .= "<thead>" . "<tr><th class=\"suhv-rank\"><abbr title=\"Rang\">" . $header_Rank . "</abbr>" . "</th>
				<th class=\"suhv-team\"><abbr title=\"Team\">" . $header_Team . "</abbr>" . "</th>
				<th class=\"suhv-games\"><abbr title=\"Spiele\">" . $header_Sp . "</abbr>" . "</th>
				<th class=\"suhv-wins\"><abbr title=\"Siege\">" . $header_S . "</abbr>";
			if ($smallTable) {
				$html .= "</th>
					<th class=\"suhv-even\"><abbr title=\"Spiele unentschieden\">" . $header_U . "</abbr>" . "</th>
					<th class=\"suhv-lost\"><abbr title=\"Niederlagen\">" . $header_N . "</abbr>" . "</th>
					<th class=\"suhv-scored\"><abbr title=\"Torverhältnis\">" . $header_T . "</abbr>" . "</th>
					<th class=\"suhv-diff\"><abbr title=\"Tordifferenz\">" . $header_TD . "</abbr>" . "</th>
					<th class=\"suhv-points\"><abbr title=\"Punktquotient\">" . $header_PQ . "</abbr>" . "</th>
					<th class=\"suhv-points\"><abbr title=\"Punkte\">" . $header_P . "</abbr>";
			} else {
				$html .= "</th>
					<th class=\"suhv-ties\"><abbr title=\"Siege nach Verlängerung\">" . $header_SnV . "</abbr>" . "</th>
					<th class=\"suhv-defeats\"><abbr title=\"Niederlagen nach Verlängerung\">" . $header_NnV . "</abbr>" . "</th>
					<th class=\"suhv-lost\"><abbr title=\"Niederlagen\">" . $header_N . "</abbr>" . "</th>
					<th class=\"suhv-scored\"><abbr title=\"Torverhältnis\">" . $header_T . "</abbr>" . "</th>
					<th class=\"suhv-diff\"><abbr title=\"Tordifferenz\">" . $header_TD . "</abbr>" . "</th>
					<th class=\"suhv-points\"><abbr title=\"Punktquotient\">" . $header_PQ . "</abbr>" . "</th>
					<th class=\"suhv-points\"><abbr title=\"Punkte\">" . $header_P . "</abbr>";
			}
			$html .= "</th></tr></thead>";
			$html .= "<tbody>";


			$nrRegions = count($data->regions);

			for ($r = 0; $r < $nrRegions; $r++) {
				$region   = $data->regions[$r];
				$rankings = $region->rows;
				if ($nrRegions > 1) {
					$html .= "\n<tr class=\"suhv-table-region" . $tr_class . "\">" . "<td colspan=\"9\">" . $region->text . "</td></tr>";
				}

				$entries = count($rankings);
				$i = 0;

				do {
					$ranking_TeamID   = $rankings[$i]->data->team->id;
					$ranking_TeamName = $rankings[$i]->data->team->name;

					$ranking_Rank = $rankings[$i]->cells[0]->text[0];
					$ranking_Team = $rankings[$i]->cells[2]->text[0];
					$ranking_Sp   = $rankings[$i]->cells[3]->text[0];
					$ranking_SoW  = $rankings[$i]->cells[4]->text[0];
					$ranking_S    = $rankings[$i]->cells[5]->text[0];
					if ($smallTable) {
						$ranking_U  = $rankings[$i]->cells[6]->text[0];
						$ranking_N  = $rankings[$i]->cells[7]->text[0];
						$ranking_T  = $rankings[$i]->cells[8]->text[0];
						$ranking_TD = $rankings[$i]->cells[9]->text[0];
						$ranking_PQ = $rankings[$i]->cells[10]->text[0];
						$ranking_P = $rankings[$i]->cells[11]->text[0];
					} else {
						$ranking_SnV = $rankings[$i]->cells[6]->text[0];
						$ranking_NnV = $rankings[$i]->cells[7]->text[0];
						$ranking_N   = $rankings[$i]->cells[8]->text[0];
						$ranking_T   = $rankings[$i]->cells[9]->text[0];
						$ranking_TD  = $rankings[$i]->cells[10]->text[0];
						$ranking_PQ  = $rankings[$i]->cells[11]->text[0];
						$ranking_P  = $rankings[$i]->cells[12]->text[0];
					}
					if ($team_ID == $ranking_TeamID) {
						$tr_class = 'suhv-my-team';
					} else {
						$tr_class = '';
					}

					// 					<td class=\"suhv-games\">" . $ranking_gSp . "</td>
					// 					<td class=\"suhv-games\">" . $ranking_SoW . "</td>

					$html .= "\n<tr class=\"" . $tr_class . ($i % 2 == 1 ? ' alt' : '') . "\">" . "
						<td class=\"suhv-rank\">" . $ranking_Rank . "</td>
						<td class=\"suhv-team\">" . $ranking_Team . "</td>
						<td class=\"suhv-games\">" . $ranking_Sp . "</td>
						<td class=\"suhv-wins\">" . $ranking_S;
					if ($smallTable) {
						$html .= "</td>
							<td class=\"suhv-ties\">" . $ranking_U . "</td>
							<td class=\"suhv-lost\">" . $ranking_N . "</td>
							<td class=\"suhv-scored\">" . $ranking_T . "</td>
							<td class=\"suhv-diff\">" . $ranking_TD . "</td>
							<td class=\"suhv-pointQuotient\">" . $ranking_PQ . "</td>
							<td class=\"suhv-points\">" . $ranking_P;
					} else {
						$html .= "</td>
							<td class=\"suhv-ties\">" . $ranking_SnV . "</td>
							<td class=\"suhv-defeats\">" . $ranking_NnV . "</td>
							<td class=\"suhv-lost\">" . $ranking_N . "</td>
							<td class=\"suhv-scored\">" . $ranking_T . "</td>
							<td class=\"suhv-diff\">" . $ranking_TD . "</td>
							<td class=\"suhv-pointQuotient\">" . $ranking_PQ . "</td>
							<td class=\"suhv-points\">" . $ranking_P;
					}
					$html .= "</td></tr>";
					$i++;
				} while ($i < $entries);
			} // end for nrRegions
			// Report all errors
			error_reporting(E_ALL);

			$html .= "</tbody>";
			$html .= "</table>";

			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		return $html;


	}

	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_getTeamRank($season, $club_ID, $team_ID, $mode, $cache) {

		$transient    = $club_ID . $team_ID . "getTeamRank" . $season;
		$value        = get_transient($transient);
		$trans_Factor = 3;

		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'getTeamRank', 'season' => $season, 'club_ID' =>  $club_ID, 'team_ID' =>   $team_ID, 'mode' =>$mode));

			$html = "";

			// echo "<br>".$season."-".$club_ID."-".$team_ID;

			$api          = new SwissUnihockey_Public();
			$version_info = $api->version();

			$details = $api->rankings($season, $club_ID, $team_ID, $mode, array());

			$data = $details->data;

			$header_Rank = $data->headers[0]->text;
			$header_Rank = "Rang";
			$header_Team = $data->headers[2]->text;
			$header_P    = $data->headers[9]->text; // Points
			$header_P    = "Punkte";
			$Table_title = $data->title;
			//* $rankings = $data->regions[0]->rows;
			if (isset($data->regions[0]->rows)) {
				$rankings = $data->regions[0]->rows;
				$entries  = count($rankings);
			} else
				$entries = 0;

			$headerCount = count($data->headers);


			if (!$cache) {
				$view_cache = "<br> cache = off / Team-ID: " . $team_ID;
			} else {
				$view_cache = "";
			}


			$html .= "<table class=\"suhv-table\">";
			$html .= "<caption>" . $data->title . $view_cache . "</caption>";
			$html .= "<thead>" . "<tr><th class=\"suhv-rank\">" . $header_Rank . "</th><th class=\"suhv-team\">" . $header_Team . "</th><th class=\"suhv-points\">" . $header_P . "</th></tr></thead>";
			$html .= "<tbody>";

			error_reporting(E_ALL & ~E_NOTICE);

			$smallTable = FALSE;
			if ($headerCount < 10)
				$smallTable = TRUE;

			$i = 0;

			do {
				$ranking_TeamID   = $rankings[$i]->data->team->id;
				$ranking_TeamName = $rankings[$i]->data->team->name;

				$ranking_Rank = $rankings[$i]->cells[0]->text[0];
				$ranking_Team = $rankings[$i]->cells[2]->text[0];
				if ($smallTable) {
					$ranking_P = $rankings[$i]->cells[9]->text[0]; //Points
				} else {
					$ranking_P = $rankings[$i]->cells[10]->text[0]; // Points
				}
				if ($team_ID == $ranking_TeamID) {
					$tr_class = 'suhv-my-team';
				} else {
					$tr_class = '';
				}

				$html .= "<tr class=\"" . $tr_class . ($i % 2 == 1 ? ' alt' : '') . "\">" . "<td class=\"suhv-rank\">" . $ranking_Rank . "</td><td class=\"suhv-team\">" . $ranking_Team . "</td><td class=\"suhv-points\">" . $ranking_P . "</td></tr>";
				$i++;
			} while ($i < $entries);
			// Report all errors
			error_reporting(E_ALL);

			$html .= "</tbody>";
			$html .= "</table>";
			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		return $html;


	}

	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_league_getGames($season, $league, $game_class, $group, $round, $mode, $cache) {


		$transient = $league . $game_class . $group . "league_getGames" . $season . $mode;

		$value = get_transient($transient);
		// $value = FALSE;

		$maxloop        = 20;
		$loopcnt        = 1;
		$trans_Factor   = 12;
		$ActivRound     = "";
		$lastActivRound = "";

		if ($league == 21) {
			$small = TRUE;
		} else {
			$small = FALSE;
		}

		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'league_getGames', 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'group' => $group, 'round' => $round, 'mode' => $mode));

			$html = "";

			$api = new SwissUnihockey_Public();

			$details = $api->games(array(
				'season' => $season,
				'league' => $league,
				'game_class' => $game_class,
				'round' => $round,
				'mode' => $mode,
				'group' => $group
			));
			$data    = $details->data;


			$loop      = FALSE;
			$nextround = $data->slider->next->set_in_context->round;
			//  echo "<br>Nextround:".$nextround."<br>";

			if ($data->slider->next <> NULL)
				$loop = TRUE;

			$round              = $data->context->round;
			$header_DateTime    = $data->headers[0]->text;
			$header_Location    = $data->headers[1]->text;
			$header_Home        = $data->headers[2]->text;
			$header_Home        = "Heimteam";
			$header_Guest       = $data->headers[6]->text;
			$header_Guest       = "Gastteam";
			$header_Result      = $data->headers[7]->text;
			$round_Title        = $data->slider->text;
			$games              = $data->regions[0]->rows;
			$game_location_name = $games[0]->cells[1]->text[0];

			$game_map_x = $games[0]->cells[1]->link->x;
			$game_map_y = $games[0]->cells[1]->link->y;


			$cTime = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title=\"" . $game_location_name . "\">";

			$ActivRound = trim(substr($round_Title, 0, strpos($round_Title, '/')));
			$ActivRound = str_replace(' ', '_', $ActivRound);
			$html .= '<div class="suhv-round-link"><a id="ActivRound" href="#LastGame">' . __('Zur aktuellen Runde', 'SUHV-API-2') . '</a></div>';
			$html .= '<div class="suhv-round-anchor"><a id="' . $ActivRound . '"></a></div>';
			if ($small) {
				$html .= "<h3>" . $round_Title . "&nbsp;&nbsp;" . $game_maplink . $game_location_name . "</a></h3>";
			} else {
				$html .= "<h3>" . $round_Title . "</h3>";
			}
			// $html .= "<h3>".$round_Title."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>";

			$html .= "<table class=\"suhv-table suhv-league\">\n";
			// $html .= "<caption>timestamp: ".strftime("%H:%M")." - caching: ".$cTime." min.</caption>";
			// $html .= "<caption><h3>".$round_Title."  (".$game_maplink.$game_location_name."</a>)</h3></caption>";
			$html .= "<thead><tr><th class=\"suhv-league suhv-date\">" . "Datum, Zeit";
			if (!$small) {
				$html .= "</th><th class=\"suhv-league suhv-place\">" . $header_Location;
			}
			$html .= "</th><th class=\"suhv-league suhv-opponent\">" . $header_Home;
			$html .= "</th><th class=\"suhv-league suhv-opponent\">" . $header_Guest;
			$html .= "</th><th class=\"suhv-league suhv-result\">" . $header_Result . "</th></tr></thead>";
			$html .= "<tbody>";

			error_reporting(E_ALL & ~E_NOTICE);
			$i       = 0;
			$entries = count($games);

			while ($loop) {

				do {

					$game_date_time     = $games[$i]->cells[0]->text[0];
					$game_location_name = $games[$i]->cells[1]->text[0];
					$game_map_x         = $games[$i]->cells[1]->link->x;
					$game_map_y         = $games[$i]->cells[1]->link->y;
					$game_homeclub      = $games[$i]->cells[2]->text[0];
					$game_guestclub     = $games[$i]->cells[6]->text[0];
					$game_result        = $games[$i]->cells[7]->text[0];
					if ($game_result == "")
						$game_result = "N/A";

					$game_home_result  = substr($game_result, 0, stripos($game_result, ":"));
					$game_guest_result = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";

					$game_homeDisplay  = $game_homeclub;
					$game_guestDisplay = $game_guestclub;
					$game_Diplay       = "";

					$html .= "<tr" . ($i % 2 == 1 ? ' class="alt odd"' : 'class="even"') . "><td class=\"suhv-league suhv-date\">" . $game_date_time;
					if (!$small) {
						$html .= "</td><td class=\"suhv-league suhv-place\">" . $game_maplink . $game_location_name . "</a>";
					}
					$html .= "</td><td class=\"suhv-league suhv-opponent\">" . $game_homeclub;
					$html .= "</td><td class=\"suhv-league suhv.opponent\">" . $game_guestclub;
					$html .= "</td><td class=\"suhv-league suhv-result\"><strong>" . $game_result . "</strong></td>";
					$html .= "</tr>";
					if ($game_result != "N/A")
						$LastActivRound = $ActivRound;
					$i++;

				} while ($i < $entries);
				$html .= "</tbody>";
				$html .= "</table>";
				$loopcnt++;

				if (($data->slider->next == NULL) or ($loopcnt > $maxloop)) {
					$loop = FALSE;
				} // only this loop
				else {
					//echo '<br>next loop<br>';
					$api = new SwissUnihockey_Public();

					$details = $api->games(array(
						'season' => $season,
						'league' => $league,
						'game_class' => $game_class,
						'round' => $nextround,
						'mode' => $mode,
						'group' => $group
					));

					$data      = $details->data;
					$nextround = $data->slider->next->set_in_context->round;
					//    echo "<br>Nextround:".$nextround."<br>";
					$i         = 0;
					$entries   = count($games);

					$round_Title        = $data->slider->text;
					$games              = $data->regions[0]->rows;
					$game_location_name = $games[0]->cells[1]->text[0];

					$game_map_x = $games[0]->cells[1]->link->x;
					$game_map_y = $games[0]->cells[1]->link->y;

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";
					$ActivRound   = trim(substr($round_Title, 0, strpos($round_Title, '/')));
					$ActivRound   = str_replace(' ', '_', $ActivRound);
					$html .= '<div class="suhv-round-anchor"><a id="' . $ActivRound . '"></a></div>';
					if ($small) {
						$html .= "<h3>" . $round_Title . "&nbsp;&nbsp;" . $game_maplink . $game_location_name . "</a></h3>";
					} else {
						$html .= "<h3>" . $round_Title . "</h3>";
					}

					$html .= "<table class=\"suhv-table suhv-league\" >\n";
					$html .= "<thead><tr><th class=\"suhv-league suhv-date\" >" . "Datum, Zeit";
					if (!$small) {
						$html .= "</th><th class=\"suhv-league  suhv-place\" >" . $header_Location;
					}
					$html .= "</th><th class=\"suhv-league suhv-opponent\" >" . $header_Home;
					$html .= "</th><th class=\"suhv-league suhv-opponent\" >" . $header_Guest;
					$html .= "</th><th class=\"suhv-league suhv-result\" >" . $header_Result . "</th></tr></thead>";
					$html .= "<tbody>";

				} // end else
			} // end While
			$html .= '<script>document.getElementById("ActivRound").href = "#' . $LastActivRound . '"</script>';
			// Report all errors
			error_reporting(E_ALL);

			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		return $html;

	}

	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_league_getWeekend($season, $league, $game_class, $group, $round, $mode, $cache) {


		$weekend          = TRUE;
		$weekend_games    = FALSE;
		$weekendDays      = SwissUnihockey_Api_Public::nearWeekend();
		$date_description = "Liga-Spiele vom Wochenende";
		$start_date_us    = $weekendDays["Samstag"];
		$end_date_us      = $weekendDays["Sonntag"];
		$start_date       = date("d.m.Y", $start_date_us);
		$end_date         = date("d.m.Y", $end_date_us);

		//echo "<strong>START of Call</strong><br>";
		//echo "Weekend ".date("d.m.Y H:i:s",$start_date_us)." - ".date("d.m.Y H:i:s",$end_date_us)."<br>";

		$transient = $league . $game_class . $group . "league_getWeekend" . $season . $mode;

		$value = get_transient($transient);
		// $value = FALSE;

		$maxloop        = 20;
		$loopcnt        = 1;
		$trans_Factor   = 12;
		$ActivRound     = "";
		$lastActivRound = "";
		$loop           = FALSE;


		if ($league == 21) {
			$small = TRUE;
		} else {
			$small = FALSE;
		}

		if (!$cache)
			$value = False;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'league_getGames', 'season' => $season, 'league' => $league, 'game_class' => $game_class, 'group' => $group, 'round' => $round, 'mode' => $mode));

			$html = "";

			$api = new SwissUnihockey_Public();

			// no 'round' => $round,
			$details = $api->games(array(
				'season' => $season,
				'league' => $league,
				'game_class' => $game_class,
				'mode' => $mode,
				'group' => $group
			));
			$data    = $details->data;


			$loop      = FALSE;
			$nextround = $data->slider->next->set_in_context->round;
			//  echo "<br>Nextround:".$nextround."<br>";

			if ($data->slider->next <> NULL)
				$loop = TRUE;

			//$round = $data->context->round;
			$round = $nextround + 1; //echo "round: ".$round."<br>";


			$header_DateTime    = $data->headers[0]->text;
			$header_Location    = $data->headers[1]->text;
			$header_Home        = $data->headers[2]->text;
			$header_Home        = "Heimteam";
			$header_Guest       = $data->headers[6]->text;
			$header_Guest       = "Gastteam";
			$header_Result      = $data->headers[7]->text;
			$round_Title        = $data->slider->text;
			$games              = $data->regions[0]->rows;
			$game_location_name = $games[0]->cells[1]->text[0];

			$game_map_x = $games[0]->cells[1]->link->x;
			$game_map_y = $games[0]->cells[1]->link->y;

			$game_date_time = $games[0]->cells[0]->text[0];

			$date_of_game = strtotime("today");

			$game_date = substr($game_date_time, 0, stripos($game_date_time, " "));
			if (($game_date == "heute") or ($game_date == "gestern")) {
				if ($game_date == "heute")
					$date_of_game = strtotime("today");
				if ($game_date == "gestern")
					$date_of_game = strtotime("yesterday");
			} else {
				$date_parts   = explode(".", $game_date); // dd.mm.yyyy in german
				$date_of_game = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
			}

			$cTime = (SwissUnihockey_Api_Public::cacheTime() / 60) * $trans_Factor;

			$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title=\"" . $game_location_name . "\">";

			$ActivRound = trim(substr($round_Title, 0, strpos($round_Title, '/')));
			$ActivRound = str_replace(' ', '_', $ActivRound);
			//$html .= '<div class="suhv-round-link"><a id="ActivRound" href="#LastGame">'.__('Zur aktuellen Runde','SUHV-API-2').'</a></div>';
			//$html .= '<div class="suhv-round-anchor"><a id="'.$ActivRound.'"></a></div>';
			//echo "1)Test ".$date_of_game." >= ".$start_date_us." & Test ".$date_of_game." <= ".$end_date_us." Loop ".$loopcnt."<br>";
			//echo "1)Test ".date("d.m.Y H:i:s",$date_of_game)." >= ".date("d.m.Y H:i:s",$start_date_us)." & Test ".date("d.m.Y H:i:s",$date_of_game)." <= ".date("d.m.Y H:i:s",$end_date_us)." Loop ".$loopcnt."<br>";
			if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
				if ($small) {
					$html .= "<h3>" . $round_Title . "&nbsp;&nbsp;" . $game_maplink . $game_location_name . "</a></h3>";
				} else {
					$html .= "<h3>" . $round_Title . "</h3>";
				}
				// $html .= "<h3>".$round_Title."&nbsp;&nbsp;".$game_maplink.$game_location_name."</a></h3>";

				$html .= "<table class=\"suhv-table suhv-league\">\n";
				if ($weekend)
					$html_head .= "<caption>" . $date_description . " " . $start_tag . " " . $start_date . " bis " . $end_tag . " " . $end_date . "</caption>";
				else
					$html_head .= "<caption>" . "Spiele vom " . $start_tag . " " . $start_date . " bis " . $end_tag . " " . $end_date . "</caption>";
				$html .= "<thead><tr><th class=\"suhv-league suhv-date\">" . "Datum, Zeit";
				if (!$small) {
					$html .= "</th><th class=\"suhv-league suhv-place\">" . $header_Location;
				}
				$html .= "</th><th class=\"suhv-league suhv-opponent\">" . $header_Home;
				$html .= "</th><th class=\"suhv-league suhv-opponent\">" . $header_Guest;
				$html .= "</th><th class=\"suhv-league suhv-result\">" . $header_Result . "</th></tr></thead>";
				$html .= "<tbody>";
			}
			error_reporting(E_ALL & ~E_NOTICE);
			$i       = 0;
			$entries = count($games);

			while ($loop) {

				do {

					$game_date_time     = $games[$i]->cells[0]->text[0];
					$game_location_name = $games[$i]->cells[1]->text[0];
					$game_map_x         = $games[$i]->cells[1]->link->x;
					$game_map_y         = $games[$i]->cells[1]->link->y;
					$game_homeclub      = $games[$i]->cells[2]->text[0];
					$game_guestclub     = $games[$i]->cells[6]->text[0];
					$game_result        = $games[$i]->cells[7]->text[0];
					if ($game_result == "")
						$game_result = "N/A";

					$game_home_result  = substr($game_result, 0, stripos($game_result, ":"));
					$game_guest_result = substr($game_result, stripos($game_result, ":") + 1, strlen($game_result));

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";

					$game_homeDisplay  = $game_homeclub;
					$game_guestDisplay = $game_guestclub;
					$game_Diplay       = "";

					$date_of_game = strtotime("today");
					$game_date    = substr($game_date_time, 0, stripos($game_date_time, " "));
					if (($game_date == "heute") or ($game_date == "gestern")) {
						if ($game_date == "heute")
							$date_of_game = strtotime("today");
						if ($game_date == "gestern")
							$date_of_game = strtotime("yesterday");
					} else {
						$date_parts   = explode(".", $game_date); // dd.mm.yyyy in german
						$date_of_game = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
					}
					//echo "2)Test ".$date_of_game." >= ".$start_date_us." & Test ".$date_of_game." <= ".$end_date_us." Loop ".$loopcnt."<br>";
					if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
						$html .= "<tr" . ($i % 2 == 1 ? ' class="alt odd"' : 'class="even"') . "><td class=\"suhv-league suhv-date\">" . $game_date_time;
						if (!$small) {
							$html .= "</td><td class=\"suhv-league suhv-place\">" . $game_maplink . $game_location_name . "</a>";
						}
						$html .= "</td><td class=\"suhv-league suhv-opponent\">" . $game_homeclub;
						$html .= "</td><td class=\"suhv-league suhv.opponent\">" . $game_guestclub;
						$html .= "</td><td class=\"suhv-league suhv-result\"><strong>" . $game_result . "</strong></td>";
						$html .= "</tr>";
						if ($game_result != "N/A")
							$LastActivRound = $ActivRound;
					} else
						$i = $entries; // exit
					$i++;
				} while ($i < $entries);
				if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
					$html .= "</tbody>";
					$html .= "</table>";
					$weekend_games = TRUE;
				}
				$loopcnt++;

				if (($data->slider->next == NULL) or ($loopcnt > $maxloop)) {
					$loop = FALSE;
				} // only this loop
				else {
					//echo '<br>next loop<br>';
					$api = new SwissUnihockey_Public();

					$details = $api->games(array(
						'season' => $season,
						'league' => $league,
						'game_class' => $game_class,
						'round' => $nextround,
						'mode' => $mode,
						'group' => $group
					));

					$data      = $details->data;
					$nextround = $data->slider->next->set_in_context->round;
					//echo "<br>Nextround:".$nextround."<br>";
					$i         = 0;
					$entries   = count($games);

					$round_Title        = $data->slider->text;
					$games              = $data->regions[0]->rows;
					$game_location_name = $games[0]->cells[1]->text[0];
					$game_date_time     = $games[0]->cells[0]->text[0];

					$game_map_x = $games[0]->cells[1]->link->x;
					$game_map_y = $games[0]->cells[1]->link->y;

					$date_of_game = strtotime("today");
					$game_date    = substr($game_date_time, 0, stripos($game_date_time, " "));
					if (($game_date == "heute") or ($game_date == "gestern")) {
						if ($game_date == "heute")
							$date_of_game = strtotime("today");
						if ($game_date == "gestern")
							$date_of_game = strtotime("yesterday");
					} else {
						$date_parts   = explode(".", $game_date); // dd.mm.yyyy in german
						$date_of_game = strtotime($date_parts[2] . "-" . $date_parts[1] . "-" . $date_parts[0]);
					}
					//echo "<br>Date of Game :". $game_date_time. "<br>";

					if ($date_of_game >= $end_date_us) {
						$loop = FALSE;
						//echo "<br>Exit :".$date_of_game."<br>";
					} // exit 

					$game_maplink = "<a href=\"https://maps.google.ch/maps?q=" . $game_map_y . "," . $game_map_x . "\"" . " target=\"_blank\" title= \"" . $game_location_name . "\">";
					$ActivRound   = trim(substr($round_Title, 0, strpos($round_Title, '/')));
					$ActivRound   = str_replace(' ', '_', $ActivRound);
					$html .= '<div class="suhv-round-anchor"><a id="' . $ActivRound . '"></a></div>';
					//echo "3)Test ".$date_of_game." >= ".$start_date_us." & Test ".$date_of_game." <= ".$end_date_us." Loop ".$loopcnt."<br>";
					if (($date_of_game >= $start_date_us) and ($date_of_game <= $end_date_us)) {
						if ($small) {
							$html .= "<h3>" . $round_Title . "&nbsp;&nbsp;" . $game_maplink . $game_location_name . "</a></h3>";
						} else {
							$html .= "<h3>" . $round_Title . "</h3>";
						}

						$html .= "<table class=\"suhv-table suhv-league\" >\n";
						$html .= "<thead><tr><th class=\"suhv-league suhv-date\" >" . "Datum, Zeit";
						if (!$small) {
							$html .= "</th><th class=\"suhv-league  suhv-place\" >" . $header_Location;
						}
						$html .= "</th><th class=\"suhv-league suhv-opponent\" >" . $header_Home;
						$html .= "</th><th class=\"suhv-league suhv-opponent\" >" . $header_Guest;
						$html .= "</th><th class=\"suhv-league suhv-result\" >" . $header_Result . "</th></tr></thead>";
						$html .= "<tbody>";
					}
				} // end else
			} // end While
			if (!$weekend_games) {
				$html .= 'Keine Spiele in dieser Liga am Wochenende ' . date("d.m.Y", $start_date_us) . " - " . date("d.m.Y", $end_date_us) . "<br />";
			}
			//$html .= '<script>document.getElementById("ActivRound").href = "#'.$LastActivRound.'"</script>';
			// Report all errors
			error_reporting(E_ALL);

			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		//echo "<strong>END of Call</strong><br>";
		return $html;

	}

	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_getPlayer($player_id, $sponsor_name, $sponsor_sub, $sponsor_logo, $sponsor_link, $sponsor_link_title, $cache) {

		$transient    = $player_id . "getPlayer";
		$value        = get_transient($transient);
		$trans_Factor = 1;

		if (!$cache)
			$value = False;
		//echo "<br> PLAYER ID : ".$player_id."<br>" ;

		if ($value == False) {

			// SwissUnihockey_Api_Public::log_me(array('function' => 'getPlayer', 'player_id' =>  $player_id, 'sponsor_name' => $sponsor_name, 'sponsor_sub' => $sponsor_sub, 'sponsor_link' => $sponsor_link));

			$html = "";

			$api = new SwissUnihockey_Public();

			$details = $api->playerDetails($player_id, array());

			$data = $details->data;

			$attributes = $data->regions[0]->rows[0]->cells;

			$player_name   = $data->subtitle;
			$image_url     = $attributes[0]->image->url;
			$club_name     = $attributes[1]->text[0];
			$player_nr     = $attributes[2]->text[0];
			$player_pos    = $attributes[3]->text[0];
			$player_year   = $attributes[4]->text[0];
			$player_size   = $attributes[5]->text[0];
			$player_weight = $attributes[6]->text[0];


			$html .= "<div class=\"su-spieldaten\">";
			$html .= "<div class=\"su-container su-obj-spielerdetails\">";
			if ($player_id != NULL) {
				$html .= "<div class=\"su-header\"><span class=\"su-value-name\"><h2>" . $player_nr . " " . $player_name . "</h2></span>";
			} else {
				$html .= "<div class=\"su-header\"><span class=\"su-value-name\"><h2>SUHV Player ID not set!</h2></span>";
			}
			$html .= "</div>";
			$html .= "<div class=\"su-row\">";
			$html .= "<div class=\"su-obj-portrait\">";
			$html .= "<span class=\"su-value-portrait\">";
			$html .= "<img src=\"" . $image_url . "\" alt=\"" . $player_name . " (Portrait)" . "\"></span></div>";
			$html .= "<div class=\"su-obj-details\">";
			$html .= "<table class=\"su-table\" cellpadding=\"0\" cellspacing=\"0\"><tbody>";
			$html .= "<tr><td class=\"su-strong\">Name:</td><td>" . $player_name . "</td></tr>";
			$html .= "<tr><td class=\"su-strong\">Nr:</td><td>" . $player_nr . "</td></tr>";
			$html .= "<tr><td class=\"su-strong\">Position:</td><td>" . $player_pos . "</td></tr>";
			$html .= "<tr><td class=\"su-strong\">Jahrgang:</td><td>" . $player_year . "</td></tr>";
			$html .= "<tr><td class=\"su-strong\">Grösse:</td><td>" . $player_size . "</td></tr>";
			$html .= "<tr><td class=\"su-strong\">Gewicht:</td><td>" . $player_weight . "</td></tr>";
			$html .= "</tbody></table>";
			$html .= "<div class=\"su-site-link\"><a href=\"https://www.swissunihockey.ch/de/player-detail?player_id=" . $player_id . "\">Spielerstatistik (Swissunihockey)</a></div>";
			$html .= "</div></div><!-- /su-row --></div><!-- /su-container --></div><!-- /su-spieldaten -->";

			if ($sponsor_name != NULL) {
				if ($sponsor_name[0] != '*') {
					$html .= "<h2 class=\"sponsor-header\">Sponsor</h2>";
					$html .= "<div class=\"sponsor-row\">";
					$html .= "<div class=\"sponsor-logo\"><a href=\"" . $sponsor_link . "\"><img src=\"" . $sponsor_logo . "\" alt=\"" . $sponsor_name . "\" /></a></div>";
					$html .= "<div class=\"sponsor-name\"><span><h3>" . $sponsor_name . "</h3>";
					$html .= "<h4><a href=\"" . $sponsor_link . "\">" . $sponsor_link_title . "</a></h4></span></div></div>";
				} else {
					$html .= "<h2 class=\"sponsor-header\">persönlicher Sponsor gesucht!</h2>";
					$html .= "<div class=\"sponsor-row\">";
					$html .= "<div class=\"sponsor-logo\"><a href=\"http://www.churunihockey.ch/sponsoring/kontakt-marketing/\"><img src=\"http://www.churunihockey.ch/wp-content/uploads/2013/08/ChurUnihockeyLogoSlide_460x368.png\" alt=\"www.churunihockey.ch\" /></a></div>";
					$html .= "<div class=\"sponsor-name\"><span><h3>- Hier könnte ihre Firma stehen -</h3>";
					$html .= "<h4><a href=\"http://www.churunihockey.ch/sponsoring/kontakt-marketing/\">" . "www.churunihockey.ch" . "</a></h4></span></div></div>";
				}
			}
			set_transient($transient, $html, SwissUnihockey_Api_Public::cacheTime() * $trans_Factor);
		} else {
			$html = $value;
		}
		return $html;


	}

	/* ---------------------------------------------------------------------------------------------------- */
	// Funktion: Log-Daten in WP-Debug schreiben
	public static function log_me($message) {
		if (WP_DEBUG === true) {
			if (is_array($message) || is_object($message)) {
				error_log(print_r($message, true));
			} else {
				error_log($message);
			}
		}
	}
	/* ---------------------------------------------------------------------------------------------------- */
	public static function api_show_params($season, $club_ID, $team_ID, $mode) {

		echo "<br>Season: " . $season . " - Club: " . $club_ID . " - Team: " . $team_ID;
	}

}

?>