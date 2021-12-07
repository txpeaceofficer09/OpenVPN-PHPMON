
<?php

session_start();
require_once('pass.inc.php');

if (empty($_SESSION['password']) || $_SESSION['password'] !=  $password) die("<div align=\"center\">Login failed.</div>");

function updateClient($public, $private) {
	global $clients;
	foreach ($clients AS $id=>$row) {
		if ($row['address'] == $public) {
			if (empty($clients[$id]['private'])) {
				$clients[$id]['private'] = $private;
				// break;
			} else {
				$clients[$id]['private'] .= ", $private";
			}
		}
	}
}

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

function readTo($stream, $prompt) {
        $buf = '';

        if (gettype($prompt) == 'array') {
                do {
                        $buf .= fgetc($stream);
                } while (!in_array(substr($buf, strlen($buf)-strlen($prompt[0])), $prompt));
        } else {
                do {
                        $buf .= fgetc($stream);
                } while (substr($buf, strlen($buf)-strlen($prompt)) != $prompt);
        }

        return $buf;
}

function checkTelnet($host, $port, $pass, $disconnect = '') {
        if ($fp=fsockopen($host, $port, $errno, $errstr, 1)) {
                stream_set_blocking($fp, false);
                fputs($fp, "\r\n");
                readTo($fp, "PASSWORD:");
                fputs($fp, $pass."\r\n");
                readTo($fp, "info");
                fputs($fp, "status\r\n");
                $output = readTo($fp, "END");

		if (!empty($disconnect)) {
			fputs($fp, "kill $disconnect\r\n");
		}

                fputs($fp, "quit\r\n");

                fclose($fp);
        }

        return isset($output) ? $output : '';
}

if (!empty($_REQUEST['disconnect'])) {
	$output = checkTelnet('127.0.0.1', 5555, 'jam87421', $_REQUEST['disconnect']);
} else {
	$output = checkTelnet('127.0.0.1', 5555, 'jam87421');
}
// echo $output;
$file = explode("\r\n", $output);

// $file = file('/var/log/openvpn/status.log');
$clients = [];

for ($i=0;$i<count($file);$i++) {
	if (preg_match('/^([a-z\-0-9]+),([a-f0-9.:]+),([0-9]+),([0-9]+),([a-z\s:0-9]+)$/i', $file[$i], $matches)) {
		$clients[] = ['common-name'=>$matches[1], 'address'=>$matches[2], 'received'=>$matches[3], 'sent'=>$matches[4], 'connected'=>$matches[5]];
	} elseif (preg_match('/^([0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $file[$i], $matches)) {
		updateClient($matches[3], $matches[1]);
	} elseif (preg_match('/^([a-f0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $file[$i], $matches)) {
		updateClient($matches[3], $matches[1]);
	}
}

// print_r($clients);

echo "<table><tr><th>Common Name</th><th>Public Address</th><th>Private Address</th><th>Received</th><th>Sent</th><th>Connected Since</th><!--<th></th>--></tr>";

foreach ($clients AS $id=>$row) {
	echo "<tr><td>".$row['common-name']."</td><td>".stripPort($row['address'])."</td><td>".$row['private']."</td><td>".formatBytes($row['received'])."</td><td>".formatBytes($row['sent'])."</td><td>".$row['connected']."</td><!--<td><a href=\"javascript:void(0);\" onClick=\"disconnect('".$row['common-name']."');\"><img src=\"icons8-disconnect.svg\" title=\"Kill all connections from ".$row['common-name']."\" /></td>--></tr>";
}

echo "</table>";

?>
