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
            $db = PgSqlConnection::create(
                DB_HOST,
                DB_NAME,
                DB_USER,
                DB_PASSWORD,
                DB_PORT
            );
        }

        return $db;
    }
}