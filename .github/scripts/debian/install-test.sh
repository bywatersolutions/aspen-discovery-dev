#!/bin/bash

# install-test.sh -- end to end test of the aspen-discovery package:
# install, site creation, module sync, supervision, reboot, legacy guard.
#
# usage: install-test.sh <debs-dir>
# <debs-dir> must contain aspen-discovery_*_all.deb and test-site.ini.

set -euo pipefail
cd "$(dirname "$0")"
. ./lib.sh

DEBS=$(cd "${1:?usage: install-test.sh <debs-dir>}" && pwd)
C=${ASPEN_TEST_CONTAINER:-aspen-ci-install}

banner "boot and install"
boot_container "$C" "$DEBS"
install_deb "$C"
in_container "$C" 'systemctl is-enabled --quiet aspen.target' || fail "aspen.target not enabled"
units=$(in_container "$C" 'ls /usr/lib/systemd/system/ | grep -c "^aspen" || true')
[ "$units" -ge 25 ] || fail "expected at least 25 aspen units, found $units"
in_container "$C" 'ls /usr/share/man/man8/aspen-svc.8.gz >/dev/null' || fail "manpages missing"
echo "installed: $units units, target enabled, manpages present"

banner "create site"
setup_local_db "$C"
create_site "$C"
check_site_up "$C"
keepalives=$(in_container "$C" "grep -c 'checkSolr\|checkBackgroundProcesses' /usr/local/aspen-discovery/sites/$SITE/conf/crontab_settings.txt || true")
[ "$keepalives" = 0 ] || fail "cron keep-alive lines not stripped ($keepalives left)"
modules=$(in_container "$C" "systemctl list-units --all 'aspen-*@$SITE.service' --no-legend | grep -cv -e create-dirs -e sync-modules -e solr || true")
[ "$modules" -ge 1 ] || fail "no module units loaded for the site"
echo "crontab clean, $modules module units loaded"

banner "module sync via database and flag file"
MOD=user_list_indexer
in_container "$C" "mysql aspen -e 'UPDATE modules SET enabled=0 WHERE backgroundProcess=\"$MOD\"'
    touch /data/aspen-discovery/$SITE/module_change.flag"
sleep 6
in_container "$C" "systemctl is-enabled --quiet aspen-$MOD@$SITE.service" && fail "$MOD still enabled after DB disable"
in_container "$C" "systemctl is-active --quiet aspen-$MOD@$SITE.service" && fail "$MOD still running after DB disable"
in_container "$C" "mysql aspen -e 'UPDATE modules SET enabled=1 WHERE backgroundProcess=\"$MOD\"'
    touch /data/aspen-discovery/$SITE/module_change.flag"
sleep 6
in_container "$C" "systemctl is-enabled --quiet aspen-$MOD@$SITE.service" || fail "$MOD not re-enabled"
in_container "$C" "test ! -e /data/aspen-discovery/$SITE/module_change.flag" || fail "flag file not consumed"
echo "module sync works both directions, flag consumed"

banner "supervision"
in_container "$C" "kill -9 \$(systemctl show -p MainPID --value aspen-solr@$SITE.service)"
wait_unit_active "$C" "aspen-solr@$SITE.service" 60
echo "solr recovered from kill -9"
in_container "$C" "kill \$(systemctl show -p MainPID --value aspen-solr@$SITE.service)"
wait_unit_active "$C" "aspen-solr@$SITE.service" 60
echo "solr recovered from external SIGTERM (Restart=always)"
in_container "$C" "systemctl stop aspen-solr@$SITE.service"
sleep 12
in_container "$C" "systemctl is-active --quiet aspen-solr@$SITE.service" && fail "solr restarted after explicit stop"
echo "explicit stop stays stopped"
in_container "$C" "systemctl stop aspen@$SITE.target"
sleep 3
running=$(in_container "$C" "systemctl list-units 'aspen-*@$SITE.service' --state=running --no-legend | grep -c . || true")
[ "$running" = 0 ] || fail "$running units still running after target stop"
echo "target stop tears everything down (PartOf)"
in_container "$C" "systemctl start aspen@$SITE.target"
wait_unit_active "$C" "aspen-solr@$SITE.service" 90

banner "reboot"
docker restart "$C" >/dev/null
i=0
until in_container "$C" 'systemctl is-system-running 2>/dev/null | grep -qE "running|degraded"'; do
    i=$((i + 1)); [ "$i" -gt 60 ] && fail "systemd did not return after reboot"; sleep 2
done
check_site_up "$C"
echo "everything came back after reboot"

banner "legacy guard"
in_container "$C" "cd /usr/local/aspen-discovery/code/web/cron &&
    php checkBackgroundProcesses.php $SITE | grep -q 'supervised by systemd' &&
    php checkSolr.php $SITE | grep -q 'supervised by systemd'" ||
    fail "cron keep-alive scripts did not exit early on systemd"
echo "cron keep-alive scripts exit early"

docker rm -f "$C" >/dev/null
banner "install test PASSED"
