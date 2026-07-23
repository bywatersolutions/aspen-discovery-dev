# lib.sh -- shared helpers for the Debian package test scripts
# This file is meant to be sourced, not executed.

: "${ASPEN_TEST_IMAGE:=aspen-deb-test}"
: "${SITE:=test.localhost}"

banner() {
    echo
    echo "===== $* ====="
}

fail() {
    echo "FAIL: $*" >&2
    exit 1
}

# usage: boot_container <name> <debs-dir> [extra docker run args...]
boot_container() {
    container=$1
    debs_dir=$2
    shift 2
    docker rm -f "$container" 2>/dev/null || true
    docker run -d --name "$container" --privileged --cgroupns=host \
        --tmpfs /run --tmpfs /run/lock -v "$debs_dir":/debs "$@" \
        "$ASPEN_TEST_IMAGE" >/dev/null
    i=0
    until docker exec "$container" systemctl is-system-running 2>/dev/null | grep -qE "running|degraded"; do
        i=$((i + 1))
        [ "$i" -gt 120 ] && fail "systemd did not come up in $container"
        sleep 2
    done
}

# usage: in_container <name> <command...>
in_container() {
    container=$1
    shift
    docker exec "$container" bash -c "$*"
}

# usage: install_deb <container>
install_deb() {
    in_container "$1" 'apt-get install -y /debs/aspen-discovery_*_all.deb >/tmp/install.log 2>&1' ||
        { in_container "$1" 'tail -30 /tmp/install.log'; fail "package install failed"; }
    in_container "$1" 'dpkg -s aspen-discovery | grep -q "Status: install ok installed"' ||
        fail "package not in installed state"
}

# usage: setup_local_db <container>
# The aspen database lives in the container's own mariadb.
setup_local_db() {
    in_container "$1" 'systemctl start mariadb && mysql -e "
        CREATE USER IF NOT EXISTS \"aspen\"@\"localhost\" IDENTIFIED BY \"aspen123\";
        CREATE USER IF NOT EXISTS \"aspen\"@\"127.0.0.1\" IDENTIFIED BY \"aspen123\";
        GRANT ALL ON *.* TO \"aspen\"@\"localhost\" WITH GRANT OPTION;
        GRANT ALL ON *.* TO \"aspen\"@\"127.0.0.1\";
        FLUSH PRIVILEGES;"'
}

# usage: create_site <container> [ini-path-in-container]
# Must return on its own: a hang here is the sync-modules deadlock regression.
create_site() {
    in_container "$1" "timeout 600 aspen-create-site ${2:-/debs/test-site.ini} >/tmp/create-site.log 2>&1" ||
        { in_container "$1" 'tail -30 /tmp/create-site.log'; fail "aspen-create-site failed or timed out"; }
}

# usage: wait_unit_active <container> <unit> <timeout-seconds>
wait_unit_active() {
    i=0
    until in_container "$1" "systemctl is-active --quiet '$2'"; do
        i=$((i + 5))
        [ "$i" -gt "$3" ] && fail "$2 not active after ${3}s: $(in_container "$1" "systemctl is-active '$2'" || true)"
        sleep 5
    done
}

# usage: check_site_up <container>
check_site_up() {
    wait_unit_active "$1" "aspen@$SITE.target" 30
    wait_unit_active "$1" "aspen-solr@$SITE.service" 90
    in_container "$1" "systemctl is-active --quiet aspen-sync-modules@$SITE.timer" || fail "sync timer not active"
    in_container "$1" "systemctl is-active --quiet aspen-sync-modules@$SITE.path" || fail "sync path unit not active"
    in_container "$1" 'systemctl is-active --quiet apache2 || systemctl start apache2'
    i=0
    until [ "$(in_container "$1" "curl -s -o /dev/null -w '%{http_code}' -H 'Host: $SITE' http://localhost/")" = 200 ]; do
        i=$((i + 5))
        [ "$i" -gt 60 ] && fail "web front end did not serve HTTP 200"
        sleep 5
    done
    i=0
    until in_container "$1" 'curl -sf "http://localhost:8080/solr/admin/cores?action=STATUS&wt=json" >/dev/null'; do
        i=$((i + 5))
        [ "$i" -gt 60 ] && fail "solr cores endpoint not responding"
        sleep 5
    done
    echo "site is up: target, solr, timer, path, web, cores"
}
