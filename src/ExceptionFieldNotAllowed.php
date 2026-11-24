<?php

/*
 * Thrown when a rule filters on a field that isn't one of the AllowedFields. As the JSON comes from the browser, this
 * may be a user trying to filter on a column they shouldn't. It extends ExceptionInvalidRules, so catching that also
 * catches this.
 */

namespace Programster\QueryBuilderPgsqlDriver;


class ExceptionFieldNotAllowed extends ExceptionInvalidRules
{
    public function __construct(string $field)
    {
        parent::__construct("Filtering on \"{$field}\" isn't allowed.");
    }
}
