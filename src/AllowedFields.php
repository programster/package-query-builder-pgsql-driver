<?php

/*
 * The fields (columns) the rules may filter on. The JSON comes from the browser, so a user can change a rule's field
 * to any column, including ones the page doesn't offer, and learn about it from which rows match. Rules on any other
 * field are rejected with an ExceptionFieldNotAllowed.
 */

namespace Programster\QueryBuilderPgsqlDriver;


final readonly class AllowedFields
{
    private bool $m_allowsAnyField;

    /** @var list<string> */
    private array $m_fields;


    /**
     * @param bool $allowsAnyField - whether any field is allowed, in which case $fields isn't used.
     * @param list<string> $fields - the fields that are allowed.
     */
    private function __construct(bool $allowsAnyField, array $fields)
    {
        $this->m_allowsAnyField = $allowsAnyField;
        $this->m_fields = $fields;
    }


    /**
     * Allow only these fields. They're matched exactly, including their case, as PostgreSQL matches quoted column
     * names, so "Name" and "name" are different fields.
     *
     * @param string ...$fields - the fields that are allowed, of which there must be at least one.
     *
     * @return AllowedFields
     *
     * @throws \InvalidArgumentException - if no fields are given, or a field is an empty string.
     */
    public static function createUsingFields(string ...$fields): AllowedFields
    {
        $fields = array_values($fields);

        if (count($fields) === 0)
        {
            throw new \InvalidArgumentException(
                "At least one allowed field must be given. To allow any field, use AllowedFields::createAny()."
            );
        }

        if (in_array('', $fields, true))
        {
            throw new \InvalidArgumentException("An allowed field can't be an empty string.");
        }

        return new AllowedFields(false, $fields);
    }


    /**
     * Allow any field. Only use this when the rules don't come from a user (e.g. they're built on the server), as a
     * user can filter on any column of the table, including ones the page doesn't offer.
     *
     * @return AllowedFields
     */
    public static function createAny(): AllowedFields
    {
        return new AllowedFields(true, []);
    }


    /**
     * Get whether a rule may filter on a field.
     * @param string $field - the rule's field.
     * @return bool
     */
    public function isAllowed(string $field): bool
    {
        return ($this->m_allowsAnyField || in_array($field, $this->m_fields, true));
    }
}
