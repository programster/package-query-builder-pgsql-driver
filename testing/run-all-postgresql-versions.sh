#!/bin/sh
# Runs lint and the smoke tests (testing/main.php) against every supported major version of PostgreSQL, each in its
# own throwaway Docker container, which keeps its data in memory, from the newest supported version of PHP in another.
# run-all-php-versions.sh tests every supported version of PHP, and runs PHPStan.
#
# Usage:
#   testing/run-all-postgresql-versions.sh             # test every supported version
#   testing/run-all-postgresql-versions.sh 15 19       # test only these versions
#   testing/run-all-postgresql-versions.sh --rebuild   # rebuild the PHP Docker image first
#
# Requires Docker, and "composer install" to have been run, as the tests use the vendor/ directory.
# Exits with 0 if every version passed, or 1 if any failed.

# PostgreSQL's supported major versions, see https://www.postgresql.org/support/versioning/
SUPPORTED_VERSIONS="15 16 17 18 19"

# The newest of the versions in run-all-php-versions.sh.
PHP_VERSION=8.6

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

if ! buildPhpImage "$PHP_VERSION" "$rebuild"
then
    exit 1
fi

failedVersions=""

for version in $versions
do
    echo
    echo "################ PostgreSQL ${version}"
    network="pgsql-driver-test-$$"
    database="pgsql-driver-test-pg${version}-$$"
    trap 'removeContainerAndNetwork "$database" "$network"' EXIT

    if ! createNetwork "$network" \
        || ! startPostgresql "$version" "$database" "$network" \
        || ! runTests "$PHP_VERSION" "$database" "$network"
    then
        failedVersions="$failedVersions $version"
    fi

    removeContainerAndNetwork "$database" "$network"
done

echo
if [ -z "$failedVersions" ]
then
    echo "All passed: PostgreSQL" $versions
    exit 0
else
    echo "FAILED on PostgreSQL:" $failedVersions
    exit 1
fi
