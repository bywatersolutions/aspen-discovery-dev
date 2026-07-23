# Build environment for the aspen-discovery Debian package.
# Debian 12 to match the target platform (and its lintian).
FROM debian:12
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential debhelper devscripts lintian fakeroot zstd \
    && rm -rf /var/lib/apt/lists/*
