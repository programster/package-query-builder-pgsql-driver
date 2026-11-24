<?php

/*
 * Gives the tests a connection to the test database. The driver only needs one to escape values for, so the tests
 * never change anything in it, other than in temporary tables, which are dropped when the connection closes.
 */

namespace Programster\QueryBuilderPgsqlDriver\Testing;

use Programster\PgsqlLib\PgSqlConnection;


final class TestDatabase
{
    private static ?PgSqlConnection $s_connection = null;


    /**
     * Get the connection to the test database, creating it the first time it's needed.
     *
     * Each connection detail comes from an environment variable of the same name with a TEST_ prefix (e.g.
     * TEST_DB_PORT), if it's set, otherwise from the constant in testing/settings.php. This lets the
     * run-all-*.sh scripts point the tests at their PostgreSQL containers.
     *
     * @return PgSqlConnection
     */
    public static function getConnection(): PgSqlConnection
    {
        if (self::$s_connection === null)
        {
            self::$s_connection = PgSqlConnection::create(
                host: self::getSetting('DB_HOST'),
                dbName: self::getSetting('DB_NAME'),
                user: self::getSetting('DB_USER'),
                password: self::getSetting('DB_PASSWORD'),
                forceNew: false,
                port: (int)self::getSetting('DB_PORT')
            );
        }

        return self::$s_connection;
    }


    /**
     * Get the version of the PostgreSQL server the tests run against, e.g. "19.1 (Debian 19.1-1.pgdg13+1)".
     * @return string
     */
    public static function getServerVersion(): string
    {
        $version = pg_version(self::getConnection()->getResource());
        $serverVersion = $version['server'] ?? null;
        return is_string($serverVersion) ? $serverVersion : 'unknown';
    }


    /**
     * Get a connection detail, from its TEST_ environment variable if that's set, or its testing/settings.php
     * constant.
     *
     * @param string $name - the name of the setting's constant, e.g. "DB_PORT".
     *
     * @return string
     *
     * @throws \RuntimeException - if it's set in neither place.
     */
    private static function getSetting(string $name): string
    {
        $settingsFile = __DIR__ . '/settings.php';
        $environmentValue = getenv("TEST_{$name}");

        if ($environmentValue === false && !defined($name) && is_file($settingsFile))
        {
            require_once($settingsFile);
        }

        $constantValue = defined($name) ? constant($name) : null;

        if ($environmentValue !== false)
        {
            $value = $environmentValue;
        }
        elseif (is_string($constantValue) || is_int($constantValue))
        {
            $value = (string)$constantValue;
        }
        else
        {
            throw new \RuntimeException(
                "The {$name} test setting isn't set. Set TEST_{$name}, or copy testing/settings.php.tmpl to "
                . "testing/settings.php and fill it in."
            );
        }

        return $value;
    }
}
