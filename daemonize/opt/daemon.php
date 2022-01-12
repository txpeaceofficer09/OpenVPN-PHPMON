#!/usr/bin/env php
<?php

// require_once('/var/www/openvpn/pass.inc.php');

// Open connection to management port and keep it open and loop with a sleep(2) and keep getting status and parsing for data and putting it in MySQL.
// Use this daemon to be able to send updates via e-mail when clients connect/disconnect.
// Possibly look into making the do loop work while $socket is open.

$pass = 'password'; // Password for the management interface of the OpenVPN server.
$email = 'someone@example.com'; // E-Mail address we will send connect/disconnect messages to.

$mysqli = new mysqli('localhost', 'openvpn', 'password', 'openvpn');

function fixTimestamp($timestamp) {
	return date('Y-m-d H:i:s', strtotime($timestamp));
}

function getCIDfromAddr($public) {
	global $mysqli;

	return $mysqli->query("SELECT `id` FROM `connections` WHERE `public`='$public' LIMIT 1;")->fetch_object()->id;
}

function updateClient($cid, $private) {
	global $mysqli;

	$result = $mysqli->query("SELECT `ip` FROM `addr` WHERE `conn_id`=$cid LIMIT 1;");
	if (!$result || $result->num_rows == 0) {
		$mysqli->query("INSERT INTO `addr` (`conn_id`, `ip`) VALUES ($cid, '$private');");
	}
	$mysqli->query("UPDATE `connections` SET `connected`=1 WHERE `id`=$cid LIMIT 1;");
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

function createClient($name, $addr, $received, $sent, $date) {
	global $mysqli;
	global $email;

	$result = $mysqli->query("SELECT `connections`.`id` FROM `connections` LEFT JOIN `addr` ON `addr`.`conn_id`=`connections`.`id` WHERE `public`='$addr' LIMIT 1;");
	if ($result && $result->num_rows == 1) {
		mail($email, 'OpenVPN new client', "$name\t$addr\t".fixTimestamp($date), "From: OpenVPN<no-reply@trap-nine.com\r\nReply-To: OpenVPN<no-reply@trap-nine.com>\r\nX-Mailer: PHP/".phpversion());
		$cid = $result->fetch_object()->id;
		if ($mysqli->query("UPDATE `connections` SET `received`='$received', `sent`='$sent', `date`='".fixTimestamp($date)."', `connected`=1 WHERE `id`=$cid LIMIT 1;")) {
			// echo "Updated $name\t$addr\t$received\t$sent\t$date\n";
		} else {
			// echo $mysqli->error."\n";
		}
	} else {
		$mysqli->query("INSERT INTO `connections` (`name`, `public`, `sent`, `received`, `connected`, `date`) VALUES ('$name', '$addr', '$sent', '$received', 1, '".fixTimestamp($date)."');");
		$cid = $mysqli->insert_id;
		$mysqli->query("INSERT INTO `log` (`conn_id`, `action`) VALUES ($cid, 'connect');");
	}

	return $cid;
}

if ($socket=fsockopen('127.0.0.1', 5555, $errno, $errstr, 5)) {
	stream_set_blocking($socket, false);
	fputs($socket, "\r\n");
	readTo($socket, "PASSWORD:");
	fputs($socket, $pass."\r\n");
	readTo($socket, "info");
	do {
		fputs($socket, "status\r\n");
		$output = readTo($socket, "END");
		$file = explode("\r\n", $output);
		$clients = [];
		for ($i=0;$i<count($file);$i++) {
			$line = trim($file[$i]);
			if (preg_match('/^([a-z\-0-9]+),([a-f0-9.:]+),([0-9]+),([0-9]+),([a-z\s:0-9]+)$/i', $line, $matches)) {
				$clients[] = $matches[2];
				$cid = createClient($matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
			} elseif (preg_match('/^([0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $line, $matches)) {
				$cid = getCIDfromAddr($matches[3]);
				updateClient($cid, $matches[1]);
			} elseif (preg_match('/^([a-f0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $line, $matches)) {
				$cid = getCIDfromAddr($matches[3]);
				updateClient($cid, $matches[1]);
			}
			// echo "$line\n";
		}
		$query = "SELECT * FROM `connections` WHERE `connected`=1 AND `public` NOT IN ('".join("', '", $clients)."');";
		$result = $mysqli->query($query);
		if ($result && $result->num_rows > 0) {
			while ($row=$result->fetch_assoc()) {
				mail($email, 'OpenVPN client disconnected', $row['name']."\t".$row['public'], "From: OpenVPN<no-reply@trap-nine.com\r\nReply-To: OpenVPN<no-reply@trap-nine.com>\r\nX-Mailer: PHP/".phpversion());
				$mysqli->query("INSERT INTO `log` (`conn_id`, `action`) VALUES (".$row['id'].", 'disconnected');");
			}
		}
		$mysqli->query("UPDATE `connections` SET `connected`=0 WHERE `connected`=1 AND `public` NOT IN ('".join("', '", $clients)."');");
		unset($clients); // Empty the $clients array since we are done with it, so we don't end up with left over entries on the next loop iteration.
		unset($cid); // Clear $cid since we don't need it anymore.
		sleep(2); // Wait 2 seconds before processing the loop again.
	} while ($socket); // Keep iterating the loop so long as our socket is still connected.
}

if (@$mysqli) @$mysqli->close(); // We are finished so close our connection to the database backend.

?>
