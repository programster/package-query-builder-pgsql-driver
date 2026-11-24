<?php

/*
 * How the rules in a group are combined, which is the group's "condition" in jQuery QueryBuilder's JSON output:
 * AND for all of them to match, or OR for any of them.
 */

namespace Programster\QueryBuilderPgsqlDriver;


enum Conjunction: string
{
    case AND = 'AND';
    case OR = 'OR';
}
