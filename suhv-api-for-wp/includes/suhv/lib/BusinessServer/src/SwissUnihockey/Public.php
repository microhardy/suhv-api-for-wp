<?php

/**
 * Interface class for the public interface of Swiss Unihockey API server. 
 * All methods that exist on the REST interface will eventually be methods 
 * here, so that calls can be transparent for the rest of the code. 
 * 
 */
class SwissUnihockey_Public {
    public function __construct($base_url='https://api-v2.swissunihockey.ch/api', 
        $key=null, $secret=null)
    {
        $plugin_options = get_option('SUHV_WP_plugin_options');
        $key = $plugin_options['SUHV_api_key'];
        $secret = $plugin_options['SUHV_api_secret'];

        $this->base_url = $base_url; 
        $this->key = $key; 
        $this->secret = $secret; 
        $this->authenticated = isset($key);
    }

    public function version() 
    {
        $json = $this->get('');
        return $json; 
    }

    /* Internal use (Swiss Unihockey) only */

    /** 
     * Searches for images in the asset database. Only images that can be 
     * displayed on the web will be returned. 
     * 
     *      $images = $api->imageSearch('test'); 
     */
    public function imageSearch($query, $variant)
    {
        $json = $this->get('/internal/images', 
            array('q' => $query, 'v' => $variant));
        return $json; 
    }

    /**
     * Retrieves attributes for a single image from the asset database. 
     */
    public function getImageAttrs($fID)
    {
        $json = $this->get('/internal/images/'.$fID);
        return $json;         
    }
    
   /**
    * Return all seasons.
    *
    * Context:
    *  * return_type: An optional type, e.g. 'dropdown'.
    */
   public function seasons($context = array())
   {
       $url = '/seasons';
       
       $json = $this->get($url, $context);
       return $json; 
   }

    /** 
     * Retrieves the games overview (Spielübersicht) data.
     *
     * Context:
     *  * season: The season (e.g. 2013) to filter the games.
     *  * league: A league id.
     *  * game_class: A game_class id.
     *  * round: A round id.
     *  * group: A group id.
     *  * view: 'full' or 'short' - how much information is returned.
     */
    public function games($context=array())
    {
        $context['mode'] = 'list';
      
        $json = $this->get('/games', $context);
        return $json; 
    }
    
    /** 
     * Retrieves the current games (Aktuelle Spiele) data.
     *
     * Context:
     *  * on_date: Uses the given date to get that day's games.
     *  * before_date: Gets the first game day before the given date.
     *  * after_date: Gets the first game day after the given date.
     */
    public function currentGames($context=array())
    {
        $context['mode'] = 'current';
        
        $json = $this->get('/games', $context);
        return $json; 
    }
    
    /** 
     * Retrieves cup games data for a given round.
     *
     * Parameters:
     *  * tournament_id: Identifies the desired tournament.
     *  * round: Identifies the desired round.
     *  * side: either 'left' or 'right', specifies the data formatting.
     *  * context: additional parameters
     */
    public function cupGames($tournament_id, $round=null, $side="left", $context=array())
    {
        $context['mode'] = 'cup';
        $context['tournament_id'] = $tournament_id; 
        $context['side'] = $side;

        if ($round) {
            $context['round'] = $round; 
        }
        
        $json = $this->get('/games', $context);
        return $json; 
    }
    
    /** 
     * Retrieves favorite games.
     *
     * Parameters:
     *  * sid: Identifies the session containing the favorites.
     */
    public function favoriteGames($sid, $context=array())
    {
        $context['mode'] = 'favorite';
        $context['sid']  = $sid;
        
        $json = $this->get('/games', $context);
        return $json; 
    }
    
    /** 
     * Retrieves the most recent games (Direktbegegnungen) data of two teams.
     *
     * Note: The game_id is not a parameter because this should not be confused
     *       with a specific game.
     *
     * Context:
     *  * game_id: Uses the given game id to get the game's teams' games.
     *  * amount: The maximum amount of games to get.
     */
    public function directGames($context=array())
    {
        $context['mode'] = 'direct';
        
        $json = $this->get('/games', $context);
        return $json; 
    }
    
