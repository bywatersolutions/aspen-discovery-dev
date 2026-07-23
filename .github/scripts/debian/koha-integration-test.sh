#!/bin/bash

# koha-integration-test.sh -- run the packaged Aspen against a real Koha
# from koha-testing-docker and verify the koha_export daemon connects and
# extracts.
#
# usage: koha-integration-test.sh <debs-dir> [ktd-instance-name]
#
# Expects a ktd instance to already be up (default name kohadev). The
# aspen container joins the instance's docker network, so the Koha
# database is reachable at db:3306 (koha_kohadev/password) and the Koha
# web interfaces at koha:8080 (OPAC) and koha:8081 (staff).

set -euo pipefail
cd "$(dirname "$0")"
. ./lib.sh

DEBS=$(cd "${1:?usage: koha-integration-test.sh <debs-dir> [ktd-name]}" && pwd)
KTD_NAME=${2:-kohadev}
C=${ASPEN_TEST_CONTAINER:-aspen-ci-koha}
NETWORK=${KTD_NETWORK:-${KTD_NAME}_kohanet}

banner "check the ktd instance"
docker network inspect "$NETWORK" >/dev/null 2>&1 || fail "docker network '$NETWORK' not found; is ktd up?"
KOHA_CONTAINER=$(docker ps --filter "name=${KTD_NAME}[-_]koha" --format '{{.Names}}' | head -1)
[ -n "$KOHA_CONTAINER" ] || fail "no running koha container for instance '$KTD_NAME'"
echo "ktd instance '$KTD_NAME' up (koha container: $KOHA_CONTAINER)"

banner "give Aspen an API key in Koha"
docker exec "$KOHA_CONTAINER" bash -c 'echo "INSERT IGNORE INTO api_keys (client_id, secret, description, patron_id, active)
    SELECT \"aspen-ci\", \"aspen-ci-secret\", \"aspen ci\", borrowernumber, 1 FROM borrowers WHERE userid = \"koha\"" | koha-mysql kohadev'

banner "boot aspen and create a site pointed at Koha"
boot_container "$C" "$DEBS" --network "$NETWORK"
install_deb "$C"
setup_local_db "$C"
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
sed -e 's/^DBHost = koha.invalid/DBHost = db/' \
    -e 's/^DBName = koha$/DBName = koha_kohadev/' \
    -e 's/^DBUser = koha$/DBUser = koha_kohadev/' \
    -e 's/^DBPwd = koha123/DBPwd = password/' \
    -e 's/^DBTimezone = .*/DBTimezone = UTC/' \
    -e 's/^ClientId = .*/ClientId = aspen-ci/' \
    -e 's/^ClientSecret = .*/ClientSecret = aspen-ci-secret/' \
    -e 's|^ilsUrl = .*|ilsUrl = http://koha:8080|' \
    -e 's|^ilsStaffUrl = .*|ilsStaffUrl = http://koha:8081|' \
    test-site.ini > "$work/koha-site.ini"
docker cp "$work/koha-site.ini" "$C:/tmp/koha-site.ini"
create_site "$C" /tmp/koha-site.ini
check_site_up "$C"

banner "koha_export must connect and extract"
in_container "$C" "systemctl restart aspen-koha_export@$SITE.service"
sleep 120
db_errors=$(in_container "$C" "journalctl -u aspen-koha_export@$SITE.service | grep -ci 'error connecting to database' || true")
[ "$db_errors" = 0 ] || {
    in_container "$C" "journalctl -u aspen-koha_export@$SITE.service --no-pager | tail -30"
    fail "koha_export logged $db_errors database connection errors"
}
state=$(in_container "$C" "systemctl is-active aspen-koha_export@$SITE.service" || true)
[ "$state" = active ] || {
    in_container "$C" "journalctl -u aspen-koha_export@$SITE.service --no-pager | tail -30"
    fail "koha_export is '$state' after two minutes against a real Koha"
}
restarts=$(in_container "$C" "systemctl show -p NRestarts --value aspen-koha_export@$SITE.service")
[ "$restarts" = 0 ] || fail "koha_export restarted $restarts times against a real Koha"
echo "koha_export connected to Koha and stayed up (no restarts, no DB errors)"

# Informational: how far the first extract got
works=$(in_container "$C" 'mysql aspen -N -e "SELECT COUNT(*) FROM grouped_work" 2>/dev/null || echo "n/a"')
echo "grouped_work rows after first extract window: $works"
in_container "$C" "journalctl -u aspen-koha_export@$SITE.service --no-pager | tail -8"

docker rm -f "$C" >/dev/null
banner "koha integration test PASSED"
