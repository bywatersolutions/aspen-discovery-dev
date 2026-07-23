# Systemd-capable test environment for the aspen-discovery Debian package.
# Run with: --privileged --cgroupns=host --tmpfs /run --tmpfs /run/lock
FROM debian:12
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
    systemd systemd-sysv dbus wget gpg lsb-release ca-certificates curl less procps \
    && rm -rf /var/lib/apt/lists/*
RUN install -d /etc/apt/keyrings \
    && wget -q -O - https://packages.sury.org/php/apt.gpg | gpg --yes -o /etc/apt/keyrings/sury.gpg --dearmor \
    && echo "deb [signed-by=/etc/apt/keyrings/sury.gpg] https://packages.sury.org/php/ bookworm main" > /etc/apt/sources.list.d/sury.list
# Preinstall the heavy dependencies so installing the aspen deb is fast
RUN apt-get update && apt-get install -y \
    apache2 mariadb-server mariadb-client cron openjdk-17-jre-headless \
    libapache2-mod-php8.4 php8.4 php8.4-cli php8.4-curl php8.4-gd php8.4-imagick php8.4-intl \
    php8.4-ldap php8.4-mbstring php8.4-mcrypt php8.4-mysql php8.4-pgsql php8.4-soap php8.4-ssh2 \
    php8.4-xml php8.4-zip zip pigz rsyslog logrotate \
    && rm -rf /var/lib/apt/lists/*
CMD ["/sbin/init"]
