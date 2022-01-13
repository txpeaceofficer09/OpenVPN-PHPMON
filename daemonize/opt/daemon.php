#!/usr/bin/env php
<?php

// require_once('/var/www/openvpn/pass.inc.php');

// Open connection to management port and keep it open and loop with a sleep(2) and keep getting status and parsing for data and putting it in MySQL.
// Use this daemon to be able to send updates via e-mail when clients connect/disconnect.

define('DEBUG', false); // true = verbose logging, false = no verbose logging
define('HOST', '127.0.0.1'); // OpenVPN server
define('PORT', 5555); // OpenVPN monitor port
define('EMAIL', 'someone@example.com'); // E-Mail address we will send connect/disconnect messages to.
define('FROM', 'no-reply@example.com'); // Reply to/From address for our e-mails.
define('PASS', 'password'); // Password for the management interface of the OpenVPN server.
define('MYSQL_HOST', 'localhost'); // MySQL server
define('MYSQL_USER', 'openvpn'); // MySQL user
define('MYSQL_PASS', 'password'); // MySQL password
define('MYSQL_DB', 'openvpn'); // MySQL database

$mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB); // Open a connection to MySQL to store our data.

function getCIDfromNameDate($name, $date) {
	$date = fixTimestamp($date);
	$retVal = null;

	$result = $mysqli->query("SELECT `id` FROM `connections` WHERE `name`='$name' AND `date`='$date' LIMIT 1;");
	if ($result && $result->num_rows == 1) {
		$retVal = $result->fetch_object()->id;
	}
	return $retVal;
}

function debug($msg) {
	echo DEBUG == true ? "$msg\n" : ""; // if DEBUG is true echo the debug message.
}

function fixTimestamp($timestamp) {
	return date('Y-m-d H:i:s', strtotime($timestamp)); // Convert the timestamp to standard date time format.
}

function getCIDfromAddr($public) {
	global $mysqli;

	return $mysqli->query("SELECT `id` FROM `connections` WHERE `public`='$public' LIMIT 1;")->fetch_object()->id;
}

function updateClient($cid, $private) {
	global $mysqli;
	debug("Updating $cid with $private");

	$query = "SELECT `ip` FROM `addr` WHERE `conn_id`=$cid AND `ip`='$private' LIMIT 1;";
	$result = $mysqli->query($query);
	if ($result && $result->num_rows > 0) {
		debug($query);
	} else {
		debug($query);
		debug($mysqli->error);
		$query = "INSERT INTO `addr` (`conn_id`, `ip`) VALUES ($cid, '$private');";
		$mysqli->query($query);
		debug($query);
		debug($mysqli->error);
	}
	// $mysqli->query("UPDATE `connections` SET `connected`=1 WHERE `id`=$cid LIMIT 1;");
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
	$date = fixTimestamp($date);

	// $query = "SELECT `connections`.`id` FROM `connections` LEFT JOIN `addr` ON `addr`.`conn_id`=`connections`.`id` WHERE `public`='$addr' LIMIT 1;";
	$query = "SELECT `id` FROM `connections` WHERE `name`='$name' AND `date`='$date' LIMIT 1;";
	$result = $mysqli->query($query);
	if ($result && $result->num_rows == 1) {
		if (mail(EMAIL, 'OpenVPN new client', "$name\t$addr\t".fixTimestamp($date), "From: OpenVPN<".FROM.">\r\nReply-To: OpenVPN<".FROM.">\r\nX-Mailer: PHP/".phpversion())) {
			debug("Successfully sent connect e-mail for $name.");
		} else {
			debug("Failed to send connect e-mail for $name.");
		}
		$cid = $result->fetch_object()->id;
		$query = "UPDATE `connections` SET `received`='$received', `sent`='$sent', `date`='$date', `connected`=1 WHERE `id`=$cid LIMIT 1;";
		if ($mysqli->query($query)) {
			debug("Updated $name\t$addr\t$received\t$sent\t$date");
		} else {
			deubg($query);
			debug($mysqli->error);
		}
	} else {
		debug($query);
		debug($mysqli->error);
		$query = "INSERT INTO `connections` (`name`, `public`, `sent`, `received`, `connected`, `date`) VALUES ('$name', '$addr', '$sent', '$received', 1, '".fixTimestamp($date)."');";
		if ($mysqli->query($query)) {
			debug($query);
			$cid = $mysqli->insert_id;
			$mysqli->query("INSERT INTO `log` (`conn_id`, `action`) VALUES ($cid, 'connect');");
		} else {
			debug($query);
			debug($mysqli->error);
		}
	}

	return $cid;
}

if ($socket=fsockopen(HOST, PORT, $errno, $errstr, 5)) {
	stream_set_blocking($socket, false);
	fputs($socket, "\r\n");
	readTo($socket, "PASSWORD:");
	fputs($socket, PASS."\r\n");
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
				debug(print_r($matches, true));
			} elseif (preg_match('/^([0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $line, $matches)) {
				$cid = getCIDfromAddr($matches[3]);
				updateClient($cid, $matches[1]);
				debug(print_r($matches, true));
			} elseif (preg_match('/^([a-f0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $line, $matches)) {
				debug(print_r($matches, true));
				$cid = getCIDfromAddr($matches[3]);
				updateClient($cid, $matches[1]);
			} else {
				debug($line);
			}
			// debug($line);
		}
		$query = "SELECT * FROM `connections` WHERE `connected`=1 AND `public` NOT IN ('".join("', '", $clients)."');";
		$result = $mysqli->query($query);
		if ($result && $result->num_rows > 0) {
			debug($query);
			while ($row=$result->fetch_assoc()) {
				if (mail(EMAIL, 'OpenVPN client disconnected', $row['name']."\t".$row['public'], "From: OpenVPN<".FROM.">\r\nReply-To: OpenVPN<".FROM.">\r\nX-Mailer: PHP/".phpversion())) {
					debug("Successfully send disconnect e-mail for ".$row['name'].".");
				} else {
					debug("Failed to send disconnect e-mail for ".$row['name'].".");
				}
				$query = "INSERT INTO `log` (`conn_id`, `action`) VALUES (".$row['id'].", 'disconnected');";
				if ($mysqli->query($query)) {
					debug($query);
				} else {
					debug($query);
					debug($mysqli->error);
				}
			}
		} else {
			debug($query);
			debug($mysqli->error);
		}
		$query = "UPDATE `connections` SET `connected`=0 WHERE `connected`=1 AND `public` NOT IN ('".join("', '", $clients)."');";
		if ($mysqli->query($query)) {
			debug($query);
		} else {
			debug($query);
			debug($mysqli->error);
		}
		unset($clients); // Empty the $clients array since we are done with it, so we don't end up with left over entries on the next loop iteration.
		unset($cid); // Clear $cid since we don't need it anymore.
		sleep(2); // Wait 2 seconds before processing the loop again.
	} while ($socket); // Keep iterating the loop so long as our socket is still connected.
}

if ($mysqli) @$mysqli->close(); // We are finished so close our connection to the database backend.

?>
