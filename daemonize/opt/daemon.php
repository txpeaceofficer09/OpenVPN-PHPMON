#!/usr/bin/env php
<?php

$host = '127.0.0.1'; // Host to connect to the management port on.
$port = 5555; // Port the host is listening on for management traffic.
$pass = 'PASSWORD'; // Password to connect to the OpenVPN management port.
$email = 'someone@example.com'; // E-mail address to send alerts to.
$from = 'no-reply@example.com'; // E-mail address to send the alerts from.
$replyto = 'no-reply@example.com'; // E-mail address to reply to alerts.
$headers = "From: $from\r\nReply-To: $replyto\r\nX-Mailer: PHP/".phpversion();

function fixTimestamp($timestamp) {
	return date('Y-m-d H:i:s', strtotime($timestamp));
}

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

function diff_recursive($array1, $array2) {
	$difference = [];

	foreach ($array2 AS $key=>$value) {
		$found =  false;
		foreach ($array1 AS $k=>$v) {
			if ($value['common-name'] == $v['common-name'] && $value['address'] == $v['address']) {
				$found = true;
				break;
			}
		}
		if ($found == false) $difference[] = $value;
	}

	return $difference;
}

function compareClients() {
	global $clients;

	$tbl = json_decode(join('', file('/opt/openvpn-phpmon/clients.json')), true);

	$newClients = diff_recursive($clients, $tbl);
	$lostClients = diff_recursive($tbl, $clients);

	// Send e-mails for new clients.
	if (count($newClients) > 0) mail($email, 'OpenVPN new client(s)', print_r($newClients, true), $headers);
	// Send e-mails for disconnected clients.
	if (count($lostClients) > 0) mail($email, 'OpenVPN disconnected client(s)', print_r($lostClients, true), $headers);
}

if ($socket=fsockopen($host, $port, $errno, $errstr, 5)) {
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
			if (preg_match('/^([a-z\-0-9]+),([a-f0-9.:]+),([0-9]+),([0-9]+),([a-z\s:0-9]+)$/i', $file[$i], $matches)) {
				$clients[] = ['common-name'=>$matches[1], 'address'=>$matches[2], 'received'=>$matches[3], 'sent'=>$matches[4], 'connected'=>fixTimestamp($matches[5])];
			} elseif (preg_match('/^([0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $file[$i], $matches)) {
				updateClient($matches[3], $matches[1]);
			} elseif (preg_match('/^([a-f0-9:.]+),([a-z\-.0-9]+),([0-9a-f.:]+),([a-z\s:0-9]+)$/i', $file[$i], $matches)) {
				updateClient($matches[3], $matches[1]);
			}
		}
		compareClients();
		if ($fp=fopen('/opt/openvpn-phpmon/clients.json', 'w')) {
			fputs($fp, json_encode($clients));
			fclose($fp);
		}
		unset($clients);
		sleep(2);
	} while ($socket);
}

?>
