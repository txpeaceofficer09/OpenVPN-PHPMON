# OpenVPN-PHPMON

## Download OpenVPN-PHPMON

git clone https://github.com/txpeaceofficer09/OpenVPN-PHPMON.git /var/www/html/

## Configuration

### Configure OpenVPN

Add the following line to your OpenVPN server configuration to run the
management console on 127.0.0.1 port 5555, with the management password
in /etc/openvpn/pw-file:

```
management 127.0.0.1 5555 pw-file
```

### Configure NGINX

Create an Nginx config in `/etc/nginx/sites-available/openvpn-monitor`

```
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    
    root /var/www/html;
    
    location / {
        try_files $uri $uri/ =404;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgii_pass unix:/run/php/php7.4-fpm.sock;
    }
}
```

### Configure OpenVPN-PHPMON

In the pass.inc.php file, change the $password and $pass variables to suite your setup. $password is used for logging into the web portal and $pass is the same as the contents of the pw-file for connecting to the OpenVPN management port.

```
<?php

$password = 'PASSWORD';
$pass = 'PASSWORD';

?>
```
