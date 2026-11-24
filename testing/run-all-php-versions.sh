#!/bin/sh
# Runs lint and the smoke tests (testing/main.php) on every supported version of PHP, each in its own Docker container,
# against the newest supported version of PostgreSQL in another, then PHPStan once. This catches code that only works
# on newer versions of PHP than the minimum this package supports. run-all-postgresql-versions.sh tests every
# supported version of PostgreSQL.
#
# Usage:
#   testing/run-all-php-versions.sh                # test every supported version
#   testing/run-all-php-versions.sh 8.3 8.5        # test only these versions
#   testing/run-all-php-versions.sh --rebuild      # rebuild the Docker images first (e.g. for the latest patch release)
#
# Requires Docker, and "composer install" to have been run, as the tests use the vendor/ directory.
# Exits with 0 if every version passed, or 1 if any failed.

# PHP's supported versions, see https://www.php.net/supported-versions.php
SUPPORTED_VERSIONS="8.2 8.3 8.4 8.5 8.6"

# The newest of the versions in run-all-postgresql-versions.sh.
POSTGRESQL_VERSION=19

scriptDir=$(cd "$(dirname "$0")" && pwd)
repoDir=$(dirname "$scriptDir")
. "$scriptDir/docker-helpers.sh"
rebuild=0
versions=""

for arg in "$@"
do
    if [ "$arg" = "--rebuild" ]
    then
        rebuild=1
    else
        versions="$versions $arg"
    fi
done

if [ -z "$versions" ]
then
    versions=$SUPPORTED_VERSIONS
fi

if [ ! -f "$repoDir/vendor/autoload.php" ]
then
    echo "vendor/autoload.php is missing. Please run \"composer install\" first."
    exit 1
fi

network="pgsql-driver-test-$$"
database="pgsql-driver-test-pg${POSTGRESQL_VERSION}-$$"
failedVersions=""
phpstanFailed=0
trap 'removeContainerAndNetwork "$database" "$network"' EXIT

if ! createNetwork "$network" || ! startPostgresql "$POSTGRESQL_VERSION" "$database" "$network"
then
    exit 1
fi

for version in $versions
do
    echo
    echo "################ PHP ${version}"

    if ! buildPhpImage "$version" "$rebuild" || ! runTests "$version" "$database" "$network"
    then
        failedVersions="$failedVersions $version"
    fi
done

# Static analysis only needs to run once. It runs on the lowest version tested, as that is the most likely to differ.
lowestVersion=$(echo $versions | tr ' ' '\n' | sort -V | head -n 1)
echo
echo "################ PHPStan (PHP ${lowestVersion})"

if ! docker run --rm --network none -v "$repoDir":/src:ro "pgsql-driver-php${lowestVersion}-test" \
    sh -c 'cp -r /src /app && cd /app && php vendor/bin/phpstan --no-progress --memory-limit=1G'
then
    phpstanFailed=1
fi

echo
if [ -z "$failedVersions" ] && [ "$phpstanFailed" -eq 0 ]
then
    echo "All passed:" $versions
    exit 0
else
    [ -z "$failedVersions" ] || echo "FAILED on PHP:" $failedVersions
    [ "$phpstanFailed" -eq 0 ] || echo "FAILED PHPStan"
    exit 1
fi
