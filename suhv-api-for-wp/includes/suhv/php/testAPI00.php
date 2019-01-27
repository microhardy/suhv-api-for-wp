<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" 
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>Ex 02 - Getting Started</title>

  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>

  <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/styles/default.min.css">
  <script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.6/highlight.min.js"></script>  <script>hljs.initHighlightingOnLoad();</script>
</head>
<body>

<div class="container">
  <div class="row">
    <h1>Check if API-URL exists</h1>
    <div class="col-md-4">
      <h2>Hello API</h2>
    </div>
  </div>
  <div class="row">
    <?php


      function suhvDown() {

      //return FALSE; // all is OK
          date_default_timezone_set("Europe/Paris");
          $url = 'https://xxbuv.ch/api/games?mode=club&club_id=423403&season=2017';
          echo '<br>'.strftime("Time: %H:%M:%S");
          echo '<br>URL: ' . $url;
          //$url = 'http://www.example.com';
          $ch = curl_init($url);
          curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
          curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
          curl_setopt($ch, CURLOPT_TIMEOUT,5);
          $response = curl_exec($ch);
          $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
          $stringinbodyOK = strpos($response, 'Chur');
          $data = $response;
          curl_close($ch);

          if ($stringinbodyOK and ($httpcode == 200)) {$allOK = TRUE; echo "<br>API is OK!";}
          else { echo "<br>API NOT OK!"; echo "<br>HTTPCODE: ".$httpcode; echo "<br>String on: ".$stringinbodyOK;}
          echo '<br>'.strftime("Time: %H:%M:%S");
          return $stringinbodyOK;
      }
    ?>


    <?php

      $test = suhvDown(); 

    ?>

  </div>


</div>

</body>
</html>