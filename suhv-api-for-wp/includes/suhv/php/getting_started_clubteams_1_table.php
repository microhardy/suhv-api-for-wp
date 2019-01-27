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
    <h1>Where we retrieve all Club Teams for Saison</h1>

    <?php

      set_include_path(preg_replace("/php/", "lib", __DIR__));
      // echo "get inc:".get_include_path();

      require('BusinessServer/bootstrapTrial.php');
      $api = new SwissUnihockey_Public();
      $version_info = $api->version();
      echo "API-Version: " .$version_info->data->full_version. "<br>";  
 
      $details = $api->clubTeams(2016,423403,array(
        'locale' => 'de'
      )); 

      $entries = $details->entries; 
    
      $entriesCount = count($entries);

      echo "entries: " . $entriesCount. "<br>";  
      //* echo "<div class=".addslashes("container").">";
      echo "<table class=".addslashes("table-bordered")." width=".addslashes("850px").">";
      echo "<thead><tr>".
           "<th>"."Liga"."</th><th>"."Team-ID"."</th><th>"."highlight"."</th>". 
           "</tr></thead>";
      echo "<tbody>";
      $i = 0;
      // Report all errors except E_NOTICE
      //error_reporting(E_ALL & ~E_NOTICE);
      do {
           $Team_Name =  $entries[$i]->text;
           $Team_ID =  $entries[$i]->set_in_context->team_id;
           $High_Class =  $entries[$i]->highlight;
          
           echo "<tr>".
           "<td>".$Team_Name."</td><td>". $Team_ID ."</td><td>".$High_Class."</td>". 
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