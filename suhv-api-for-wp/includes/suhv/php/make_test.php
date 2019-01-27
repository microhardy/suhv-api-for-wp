<!DOCTYPE html>
<html>
<body>

<h2>Use the XMLHttpRequest to get the content of a file.</h2>
<p>The content is written in JSON format, and can easily be converted into a JavaScript object.</p>

<p id="demo"></p>

<script>

var xmlhttp = new XMLHttpRequest();
xmlhttp.onreadystatechange = function() {
    document.getElementById("demo").innerHTML = "Hello";
    document.getElementById("demo").innerHTML += " " + this.status + " " + this.readyState;
    if (this.readyState == 4 && this.status == 200) {
        var myObj = JSON.parse(this.responseText);
        document.getElementById("demo").innerHTML += " " + myObj.name;
    }
};

xmlhttp.open("GET", "https://abstimmungen.gr.ch/vote/bundesbeschluss-ueber-die-neue-finanzordnung-2021/summary", true);
xmlhttp.setRequestHeader("Content-Type", "application/json");
xmlhttp.send();

</script>

<p>Take a look at <a href="https://abstimmungen.gr.ch/vote/bundesbeschluss-ueber-die-neue-finanzordnung-2021/summary" target="_blank">json_demo.txt</a></p>
<div id="demo"></div>

</body>
</html>
