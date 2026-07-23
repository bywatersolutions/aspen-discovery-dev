#!/bin/bash

# migrate-test.sh -- rehearse adopting a git based install with
# aspen-migrate-from-git: preinst refusal, move aside, install, migrate.
#
# usage: migrate-test.sh <debs-dir>
# <debs-dir> must contain aspen-discovery_*_all.deb and test-site.ini.
#
# A real site is created with the package first, then the tree is dressed
# up as a legacy git install (a .git directory, unmarked cron keep-alive
# lines, a launcher with a non default heap, the legacy conf snippets)
# and the package is purged, leaving exactly what a git based server has.

set -euo pipefail
# Resolve the debs dir before changing directory so relative paths work
DEBS=$(cd "${1:?usage: migrate-test.sh <debs-dir>}" && pwd)
cd "$(dirname "$0")"
. ./lib.sh

C=${ASPEN_TEST_CONTAINER:-aspen-ci-migrate}
TREE=/usr/local/aspen-discovery

banner "build a legacy looking install"
boot_container "$C" "$DEBS"
install_deb "$C"
setup_local_db "$C"
create_site "$C"

# Legacy fixture files, built on the host and copied in to keep the
# quoting sane: the crontab keep-alive lines (unmarked, as the old
# template shipped them) and a launcher with a non default heap.
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
cat > "$work/keepalives.txt" <<EOF
@reboot       solr    php /usr/local/aspen-discovery/code/web/cron/checkSolr.php $SITE
*/2 * * * *   solr    php /usr/local/aspen-discovery/code/web/cron/checkSolr.php $SITE
*/5 * * * *   aspen   php /usr/local/aspen-discovery/code/web/cron/checkBackgroundProcesses.php $SITE
EOF
cat > "$work/launcher.sh" <<EOF
#!/bin/bash
if [[ ( "\$1" == "stop" ) || ( "\$1" == "restart") ]]
  then
    $TREE/sites/default/solr-8.11.2/bin/solr stop -p 8080 -s "/data/aspen-discovery/$SITE/solr7"
fi
if [[ ( "\$1" == "start" ) || ( "\$1" == "restart") ]]
  then
    $TREE/sites/default/solr-8.11.2/bin/solr start -m 4g -p 8080 -s "/data/aspen-discovery/$SITE/solr7"
fi
EOF
in_container "$C" "systemctl stop aspen@$SITE.target"
docker cp "$work/keepalives.txt" "$C:/tmp/keepalives.txt"
docker cp "$work/launcher.sh" "$C:$TREE/sites/$SITE/$SITE.sh"
in_container "$C" "mkdir $TREE/.git
    cat /tmp/keepalives.txt >> $TREE/sites/$SITE/conf/crontab_settings.txt
    chmod +x $TREE/sites/$SITE/$SITE.sh
    touch /etc/logrotate.d/aspen_discovery /etc/security/limits.d/solr.conf /etc/security/limits.d/aspen.conf"
in_container "$C" 'apt-get purge -y aspen-discovery >/tmp/purge.log 2>&1'
in_container "$C" 'dpkg -s aspen-discovery 2>/dev/null | grep -q "install ok installed"' && fail "package still installed after purge"
in_container "$C" "test -d $TREE/.git && test -d $TREE/sites/$SITE" || fail "legacy tree not intact after purge"
echo "legacy tree ready (site, .git, cron keep-alives, 4g launcher, conf snippets)"

banner "preinst must refuse over a git install"
in_container "$C" 'apt-get install -y /debs/aspen-discovery_*_all.deb >/tmp/refuse.log 2>&1' && fail "install over a git tree did not refuse"
in_container "$C" 'grep -q "git based install" /tmp/refuse.log' || fail "refusal message missing"
in_container "$C" 'dpkg -s aspen-discovery 2>/dev/null | grep -q "install ok installed"' && fail "package installed despite refusal"
echo "preinst refused as designed"

banner "move aside, install, migrate"
in_container "$C" "mv $TREE $TREE.pre-deb"
install_deb "$C"
in_container "$C" "aspen-migrate-from-git $TREE.pre-deb >/tmp/migrate.log 2>&1" ||
    { in_container "$C" 'tail -30 /tmp/migrate.log'; fail "aspen-migrate-from-git failed"; }

banner "verify the migrated site"
check_site_up "$C"
keepalives=$(in_container "$C" "grep -c 'checkSolr\|checkBackgroundProcesses' $TREE/sites/$SITE/conf/crontab_settings.txt || true")
[ "$keepalives" = 0 ] || fail "cron keep-alive lines survived the migration ($keepalives left)"
in_container "$C" "grep -q 'solrMemory = 4g' $TREE/sites/$SITE/conf/config.ini" ||
    fail "non default Solr heap not carried into solrMemory"
in_container "$C" "grep -q 'SOLR_MEM=4g' /run/aspen/$SITE/solr.env" ||
    fail "carried heap did not reach the Solr unit"
in_container "$C" 'test ! -e /etc/logrotate.d/aspen_discovery &&
    test ! -e /etc/security/limits.d/solr.conf &&
    test ! -e /etc/security/limits.d/aspen.conf' || fail "legacy conf snippets not removed"
echo "cron stripped, heap carried, snippets removed"

banner "re-running the migration is idempotent"
in_container "$C" "aspen-migrate-from-git $TREE.pre-deb 2>&1 | grep -q 'already exists'" ||
    fail "second migration run did not skip the existing site"

docker rm -f "$C" >/dev/null
banner "migration test PASSED"
