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
    <h1>Where we retrieve RANKING-details for Chur Unihockey(423403)</h1>

    <?php

      set_include_path(preg_replace("/php/", "lib", __DIR__));
      // echo "get inc:".get_include_path();

      require('BusinessServer/bootstrap.php');
      $api = new SwissUnihockey_Public();
      $version_info = $api->version();
      echo "API-Version: " .$version_info->data->full_version. "<br>";  
 
      $details = $api->rankings(array(
        'locale' => 'de'
      )); 

      $data = $details->data; 

      $header_Rank = $data->headers[0]->text;
      $header_Team = $data->headers[1]->text;
      $header_Sp = $data->headers[2]->text;
      $header_S = $data->headers[3]->text;
      $header_SnV = $data->headers[4]->text;
      $header_NnV = $data->headers[5]->text;
      $header_N = $data->headers[6]->text;
      $header_T = $data->headers[7]->text;
      $header_TD = $data->headers[8]->text;
      $header_P = $data->headers[9]->text;
      $Table_title = $data->title;
      //* $rankings = $data->regions[0]->rows;
      $rankings = $data->regions[0]->rows;
    
      $entries = count($rankings);

      echo "entries: " . $entries . "<br>".$Table_title;  
      //* echo "<div class=".addslashes("container").">";
      echo "<table class=".addslashes("table-bordered")." width=".addslashes("850px").">";
      echo "<thead><tr>".
           "<th>".$header_Rank."</th><th>".$header_Team ."</th><th>".$header_Sp."</th><th>".$header_S."</th><th>".$header_SnV."</th><th>".$header_NnV."</th>". 
           "<th>".$header_N."</th><th>".$header_T."</th><th>".$header_TD."</th><th>".$header_P."</th>". 
           "</tr></thead>";
      echo "<tbody>";
      $index = 0;
      echo "<br>help-".$data->regions[0]->rows[$index]->cells[1]->text[0];
      // Report all errors except E_NOTICE
      error_reporting(E_ALL & ~E_NOTICE);
      do {
           $ranking_Rank = $data->regions[0]->rows[$index]->cells[0]->text[0];
           $ranking_Team = $data->regions[0]->rows[$index]->cells[1]->text[0];
           $ranking_Sp = $data->regions[0]->rows[$index]->cells[2]->text[0];
           $ranking_S = $data->regions[0]->rows[$index]->cells[3]->text[0];
           $ranking_SnV = $data->regions[0]->rows[$index]->cells[4]->text[0];
           $ranking_NnV = $data->regions[0]->rows[$index]->cells[5]->text[0];
           $ranking_N = $data->regions[0]->rows[$index]->cells[6]->text[0];
           $ranking_T = $data->regions[0]->rows[$index]->cells[7]->text[0];
           $ranking_TD = $data->regions[0]->rows[$index]->cells[8]->text[0];
           $ranking_P = $data->regions[0]->rows[$index]->cells[9]->text[0];
          
           echo "<tr>".
           "<td>".$ranking_Rank."</td><td>".$ranking_Team."</td><td>".$ranking_Sp."</td><td>".$ranking_S."</td><td>".$ranking_SnV."</td><td>".$ranking_NnV."</td>". 
           "<td>".$ranking_N."</td><td>".$ranking_T."</td><td>".$ranking_TD."</td><td>".$ranking_P."</td>". 
           "</tr>"; 
           $index++; 
        } while ($index < $entries);
        // Report all errors
        error_reporting(E_ALL);

      echo "</tbody>";
      echo "</table>";
      

    ?>

  <h2> (RAW data) </h2>

  <pre>
  <code class="javascript">
    <?= json_encode($data, JSON_PRETTY_PRINT) ?>
  </code>
  </pre>
</div>

</body>
</html>