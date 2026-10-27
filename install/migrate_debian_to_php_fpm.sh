#!/bin/sh
# Move a Debian Aspen server from Apache prefork with mod_php to the event MPM with PHP-FPM.
#
# Under prefork every connection holds a whole PHP process, including idle keep-alive connections and requests for
# covers, so memory caps how many connections Apache can take and a crawler flood takes every one of them. With the
# event MPM, Apache threads hold connections cheaply and PHP workers are only busy while a script runs.
#
# Requests that carry an aspen_session cookie (logged in patrons and staff, and visitors who have been browsing) are
# sent to their own pool, so they still get a PHP worker when crawlers fill the anonymous pool.
#
# Usage: sudo ./migrate_debian_to_php_fpm.sh <sitename> [anonymous workers] [session workers] [php version]
#
# Size the pools from memory: each worker needs about 80M, so (RAM - MariaDB - Solr) / 80M workers in total,
# roughly two thirds for anonymous traffic. The defaults suit a server with about 4G for PHP.
#
# To go back: restore the httpd-<sitename>.conf.mod_php backup this script makes, then
#   a2disconf aspen-php-fpm && a2dismod mpm_event proxy_fcgi && a2enmod mpm_prefork php<version> && systemctl restart apache2

set -e

if [ -z "$1" ]; then
	echo "Usage: $0 <sitename> [anonymous workers] [session workers] [php version]"
	exit 1
fi
sitename="$1"
anonymous_workers="${2:-32}"
session_workers="${3:-16}"
php_vers="${4:-8.4}"

site_conf="/usr/local/aspen-discovery/sites/$sitename/httpd-$sitename.conf"
if [ ! -f "$site_conf" ]; then
	echo "Could not find $site_conf"
	exit 1
fi
if ! grep -q 'SetHandler "proxy:fcgi://{phpFpmHost}:{phpFpmPort}"' "$site_conf"; then
	echo "$site_conf does not have the PHP-FPM handler from the site template, update it by hand:"
	echo "see the <IfModule mod_proxy_fcgi.c> block in sites/template.linux/httpd-{sitename}.conf"
	exit 1
fi

install_dir=$(dirname "$(readlink -f "$0")")

echo "Installing PHP $php_vers FPM"
apt-get update -q
DEBIAN_FRONTEND=noninteractive apt-get install -y -q "php${php_vers}-fpm" "php${php_vers}-apcu"

# The same settings mod_php uses
cp "$install_dir/php.ini" "/etc/php/$php_vers/fpm/php.ini"

# Replace the stock pool with Aspen's two pools
pool_dir="/etc/php/$php_vers/fpm/pool.d"
if [ -f "$pool_dir/www.conf" ]; then
	mv "$pool_dir/www.conf" "$pool_dir/www.conf.disabled"
fi

# There is no request_terminate_timeout, the same as mod_php, so long requests such as imports and reports run by staff
# are not cut off. Spare workers have to fit inside pm.max_children or PHP-FPM refuses the pool.
write_pool() {
	pool_name="$1"
	socket="$2"
	max_children="$3"
	min_spare=$(( max_children / 8 > 0 ? max_children / 8 : 1 ))
	max_spare=$(( max_children / 2 > min_spare ? max_children / 2 : min_spare ))
	cat >> "$pool_dir/aspen.conf" <<POOL

[$pool_name]
listen = /run/php/$socket.sock
listen.owner = www-data
listen.group = www-data
user = www-data
group = www-data
pm = dynamic
pm.max_children = $max_children
pm.start_servers = $min_spare
pm.min_spare_servers = $min_spare
pm.max_spare_servers = $max_spare
pm.max_requests = 500
chdir = /usr/local/aspen-discovery/code/web
; Moved here from the mod_php block of the site's Apache config. use_cookies is php_value so Aspen can skip the
; session cookie for crawlers.
php_value[session.use_cookies] = 1
php_admin_value[session.use_only_cookies] = 1
php_admin_value[session.auto_start] = 0
php_admin_value[session.cookie_lifetime] = 0
php_admin_value[session.gc_maxlifetime] = 6000
POOL
}
echo "; Created by migrate_debian_to_php_fpm.sh. Requests with a session cookie get their own pool so crawlers cannot use up every worker." > "$pool_dir/aspen.conf"
write_pool aspen_anonymous aspen-anonymous "$anonymous_workers"
write_pool aspen_session aspen-session "$session_workers"

# Start PHP-FPM before touching Apache, so if it does not come up the server is still running on mod_php
php-fpm"$php_vers" -t
systemctl enable "php${php_vers}-fpm"
systemctl restart "php${php_vers}-fpm"

# mod_php had no time limit on a request, but Apache gives up on a proxied one after Timeout (300 seconds on Debian)
cat > /etc/apache2/conf-available/aspen-php-fpm.conf <<'CONF'
# Created by migrate_debian_to_php_fpm.sh so long staff requests such as imports are not cut off with a 504
ProxyTimeout 3600
CONF

echo "Sending PHP requests to the pools from $site_conf"
cp "$site_conf" "$site_conf.mod_php"
sed -i 's#^\([[:space:]]*\)SetHandler "proxy:fcgi://{phpFpmHost}:{phpFpmPort}"#\1<If "%{HTTP_COOKIE} =~ /aspen_session=/">\n\1\tSetHandler "proxy:unix:/run/php/aspen-session.sock|fcgi://aspen-session"\n\1</If>\n\1<Else>\n\1\tSetHandler "proxy:unix:/run/php/aspen-anonymous.sock|fcgi://aspen-anonymous"\n\1</Else>#' "$site_conf"

echo "Switching Apache to the event MPM"
a2dismod -q "php${php_vers}" mpm_prefork || true
a2enmod -q mpm_event proxy_fcgi setenvif
a2enconf -q aspen-php-fpm

if ! apache2ctl configtest; then
	echo "Apache configuration test failed, restoring $site_conf and mod_php"
	mv "$site_conf.mod_php" "$site_conf"
	a2disconf -q aspen-php-fpm
	a2dismod -q mpm_event proxy_fcgi
	a2enmod -q mpm_prefork "php${php_vers}"
	exit 1
fi

systemctl restart apache2
echo "Done. $sitename now runs on PHP-FPM with $anonymous_workers anonymous and $session_workers session workers."
