
<?php

session_start();
require_once('pass.inc.php');

$mysqli = new mysqli('localhost', 'openvpn', 'password', 'openvpn'); // 

if (empty($_SESSION['password']) || $_SESSION['password'] !=  $password) die("<div align=\"center\">Login failed.</div>");

function stripPort($addr) {
	if (preg_match('/([0-9a-f:.]+):[0-9]+/i', $addr, $matches)) {
		return $matches[1];
	} else {
		return $addr;
	}
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
}

// $clients = json_decode(join('', file('/opt/openvpn-phpmon/clients.json')), true);

echo "<table><tr><th>Common Name</th><th>Public Address</th><th>Private Address</th><th>Received</th><th>Sent</th><th>Connected Since</th><!--<th></th>--></tr>";

$results = $mysqli->query("SELECT * FROM `connections` LEFT JOIN `addr` ON `addr`.`conn_id`=`connections`.`id` WHERE `connected`<>0 ORDER BY `date` DESC;");
if ($results && $results->num_rows > 0) {
	while ($row=$results->fetch_assoc()) {
		echo "<tr><td>".$row['name']."</td><td>".stripPort($row['public'])."</td><td>".$row['ip']."</td><td>".formatBytes($row['received'])."</td><td>".formatBytes($row['sent'])."</td><td>".$row['date']."</td></tr>";
	}
} else {
	echo "<tr><td colspan=\"6\"><i>No connections</i></td></tr>";
}

/*
foreach ($clients AS $id=>$row) {
	echo "<tr><td>".$row['common-name']."</td><td>".stripPort($row['address'])."</td><td>".$row['private']."</td><td>".formatBytes($row['received'])."</td><td>".formatBytes($row['sent'])."</td><td>".$row['connected']."</td><!--<td><a href=\"javascript:void(0);\" onClick=\"disconnect('".$row['common-name']."');\"><img src=\"icons8-disconnect.svg\" title=\"Kill all connections from ".$row['common-name']."\" /></td>--></tr>";
}
*/

echo "</table>";

if (@$mysqli) @$mysqli->close();

?>
