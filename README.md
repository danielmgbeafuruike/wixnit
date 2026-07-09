# Wixnit — Changes Documentation

This document covers everything added or fixed in this patch: new query features, new
read/write shortcut methods, the relation-filtering system, identifier-safety validation,
the exception overhaul, and the pre-existing bugs found and fixed along the way.

Nothing here was executed against a live database (no PHP interpreter or MySQL server was
available while this was written) — it was written to match the existing codebase's
conventions exactly and reviewed by hand. Run `tests/smoke_test.php` and try the DB-backed
methods against a real table before deploying.

---

## Table of contents

1. [New filter operators — `In`, `NotIn`, `IsNull`, `IsNotNull`](#1-new-filter-operators)
2. [Relation filtering — `Filter::On()` and `WhereHas`](#2-relation-filtering)
3. [New aggregate & read-shortcut methods](#3-new-aggregate--read-shortcut-methods)
4. [New write-shortcut methods](#4-new-write-shortcut-methods)
5. [Identifier safety (`Identifier` class)](#5-identifier-safety)
6. [Exception overhaul](#6-exception-overhaul)
7. [Bugs found and fixed](#7-bugs-found-and-fixed)
8. [File-by-file summary](#8-file-by-file-summary)
9. [Testing](#9-testing)
10. [Known limitations / suggested next steps](#10-known-limitations--suggested-next-steps)

---

## 1. New filter operators

Four new classes in `Wixnit\Data`, used the same way `GreaterThan`/`LessThan`/`NotEqual`
already are — as the value side of a `Filter`'s associative array.

### `In`

```php
$users = User::Get(new Filter(['status' => new In('active', 'pending')]));
// WHERE status IN (?, ?)
```

Accepts either a variadic list (`new In('a', 'b')`) or a single array (`new In($statuses)`).
An empty list (`new In()`) renders as `1=0` (always false) rather than the invalid SQL
`IN ()`.

### `NotIn`

Same shape as `In`, negated:

```php
$users = User::Get(new Filter(['status' => new NotIn('banned', 'deleted')]));
// WHERE status NOT IN (?, ?)
```

An empty list renders as `1=1` (always true), the correct complement of `In`'s empty-list
behavior.

### `IsNull` / `IsNotNull`

No constructor arguments — the presence of the operator is the whole condition:

```php
$users = User::Get(new Filter(['deletedReason' => new IsNull()]));
// WHERE deletedReason IS NULL

$users = User::Get(new Filter(['deletedReason' => new IsNotNull()]));
// WHERE deletedReason IS NOT NULL
```

All four operators are wired into **both** branches of `Filter::getquery()` — the scalar
branch and the array-of-values-per-key branch — so they compose correctly whether used
alone or alongside other conditions on the same key.

---

## 2. Relation filtering

Two different tools for two different relationship shapes.

### `Filter::On()` — for auto-joined singular relations

Wixnit already auto-joins any typed property on a model that extends `Transactable`
(e.g. `public Wallet $wallet` on `User`), and `DBQuery` already supports dot-notation
filter keys (`"wallet.amount"`) against that join. That was previously undocumented and
required knowing the internal aliasing rule (the alias is the **property name**, not the
class name) by hand.

`Filter::On()` is sugar over the same mechanism, so callers don't need to know that rule:

```php
use Wixnit\Data\Filter;
use Wixnit\Data\GreaterThan;

// equivalent to: new Filter(['wallet.amount' => new GreaterThan(100)])
$users = User::Get(Filter::On('wallet', ['amount' => new GreaterThan(100)]));
```

- `$relation` must match the **property name** on the model (lowercased internally),
  not the related class name, if they differ.
- Throws `RelationException::EmptyRelationTarget` if `$relation` is blank.
- Throws `RelationException::NoConditionsProvided` if `$conditions` is empty (an empty
  condition list restricts nothing and is almost always a mistake).
- Only works for **singular** relations (a property typed as a single `Transactable`
  subclass) — see `WhereHas` below for one-to-many.

### `WhereHas` — for real one-to-many relations

Array-typed "relation" properties in Wixnit are **not** joined — they're resolved via a
second query against a list of stored reference ids on the parent row. There was
previously no way to filter a parent by a condition on a *real* one-to-many child (a
child table that owns a foreign key pointing back to the parent), e.g. "users who made a
wallet transaction over 100":

```php
use Wixnit\Data\WhereHas;
use Wixnit\Data\Filter;
use Wixnit\Data\GreaterThan;

$users = User::Get(
    new WhereHas('wallettransaction', 'userid', new Filter(['amount' => new GreaterThan(100)]))
);
```

This renders as a correlated `EXISTS` subquery, **not** a `JOIN` — so matching parent rows
are never duplicated by multiple matching children:

```sql
SELECT ... FROM user
WHERE EXISTS (
    SELECT 1 FROM wallettransaction __wh_wallettransaction
    WHERE __wh_wallettransaction.userid = user.userid
    AND (amount>?)
)
```

Constructor signature:

```php
new WhereHas(string $childTable, string $foreignKey, Filter|FilterBuilder|null $condition = null)
```

- `$childTable` — the real table name storing the child rows.
- `$foreignKey` — the column on that table holding the parent's business id (the
  parent's `{table}id` column, following Wixnit's existing id-column convention).
- `$condition` — optional; omit it to just check "has any row at all" (`WhereHas('order', 'userid')`).
- Both `$childTable` and `$foreignKey` are validated with `Identifier::assertSafe()` and
  must be non-empty (`RelationException::EmptyRelationTarget` otherwise).

`WhereHas` is a first-class citizen everywhere `Filter`/`FilterBuilder` are accepted —
`Get()`, `Count()`, `FromDeleted()`, `DeletedCount()`, and all of the new aggregate/
shortcut methods below.

---

## 3. New aggregate & read-shortcut methods

All follow the existing `Model::Get()`/`Count()` conventions: variadic arguments,
type-sniffed via `instanceof`, composable with `Filter`, `FilterBuilder`, `WhereHas`,
`Search`, `SearchBuilder`, `Order`, `Span`, `DistinctOn`, and `GroupBy` (where relevant),
and an optional leading/trailing `mysqli` connection to override the default `DBConfig`.

| Method | Signature | Description |
|---|---|---|
| `Sum` | `Model::Sum(string $field, ...)` | `SUM(field)` over matching rows |
| `Average` | `Model::Average(string $field, ...)` | `AVG(field)` over matching rows |
| `Min` | `Model::Min(string $field, ...)` | `MIN(field)` over matching rows |
| `Max` | `Model::Max(string $field, ...)` | `MAX(field)` over matching rows |
| `Exists` | `Model::Exists(...)` | `SELECT 1 ... LIMIT 1` — cheaper than `Count() > 0` |
| `First` | `Model::First(...)` | First matching row as an object, or `null` |
| `Latest` | `Model::Latest(...)` | `First()` ordered by `created DESC` |
| `Oldest` | `Model::Oldest(...)` | `First()` ordered by `created ASC` |
| `Pluck` | `Model::Pluck(string $field, ...)` | Flat array of one column, no object hydration |
| `GroupCount` | `Model::GroupCount(string $field, ...)` | `[value => count]` via `GROUP BY` |

Examples:

```php
$totalOwed = Invoice::Sum('amount', new Filter(['paid' => 0]));

$avgAge = User::Average('age');

$isTaken = User::Exists(new Filter(['email' => 'a@b.com']));

$mostRecentSignup = User::Latest();

$activeUserIds = User::Pluck('id', new Filter(['status' => 'active']));

$countsByStatus = Order::GroupCount('status');
// ['pending' => 12, 'shipped' => 44, 'cancelled' => 3]
```

All five field-taking methods (`Sum`/`Average`/`Min`/`Max`/`Pluck`/`GroupCount`) validate
`$field` against the model's real, known fields via `Identifier::assertKnownField()` —
see [§5](#5-identifier-safety).

---

## 4. New write-shortcut methods

| Method | Signature | Description |
|---|---|---|
| `Increment` | `Model::Increment(string $field, int\|float $by = 1, ...)` | Atomic `field = field + ?` |
| `Decrement` | `Model::Decrement(string $field, int\|float $by = 1, ...)` | Atomic `field = field - ?` |
| `UpdateWhere` | `Model::UpdateWhere(array $data, ...)` | Bulk `UPDATE` matching rows directly, no fetch-then-save |
| `Restore` | `Model::Restore(...)` | Undo a soft delete on matching rows (counterpart to `Delete()`/`PurgeDeleted()`) |
| `Chunk` | `Model::Chunk(int $size, callable $callback, ...)` | Iterate matching rows in pages, without loading them all into memory |

```php
// Atomic - safe under concurrent requests, unlike $post->views++; $post->save();
Post::Increment('views', 1, new Filter(['id' => $postId]));

// Bulk update without loading every matching row into memory first
Order::UpdateWhere(['status' => 'archived'], new Filter(['status' => 'shipped', 'created' => new LessThan($cutoff)]));

User::Restore(new Filter(['id' => $userId]));

User::Chunk(500, function($batch) {
    foreach($batch as $user)
    {
        // process $user
    }
});
```

`Increment`/`Decrement` matter specifically because they're atomic at the database level —
loading an object, incrementing a property in PHP, then saving it has a race condition
under concurrent requests that a direct SQL increment does not.

`Chunk()` works by internally re-calling `Get()` with an added `Span` per page, so it
composes with any `Filter`/`Order`/etc. you pass it, the same as `Get()` does.

---

## 5. Identifier safety

New class: `Wixnit\Data\Identifier`.

Previously, column and table names used in filters, aggregates, `Pluck`, `groupBy`, and
joins were concatenated directly into SQL strings with no validation — while *values*
were always safely parameterized, the *names* were not. If a name were ever built from
user-controlled input (e.g. a generic `?sort=` or `?filter[x]=` API parameter used
directly as a filter key), that was a real SQL injection path.

`Identifier` provides two checks, both used throughout the new code:

```php
Identifier::assertSafe(string $identifier): string
```
Rejects anything that isn't `[A-Za-z_][A-Za-z0-9_]*` optionally followed by
`.[A-Za-z_][A-Za-z0-9_]*` (i.e. a plain identifier, or a `relation.field` pair). Throws
`DatabaseException::UnsafeIdentifier` otherwise.

```php
Identifier::assertKnownField(string $identifier, array $knownFields): string
```
Additionally checks the identifier (case-insensitively) against a list of real field
names on the target table. A `relation.field` qualified identifier only gets the
character-safety check, since the related model's fields aren't known at that point.
Throws `DatabaseException::InvalidFieldName` (now listing the known fields — see
[§6](#6-exception-overhaul)) otherwise.

**Where it's applied:**

| Call site | Check used |
|---|---|
| `Filter` constructor & `->add()` (every key) | `assertSafe` |
| `WhereHas` constructor (`childTable`, `foreignKey`) | `assertSafe` |
| `Filter::On()` (`relation`) | `assertSafe` |
| `DBQuery::aggregate()`, `pluck()`, `groupCount()` | `assertKnownField` |
| `DBQuery::increment()` / `decrement()` | `assertKnownField` |

This does **not** validate identifiers on relation-qualified paths (`wallet.amount`)
against the related model's actual fields, since the related model's `ObjectMap` isn't
available at that point in the call chain — only the character-safety check applies
there. Closing that remaining gap would mean threading the related model's known fields
through `buildJoins()`/`preProcessOp()`, which is a larger, separate change (see
[§10](#10-known-limitations--suggested-next-steps)).

---

## 6. Exception overhaul

### New: `DatabaseException::QueryFailed()`

Replaces the old generic query-failure path everywhere a prepared statement is executed
in `DBQuery`. Builds a message with:

- **Where** it happened (via `__METHOD__`)
- The **exact SQL** that was run
- The **bound values**, in placeholder order
- The **raw database error** and error number
- A **heuristic suggestion**, based on matching the error text against common MySQL
  failure patterns:

| Error contains | Suggestion given |
|---|---|
| `unknown column` | Check for a typo in a Filter/Order/aggregate/Pluck/groupBy/WhereHas field name, and that the property is public and mapped |
| `duplicate entry` | A unique constraint was violated — check for an existing row first |
| `foreign key constraint` | Related row ordering — check the referenced row exists / dependent rows are cleared first |
| `syntax` | Points to a query-construction bug, not bad data — check raw field names passed to aggregates/Pluck/groupBy/WhereHas |
| `doesn't have a default value` / `cannot be null` | A required column was left out of the insert/update data |
| `has gone away` / `lost connection` / `connection` | Connection lost/timed out — check `DBConfig` host/credentials |
| `data too long` | A value exceeds its column's length — validate/truncate, or widen the column |
| *(no match)* | Generic guidance to compare the query/values against the schema |

Example rendered message:

```
Query failed in Wixnit\Data\DBQuery::update.
  Query: UPDATE user SET email=? WHERE (id=?)
  Bound values: a@b.com, 42
  Database error: Duplicate entry 'a@b.com' for key 'user.email' (errno 1062)
  Suggestion: A unique constraint was violated. Check for an existing row with the same
  value before inserting/updating, or catch this exception and show a friendly
  'already exists' message instead.
```

### New: `DatabaseException::UnsafeIdentifier()`

Thrown by `Identifier::assertSafe()`. Explains that column/table/relation names must be
alphanumeric (optionally `relation.field`) and correspond to a real, known field.

### Improved: `DatabaseException::InvalidFieldName()`

Now takes an optional `array $knownFields` and, when provided, lists the model's actual
field names in the message alongside a suggestion to check for a typo or an unmapped
property.

### New class: `Wixnit\Exception\RelationException`

A separate exception class from `DatabaseException` on purpose: it covers mistakes in how
a relation is *described* (`Filter::On()`, `WhereHas()`) — catchable and fixable before a
query is even built — as opposed to `DatabaseException`, which covers failures the
database itself reports once a query actually runs. Two factory methods:

- `RelationException::EmptyRelationTarget(string $context, string $argument)` — an empty
  relation/table/column name was passed to `Filter::On()` or `WhereHas()`.
- `RelationException::NoConditionsProvided(string $relation)` — `Filter::On()` was called
  with an empty `$conditions` array (which restricts nothing and is almost certainly a
  mistake).

Both produce a message with **what** was wrong, **why** it matters, and a concrete
**fix**, e.g.:

```
Empty 'childTable' passed to WhereHas().
  Why: relation/table names can't be blank - an empty string can't be resolved to a real property or table.
  Fix: pass the related property's name (for Filter::On(), e.g. 'wallet' for a `public Wallet $wallet` property) or the child table's real name (for WhereHas(), e.g. 'wallettransaction').
```

---

## 7. Bugs found and fixed

These were pre-existing issues in the original codebase, found while wiring up the
exception improvements. All were fixed in place.

### 7.1 `mysqli_stmt::get_warnings()` does not exist

Every failed prepared-statement execution in `DBQuery` (`insert`, `update`, `delete`,
`get`, `count`, and now the new `aggregate`/`exists`/`pluck`/`groupCount`/`increment`/
`decrement`) called `$operation->get_warnings()` to build the error message.
`get_warnings()` is a method on the `mysqli` **connection**, not on `mysqli_stmt` — so
this call would itself throw a fatal `Call to undefined method` error, completely masking
whatever the real database problem was. **Fixed**: replaced with the correct
`mysqli_stmt::$error` / `$errno` properties, and routed through the new
`DatabaseException::QueryFailed()` for a properly diagnosed message instead.

### 7.2 `mysqli_stmt::num_rows()` called as a method

In `insert()` and `update()`, the affected-row count was read via
`$operation->num_rows()`. `num_rows` is a **property** on `mysqli_stmt` (and only
meaningful for buffered `SELECT` results in the first place) — calling it as a method
would throw a fatal error. **Fixed**: replaced with `$operation->affected_rows`, the
correct property for insert/update/delete row counts.

### 7.3 `DistinctOn->getValue()` does not exist

`FromDeleted()`, `CountCollection()`, and `DeletedCount()` in `Transactable` called
`$args[$i]->getValue()` on a `DistinctOn` instance to read its fields — but `DistinctOn`
only has a public `$fields` property, no `getValue()` method. Passing a `DistinctOn` to
`Count()`, `CountDeleted()`, or `FromDeleted()` would have thrown a fatal error.
**Fixed**: all three now read `$args[$i]->fields` directly, matching how `BuildCollection()`
already did it correctly.

### 7.4 No PHP version constraint

`composer.json` had `"require": {}` despite the codebase using PHP 8.1+ features (enums,
union types). **Fixed**: added `"php": "^8.1"`.

---

## 8. File-by-file summary

| File | Change |
|---|---|
| `src/Data/In.php` | New — `In` filter operator (pre-existing in the repo, unused/unwired; now wired into `Filter::getquery()`) |
| `src/Data/NotIn.php` | New — `NotIn` filter operator |
| `src/Data/IsNull.php` | New — `IsNull` filter operator |
| `src/Data/IsNotNull.php` | New — `IsNotNull` filter operator |
| `src/Data/WhereHas.php` | New — one-to-many relation filter via `EXISTS` subquery |
| `src/Data/Identifier.php` | New — `assertSafe()` / `assertKnownField()` raw-identifier validation |
| `src/Data/Filter.php` | Wired `In`/`NotIn`/`IsNull`/`IsNotNull` into `getquery()` (both branches); added `buildInClause()`; added `Filter::On()`; added `Identifier::assertSafe()` validation in the constructor and `add()` |
| `src/Data/DBQuery.php` | Added `aggregate()`, `exists()`, `pluck()`, `groupCount()`, `increment()`, `decrement()`, `incrementBy()` (private); added `WhereHas` handling to `where()` and `executeOperations()`; fixed `get_warnings()` (10 call sites) and `num_rows()` (2 call sites) bugs |
| `src/Data/Transactable.php` | Added `applyQueryArgs()`/`applyFilterArgs()` (private, shared arg-processing loops); added `SumValue`/`AverageValue`/`MinValue`/`MaxValue`/`ExistsCollection`/`FirstOf`/`LatestOf`/`OldestOf`/`PluckValue`/`GroupCountValue`/`IncrementValue`/`DecrementValue`/`UpdateMatching`/`RestoreValue`/`ChunkCollection` (protected); wired `WhereHas` into the 4 existing arg-loops (`BuildCollection`, `FromDeleted`, `CountCollection`, `DeletedCount`); fixed the `DistinctOn->getValue()` bug (3 sites); added `use Wixnit\Enum\OrderDirection;` |
| `src/App/Model.php` | Added public wrappers: `Sum`, `Average`, `Min`, `Max`, `Exists`, `First`, `Latest`, `Oldest`, `Pluck`, `GroupCount`, `Increment`, `Decrement`, `UpdateWhere`, `Restore`, `Chunk` |
| `src/App/PointerModel.php` | Same public wrappers as `Model.php`, adapted to `PointerModel`'s explicit-`mysqli`-connection style |
| `src/Exception/DatabaseException.php` | Added `QueryFailed()`, `UnsafeIdentifier()`; improved `InvalidFieldName()` with a known-fields list |
| `src/Exception/RelationException.php` | New — `EmptyRelationTarget()`, `NoConditionsProvided()` |
| `composer.json` | Added `"php": "^8.1"` requirement |
| `tests/smoke_test.php` | New — dependency-free smoke test for the SQL-generation logic (see [§9](#9-testing)) |

---

## 9. Testing

`tests/smoke_test.php` is a plain PHP CLI script (no PHPUnit dependency, since none
existed in the project before) covering the pieces that don't need a live database
connection to exercise — `Filter::getquery()` and `Filter::On()` just build a SQL string
and a bound-value array in memory:

```bash
composer install
php tests/smoke_test.php
```

It checks:
- `In()` / `NotIn()` render correctly and bind the right values
- an empty `In()` renders an always-false condition instead of invalid SQL
- `IsNull()` / `IsNotNull()` render correctly
- `Filter::On()` prefixes keys correctly and still binds comparison values
- `Filter::On()` rejects an empty relation name and an empty condition list
- `Filter` rejects an unsafe (injection-shaped) key

**Not covered** (requires a real `mysqli` connection to run against): `DBQuery::aggregate()`/
`exists()`/`pluck()`/`groupCount()`/`increment()`/`decrement()`, and `WhereHas`'s actual
`EXISTS` subquery execution. Wire up a test database and extend this file (or a proper
PHPUnit suite) using the same `check()` pattern to cover those — ideally against a small
fixture schema (e.g. `user` / `wallet` / `wallettransaction`) matching the examples used
throughout this document.

---

## 10. Known limitations / suggested next steps

- **`relation.field` identifiers aren't validated against the related model's real
  fields** — only character-safety is checked for dot-qualified keys, since the related
  `ObjectMap` isn't available at validation time. Closing this fully means threading
  known-fields lists through `buildJoins()`.
- **mysqli-only.** All of the new methods are built on the same `mysqli`-specific
  foundation as the rest of the codebase — no PDO/Postgres/SQLite path exists.
- **No real transactions.** `Increment`/`Decrement`/`UpdateWhere` are each single atomic
  statements, but there's still no `BEGIN`/`COMMIT`/`ROLLBACK` wrapper for grouping
  multiple operations (e.g. `$order->save(); $item->save();`) into one atomic unit.
- **`Random()`, `Upsert()`, `FirstOrCreate()`** were discussed but intentionally not
  added — see the prior conversation for the reasoning (performance caveats for
  `ORDER BY RAND()`, MySQL-syntax lock-in for `ON DUPLICATE KEY`, and `FirstOrCreate`
  depending on `Exists()`/`UpdateWhere()` existing first, which they now do, so it's a
  reasonable next addition).
- **No CI / no PHPUnit.** `tests/smoke_test.php` is a stopgap; the project would benefit
  from a proper PHPUnit setup and a GitHub Actions workflow running it against a real
  MySQL service container.
