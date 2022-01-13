  GNU nano 4.8                                                                                                                       list.php                                                                                                                                  

<?php

session_start();
require_once('pass.inc.php');

$mysqli = new mysqli('localhost', 'openvpn', '$3rv!C3$', 'openvpn');

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

echo "<table><tr><th>Common Name</th><th>Public Address</th><th>Private Address</th><th>Received</th><th>Sent</th><th>Connected Since</th><!--<th></th>--></tr>";

// $results = $mysqli->query("SELECT * FROM `connections` LEFT JOIN `addr` ON `addr`.`conn_id`=`connections`.`id` WHERE `connected`<>0 ORDER BY `date` DESC;");
$results = $mysqli->query("SELECT * FROM `connections` WHERE `connected`=1 ORDER BY `date` DESC;");
if ($results && $results->num_rows > 0) {
        while ($row=$results->fetch_assoc()) {
                echo "<tr><td>".$row['name']."</td><td>".stripPort($row['public'])."</td><td>";
                $result = $mysqli->query("SELECT * FROM `addr` WHERE `conn_id`=".$row['id'].";");
                while ($addr=$result->fetch_assoc()) {
                        echo "<div>".$addr['ip']."</div>";
                }
                echo "</td><td>".formatBytes($row['received'])."</td><td>".formatBytes($row['sent'])."</td><td>".$row['date']."</td></tr>";
        }
} else {
        echo "<tr><td colspan=\"6\"><i>No connections</i></td></tr>";
}

echo "</table>";

if (@$mysqli) @$mysqli->close();

?>
