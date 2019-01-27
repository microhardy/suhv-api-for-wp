<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Ex Ranking - Getting Started</title>

  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>


  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/styles/default.min.css">
  <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/highlight.min.js"></script>  <script>hljs.initHighlightingOnLoad();</script>
</head>
<body>

<div class="container">
  <div class="row">
    <h1>Where we retrieve all Players of of Teams for Saison</h1>

    <?php

      set_include_path(preg_replace("/php/", "lib", __DIR__));
      // echo "get inc:".get_include_path();

      require('BusinessServer/bootstrapTrial.php');
      $api = new SwissUnihockey_Public();
      $version_info = $api->version();
      $season = 2016;
      $Team_ID = 429283; 
      echo "API-Version: " .$version_info->data->full_version. "<br>";  
      echo "Season: " . $season. " (no effect) Team-ID: ".$Team_ID."<br>"; 
      $details = $api->teamPlayers($Team_ID,array(
        'locale' => 'de','season' => '2016'
      )); 

      if (isset($details->status))  echo "<br>"."Status: " . $details->status. "<br>"; 
      if (isset($details->type))  echo "Type: " . $details->type. "<br>";  
      if (isset($details->message))  echo "Message: " . $details->message. "<br>"; 
      
      $entries = $details->data->regions[0]->rows; 
      //echo "Players: ".$entries;
    
      $entriesCount = count($entries);

      echo "Player-entries: " . $entriesCount. "<br>";  
      //* echo "<div class=".addslashes("container").">";
      echo "<table class=".addslashes("table-bordered")." width=".addslashes("850px").">";
      echo "<thead><tr>".
           "<th>"."Nr."."</th><th>"."Position"."</th><th>"."Player-Name"."</th><th>"."Year of Birth"."</th><th>"."Player-ID"."</th>". 
           "</tr></thead>";
      echo "<tbody>";
      $i = 0;
      // Report all errors except E_NOTICE
      //error_reporting(E_ALL & ~E_NOTICE);
      do {
           $Player_Nr =  $entries[$i]->cells[0]->text[0];
           $Player_Position =  $entries[$i]->cells[1]->text[0];
           $Player_Name =  $entries[$i]->cells[2]->text[0];
           $Player_ID =  $entries[$i]->cells[2]->link->ids[0];
           $Player_Year =  $entries[$i]->cells[3]->text[0];
          
           echo "<tr>".
           "<td>".$Player_Nr."</td><td>".$Player_Position."</td><td>".$Player_Name."</td><td>".$Player_Year."</td><td>".$Player_ID."</td>". 
           "</tr>"; 
           $i++; 
        } while ($i < $entriesCount);
        // Report all errors
        //error_reporting(E_ALL);

      echo "</tbody>";
      echo "</table>";
      

    ?>

  <h2> (RAW data) </h2>

  <pre>
  <code class="javascript">
    <?= json_encode($entries, JSON_PRETTY_PRINT) ?>
  </code>
  </pre>
</div>

</body>
</html>