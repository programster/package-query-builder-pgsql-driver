<?php

/*
 * Thrown when the rules can't be turned into SQL, as they're marked as invalid, or aren't in the shape jQuery
 * QueryBuilder's JSON output has (e.g. a rule with no field, or an "in" rule whose value isn't a list).
 */

namespace Programster\QueryBuilderPgsqlDriver;


class ExceptionInvalidRules extends \Exception
{
}