    /** 
     * Retrieves all games of a club.
     */
    public function clubGames($season, $club_id, $team_id, $mode, $context=array())
    {
        $context['mode'] = $mode;
        $context['season'] = $season;
        $context['club_id'] = $club_id;
        $context['team_id'] = $team_id;
        //echo "<br>context: mode ".$context['mode']." - context: page ".$context['page'];
        $json = $this->get('/games', $context);
        return $json; 
    }
    
    /** 
     * Retrieves list of all leagues
     * Extra T. Hardegger
     */
    public function leagues($season)
    {
        $context['season'] = $season;

        $json = $this->get('/leagues', $context);
        return $json; 
    }
    /** 
     * Retrieves the rankings (Rangliste) data.
     * 
     * Context:
     *  * season: The season (e.g. 2013) to filter the games.
     *  * league: A league id.
     *  * game_class: A game_class id.
     *  * group: A group id.
     *  * view: 'full' or 'short' - how much information is returned.
     */
    public function rankings($season, $club_id, $team_id, $mode, $context=array())
    {
        if ($mode != NULL) $context['mode'] = $mode;
        if ($team_id != NULL) $context['team_id'] = $team_id;
        $context['season'] = $season;
        $context['club_id'] = $club_id;
        $json = $this->get('/rankings', $context);
        return $json; 
    }


    /**
     * Lineup for a game.
     * 
     * Parameters:
     *  * game_id: A game id.
     *  * home_team: true to get the home team lineup, false to get the away team lineup.
     */
    public function lineup($game_id, $home_team, $context=array())
    {
        $url = '/games/' . $game_id . '/teams/'; 
        
        if (is_numeric($home_team)) {
            $url .=  $home_team; 
        }
        else {
            if ($home_team) 
                $url .= '0'; 
            else
                $url .= '1';
        }

        $url .= '/players';

        $json = $this->get($url, $context);
        return $json;         
    }

    /** 
     * Details for a game.
     *
     * Parameters:
     *  * game_id: A game id.
     */
    public function gameDetails($game_id, $context=array()) 
    {
        $url = '/games/'.$game_id;
        
        $json = $this->get($url, $context);
        return $json; 
    }

