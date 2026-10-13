#!/bin/sh
#
# Creates a crest project, starts it and installs it, with docker only. PHP on
# the host is not necessary.
#
#   curl -fsSL https://phalcon.io/crest-setup | sh -s -- my-app [options of crest new]
#
# To read the script before it runs:
#
#   curl -fsSL https://phalcon.io/crest-setup -o crest-setup.sh
#   sh crest-setup.sh my-app [options of crest new]
#
# APP_PORT sets the port of the project. --help shows the options of crest new.

set -eu

# The crest that creates the project. The release step sets the release version.
crest_version=dev-master

usage='usage: setup.sh <name> [options of crest new]'

# Stops the script when docker or docker compose is not installed.
need_docker() {
    command -v docker >/dev/null 2>&1 || { echo 'crest: docker is not installed' >&2; exit 1; }
    docker compose version >/dev/null 2>&1 || { echo 'crest: docker compose is not installed' >&2; exit 1; }
}

# Shows the options of crest new, from the crest of a one-off container.
help() {
    echo "$usage"
    need_docker
    docker run --rm -e COMPOSER_HOME=/composer composer:2 \
        sh -c 'composer global require --quiet --no-interaction "phalcon/crest:$1" && exec "$COMPOSER_HOME"/vendor/bin/crest new --help' \
        setup "$crest_version" </dev/null
    exit 0
}

# After a failed step: the project stays, and these commands continue.
failed() {
    status=$1 name=$2 step=$3
    shift 3
    printf "crest: %s is created, but '%s' failed. Fix the problem, then run:\n" "$name" "$step" >&2
    printf '    cd %s\n' "$name" >&2
    for next in "$@"; do
        printf '    %s\n' "$next" >&2
    done
    exit "$status"
}

main() {
    for argument in "$@"; do
        case "$argument" in
            -h | --help) help ;;
        esac
    done

    name=${1:-}

    case "$name" in
        '' | -*)
            echo "$usage" >&2
            exit 1
            ;;
    esac

    # Only the working folder is mounted in the one-off container. A project
    # in another folder stays in the container and is lost.
    for argument in "$@"; do
        case "$argument" in
            --directory | --directory=*)
                echo 'crest: --directory is not supported; the project goes into the working folder' >&2
                exit 1
                ;;
        esac
    done

    # The one-off container mounts the working folder at the same path. On a
    # system folder, the mount hides what the container needs, for example
    # php in /usr/local/bin.
    case "$PWD" in
        / | /bin | /bin/* | /composer | /composer/* | /dev | /dev/* | /etc | /etc/* | /lib | /lib/* | \
        /proc | /proc/* | /sbin | /sbin/* | /sys | /sys/* | /usr | /usr/*)
            echo "crest: $PWD is a system folder in the one-off container; run the setup in another folder" >&2
            exit 1
            ;;
    esac

    # The port of the project goes into its .env, so each later ./crest up
    # uses it. No leading zero: some shells read 080 as an octal number.
    port=${APP_PORT:-}

    if [ -n "$port" ]; then
        case "$port" in
            *[!0-9]* | 0* | ??????*) port= ;;
        esac

        if [ -z "$port" ] || [ "$port" -gt 65535 ]; then
            echo 'crest: APP_PORT must be a number from 1 to 65535' >&2
            exit 1
        fi
    fi

    need_docker

    # The project belongs to you. Rootless docker maps root in the container
    # to you, and the other IDs to subordinate IDs. There, the container user
    # must be root, and the files need no chown.
    uid=$(id -u)
    gid=$(id -g)

    case "$(docker info --format '{{.SecurityOptions}}' 2>/dev/null || true)" in
        *rootless*)
            uid=0
            gid=0
            ;;
    esac

    printf 'crest: creating %s\n' "$name"

    # crest new runs in a one-off container, as root. The working folder has
    # the same path there, so the errors of new show the host path. new
    # checks the name before sed and chown use it. The report of new does not
    # show, because this script runs the next steps. Errors go to stderr.
    docker run --rm -v "$PWD:$PWD" -w "$PWD" -e COMPOSER_HOME=/composer -e APP_PORT composer:2 \
        sh -c '
            set -e
            version=$1 uid=$2 gid=$3
            shift 3
            composer global require --no-interaction "phalcon/crest:$version"
            "$COMPOSER_HOME"/vendor/bin/crest new "$@" >/dev/null
            sed -i "s/^UID=.*/UID=$uid/; s/^GID=.*/GID=$gid/" "$1/.env"
            [ -z "${APP_PORT:-}" ] || sed -i "s/^APP_PORT=.*/APP_PORT=$APP_PORT/" "$1/.env"
            [ "$uid" = 0 ] || chown -R "$uid:$gid" "$1"
        ' setup "$crest_version" "$uid" "$gid" "$@" </dev/null

    # An empty CDPATH: cd goes only to the new project.
    CDPATH= cd -- "$name"

    # stdin can be the curl pipe. docker must not read it.
    printf 'crest: starting %s\n' "$name"
    ./crest up </dev/null || failed $? "$name" './crest up' './crest up' './crest install'

    printf 'crest: installing %s\n' "$name"
    ./crest install </dev/null || failed $? "$name" './crest install' './crest install'

    printf 'crest: %s runs on http://localhost:%s/\n' "$name" "${APP_PORT:-8080}"
    echo 'Next:'
    printf '    cd %s\n' "$name"
    echo '    ./crest make:action GET /hello'
}

# The last line. The shell reads all of the script before the first command
# runs, and a download that stops early runs nothing.
main "$@"
