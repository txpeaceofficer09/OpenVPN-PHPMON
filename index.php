<?php

session_start();
require_once('pass.inc.php');

if (isset($_REQUEST['logout'])) $_SESSION['password'] = NULL;

?>
<html>
<head>
<title>OpenVPN - PHPMON</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<style>

body {
	font-family: Verdana, Arial, Sans-Serif;
	max-width: 80%;
	margin: 20px auto;
}

table {
	width: 100%;
}

table, tr, td, th {
	border-collapse: collapse;
	border: 1px solid #000;
}

td, th {
	padding: 5px;
}

th {
	background: #acf;
}

h1 {
	border-style: solid;
	border-color: #000;
	border-width: 0px 0px 1px 0px;
	background-image: linear-gradient(white, grey);
	color: #0af;
	padding: 20px;
	text-shadow: 2px 2px 0px #036;
}

h1 img {
	vertical-align: middle;
}

h1 button {
	float: right;
	padding: 10px;
	border-color: #0c3c6c;
	border-radius: 5px;
	background-image: linear-gradient(#acf, #036);
	color: #fff;
	text-shadow: 1px 1px 0px #000;
	vertical-align: middle;
}

h1 button:hover {
	background-image: linear-gradient(#036, #acf);
}

</style>
</head>
<body>
<?php

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	if ($_REQUEST['password'] != $password) die("<div align=\"center\">Login failed.</div>");
	$_SESSION['password'] = $_REQUEST['password'];
}

if (empty($_SESSION['password']) || $_SESSION['password'] != $password) {
	echo "<div align=\"center\"><form action=\"index.php\" method=\"POST\"><input type=\"password\" placeholder=\"Password\" name=\"password\" /> <input type=\"submit\" value=\"Login\" /></form></div>";
	die();
}

echo "	<h1><button type=\"button\" onClick=\"top.location.href = '/index.php?logout';\">Log Out</button><img src=\"icons8-heart-monitor-96.png\" /> OpenVPN - PHP Monitor</h1>";

?>

<script>

async function disconnect(addr) {
	$.get('/list.php?_=' + Math.round(new Date.getTime()/1000) + '&disconnect=' + addr, function(data, status) {
		$('#list').html(data);
	});
}

async function doLoop() {
	$.get('/list.php?_=' + Math.round(new Date().getTime()/1000), function(data, status) {
	// $.post('/list.php?_=' + Math.round(new Date().getTime()/1000), {password: '<?php echo $_REQUEST['password']; ?>'},  function(data, status) {
		$('#list').html(data);
	});
}

$(document).ready(function() {
	doLoop();
	setInterval("doLoop();", 2500);
});

</script>

<div id="list"></div>
</body>
</html>