        /** 
     * Summary for a game.
     *
     * Parameters:
     *  * game_id: A game id.
     */
    public function gameDetailsSummary($game_id, $context=array()) 
    {
        $url = '/games/'.$game_id."/summary";
        
        $json = $this->get($url, $context);
        return $json; 
    }
    
    
    /** 
     * Timeline for a cup. 
     *
     * Parameters:
     *  * tournament_id: A tournament (cup) id.
     *
     * Context:
     *  * round: The round to return.
     */
    public function cupTimeline($tournament_id, $context=array()) 
    {
        $url = '/cups/'.$tournament_id;

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * Details for a player. 
     *
     * Parameters:
     *  * player_id: A player id.
     */
    public function playerDetails($player_id, $context=array()) 
    {
        $url = '/players/'.$player_id;

        $json = $this->get($url, $context);
        return $json; 
    }

    /** 
     * Details for a team. 
     *
     * Parameters:
     *  * team_id: A team id.
     */
    public function teamDetails($team_id, $context=array()) 
    {
        $url = '/teams/'.$team_id;

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * Statistics for a team. 
     *
     * Parameters:
     *  * team_id: A team id.
     */
    public function teamStatistics($team_id, $context=array())
    {
        $url = '/teams/'.$team_id.'/statistics';

        $json = $this->get($url, $context);
        return $json; 
    }

    /** 
     * List of teams in a given league and game_class.
     *
     * Parameters:
     *  * league: A league id.
     *  * game_class: A game_class id.
     */
    public function teamList($league=null, $game_class=null, $context=array()) 
    {
        $context = array_merge(array('league' => $league, 'game_class' => $game_class), $context); 
        $json = $this->get('/teams', $context);

        return $json; 
    }
    
    /**
     * Returns a list of active teams for a given season and club id. 
     *
     * Parameters:
     *  * season: The season for which to get the clubs. 
     *  * club_id: The team's club's id.
     *
     * Context:
     *  * return_type: An optional type, e.g. "dropdown".
     *
     * Example: 
     *   $client->clubTeams(2013, 467, array('return_type' => 'dropdown'));
     */
    public function clubTeams($season, $club_id, $context=array())
    {
        $params = array('season' => $season, 'club_id' => $club_id, 'mode' => 'by_club');
        $context = array_merge($params, $context); 
        $json = $this->get('/teams', $context);
        return $json; 
    }
    
    /** 
     * Events (Spielereignisse) for a game. 
     *
     * Parameters:
     *  * game_id: A game id.
     *
     * Context:
     *  * team: Optionally 'home' or 'away'.
     */
    public function gameEvents($game_id, $context = array()) 
    {
        $url = '/game_events/'.$game_id;
        
        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * National league statistics for a player.
     *
     * Parameters:
     *  * player_id: A player id.
     */
    public function playerStatistics($player_id, $context=array()) 
    {
        $url = '/players/'.$player_id.'/statistics';

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * National team player details.
     *
     * Parameters:
     *  * national_player_id: A national player id.
     */
    public function nationalPlayerDetails($national_player_id, $context=array()) 
    {
        $url = '/national_players/'.$national_player_id;

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * National team player list.
     *
     * Parameters:
     *  * selection_id: Player selection to display. (Nationalspieler-Auswahl)
     */
    public function nationalPlayerList($selection_id, $context=array()) 
    {
        $url = '/national_players';
        $context['selection'] = $selection_id; 

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * National team (Auswahl) statistics for a national player.
     *
     * Parameters:
     *  * national_player_id: A national player id.
     */
    public function nationalPlayerStatistics($national_player_id, $context=array()) 
    {
        $url = '/national_players/'.$national_player_id.'/statistics';

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * Overview for a player for a season.
     *
     * Parameters:
     *  * player_id: A player id.
     *
     * Context:
     *  * season: An optional season to filter on, e.g. 2013.
     */
    public function playerOverview($player_id, $context = array()) 
    {
        $url = '/players/'.$player_id.'/overview';

        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * Players of a team.
     *
     * Parameters:
     *  * team_id: A team id.
     *
     * Context:
     *  * season: An optional season to filter on, e.g. 2013.
     */
    public function teamPlayers($team_id, $context = array())
    {
        $url = '/teams/'.$team_id.'/players';
        
        $json = $this->get($url, $context);
        return $json; 
    }
    
    /** 
     * SU topscorers for a season.
     *
     * Parameters:
     *  * type: 'su' or 'mobiliar'.
     *
     * Context:
     *  * season: An optional season to filter on, e.g. 2013.
     *  * league: A league id.
     *  * game_class: A game class id.
     *  * phase: Either 'group' or 'finals'.
     */
    public function topscorers($type, $context=array()) 
    {
        $url = '/topscorers/'.$type;
        
        $json = $this->get($url, $context);
        return $json; 
    }

    /**
     * Adds a favorite to the matchcenter. Returned object has a type of
     * 'session' and a data key that contains the session id.
     *
     * You should always replace the session you store with this new session
     * that has been returned from this function. 
     *
     * See https://api-v2.swissunihockey.ch/api/sessions/doc.
     * 
     * Example: 
     *   $client->addFavorite($sid, 
     *       array('type' => 'league', 'game_class' => 11, 'league' => 1));
     */
    public function addFavorite($sid, $favorite)
    {
        $json = $this->post('/sessions/add_favorite', array(
            'sid' => $sid, 
            'favorites[]' => json_encode($favorite)));
        return $json; 
    }

    /**
     * Removes a favorite from the matchcenter. Returned object has a type of
     * 'session' and a data key that contains the session id.
     * 
     * You should always replace the session you store with this new session
     * that has been returned from this function. 
     * 
     * See https://api-v2.swissunihockey.ch/api/sessions/doc.
     *
     * Example: 
     *   $client->removeFavorite($sid, 
     *       array('type' => 'league', 'game_class' => 11, 'league' => 1));
     */
    public function removeFavorite($sid, $favorite)
    {
        $json = $this->post('/sessions/remove_favorite', array(
            'sid' => $sid, 
            'favorites[]' => json_encode($favorite)));
        return $json; 
    }

    /**
     * Returns the matchcenter context data. 
     *
     * See https://api-v2.swissunihockey.ch/api/sessions/doc.
     */
    public function matchcenter($sid, $context = array())
    {
        $context = array_merge(array('sid' => $sid), $context); 
        $json = $this->get('/sessions/matchcenter', $context);
        return $json; 
    }
    
    /**
     * Returns query result data. 
     *
     * Context:
     *  * query: The query.
     *  * season: Which season to search in, default the current one.
     *  * amount: How many results per result type should be returned.
     *
     * Example: 
     *   $client->search(array('query' => 'lyss', 'season' => 2013, 'amount' => 5));
     */
    public function search($context=array())
    {
        $json = $this->get('/search', $context);
        return $json; 
    }

    /**
     * Returns a list of teams for a given club and their achievements in the
     * current season / the season given as parameter. 
     * 
     * Example: 
     *   $client->clubStatistics(429432, array('season' => 2013));
     */
    public function clubStatistics($club_id, $context=array()) 
    {
        $json = $this->get('/clubs/'.$club_id.'/statistics', $context);
        return $json; 
    }
    
    /**
     * Returns a list of active clubs for a given season. 
     *
     * Parameters:
     *  * season: The season for which to get the clubs. 
     *
     * Context:
     *  * return_type: An optional type, e.g. "dropdown".
     *
     * Example: 
     *   $client->clubs(2013, array('return_type' => 'dropdown'));
     */
    public function clubs($season, $context=array())
    {
        $context = array_merge(array('season' => $season), $context); 
        $json = $this->get('/clubs', $context);
        return $json; 
    }

    /**
     * Returns players statistics for national players. (aka 'Ewiges Kader') 
     * Pass a selection_id of 1 for gents and a selection_id of 2 for ladies. 
     * 
     * Example: 
     *   $client->nationalPlayersStatistics(1);
     */
    public function nationalPlayersStatistics($selection_id, $context=array()) 
    {
        $context = array_merge(array('selection_id' => $selection_id), $context); 
        $json = $this->get('/national_players/statistics', $context);
        return $json;         
    }

    /**
     * Returns a multi table that contains information about a player. 
     * 
     * Example: 
     *   $client->uiPlayerInfo(403320)
     */
    public function uiPlayerInfo($player_id, $context=array())
    {
      $json = $this->get('/ui/players/'.$player_id, $context);
      return $json;  
    }

    /**
     * Posts to an URL. Authenticated posts are not yet supported. 
     */
    private function post($path, $params=array())
    {
        $uri = $this->base_url . $path; 

        $request = \Httpful\Request::post($uri, 
            $this->encodeQuery($params));
        $response = $request->send();

        $json = $response->body; 
        return $json; 
    }

    private function get($path, $params=array())
    {
        $uri = $this->base_url . $path; 

        if ($this->authenticated) {
            $params = $this->HMACAuth('GET', $uri, $params); 
        }

        $request = \Httpful\Request::get(
            $this->paramsToQuery($uri, $params));

        $response = $request->send();

        $json = $response->body; 
        return $json; 
    }

    /**
     * Converts query parameters into a full URI. 
     */
    private function paramsToQuery($uri, $params) 
    {
        if (empty($params)) 
            return $uri;

        return $uri . "?" . $this->encodeQuery($params);
    }

    private function encodeQueryPart($part) 
    {
        return str_replace( '%7E', '~', rawurlencode( $part ) );
    }
    private function encodeQuery($params)
    {
        // Create the canonicalized query
        $query = array();
        foreach ( $params as $param => $value ) {
            $param = $this->encodeQueryPart($param);
            $value = $this->encodeQueryPart($value); 
            $query[] = $param . '=' . $value;
        }
        return implode( '&', $query );
    }

    /**
     * Authenticates the given request by adding HMAC signature. 
     * 
     * Much of the procedure for this is what Amazon does to authenticate their
     * services. Please refer to their documentation to understand all the 
     * details. (http://aws.amazon.com/articles/1928?_encoding=UTF8&jiveRedirect=1)
     */
    private function HMACAuth($method, $uri, $params) 
    {
        $params['key']   = $this->key; 
        $params['ts']    = time();

        // Sort the parameters
        ksort( $params );

        $canonicalized_query = $this->encodeQuery($params);
        $string_to_sign = $method . "\n" . $uri . "\n" . $canonicalized_query;

        // Calculate HMAC with SHA256 and base64-encoding
        $signature = base64_encode( 
            hash_hmac( 'sha256', $string_to_sign, $this->secret, TRUE ) );

        // Encode the signature for the request
        $signature = $this->encodeQueryPart($signature); 
        
        $params['sig'] = $signature; 
        return $params; 
    }
}
