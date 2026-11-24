# Upgrading

Newest first.

## 2.0.0

2.0 only allows rules on the fields you give it, fixes SQL that matched the wrong rows, or failed, and checks every
rule before making any SQL. `getSql()` and `getSelectStatement()` haven't changed, but some rules now give different
SQL, or are rejected.

**The driver needs the fields the rules may filter on.** Before, a user could edit the JSON to filter on any column of
the table, including ones the page doesn't offer (e.g. `password_hash`), and learn about it from which rows matched.
* Change `new PgSqlDriver($conn)` to `new PgSqlDriver($conn, AllowedFields::createUsingFields('name', 'age'))`, with
  the fields your page offers, which are usually the filters you give QueryBuilder. A rule on any other field throws
  an `ExceptionFieldNotAllowed`, which extends `ExceptionInvalidRules`.
* Fields are matched exactly, including their case.
* If the rules don't come from a user (e.g. they're built on the server), you can allow any field with
  `new PgSqlDriver($conn, AllowedFields::createAny())`.

**Requirements**
* PHP 8.2 or later is needed, as 8.1 is no longer supported. It's tested on PHP 8.2 to 8.6, and PostgreSQL 15 to 19.
* `programster/pgsql-lib` 3 is needed, as it's the only version that's tested. Update its constraint to `^3.0`.

**Rules that now give different SQL.** These were bugs, so check any code that relied on the old SQL.
* A group within a group is put in brackets. Before, `a AND (b OR c)` gave `a AND b OR c`, which PostgreSQL reads as
  `(a AND b) OR c`, so it matched the wrong rows.
* A group with `"not": true` is negated, as `NOT (...)`. Before, `not` was ignored, so the group matched the opposite
  rows.
* `is_empty` and `is_not_empty` no longer also compare with `0`. Before, they compared the column with both `''` and
  `0`, which PostgreSQL rejects for every type of column, so they always failed. They're now for text columns, as in
  QueryBuilder.
* A `%`, `_` or `\` in the value of `begins_with`, `contains`, `ends_with` or their opposites is escaped, so it only
  matches itself. Before, `%` and `_` were wildcards (e.g. `contains` `%` matched every row), and a backslash escaped
  the next character.
* Rules and groups are combined in the order they're given. Before, a group's rules came first, then its groups.

**Rules that are now rejected,** with an exception that names the rule, rather than PHP warnings, a `TypeError`, or
SQL that fails or matches the wrong rows. See [Exceptions](exceptions.md).
* JSON that can't be decoded throws a `\JsonException`. Before, it gave warnings and then a `TypeError`.
* Rules marked as invalid throw `ExceptionInvalidRules`, rather than a plain `\Exception`. It extends `\Exception`,
  so a `catch (\Exception $e)` still catches it.
* Rules not in the shape QueryBuilder gives throw `ExceptionInvalidRules`: e.g. a rule with no field, a group with no
  rules, an `in` whose value isn't a non-empty list, or a `between` without exactly two values.
* A null value for an operator that compares with one, such as `equal`, throws `ExceptionInvalidRules`, as `= NULL`
  never matches. Use `is_null` instead.
* A rule no longer needs `id`, `type` or `input`, and JSON no longer needs `valid`, which aren't used.

**Classes**
* `PgSqlDriver`, `Condition` and `RuleGroup` are `final readonly`, so they can't be extended.
* `RuleGroup`'s constructor takes the decoded group, as `Condition`'s takes the decoded rule:
  `new RuleGroup($group, $conn, $allowedFields)`, rather than
  `new RuleGroup($group['condition'], $group['rules'], $conn)`.
* `Condition`'s and `RuleGroup`'s constructors also need the `AllowedFields`, after the connection.
* `Condition` and `RuleGroup` check their rules, and make their SQL, in their constructors, so they throw when they're
  created, rather than when they're turned into a string.
* The new `Operator` and `Conjunction` enums have a case for each operator and conjunction, the new
  `ExceptionInvalidRules` is thrown for rules that aren't in the shape QueryBuilder gives, and the new
  `ExceptionFieldNotAllowed` for rules on a field that isn't allowed.

## 1.1.0

Nothing needs changing. This allows `programster/pgsql-lib` 3, as well as 1 and 2.

## 1.0.1

Nothing needs changing. This allows `programster/pgsql-lib` 2, as well as 1.

## 1.0.0

The first release.
