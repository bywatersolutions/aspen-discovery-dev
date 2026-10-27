# Running Aspen under crawler load

Crawlers and scrapers that ignore robots.txt, rotate addresses and send browser user agents cannot be blocked
outright. What Aspen can do is make each of their requests cheap, and keep a share of the server for patrons. This
guide covers what the server needs for that and the settings to change on servers that already exist.

## What a request costs

Measured on a 100,000 title catalog, logged out, with APCu installed. Queries are all MariaDB statements for the
request, writes are inserts, updates and deletes among them.

| Request | Before | After |
|---|---|---|
| Home page | 570 queries, 5 writes | 36 queries, 0 writes |
| Search results (20 titles) | 1,531 queries, 8 writes | 368 queries, 3 writes (0 for a crawler) |
| Grouped work page | 703 queries, 5 writes | 57 queries, 1 write (0 for a crawler) |
| Record page AJAX call | about 50 queries, 3 writes | about 30 queries, 0 writes |
| Cached cover image | 23 queries, 3 writes | 0 queries |
| robots.txt | 18 queries, 1 write | 8 queries, 0 writes |

Usage statistics are written in one batch every 10 seconds, so one request in each 10 seconds also writes those.

## APCu is required for most of this

Settings, translations, user agents, cover markers and usage counts are kept in APCu shared memory. Without the
php-apcu extension Aspen works exactly as before, with none of the savings.

New installs and the Docker image include it. On an existing Debian server:

```
apt install php8.4-apcu
```

Then add the `[opcache]` and `[apcu]` sections from `install/php.ini` to the server's php.ini
(`/etc/php/8.4/apache2/php.ini` for mod_php, `/etc/php/8.4/fpm/php.ini` for PHP-FPM) and restart Apache or PHP-FPM.
The important line is `apc.shm_size = 256M`: when APCu fills up it clears everything, and the 32M default is too small.

Check it from a browser on a debugging address: covers load with no queries in the query log once they have been
loaded twice.

Shared memory belongs to one web server, so cron jobs and the indexer cannot clear it. Anything they change shows up
within 5 minutes (10 minutes for cover images uploaded through the ILS). Changes made in the Aspen admin interface
show up right away on the server that handled the change. A site with more than one web server sees them on the others
within the same 5 or 10 minutes.

## Compressing covers

Covers are PNG images even though the URL ends in `.php`, and the site template used to gzip them again for
nothing. Add this to the `<IfModule mod_deflate.c>` block of `/usr/local/aspen-discovery/sites/<site>/httpd-<site>.conf`:

```
SetEnvIfNoCase Request_URI ^/bookcover\.php no-gzip dont-vary
```

## Moving to PHP-FPM

The Debian installer sets Aspen up on Apache prefork with mod_php, where every connection holds a whole PHP process
and a crawler flood takes all of them. PHP-FPM with the event MPM only uses a PHP worker while a script runs, and lets
requests that carry a session cookie (logged in patrons and staff) use a pool of their own.

```
cd /usr/local/aspen-discovery/install
sudo ./migrate_debian_to_php_fpm.sh <sitename> <anonymous workers> <session workers>
```

Each worker needs about 80M. Take the memory left after MariaDB and Solr, divide by 80M, and give about two thirds to
anonymous workers. The script starts PHP-FPM before it changes Apache, keeps a backup of the site's Apache config, and
explains at the top how to go back. It also raises Apache's ProxyTimeout to an hour, since mod_php never cut off a
long request.

## Behind Cloudflare

### Real client addresses

Behind a proxy every request comes from the proxy's address, so IP blocking, the spam checks and the usage by IP
report all see Cloudflare instead of the client. Enable mod_remoteip so Apache uses `CF-Connecting-IP`:

```
a2enmod remoteip
(curl -s https://www.cloudflare.com/ips-v4; echo; curl -s https://www.cloudflare.com/ips-v6) > /etc/apache2/cloudflare-ips.txt
cat > /etc/apache2/conf-available/cloudflare-remoteip.conf <<'EOF'
RemoteIPHeader CF-Connecting-IP
RemoteIPTrustedProxyList /etc/apache2/cloudflare-ips.txt
LogFormat "%a %l %u %t \"%r\" %>s %O \"%{Referer}i\" \"%{User-Agent}i\"" combined
EOF
a2enconf cloudflare-remoteip
systemctl reload apache2
```

Refresh `cloudflare-ips.txt` now and then; Cloudflare publishes changes at those two addresses. Only do this on servers
that are reached through Cloudflare, otherwise any client can claim any address.

### Cache rules

- Cover images: a Cache Rule for URI path `/bookcover.php` set to Eligible for cache, using the origin's cache control.
  Covers are sent with `Cache-Control: public, max-age=1209600`, so Cloudflare keeps them for two weeks.
- Static files: the same for URI paths starting with `/js/`, `/images/`, `/fonts/` and `/interface/themes/`.
- Do not cache HTML pages. They depend on cookies, language and login.

### Rate limiting

A rate limiting rule per client address on URI paths starting with `/Search/`, `/GroupedWork/`, `/Record/`, `/Author/`,
`/Series/` and `/Union/`, with a managed challenge for 10 minutes above about 120 requests a minute. Add
`and not cf.client.bot` to the rule's expression so search engines Cloudflare has verified are not challenged.

On plans with complexity based rate limiting, set `enableComplexityHeader = true` in the `[Security]` section of the
site's config.ini and count the `x-complexity-score` response header instead of requests. Search results pages count
25, record pages 15 and everything else less.

### Bot settings

Super Bot Fight Mode can block "definitely automated" traffic and challenge "likely automated" traffic. Add a skip
rule for URI paths starting with `/API/` first: Aspen LiDA and integrations call the API without a browser and would
otherwise be blocked. Bot Fight Mode on the free plan cannot be scoped like that, so leave it off.

## MariaDB

Every write is flushed to disk before it returns. With the usage counters now written in batches there are far fewer
writes, but servers that are short on disk speed can set this in `/etc/mysql/mariadb.conf.d/60-aspen.cnf`:

```
innodb_flush_log_at_trx_commit = 2
```

Writes then reach the disk once a second instead of on every commit. A crash of MariaDB itself loses nothing; a power
loss or kernel crash can lose up to the last second of writes.

## Measuring

- Add your address in Admin > IP Addresses with Log All Queries and Log Timing Information on, then add `?logQueries`
  to a URL to log every query that page runs to messages.log.
- Admin > User Agents and Usage by User Agent show how much traffic comes from crawlers.
- How often the same URLs are asked for, from the Apache access log (the share of requests that repeat another one):

```
awk '{print $7}' access.log | sort | uniq -c | awk '{t+=$1; if($1>1) r+=$1} END {print r/t}'
awk '{print $7}' access.log | sed 's/?.*//' | sort | uniq -c | sort -rn | head -30
awk -F'"' '{print $6}' access.log | sort | uniq -c | sort -rn | head -30
```

## During a flood

In order:

1. Add any self-identified offenders to the site's `conf/badBotsLocal.conf`. Apache answers them with a 403 before PHP
   starts.
2. Turn on the Cloudflare rate limiting rule above, or tighten it.
3. Keep `trackIpAddresses` off in System Variables; it adds work to every request.
4. Move the server to PHP-FPM so logged in patrons keep their own workers.
