<?php

/*
 * A library for helping with the test.
 */

namespace Programster\QueryBuilderPgsqlDriver\Testing;

use Programster\PgsqlLib\PgSqlConnection;


class TestHelperLib
{
    public static function getDb() : PgSqlConnection
    {
        static $db = null;

        if ($db === null)
        {
            // named arguments, as pgsql-lib 3 added a required $forceNew before $port.
            $db = PgSqlConnection::create(
                host: DB_HOST,
                dbName: DB_NAME,
                user: DB_USER,
                password: DB_PASSWORD,
                forceNew: false,
                port: DB_PORT
            );
        }

        return $db;
    }
}