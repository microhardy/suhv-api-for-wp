<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Make Logo-Table</title>

  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>


  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/styles/default.min.css">
  <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/highlight.min.js"></script>  <script>hljs.initHighlightingOnLoad();</script>
</head>
<body>

<div class="container">
  <div class="row">
    <h1>all Clubs for Saison</h1>

    <?php

      function getSeason()
      {
         $season = intval(date('Y'));
        if (date('m') < 6) {
        $currentSeason = strval($season-1);
          }
        else {
            $currentSeason = strval($season);
        }
        return ($currentSeason);
      }

      function base_url(){
        return sprintf(
        "%s://%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME']
        );
       }

      set_include_path(preg_replace("/php/", "lib", __DIR__));
      // echo "get inc:".get_include_path();

      require('BusinessServer/bootstrapTrial.php');
      $api = new SwissUnihockey_Public();
      $version_info = $api->version();
      $current = getSeason();
      echo "API-Version: " .$version_info->data->full_version. "<br>";  
      echo "Season: " . $current. "<br>";  
//echo "Dirname: ".dirname(__FILE__). "<br>";
//echo "Basename: ".basename(__FILE__). "<br>";
//echo "Dir: ". __DIR__. "<br>";
$doc_root = preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']);
echo "Docs: ".$doc_root. "<br>";

      $details = $api->clubs($current,array(
        'locale' => 'de'
      )); 

      $entries = $details->entries; 
    
      $entriesCount = count($entries);

      echo "entries: " . $entriesCount. "<br>";  
      //* echo "<div class=".addslashes("container").">";
      echo "<table class=".addslashes("table-bordered")." width=".addslashes("850px").">";
      echo "<thead><tr>".
           "<th>"."Clubname"."</th><th>"."Club-ID"."</th>". 
           "</tr></thead>";
      echo "<tbody>";
      $i = 0;
      // Report all errors except E_NOTICE
      //error_reporting(E_ALL & ~E_NOTICE);
      do {
           $Club_Name =  $entries[$i]->text;
           $Club_ID =  $entries[$i]->set_in_context->club_id;
           $High_Class =  $entries[$i]->highlight;
           $club_array[$Club_Name] =  $Club_ID ;
           echo "<tr>".
           "<td>".$Club_Name."</td><td>". $Club_ID."</td>". 
           "</tr>"; 
           $i++; 
        } while ($i < $entriesCount);
        // Report all errors
        //error_reporting(E_ALL);

      echo "</tbody>";
      echo "</table>";


      $index_array = array('version' => '1.0', 'season' => $current,'refresh' => 3600*4); // 4h
      date_default_timezone_set("Europe/Paris");
      $index_array['timestamp'] = strftime("%d.%m.%Y %H:%M:%S");

      $homeurl = preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']);
      $path = $homeurl;
      $len = strlen($path);
      $mainurl =  base_url();
      // $mainurl = "https://www.churunihockey.ch";

      $filesearch = $path."/club-logos/*.jpg";
      $files=glob($filesearch);
      $i=0;
      foreach ($files as $file) {
         $jpgfile = substr($file,$len); 
    //echo "jpg: ".$jpgfile."<br>" ;
         $jpg_url = "<a href=\"".$mainurl.$jpgfile."\">".$mainurl.$jpgfile."</a>";
         $club_id = str_replace("/club-logos/","",str_replace(".jpg","",$jpgfile));
         $index_array['logos']['club_ID'][$i] = $club_id ;
         $index_array['logos']['club_Name'][$i] = array_search($club_id,$club_array);
         $index_array['logos']['club_Logo'][$i] = $mainurl.$jpgfile;

    //echo $jpg_url."<br>";
         $i++;
      }

      $file = $_SERVER['DOCUMENT_ROOT']."/club-logos/clubindex.json";
      if (strpos($file,"xampp")) {
        $file = "C:/xampp2/apps/churunihockey/htdocs/club-logos/clubindex.json";
      }
      $fp = fopen($file, "w");
      fputs($fp, json_encode($index_array));
      fclose($fp);
      echo "<br>Saved on ".$file."<br>";

      $found = array_search($club_id,$index_array['logos']['club_ID']);
      if ($found != NULL)
        echo "Last ARRAY-Index: ".$found." - ".$index_array['logos']['club_Logo'][$found]." - ".$index_array['logos']['club_Name'][$found] ;
      else echo "ARRAY-Index NOT FOUND";
    ?>

  <h2>Logo and Clubs JSON (RAW data) </h2>

  <pre>
  <code class="javascript">
    <?= json_encode($index_array, JSON_PRETTY_PRINT) ?>
  </code>
  </pre>

</div>

</body>
</html>