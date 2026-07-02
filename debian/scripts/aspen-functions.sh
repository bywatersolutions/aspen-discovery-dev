# aspen-functions.sh -- shared shell functions for the aspen-* helper scripts
# This file is meant to be sourced, not executed.

ASPEN_HOME=${ASPEN_HOME:-/usr/local/aspen-discovery}
ASPEN_SITES_DIR=$ASPEN_HOME/sites
ASPEN_DATA_DIR=/data/aspen-discovery
ASPEN_LOG_DIR=/var/log/aspen-discovery
ASPEN_RUN_DIR=/run/aspen

die() {
    echo "$(basename "$0"): $*" >&2
    exit 1
}

# Returns 'systemd' when systemd is running and the aspen unit templates are
# installed, 'legacy' otherwise (cron keep-alive supervision).
aspen_init_backend() {
    if [ -z "$_ASPEN_INIT_BACKEND" ]; then
        if [ -d /run/systemd/system ] &&
            systemctl list-unit-files 'aspen-solr@.service' 2>/dev/null | grep -q '^aspen-solr@\.service'; then
            _ASPEN_INIT_BACKEND="systemd"
        else
            _ASPEN_INIT_BACKEND="legacy"
        fi
    fi
    echo "$_ASPEN_INIT_BACKEND"
}

is_site() {
    case "$1" in
        '' | default | template.*) return 1 ;;
    esac
    [ -f "$ASPEN_SITES_DIR/$1/conf/config.ini" ]
}

get_sites() {
    for dir in "$ASPEN_SITES_DIR"/*/; do
        [ -d "$dir" ] || continue
        site=$(basename "$dir")
        is_site "$site" && echo "$site"
    done
    return 0
}

# usage: get_ini_value <file> <section> <key>
# Strips trailing ';' comments and surrounding quotes, matching how Aspen's
# own config loaders clean values.
get_ini_value() {
    [ -f "$1" ] || return 1
    awk -v section="$2" -v key="$3" '
        /^[ \t]*\[/ {
            line = $0
            gsub(/[\[\]\r]/, "", line)
            gsub(/^[ \t]+|[ \t]+$/, "", line)
            in_section = (line == section)
            next
        }
        in_section && index($0, "=") {
            k = substr($0, 1, index($0, "=") - 1)
            gsub(/^[ \t]+|[ \t]+$/, "", k)
            if (k == key) {
                v = substr($0, index($0, "=") + 1)
                sub(/;.*$/, "", v)
                gsub(/^[ \t]+|[ \t\r]+$/, "", v)
                gsub(/^"|"$/, "", v)
                print v
                exit
            }
        }
    ' "$1"
}

# usage: get_site_conf_value <site> <section> <key> [file]
# Reads the site config, falling back to the shared default config.
get_site_conf_value() {
    conf_file=${4:-config.ini}
    conf_value=$(get_ini_value "$ASPEN_SITES_DIR/$1/conf/$conf_file" "$2" "$3")
    if [ -z "$conf_value" ]; then
        conf_value=$(get_ini_value "$ASPEN_SITES_DIR/default/conf/$conf_file" "$2" "$3")
    fi
    echo "$conf_value"
}

# usage: get_db_creds <site>
# Sets ASPEN_DB_NAME, ASPEN_DB_USER, ASPEN_DB_PASSWORD, ASPEN_DB_HOST and
# ASPEN_DB_PORT from the site's config.pwd.ini.
get_db_creds() {
    pwd_ini=$ASPEN_SITES_DIR/$1/conf/config.pwd.ini
    [ -f "$pwd_ini" ] || return 1
    ASPEN_DB_NAME=$(get_ini_value "$pwd_ini" Database database_aspen_dbname)
    ASPEN_DB_USER=$(get_ini_value "$pwd_ini" Database database_user)
    ASPEN_DB_PASSWORD=$(get_ini_value "$pwd_ini" Database database_password)
    db_dsn=$(get_ini_value "$pwd_ini" Database database_dsn)
    ASPEN_DB_HOST=$(echo "$db_dsn" | sed -n 's/.*host=\([^;]*\).*/\1/p')
    ASPEN_DB_PORT=$(echo "$db_dsn" | sed -n 's/.*port=\([^;]*\).*/\1/p')
    ASPEN_DB_HOST=${ASPEN_DB_HOST:-localhost}
    ASPEN_DB_PORT=${ASPEN_DB_PORT:-3306}
    [ -n "$ASPEN_DB_NAME" ] && [ -n "$ASPEN_DB_USER" ]
}

# usage: is_local_solr <site>
is_local_solr() {
    solr_host=$(get_site_conf_value "$1" Index solrHost)
    case "$solr_host" in
        localhost | 127.0.0.1) return 0 ;;
        *) return 1 ;;
    esac
}

# Lists the background module names that have an installed unit template,
# derived from the unit files themselves so the list never needs maintenance.
get_module_templates() {
    for unit in /usr/lib/systemd/system/aspen-*@.service; do
        [ -e "$unit" ] || continue
        module=$(basename "$unit" '@.service')
        module=${module#aspen-}
        case "$module" in
            solr | create-dirs | sync-modules) continue ;;
        esac
        echo "$module"
    done
    return 0
}
