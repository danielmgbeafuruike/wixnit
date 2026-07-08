<?php

    /**
     * Dependency-free smoke test for the new query-composition pieces added to Wixnit:
     * In/NotIn/IsNull/IsNotNull filter operators, Filter::On(), and WhereHas's SQL shape.
     *
     * These specific pieces (Filter::getquery(), Filter::On()) don't need a live database
     * connection to exercise - they just build a SQL string and a bound-value array - so this
     * file can run with nothing but the PHP CLI and no MySQL server:
     *
     *   composer install
     *   php tests/smoke_test.php
     *
     * It does NOT cover the DBQuery-level methods (aggregate/exists/pluck/groupCount/
     * increment/decrement/WhereHas's EXISTS subquery) since those require an actual mysqli
     * connection to run against - wire up a test DB and call those from your own script/
     * PHPUnit suite using this file's assert() pattern as a starting point.
     */

    require_once __DIR__ . "/../vendor/autoload.php";

    use Wixnit\Data\Filter;
    use Wixnit\Data\In;
    use Wixnit\Data\NotIn;
    use Wixnit\Data\IsNull;
    use Wixnit\Data\IsNotNull;
    use Wixnit\Data\GreaterThan;
    use Wixnit\Exception\RelationException;
    use Wixnit\Exception\DatabaseException;

    $failures = 0;
    $passed = 0;

    function check(string $name, bool $condition, string $detail = ""): void
    {
        global $failures, $passed;

        if($condition)
        {
            $passed++;
            echo "  [PASS] $name\n";
        }
        else
        {
            $failures++;
            echo "  [FAIL] $name" . ($detail ? " - $detail" : "") . "\n";
        }
    }

    echo "In / NotIn\n";

    $q = (new Filter(["status" => new In("active", "pending")]))->getquery();
    check("In() renders IN (?, ?)", str_contains($q->query, "IN (?, ?)"), $q->query);
    check("In() binds both values", $q->values === ["active", "pending"], json_encode($q->values));

    $q = (new Filter(["status" => new NotIn("banned", "deleted")]))->getquery();
    check("NotIn() renders NOT IN (?, ?)", str_contains($q->query, "NOT IN (?, ?)"), $q->query);

    $q = (new Filter(["status" => new In()]))->getquery();
    check("empty In() renders an always-false condition, not invalid SQL", str_contains($q->query, "1=0"), $q->query);

    echo "\nIsNull / IsNotNull\n";

    $q = (new Filter(["deletedReason" => new IsNull()]))->getquery();
    check("IsNull() renders IS NULL", str_contains($q->query, "IS NULL"), $q->query);

    $q = (new Filter(["deletedReason" => new IsNotNull()]))->getquery();
    check("IsNotNull() renders IS NOT NULL", str_contains($q->query, "IS NOT NULL"), $q->query);

    echo "\nFilter::On()\n";

    $q = Filter::On("wallet", ["amount" => new GreaterThan(100)])->getquery();
    check("On() prefixes the key with the relation alias", str_contains($q->query, "wallet.amount>"), $q->query);
    check("On() still binds the comparison value", $q->values === [100], json_encode($q->values));

    try
    {
        Filter::On("", ["amount" => new GreaterThan(100)]);
        check("On() rejects an empty relation name", false, "no exception thrown");
    }
    catch(RelationException $e)
    {
        check("On() rejects an empty relation name", true);
    }

    try
    {
        Filter::On("wallet", []);
        check("On() rejects an empty condition list", false, "no exception thrown");
    }
    catch(RelationException $e)
    {
        check("On() rejects an empty condition list", true);
    }

    echo "\nIdentifier safety\n";

    try
    {
        new Filter(["age; DROP TABLE users;--" => new GreaterThan(18)]);
        check("Filter rejects an unsafe key", false, "no exception thrown");
    }
    catch(DatabaseException $e)
    {
        check("Filter rejects an unsafe key", true);
    }

    echo "\n" . str_repeat("-", 40) . "\n";
    echo "$passed passed, $failures failed\n";

    exit($failures > 0 ? 1 : 0);
