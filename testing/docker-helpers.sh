#!/bin/sh
# Functions shared by run-all-php-versions.sh and run-all-postgresql-versions.sh, which source this file. The tests run
# in a PHP container, against PostgreSQL in another, on a Docker network of their own that's "internal", so neither
# container can reach the internet or the host.

TEST_DB_USER=test
TEST_DB_PASSWORD=test
TEST_DB_NAME=pgsql_driver_test


# Build the image for testing on a version of PHP, unless it's already built (or $2 is 1, to rebuild it).
# Usage: buildPhpImage <PHP version> <rebuild: 0 or 1>
buildPhpImage() {
    image="pgsql-driver-php$1-test"

    if [ "$2" -eq 1 ] || ! docker image inspect "$image" > /dev/null 2>&1
    then
        echo "building ${image}..."

        if ! docker build -q --pull --build-arg PHP_VERSION="$1" -t "$image" "$scriptDir/php-versions" > /dev/null
        then
            echo "FAILED to build the image for PHP $1"
            return 1
        fi
    fi

    return 0
}


# Create the internal network the containers talk to each other on.
# Usage: createNetwork <network name>
createNetwork() {
    docker network create --internal "$1" > /dev/null
}


# Remove a container, if it exists, and then the network, if it exists.
# Usage: removeContainerAndNetwork <container name> <network name>
removeContainerAndNetwork() {
    docker rm --force "$1" > /dev/null 2>&1
    docker network rm "$2" > /dev/null 2>&1
}


# Start a throwaway PostgreSQL container on the network, which keeps its data in memory, and wait up to a minute for
# it to accept queries. pg_isready alone isn't enough, as the image's entrypoint restarts the server once it has set up
# the database.
# Usage: startPostgresql <PostgreSQL version> <container name> <network name>
startPostgresql() {
    docker rm --force "$2" > /dev/null 2>&1

    if ! docker run --detach --rm --name "$2" --network "$3" --tmpfs /var/lib/postgresql \
        --env POSTGRES_USER="$TEST_DB_USER" --env POSTGRES_PASSWORD="$TEST_DB_PASSWORD" \
        --env POSTGRES_DB="$TEST_DB_NAME" "postgres:$1" > /dev/null
    then
        echo "FAILED to start a PostgreSQL $1 container"
        return 1
    fi

    attempts=0

    while [ "$attempts" -lt 60 ]
    do
        if docker exec "$2" psql -U "$TEST_DB_USER" -d "$TEST_DB_NAME" -h 127.0.0.1 -c 'SELECT 1' > /dev/null 2>&1
        then
            return 0
        fi

        attempts=$((attempts + 1))
        sleep 1
    done

    echo "FAILED: PostgreSQL $1 didn't start within a minute"
    return 1
}


# Lint every file and run the tests in a PHP container, against the PostgreSQL container.
# Usage: runTests <PHP version> <PostgreSQL container name> <network name>
runTests() {
    docker run --rm --network "$3" -v "$repoDir":/src:ro \
        --env TEST_DB_HOST="$2" --env TEST_DB_PORT=5432 --env TEST_DB_USER="$TEST_DB_USER" \
        --env TEST_DB_PASSWORD="$TEST_DB_PASSWORD" --env TEST_DB_NAME="$TEST_DB_NAME" \
        "pgsql-driver-php$1-test" sh /src/testing/php-versions/run-in-container.sh
}
