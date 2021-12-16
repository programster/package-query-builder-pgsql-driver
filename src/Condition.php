<?php

/*
 * An object to represent a condition, such as "x > y" or "a = b"
 */

namespace Programster\QueryBuilderPgsqlDriver;

use Programster\PgsqlLib\PgsqlLib;


class Condition implements \Stringable
{
    private readonly string $m_id;
    private readonly string $m_field;
    private readonly string $m_type; // "integer", "string"
    private readonly string $m_input; // "number", "text"
    private readonly string $m_operator; // "greater"
    private readonly mixed $m_value; // e.g. 3 or "bob"
    private \Pgsql\Connection $m_connection; // the postgresql connection resource.


    public function __construct(array $input, \Pgsql\Connection $pgsqlConnection)
    {
        $this->m_id = $input['id'];
        $this->m_field = $input['field'];
        $this->m_type = $input['type'];
        $this->m_input = $input['input'];
        $this->m_operator = $input['operator'];
        $this->m_value = $input['value'];
        $this->m_connection = $pgsqlConnection;
    }


    /**
     * Converts the condition to the relevant SQL string
     * @throws ExceptionUnrecognizedOperator -
     */
    public function __toString(): string
    {
        $escapedField = PgsqlLib::escapeIdentifier($this->m_connection, $this->m_field);

        // https://querybuilder.js.org/#operators
        switch ($this->m_operator)
        {
            case 'equal':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} = {$escapedValue}";
            }
            break;

            case 'not_equal':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, $this->m_value);
                return "{$escapedField} != {$escapedValue}";
            }

            case 'in':
            {
                $escapedValues = PgsqlLib::escapeValues($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} IN (" . implode(",", $escapedValues) . ")";
            }
            break;

            case 'not_in':
            {
                $escapedValues = PgsqlLib::escapeValues($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} NOT IN (" . implode(",", $escapedValues) . ")";
            }
            break;

            case 'less':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} < {$escapedValue}";
            }
            break;

            case 'less_or_equal':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} <= {$escapedValue}";
            }
            break;

            case 'greater':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} > {$escapedValue}";
            }
            break;

            case 'greater_or_equal':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} >= {$escapedValue}";
            }
            break;

            case 'between':
            {
                // @TODO - need to get example having this.
                $escapedValues = PgsqlLib::escapeValues($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} BETWEEN {$escapedValues[0]} AND {$escapedValues[1]}";
            }
            break;

            case 'not_between':
            {
                // @TODO - need to get example having this.
                $escapedValues = PgsqlLib::escapeValues($this->m_connection, $this->m_value);
                $stringForm = "{$escapedField} NOT BETWEEN {$escapedValues[0]} AND {$escapedValues[1]}";
            }
            break;

            case 'begins_with':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, "{$this->m_value}%");
                $stringForm = "{$escapedField} LIKE {$escapedValue}";
            }
            break;

            case 'not_begins_with':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, "{$this->m_value}%");
                $stringForm = "{$escapedField} NOT LIKE {$escapedValue}";
            }
            break;

            case 'contains':
            {
                $escapedValue = PgsqlLib::escapeValue(
                    $this->m_connection,
                    "%{$this->m_value}%"
                );

                $stringForm = "{$escapedField} LIKE {$escapedValue}";
            }
            break;

            case 'not_contains':
            {
                $escapedValue = PgsqlLib::escapeValue(
                    $this->m_connection,
                    "%{$this->m_value}%"
                );

                $stringForm = "{$escapedField} NOT LIKE {$escapedValue}";
            }
            break;

            case 'ends_with':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, "%{$this->m_value}");
                $stringForm = "{$escapedField} LIKE {$escapedValue}";
            }
            break;

            case 'not_ends_with':
            {
                $escapedValue = PgsqlLib::escapeValue($this->m_connection, "%{$this->m_value}");
                $stringForm = "{$escapedField} NOT LIKE {$escapedValue}";
            }
            break;

            case 'is_empty':
            {
                $stringForm = "({$escapedField} IS NULL OR {$escapedField} = '' OR {$escapedField} = 0)";
            }
            break;

            case 'is_not_empty':
            {
                $stringForm = "({$escapedField} IS NOT NULL AND {$escapedField} != '' AND {$escapedField} != 0)";
            }
            break;

            case 'is_null':
            {
                $stringForm = "{$escapedField} IS NULL";
            }
            break;

            case 'is_not_null':
            {
                $stringForm = "{$escapedField} IS NOT NULL";
            }
            break;

            default:
            {
                throw new ExceptionUnrecognizedOperator($this->m_operator);
            }
        }

        return $stringForm;
    }
}