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

set -eu

# The crest that creates the project. The release step sets the release version.
crest_version=dev-master

main() {
    name=${1:-}

    case "$name" in
        '' | -*)
            echo 'usage: setup.sh <name> [options of crest new]' >&2
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

    command -v docker >/dev/null 2>&1 || { echo 'crest: docker is not installed' >&2; exit 1; }
    docker compose version >/dev/null 2>&1 || { echo 'crest: docker compose is not installed' >&2; exit 1; }

    printf 'crest: creating %s\n' "$name"

    # crest new runs in a one-off container, as root. new checks the name
    # before sed and chown use it. The report of new does not show, because
    # this script runs the next steps. Errors go to stderr and show.
    docker run --rm -v "$PWD:/app" -w /app -e COMPOSER_HOME=/composer composer:2 \
        sh -c '
            set -e
            version=$1 uid=$2 gid=$3
            shift 3
            composer global require --no-interaction "phalcon/crest:$version"
            "$COMPOSER_HOME"/vendor/bin/crest new "$@" >/dev/null
            sed -i "s/^UID=.*/UID=$uid/; s/^GID=.*/GID=$gid/" "$1/.env"
            chown -R "$uid:$gid" "$1"
        ' setup "$crest_version" "$(id -u)" "$(id -g)" "$@" </dev/null

    # An empty CDPATH: cd goes only to the new project.
    CDPATH= cd -- "$name"

    # stdin can be the curl pipe. docker must not read it.
    printf 'crest: starting %s\n' "$name"
    ./crest up </dev/null

    printf 'crest: installing %s\n' "$name"
    ./crest install </dev/null

    printf 'crest: %s runs on http://localhost:%s/\n' "$name" "${APP_PORT:-8080}"
    echo 'Next:'
    printf '    cd %s\n' "$name"
    echo '    ./crest make:action GET /hello'
}

# The last line. The shell reads all of the script before the first command
# runs, and a download that stops early runs nothing.
main "$@"
