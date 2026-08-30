# Wixnit Library

**Package:** `wixnit/core`
**Namespace root:** `Wixnit\`
**Requires:** PHP `^8.1`
**License:** MIT

Wixnit is a "slim, sleek" PHP library built around a single idea: **a model's public properties are the source of truth for everything ** — the database schema, the JSON output, the fillable fields, the validation target. There's no separate migration DSL, no config-driven schema, no DTO layer to keep in sync — you declare a class, and Wixnit derives the table, the columns, the joins, and the serialization from it via reflection and PHP 8 attributes. Wixnit gives the developer full control over project structure, authentication while providing the right tools that make building systems enjoyable. Wixnit it not opinionated so the developers decide the shape the project takes.

This is a summarized cover of everything in the library: the ORM (`Wixnit\Data` / `Wixnit\App`), routing and HTTP (`Wixnit\Routing`), validation, events, queues, scheduling, the console, and the large utility library. Visit https://dev.wixnit.com for the complete documentation

---

## Table of contents

1. [Installation & project layout](#1-installation--project-layout)
2. [Core concepts](#2-core-concepts)
3. [Defining models](#3-defining-models)
4. [Relationships](#4-relationships)
5. [Querying — Filter, Search, Order, Pagination](#5-querying)
6. [Reading & writing data](#6-reading--writing-data)
7. [Value objects](#7-value-objects)
8. [State machines](#8-state-machines)
9. [Database configuration & migrations](#9-database-configuration--migrations)
10. [Routing & HTTP](#10-routing--http)
11. [Validation](#11-validation)
12. [Events](#12-events)
13. [Queues](#13-queues)
14. [Scheduling](#14-scheduling)
15. [Console commands](#15-console-commands)
16. [Dependency container](#16-dependency-container)
17. [Configuration](#17-configuration)
18. [Access control](#18-access-control)
19. [Utilities reference](#19-utilities-reference)
20. [Exceptions](#20-exceptions)
21. [Extension points (interfaces)](#21-extension-points-interfaces)

---

## 1. Installation & project layout

```bash
composer require wixnit/core
```

```json
{
    "autoload": {
        "psr-4": { "Wixnit\\": "vendor/wixnit/core/src/" }
    },
    "require": { "php": "^8.1" }
}
```

Suggested project shape

```
app/
  Models/          User.php, Order.php, ...      (extend Wixnit\App\Model)
  Controllers/      UserController.php ...        (extend Wixnit\App\Controller)
  Jobs/             SendWelcomeEmail.php ...       (extend Wixnit\Queue\Job)
  Events/           OrderPlaced.php ...
  Listeners/
  Commands/         MigrateCommand.php ...         (extend Wixnit\Console\Command)
views/               *.php templates
config/              app.php, mail.php, database.php
bin/wixnit           console entry point
public/index.php     HTTP entry point
```

---

## 2. Core concepts

| Concept | Namespace | Role |
|---|---|---|
| `Model` / `PointerModel` / `Savable` / `PointerSavable` | `Wixnit\App` | Base classes for persisted entities |
| `Transactable` | `Wixnit\Data` | The engine underneath every model — reflection-based mapping, save/delete, relation loading |
| `Mappable` | `Wixnit\Data` | Lower-level reflection/object-mapping base `Transactable` builds on |
| `DBConfig` | `Wixnit\Data` | Wraps a `mysqli` connection |
| `Filter` / `FilterBuilder` / `Search` / `Order` / `Pagination` | `Wixnit\Data` | Composable query-building objects passed as variadic arguments to `Get()`/`Count()`/etc. |
| `Router` / `Route` / `Controller` | `Wixnit\Routing` / `Wixnit\App` | HTTP layer |
| `Validation` | `Wixnit\Validation` | Laravel-style pipe-delimited rule validation |
| `Event` | `Wixnit\Events` | Publish/subscribe |
| `Queue` / `Job` / `Worker` | `Wixnit\Queue` | Background job processing, pluggable drivers |
| `Schedule` | `Wixnit\Schedule` | Cron-style task scheduling |
| `Kernel` / `Command` | `Wixnit\Console` | CLI commands with attribute-declared arguments/options |
| `Container` | `Wixnit\App` | A minimal service locator |
| `Config` | `Wixnit\Data` | Dot-notation configuration store |

Two conventions run through the whole ORM and are worth internalizing up front:

- **Table names** are the model's own class's short name, lowercased (`class User` → table `user`).
- **Models** have id - a random string generated when save() is called. created - a Wixnit DateTime object set to the time the class is created. modified - a Wixnit DateTime object updated when ever a model is saved.

---

## 3. Defining models

There are four base classes to extend, depending on how the object's database connection and persistence are handled:

| Base class | Connection | Use case |
|---|---|---|
| `Model` | Resolved automatically (container / `DBConfig`) | The default choice for almost everything |
| `PointerModel` | Passed explicitly to every static call | Multi-tenant / multi-database apps where the connection isn't fixed |
| `Savable` | Resolved automatically | A `Transactable` that isn't queried as a collection (e.g. a singleton settings row) |
| `PointerSavable` | Passed to the constructor | `Savable`, with an explicit connection |

A minimal model:

```php
namespace App\Models;

use Wixnit\App\Model;
use Wixnit\Data\Unique;
use Wixnit\Data\Email;

class User extends Model
{
    #[Unique]
    public Email $email;

    public string $name; //Wixnit will initialize it with an empty string
    public bool $isActive = true; //if left uninitialized, it'll be set to false
    public int $age; //Wixnit will intialize it with the default value 0
}
```

That's the entire schema declaration. Every public property becomes a database column; the type of the property drives the generated SQL column type via `DBMapper`. Nothing else is required to `Get()`, `save()`, or `delete()` a `User`.

### Property-level attributes

All attributes live in `Wixnit\Data` and are applied directly to public properties:

| Attribute | Effect |
|---|---|
| `#[Unique]` | Marks the column as `UNIQUE` in the schema — alternative to listing the property name in `protected array $unique = [...]` |
| `#[Fillable]` | Allow-lists a property for `fill()`/mass assignment. Presence of *any* `#[Fillable]` on a class puts it in allow-list mode. |
| `#[Guarded]` | Deny-lists a property from `fill()`. Inverse of `Fillable` — use one or the other, not both. |
| `#[Immutable]` | The property is settable up through the object's first `save()`, then never again — enforced against the value captured at hydration time. |
| `#[Exclude]` | Keeps the property out of the database schema entirely (it isn't a column). Give it a value yourself, e.g. in `onInitialized()`. |
| `#[Redacted]` | Excludes the property from `json_encode()` output only — it's still a normal, directly-readable/writable PHP property everywhere else. |
| `#[Cast(SomeCaster::class)]` | Applies a stateless transform to a scalar property on the way in/out of the DB — see [`ICaster`](#21-extension-points-interfaces). |
| `#[Mask(SomeMasker::class)]` | Chooses which `IMasker` a `Masked` property uses (default `GenericMasker`). |
| `#[Searchable]` | Narrows `Search`'s default field list (which is otherwise every public property) to just the marked properties. |
| `#[Flags('edit', 'publish', 'delete')]` | Gives named bit positions to a `FlagSet` property. |
| `#[BelongsTo(Class::class)]`, `#[HasMany(...)]`, `#[BelongsToMany(...)]`, `#[HasManyThrough(...)]` | Relationship declarations — see [§4](#4-relationships). |

```php
use Wixnit\Data\{Fillable, Guarded, Immutable, Exclude, Redacted, Unique, Cast, Masked, Mask, Email};

class User extends Model
{
    #[Unique]
    #[Fillable]
    public Email $email;

    #[Fillable]
    public string $name;

    #[Immutable]           // set once at creation, never changed again
    public string $referredBy;

    #[Redacted]             // never appears in json_encode($user)
    public string $apiKey;

    #[Exclude]               // not a DB column — computed at runtime
    public string $displayName;

    #[Mask(\Wixnit\Data\EmailMasker::class)]
    public Masked $ssn;      // stores the raw value, exposes only a masked() view

    protected function onInitialized()
    {
        $this->displayName = trim($this->name) ?: $this->email;
    }
}
```

### Lifecycle hooks

`Transactable` exposes protected no-op hooks you can override:

```php
protected function onCreated()    // right after the object is constructed
protected function onInitialized()// after all fields/relations/value properties are bound
protected function onPreSave()    // right before save() writes to the database
protected function onSaved()      // after save() completes (insert or update)
protected function onInserted()   // after a fresh insert specifically
protected function onUpdated()    // after an update specifically
protected function onDeleted()    // after delete()
```

### Soft deletes

```php
class User extends Model
{
    protected bool $useSoftDelete = true;
}

$user->delete();                 // marks `deleted`, excluded from Get() from now on
User::SoftDeleted();             // DBCollection of only soft-deleted rows
User::Restore(new Filter(["id" => $id]));  // undo it
User::Purge();                   // permanently remove every soft-deleted row
```

---

## 4. Relationships

Wixnit has four relationship attributes, each solving a distinct data shape.

### `#[BelongsTo]` — the "child" foreign key

```php
use Wixnit\Data\BelongsTo;

class Order extends Model
{
    #[BelongsTo(User::class)]
    public string $userid;
}
```

### `#[HasMany]` — one-to-many, backed by a real foreign key

```php
use Wixnit\Data\{HasMany, HasManyCollection};

class User extends Model
{
    // eager, array-typed: loaded (batched, page-wide) whenever a collection of Users is fetched
    #[HasMany(Order::class, 'userid')]
    public array $orders = [];

    // lazy, collection-typed: only queried when you touch it — for large relations
    #[HasMany(Review::class, 'userid')]
    public HasManyCollection $reviews;
}
```

```php
// Lazy relation usage
$user->reviews->load();
foreach($user->reviews as $review) { ... }
$user->reviews->count();
$user->reviews->add($newReview);
```

### `#[BelongsToMany]` — many-to-many through an auto-generated pivot

No pivot model is needed; `DBMigrator` synthesizes the junction table automatically.

```php
use Wixnit\Data\{BelongsToMany, BelongsToManyCollection};

class Post extends Model
{
    #[BelongsToMany(Tag::class, pivot: 'post_tag', localKey: 'postid', relatedKey: 'tagid')]
    public BelongsToManyCollection $tags;
}

class Tag extends Model
{
    #[BelongsToMany(Post::class, pivot: 'post_tag', localKey: 'tagid', relatedKey: 'postid')]
    public BelongsToManyCollection $posts;
}
```

```php
$post->tags->attach($tag);
$post->tags->attach([$tag1, $tag2]);
$post->tags->detach($tag);
$post->tags->sync([$tag1, $tag2]);   // replace the full set
$post->tags->all();
count($post->tags);                  // Countable
foreach($post->tags as $tag) { ... } // IteratorAggregate
```

### `#[HasManyThrough]` — many-to-many with data on the association itself

If the association needs its own fields (a quantity, a timestamp), use a real pivot model instead of `BelongsToMany`:

```php
class OrderLine extends Model
{
    #[BelongsTo(Order::class)]
    public string $orderid;

    #[BelongsTo(Product::class)]
    public string $productid;

    public int $quantity = 1;
}

class Order extends Model
{
    #[HasMany(OrderLine::class, 'orderid')]
    public array $lines = [];   // the pivot rows themselves, quantity and all

    #[HasManyThrough(Product::class, through: OrderLine::class, throughLocalKey: 'orderid', throughRelatedKey: 'productid')]
    public array $products = []; // the actual Product objects, read-only
}

// add an association with data:
$line = new OrderLine();
$line->orderid = $order->id;
$line->productid = $product->id;
$line->quantity = 3;
$line->save();
```

### `Morph` — polymorphic references

For a property that can point at *one of several different tables* (e.g. `Order::$from` being either a `Staff` or a `Customer`) — something a normal join can't express, since it targets exactly one table.

```php
use Wixnit\Data\Morph;

class OrderFrom extends Morph
{
    protected static function types(): array
    {
        return ["staff" => Staff::class, "customer" => Customer::class];
    }
}

class Order extends Model
{
    public OrderFrom $from;
}

$order->from = OrderFrom::To($customer);
$order->save();

$who = $order->from->resolve();          // Staff|Customer|null (lazy, cached after first call)
$order->from->is(Customer::class);       // bool
```

The inverse side (find every row pointing back at an owner) uses `MorphMany`, built via `Morph::Many()`:

```php
class Customer extends User
{
    public function orders(): MorphMany
    {
        return OrderFrom::Many(Order::class, "from", $this);
    }
}

$customer->orders()->get();     // DBCollection of every matching Order
$customer->orders()->count();
$customer->orders()->first();
```

---

## 5. Querying

Every collection-returning static method (`Get`, `Count`, `SoftDeleted`, `Sum`, `Pluck`, ...) accepts a **variadic list of query objects**, type-sniffed via `instanceof` — order doesn't matter, and you only pass what you need.

### `Filter` — WHERE conditions

```php
use Wixnit\Data\{Filter, GreaterThan, LessThan, NotEqual, In, NotIn, IsNull, IsNotNull};

// simple equality, AND'ed together
$users = User::Get(new Filter(["isActive" => true, "age" => 30]));

// comparison operators as the value
$adults   = User::Get(new Filter(["age" => new GreaterThan(18)]));
$adultsEq = User::Get(new Filter(["age" => new GreaterThan(18, orEqualTo: true)])); // >=
$minors   = User::Get(new Filter(["age" => new LessThan(18)]));
$notBanned = User::Get(new Filter(["status" => new NotEqual("banned")]));

// IN / NOT IN
$users = User::Get(new Filter(["status" => new In("active", "pending")]));
$users = User::Get(new Filter(["status" => new NotIn("banned", "deleted")]));

// NULL checks
$users = User::Get(new Filter(["deletedReason" => new IsNull()]));
$users = User::Get(new Filter(["deletedReason" => new IsNotNull()]));

// OR instead of AND
use Wixnit\Enum\FilterOperation;
$filter = new Filter(["email" => "a@b.com", "username" => "a@b.com"], FilterOperation::OR);
```

`Filter::Builder()` composes multiple `Filter`s with mixed AND/OR groups via `FilterBuilder`:

```php
$builder = Filter::Builder();
$builder->add(new Filter(["isActive" => true]));
$builder->add(new Filter(["age" => new GreaterThan(65)]), );
$builder->setOperation(FilterOperation::OR);
$users = User::Get($builder);
```

### Relation-aware filtering

**`Filter::On()`** filters a singular (auto-joined) relation without knowing the internal join-aliasing rule:

```php
use Wixnit\Data\Filter;
use Wixnit\Data\GreaterThan;

// equivalent to: new Filter(['wallet.amount' => new GreaterThan(100)])
$users = User::Get(Filter::On('wallet', ['amount' => new GreaterThan(100)]));
```

**`WhereHas`** filters by a real one-to-many child, rendered as a correlated `EXISTS` subquery (parent rows are never duplicated):

```php
use Wixnit\Data\WhereHas;

$users = User::Get(
    new WhereHas('wallettransaction', 'userid', new Filter(['amount' => new GreaterThan(100)]))
);

// just "has any row at all":
$buyers = User::Get(new WhereHas('order', 'userid'));
```

### `Search` — LIKE-based text search

```php
use Wixnit\Data\Search;
use Wixnit\Enum\SearchPosition;

// search across specific fields
$results = User::Get(new Search("john", ["name", "email"]));

// search across every public property (or every #[Searchable] one, if present)
$results = User::Get(new Search("john"));

$results = User::Get(new Search("john", ["name"], SearchPosition::START)); // "john%"
```

Combine multiple searches with `Search::Builder()`, mirroring `Filter::Builder()`.

### `Order`, `Pagination`, `DistinctOn`, `GroupBy`

```php
use Wixnit\Data\{Order, Pagination, DistinctOn, GroupBy};
use Wixnit\Enum\OrderDirection;

$users = User::Get(new Order("created", OrderDirection::DESCENDING));
$page  = User::Get(new Pagination(page: 2, perpage: 25));
$users = User::Get(new DistinctOn(["country"]));
```

### `With` / `Without` — control eager relation loading

```php
use Wixnit\Data\{With, Without};

$users = User::Get(new With("orders", "reviews"));   // force-load, even if lazy by default
$users = User::Get(new Without("orders"));           // skip loading, even if eager by default
```

---

## 6. Reading & writing data

### Reads

Every method below is available on `Model`/`PointerModel` (add a leading/trailing `mysqli` argument for `PointerModel`), accepting the same variadic query-object arguments as `Get()`.

```php
User::Get(...$args): DBCollection
User::SoftDeleted(...$args): DBCollection
User::Count(...$args): int
User::CountDeleted(...$args): int
User::Exists(...$args): bool
User::First(...$args)                 // first row or null
User::Latest(...$args)                // First(), ordered by created DESC
User::Oldest(...$args)                // First(), ordered by created ASC
User::Pluck('email', ...$args): array // flat array of one column
User::GroupCount('status', ...$args): array   // ['active' => 12, 'banned' => 3]
User::Sum('amount', ...$args): int|float|string|null
User::Average('age', ...$args)
User::Min('age', ...$args)
User::Max('age', ...$args)
```

```php
$collection = User::Get(new Filter(["isActive" => true]), new Order("created"));
foreach($collection->list as $user) { echo $user->name; }

$totalOwed = Invoice::Sum('amount', new Filter(['paid' => false]));
$isTaken   = User::Exists(new Filter(['email' => 'a@b.com']));
$mostRecent = User::Latest();
$activeIds = User::Pluck('id', new Filter(['status' => 'active']));
$byStatus  = Order::GroupCount('status');
```

`DBCollection` extends `Collection` (see [§19](#collections)) — it's `ArrayAccess`, `Countable`, `IteratorAggregate`, and `JsonSerializable`, with `->list` holding the hydrated model instances plus every `Collection` helper (`map`, `filter`, `pluck`, `sortBy`, ...).

### Writes

```php
$user = new User();
$user->email = "a@b.com";
$user->name  = "Jane";
$user->save();          // INSERT (id is empty) or UPDATE (id is set)

$user->delete();        // soft-delete if $useSoftDelete, else a real DELETE

$user->fill(["name" => "New Name"])->save();  // mass-assign only #[Fillable]/non-#[Guarded] fields
```

Bulk / atomic write shortcuts — all avoid the fetch-then-save race conditions of doing the same thing in PHP:

```php
Post::Increment('views', 1, new Filter(['id' => $postId]));   // atomic field = field + ?
Post::Decrement('stock', 5, new Filter(['id' => $productId]));

Order::UpdateWhere(
    ['status' => 'archived'],
    new Filter(['status' => 'shipped', 'created' => new LessThan($cutoff)])
);  // bulk UPDATE, no fetch-then-save

User::Restore(new Filter(['id' => $userId]));  // undo a soft delete

User::Chunk(500, function(DBCollection $batch) {
    foreach($batch as $user) {
        // process without loading every matching row into memory at once
    }
}, new Filter(["isActive" => true]));   // composes with Filter/Order/etc like Get() does
```

### Dirty tracking

Every `Transactable` uses the `DirtyTracking` trait:

```php
$user = User::First(new Filter(["id" => $id]));
$user->name = "Changed";

$user->isDirty();          // true
$user->isDirty('name');    // true
$user->isDirty('email');   // false
$user->getChanges();       // ['name' => 'Changed']
$user->getOriginal('name');// the value before the change
```

### Optimistic locking / concurrency

```php
use Wixnit\Data\OptimisticLock;

class Account extends Model
{
    public OptimisticLock $version;   // bump automatically each save()
}
```

Saving a stale-loaded object throws `ConcurrencyException::StaleWrite()`.

---

## 7. Value objects

Wixnit ships a family of self-validating, self-serializing value objects. Each implements `ISerializable` (so it maps directly to/from a DB column with no extra wiring) plus `Stringable`/`JsonSerializable` where it makes sense.

### `Money` — exact currency arithmetic

Stored internally as an **integer in minor units** (cents), avoiding all floating-point rounding bugs.

```php
use Wixnit\Data\Money;

$price = new Money(19.99);                 // major units, convenient literal form
$price = Money::fromMinorUnits(1999);       // 19.99, from an already-integer cents value
$total = $price->add(new Money(5.00));
$half  = $price->divide(2);
$price->toFloat();          // 19.99
$price->toDecimalString();  // "19.99"
$price->format('en_US');
$price->allocate([1, 1, 1]); // split a total three ways with no lost cents

class Order extends Model
{
    public Money $total;    // stored as a plain BIGINT column
}
```

Arithmetic between two `Money` instances of different `$scale` throws `ScaleMismatchException`; use `withScale()` to normalize first.

### `Email`, `Username`, `PhoneNumber` — validated identity types

```php
use Wixnit\Data\{Email, Username, PhoneNumber};
use Wixnit\Enum\PhoneFormat;

$email = Email::fromString("Foo@Bar.COM"); // throws InvalidEmailException if malformed
$email->getAddress();   // "foo@bar.com" — always lowercased/trimmed
$email->mask();          // "f**@bar.com"

$username = Username::fromString("JohnDoe");
$username->getValue();       // "JohnDoe" — display casing preserved
$username->getNormalized();  // "johndoe" — compare/index on this

$phone = PhoneNumber::fromE164("+2348012345678");
$phone->format(PhoneFormat::NATIONAL);

// swap in Google's libphonenumber for real per-country validation:
PhoneNumber::useLibPhoneNumber();
// ...or your own:
PhoneNumber::setValidator($yourValidator);
```

All three have lenient constructors (for DB hydration — bad data becomes empty rather than throwing) and strict `fromString()`/`tryFrom()` factories (for fresh user input — these validate).

### `Barcode` — EAN-13 / UPC-A

```php
use Wixnit\Data\Barcode;
use Wixnit\Enum\BarcodeFormat;

$barcode = Barcode::generate("123456789012", BarcodeFormat::EAN13); // computes the check digit
$barcode = Barcode::fromString("6291041500213");
$barcode->toUpcA();
```

### `Color`

```php
use Wixnit\Utilities\Color;

$color = Color::FromHex("#3498db");
$color = Color::FromRGBO(52, 152, 219, 1.0);
$color->withOpacity(0.5)->toAHex();
```

### `HashedPassword`, `Encrypted`, `Masked`, `FlagSet`, `Counter`, `JsonDocument`, `LazyText`

```php
use Wixnit\Data\{HashedPassword, Encrypted, Masked, FlagSet, Counter, JsonDocument, LazyText, Flags};

class User extends Model
{
    public HashedPassword $password;   // bcrypt under the hood

    public Encrypted $ssn;             // libsodium-encrypted at rest

    #[Flags('edit', 'publish', 'delete')]
    public FlagSet $permissions;       // named-bit bitmask, stored as one BIGINT

    public Counter $loginCount;        // atomic increment/decrement counter column

    public JsonDocument $settings;     // dot-path get/set into a JSON column

    public LazyText $biography;        // large text column, only loaded when touched
}

$user->password->set("secret123");
$user->password->verify("secret123");     // true
$user->password->needsRehash();

$user->ssn->set("123-45-6789");
$user->ssn->decrypt();

$user->permissions->add('edit')->add('publish');
$user->permissions->has('edit');          // true
$user->permissions->remove('publish');

$user->loginCount->increment();

$user->settings->set('theme.color', 'dark');
$user->settings->get('theme.color');      // "dark"

echo $user->biography->get();  // triggers a load if not already fetched
```

### Encryption key configuration

```php
use Wixnit\Data\EncryptionConfig;

EncryptionConfig::Init(currentKey: getenv('APP_KEY'), oldKeys: [getenv('APP_KEY_OLD')]);
// old keys let previously-encrypted data keep decrypting through a key rotation
```

---

## 8. State machines

`StateMachine` (`Wixnit\Data`) turns a property into a fully-validated transition graph — the state IS a model property, with the graph declared once as class-level configuration.

```php
use Wixnit\Data\StateMachine;

class OrderStatusMachine extends StateMachine
{
    protected static function enumClass(): string { return OrderStatus::class; }

    protected static function transitions(): array
    {
        return [
            OrderStatus::PENDING->name   => [OrderStatus::PAID, OrderStatus::CANCELLED],
            OrderStatus::PAID->name      => [OrderStatus::SHIPPED, OrderStatus::REFUNDED],
            OrderStatus::SHIPPED->name   => [OrderStatus::DELIVERED],
            OrderStatus::DELIVERED->name => [],
            OrderStatus::CANCELLED->name => [],
            OrderStatus::REFUNDED->name  => [],
        ];
    }

    // optional: extra conditions beyond the transitions() graph
    protected static function guards(): array
    {
        return [
            "PAID->SHIPPED" => fn($model, $from, $to) => $model->paymentConfirmed,
        ];
    }

    // optional: side effects around a transition
    protected static function actions(): array
    {
        return [
            "enter:SHIPPED" => function($model, $from, $to) {
                Logger::Info("Order shipped", ["orderId" => $model->id]);
            },
        ];
    }
}

class Order extends Model
{
    public OrderStatusMachine $status;
}
```

```php
$order->status->current();                              // OrderStatus::PENDING
$order->status->can(OrderStatus::PAID, $order);          // bool
$order->status->transitionTo(OrderStatus::PAID, $order); // validates, saves, dispatches StateTransitioned
$order->status->getHistory();                            // StateHistory[]
$order->status->undo($order);                            // revert the last transition
```

An illegal transition throws `StateMachineException::IllegalTransition()`. History is packed as JSON alongside the current state in the same column by default (`trackHistory()` can opt out for high-frequency transitions where a dedicated history table is a better fit).

---

## 9. Database configuration & migrations

### Connecting

```php
use Wixnit\Data\DBConfig;

// standard: registers a lazy connection in the Container, reused by every Model
DBConfig::Init(hostname: "localhost", username: "root", password: "", database: "app");

// wrap an already-open mysqli connection
DBConfig::Use($mysqli);

// wrap a connection without registering it globally (used internally by PointerModel)
DBConfig::UseOnce($mysqli);
```

### Migrations

Schema is derived directly from a model's declared properties — there's no separate migration file format. `DBMigrator::mapClass()` diffs (or creates) a table to match a class's current shape, including synthesizing pivot tables for any `#[BelongsToMany]` relations:

```php
use Wixnit\Data\DBMigrator;

$migrator = new DBMigrator($mysqli);
$migrator->mapClass(User::class);
$migrator->mapClass(Order::class);
```

The bundled `migrate:run` console command (see [§15](#15-console-commands)) wraps this for every model your application registers.

---

## 10. Routing & HTTP

### Setting up the router

```php
// public/index.php
use Wixnit\Routing\Router;
use Wixnit\Routing\Route;
use Wixnit\Enum\HTTPMethod;

$router = new Router();

$router->get("/", HomeController::class);
$router->get("users/{id:int}", UserController::class);
$router->post("users", UserController::class);
$router->any("health", fn() => (new \Wixnit\Routing\Response())->text("ok"));

$router->group("api", [
    new Route("users", HTTPMethod::GET, UserController::class),
    new Route("users/{id:uuid}", HTTPMethod::GET, UserController::class, "get"),
    RouteCollection::Group("post", [ //all routes will be prepended with post
        new Route("", HTTPMethod::ANY, UserController::class),
        new Route("{id:uuid}", HTTPMethod::GET, UserController::class, "get"),
        RouteCollection::Group("tag", [
            new Route("", HTTPMethod::ANY, UserController::class),
            new Route("{id:uuid}", HTTPMethod::GET, UserController::class, 'getTags'),
        ]),
    ]),
]);

$router->mapRoutes();   // matches the current request and dispatches it
```

### Path parameters

Segments in `{}` are captured; an optional `:type` constrains and coerces them:

| Syntax | Matches |
|---|---|
| `{id}` | anything, passed through as a string |
| `{id:int}` | `-?\d+`, cast to `int` |
| `{slug:alpha}` | letters only |
| `{code:alnum}` | letters and digits |
| `{slug:slug}` | letters, digits, `-`, `_` |
| `{id:uuid}` | a UUID |
| `{*}` | wildcard — matches the rest of the path |

A path that matches but with the wrong HTTP method produces a `405`, not a `404` — the router distinguishes the two automatically.

### Handlers

A route's handler can be a `Closure`, a `Controller` class name, a `View`, or a `Path` (a plain PHP file to `require`):

```php
$router->get("about", new View("pages/about"));
$router->get("legacy.php", new Path(__DIR__ . "/legacy/page.php"));
$router->get("ping", fn(Request $req) => (new Response())->json(["pong" => true]));
```

When a `Controller` class is given, the HTTP method picks the default handler method automatically (`GET` → `get()`, `POST` → `create()`, `PUT` → `update()`, `DELETE` → `delete()`, `PATCH` → `patch()`), or pass an explicit `$handlerMethod` as the router call's fourth argument.

### Controllers

```php
use Wixnit\App\Controller;
use Wixnit\Routing\{Request, Response};

class UserController extends Controller
{
    public function get(Request $req, array $args = []): Response
    {
        $user = User::First(new Filter(["id" => $args['id']]));
        return $this->response->json($user);
    }

    public function create(Request $req, array $args = []): Response
    {
        $validation = $req->validate([
            "email" => "email|required",
            "name"  => "string|required",
        ]);

        if($validation->fails())
        {
            return $this->response->setStatusCode(HTTPResponseCode::BAD_REQUEST)->json($validation->getErrors());
        }

        $user = new User();
        $user->fill($req->getPost())->save();
        return $this->response->json($user);
    }
}
```

### `Request`

```php
$req->getPost();          // array
$req->getGet();           // array
$req->getJson();          // ?stdClass
$req->getRoutedArgs();    // path params, e.g. ['id' => 42]
$req->getMethod();        // HTTPMethod
$req->validate($rules);   // Validation instance, pre-loaded with request data
$req['someField'];        // ArrayAccess over the merged request data

Request::GetClientIP();
Request::GetBearerToken();
Request::GetRequestHeaders();
Request::HasSession('userId');
Request::GetSession('userId');
```

### `Response`

```php
(new Response())->json(['ok' => true]);
(new Response())->html('<h1>Hi</h1>');
(new Response())->text('plain text');
(new Response())->xml('<root/>');
(new Response())->redirect('/login');
(new Response())->fileDownload('report.csv', $csvContent);
(new Response())
    ->setStatusCode(HTTPResponseCode::CREATED)
    ->setHeader('X-Custom', 'value')
    ->setCookie('session', $token, expires: time() + 3600)
    ->json($data)
    ->send();

Response::SetGlobalCorsHeaders(allow: "*");
```

### The `api` response helper

`Wixnit\App\api` builds consistent envelope objects for common status codes — pair with `Response::json()`:

```php
use Wixnit\App\api;

(new Response())->json(api::Success($user));
(new Response())->json(api::NotFound("User not found"));
(new Response())->json(api::ValidationError($validation->getErrors()));
(new Response())->json(api::Unauthorized());
```

### Guards, interceptors, named routes

```php
use Wixnit\Interfaces\IRouteGuard;
use Wixnit\Routing\PayloadedGuard;

class AuthGuard extends PayloadedGuard implements IRouteGuard
{
    public function checkAccess(Request $req, array $payload = []): bool
    {
        $user = Auth::ByToken(Request::GetBearerToken());

        if($user == null)
        {
            return false;
        }
        $th->addPayload($user); //will be delivered to the contoller or closure in the array $args
        return true;
    }

    public function onFail(): Response
    {
        return (new Response())->setStatusCode(HTTPResponseCode::UNAUTHORIZED)->json(['error' => 'unauthorized']);
    }
}

$router->get('/dashboard', DashboardController::class)
    ->useGuard(new AuthGuard())
    ->name('dashboard');

echo Router::Url('dashboard');  // resolve a named route back to a URL
```

`interceptResponse(IInterceptor $interceptor)` on a `Route`/`RouteCollection` lets you post-process a `Response` before it's sent (logging, envelope wrapping, etc.), and `Router::interceptRequest()`/`interceptResponse()` register global closures that run for every route.

### Views

```php
use Wixnit\App\Views;

$views = new Views(__DIR__ . '/views');
$view = $views->get('users.profile')->with(['user' => $user]);
$view->render();
```

Inside a `.php` view template:

```php
<?php $this->extend('layouts.app'); ?>
<?php $this->section('content'); ?>
    <h1><?= $this->e($user->name) ?></h1>
<?php $this->endSection(); ?>
```

`extend`/`section`/`endSection`/`show`/`push`/`endPush`/`stack`/`component` mirror Blade-style templating, and `e()` HTML-escapes a value for safe interpolation.

---

## 11. Validation

```php
use Wixnit\Validation\Validation;

$validation = new Validation($requestData);
$validation->addValues([
    "name"     => "string|required",
    "password" => "string|required|min:6",
    "phone"    => "phone|required|max:12",
    "email"    => "email|required",
    "role"     => "in:admin,editor,viewer",
]);

if(!$validation->test())
{
    print_r($validation->getErrors());
}
```

Or the one-line convenience form:

```php
$validation = Validation::make($data, [
    "email" => "email|required",
], [
    "email.required" => "We need your email address.",
]);

$validation->passes();     // bool
$validation->fails();      // bool
$validation->validated();  // only the fields that had rules and passed
$validation->firstError('email');
```

Rules are pipe-separated; a rule with a parameter uses `:`, with multiple parameters comma-separated (`between:1,10`, `in:a,b,c`).

### Built-in rules

| Category | Rules |
|---|---|
| Type | `string`, `number`/`numeric`, `integer`/`int`, `float`/`decimal`, `bool`/`boolean`, `array` |
| Format | `email`, `phone`, `url`/`link`/`website`/`uri`, `alpha`, `alpha_num`/`alphanumeric`, `plain`, `uuid`, `ip`, `ipv4`, `ipv6`, `json` |
| Date/time | `date`, `date_format:Y-m-d`, `time`, `after:field_or_date`, `before:field_or_date` |
| Size | `min:6`, `max:12`, `between:1,10`, `size:4`, `digits:4`, `digits_between:4,6` |
| Comparison | `in:a,b,c`, `not_in:a,b`, `regex:/pattern/`, `same:otherField`, `different:otherField`, `confirmed` (checks `{field}_confirmation`) |
| Presence | `required` |

### Custom rules

```php
Validation::extend(
    name: "even",
    test: fn($value, $params, $context) => ((int)$value % 2) === 0,
    message: fn($params, $context) => "{$context['label']} must be even",
);

$validation->addValue('quantity', 'integer|even');
```

---

## 12. Events

```php
use Wixnit\Events\Event;

class OrderPlaced
{
    public function __construct(public Order $order) {}
}

Event::Listen(OrderPlaced::class, function(OrderPlaced $event) {
    Logger::Info("Order placed", ["orderId" => $event->order->id]);
});

Event::Listen(OrderPlaced::class, SendOrderConfirmation::class); // any class with handle()

Event::Dispatch(new OrderPlaced($order));
```

Listeners can be a closure, an already-built object with `handle()`, or a class name (constructed fresh per dispatch). A class-name listener implementing `IShouldQueue` runs through `Wixnit\Queue` in the background automatically instead of inline.

```php
class SendOrderConfirmation implements IListener, IShouldQueue
{
    public function handle(object $event): void { /* ... */ }
}
```

Listening against a parent class or interface catches every event that extends/implements it. Priority ordering, stoppable propagation (`IStoppable`), and exception isolation are all supported:

```php
Event::Listen(OrderPlaced::class, $listenerA, priority: 10); // runs before priority 0
Event::Dispatch($event, catchExceptions: true); // a failing listener is logged, not fatal
```

---

## 13. Queues

```php
use Wixnit\Queue\{Queue, Job};
use Wixnit\Queue\Drivers\{FileDriver, DatabaseDriver, SyncDriver, MemoryDriver};

class SendWelcomeEmail extends Job
{
    public function __construct(public string $userId) {}

    public function handle(): void
    {
        // ... send the email ...
    }

    public function maxAttempts(): int { return 5; }
    public function backoffSeconds(int $attempt): int { return $attempt * 30; }
}

Queue::UseDriver(new FileDriver(__DIR__ . "/storage/queue")); // optional; defaults to a FileDriver

Queue::Push(new SendWelcomeEmail($user->id));
Queue::Later(300, new SendReminderEmail($user->id));            // runs in 5 minutes
Queue::Push(new SendWelcomeEmail($user->id), "emails");         // named queue

Queue::Work("default");                                          // process what's available, then stop
Queue::Work("default", ["stopWhenEmpty" => false, "sleep" => 2]); // run as a daemon

Queue::Failed();
Queue::Retry($failedJobId);
Queue::ForgetFailed($failedJobId);
```

### Drivers

| Driver | Storage |
|---|---|
| `SyncDriver` | Runs jobs immediately, inline — useful for local dev/tests |
| `MemoryDriver` | In-process array — tests |
| `FileDriver` | JSON files on disk — zero external dependencies |
| `DatabaseDriver` | MySQL tables (auto-created via `createTables()`) — for multi-worker production setups |

```php
Queue::UseDriver(new DatabaseDriver($mysqli, jobsTable: "queue_jobs", failedTable: "queue_failed_jobs"));
```

Jobs are serialized with native PHP `serialize()`, so keep constructor arguments to plain scalars/arrays/IDs, not open resources or live connections.

---

## 14. Scheduling

```php
use Wixnit\Schedule\Schedule;

Schedule::Call(fn() => Report::generateDaily())->dailyAt('06:00');
Schedule::Job(new CleanupOldSessions())->hourly();
Schedule::Event(new DailyDigestDue())->dailyAt('08:00');
Schedule::Command('php /app/bin/rotate-logs.php')->daily();

// in a script invoked by: * * * * * php /app/bin/scheduler.php
Schedule::RunDue();
```

`Job()`/`Event()` tasks dispatch through the existing `Queue::Push()`/`Event::Dispatch()` — a scheduled job is just enqueued at the right time; a scheduled event runs through the normal listener pipeline.

### Frequency methods (on the `ScheduledTask` returned by every `Schedule::*` call)

```php
->cron('*/15 * * * *')
->everyMinute() ->everyFiveMinutes() ->everyFifteenMinutes() ->everyThirtyMinutes()
->hourly() ->hourlyAt(30)
->daily() ->dailyAt('06:00') ->twiceDaily('01:00', '13:00')
->weekly() ->weeklyOn([1, 5], '09:00')
->monthly() ->monthlyOn(1, '00:00') ->lastDayOfMonth()
->quarterly() ->yearly() / ->annually() ->yearlyOn(1, 1)
->weekdays() ->weekends()
->between('09:00', '17:00') ->unlessBetween('12:00', '13:00')
->timezone('America/New_York')
```

### Constraints & side effects

```php
Schedule::Call($callback)
    ->dailyAt('02:00')
    ->when(fn() => Config::Get('backup.enabled'))
    ->withoutOverlapping(expiresAfterMinutes: 60)  // uses an ILockStore so concurrent runs don't stack
    ->onOneServer()                                  // multi-server dedup via the same lock store
    ->onSuccess(fn() => Logger::Info("Backup done"))
    ->onFailure(fn(Throwable $e) => Logger::Exception($e))
    ->sendOutputTo('/var/log/backup.log');
```

```php
use Wixnit\Schedule\Locks\{FileLockStore, DatabaseLockStore};

Schedule::UseLockStore(new DatabaseLockStore($mysqli)); // default: FileLockStore
```

---

## 15. Console commands

```php
use Wixnit\Console\{Kernel, Command, AsCommand, Argument, Option};

#[AsCommand('migrate:run', description: 'Run pending migrations')]
class MigrateCommand extends Command
{
    #[Option(shortcut: 'f', description: 'Drop all tables and re-migrate from scratch')]
    public bool $fresh = false;

    #[Argument(description: 'Only migrate this model class', default: null)]
    public ?string $model = null;

    public function handle(): int
    {
        $this->io->info("Running migrations...");
        // ...
        return self::SUCCESS;   // or self::FAILURE / self::INVALID
    }
}
```

```php
// bin/wixnit
$kernel = new Kernel();
$kernel->register(MigrateCommand::class);
$kernel->register(MakeModelCommand::class);
exit($kernel->run($argv));
```

`$this->io` (a `ConsoleIO`) provides `info()`, `success()`, `warning()`, `error()`, `table()`, `progressBar()`, `ask()`, `secret()`, `confirm()`, `choice()`.

`list` and `help` are always available even before any `register()` call. Every command's `#[Argument]`/`#[Option]` shape is validated at registration time, so a typo'd attribute fails at boot, not on first invocation.

### Running a command programmatically (e.g. from a test, or from another command)

```php
$exitCode = $kernel->call('migrate:run', ['--fresh' => true, 'model' => 'User']);
$output = $kernel->lastIO()->output();
```

### Bundled commands

| Command | Purpose |
|---|---|
| `list` | Lists all registered commands, grouped by the part before `:` |
| `help {command}` | Detailed usage for one command |
| `migrate:run` | Runs `DBMigrator` against registered models (`Container::set('console.migrate.models', [...])`) |
| `make:model` | Scaffolds a new model class |
| `make:controller` | Scaffolds a new controller class |
| `make:command` | Scaffolds a new console command class |
| `route:list` | Lists registered HTTP routes |
| `schedule:list` | Lists registered scheduled tasks |
| `schedule:run` | Runs `Schedule::RunDue()` |

```bash
php bin/wixnit migrate:run --fresh
php bin/wixnit make:model Product
php bin/wixnit route:list
```

---

## 16. Dependency container

```php
use Wixnit\App\Container;

Container::set('mailer', new SmtpMailer());
Container::bind('logger', fn() => new FileLogger('/var/log/app.log'), singleton: true);

$mailer = Container::get('mailer');
$logger = Container::get('logger', expectedType: FileLogger::class); // throws on mismatch

Container::has('mailer');    // bool
Container::remove('mailer');
Container::flush();          // clear everything — mainly for test isolation
```

---

## 17. Configuration

```php
use Wixnit\Data\Config;

Config::Set('mail.host', 'smtp.example.com');
Config::Get('mail.host');
Config::Get('mail.port', 587);          // default when unset
Config::Has('mail.host');
Config::Required('mail.host');           // throws ConfigException if missing

// config/app.php returns ['name' => 'My App', 'debug' => false]
// config/mail.php returns ['host' => '...', 'port' => 587]
Config::LoadDirectory(__DIR__ . '/config');
Config::Get('app.name');

Config::LoadEnv(__DIR__ . '/.env');
Config::Env('APP_DEBUG', false);

Config::GetString('app.name'); Config::GetInt('mail.port'); Config::GetBool('app.debug'); Config::GetArray('app.locales');

Config::DBCredentials(userName: 'root', password: '', dataBase: 'app'); // legacy shortcut wiring into DBConfig
```

---

## 18. Access control

`Access` (`Wixnit\Access`) is a compact read/write permission pair, storable as a single `INT` column:

```php
use Wixnit\Access\Access;

class Document extends Model
{
    public Access $permissions;
}

$doc->permissions = new Access(1); // 1=read+write, 2=read only, 3=write only, 0=none
$doc->permissions->read;   // bool
$doc->permissions->write;  // bool
$doc->permissions->toInt();
```

---

## 19. Utilities reference

All utility classes are static-method toolkits under `Wixnit\Utilities` unless noted, safe to use standalone without any ORM/routing setup.

### <a name="collections"></a>`Collection` / `Arr` / `ArrayUtil`

```php
use Wixnit\Utilities\Collection;

$c = Collection::Make([1, 2, 3, 4, 5]);
$c->filter(fn($n) => $n % 2 === 0)->map(fn($n) => $n * 10)->all();  // [20, 40]
$c->sum(); $c->avg(); $c->groupBy('status'); $c->sortBy('name');
$c->pluck('email', 'id'); $c->chunk(2); $c->paginate(page: 1, perPage: 10);
$c->when($condition, fn($c) => $c->filter(...));
```

`Arr` and `ArrayUtil` provide the plain-array (non-object) equivalents — `Arr::Get($array, 'a.b.c', $default)` (dot notation), `Arr::Only()`, `Arr::Except()`, `Arr::Flatten()`, `Arr::GroupBy()`, `ArrayUtil::Paginate()`, `ArrayUtil::Unique()`, etc.

### Date & time — `Date`, `Time`, `DateTime`, `Duration`, `Timespan`, `Span`, `Range`, `BusinessCalendar`

```php
use Wixnit\Utilities\{Date, Time, DateTime, Duration, Timespan, BusinessCalendar};

Date::Today()->addDays(7)->format('Y-m-d');
DateTime::IsPast($someDate);
Duration::Hours(2)->add(Duration::Minutes(30))->inWholeMinutes();  // 150

$span = Timespan::ThisMonth();
$span->duration(); $span->splitWeekly(); $span->businessDays();

$calendar = BusinessCalendar::UnitedStates()
    ->setWorkingHours('09:00', '17:00')
    ->addHoliday(Date::Today(), 'Company Holiday');

$calendar->isBusinessDay();
$calendar->nextBusinessDay();
$calendar->businessDaysBetween($start, $end);
$calendar->businessHoursBetween($start, $end); // Duration
```

`Span`/`Range` are the generic numeric-interval primitives `Timespan` builds on: `contains()`, `intersects()`, `union()`, `intersection()`, `split()`.

### Strings — `Str` / `StringUtil`

```php
use Wixnit\Utilities\Str;

Str::Slug("Hello World!");        // "hello-world"
Str::CamelCase("hello_world");    // "helloWorld"
Str::StudlyCase("hello_world");   // "HelloWorld"
Str::SnakeCase("HelloWorld");     // "hello_world"
Str::Truncate($text, 100);
Str::Mask("4111111111111111", visibleStart: 0, visibleEnd: 4);  // "************1111"
Str::MaskEmail("john@example.com");
Str::Contains($haystack, $needle);
Str::Initials("John Doe");        // "JD"
```

`StringUtil` additionally offers `Plural()`/`Singular()`, `RemoveAccents()`, `IsEmail()`/`IsUrl()`/`IsPhone()`.

### Random & crypto

```php
use Wixnit\Utilities\{Random, Crypto, Hash};
use Wixnit\Enum\CharacterType;

Random::UUID(); Random::Token(32); Random::Otp(6); Random::Password(16);
Random::Characters(12, CharacterType::ALPHANUMERIC);
Random::Pick(['a', 'b', 'c']);
Random::WeightedPick(['common' => 80, 'rare' => 20]);

Crypto::GenerateKey();
$encrypted = Crypto::Encrypt($data, $key);
$plain = Crypto::Decrypt($encrypted, $key);
Crypto::Sign($data, $key); Crypto::Verify($data, $signature, $key);

Hash::Bcrypt($password); Hash::Verify($password, $hash);
Hash::Sha256($data); Hash::Hmac($data, $key);
```

### Files, directories, images, uploads

```php
use Wixnit\Utilities\{File, Directory, Image, Upload};

File::Read($path); File::Write($path, $content); File::Copy($from, $to);
File::Mime($path); File::Size($path); File::Hash($path);
File::Download($path, downloadName: 'report.csv');

Directory::Create($path); Directory::Files($path, recursive: true); Directory::Clean($path);

Image::Resize($src, $dest, 800, 600);
Image::Thumbnail($src, $dest, size: 150);
Image::Watermark($src, $watermarkPath, $dest, position: 'bottom-right');
Image::Compress($src, $dest, quality: 75);

$upload = new Upload($_FILES['avatar']);
$upload->typeIs('image');
$upload->save('/uploads', newName: 'avatar.png');
```

### Caching & logging

```php
use Wixnit\Utilities\{Cache, Logger};
use Wixnit\Enum\LogLevel;

Cache::UseDirectory(__DIR__ . '/storage/cache');
$value = Cache::Remember('expensive-report', ttlSeconds: 3600, fn() => Report::build());
Cache::Put('key', $value, ttlSeconds: 60);
Cache::Forget('key');

Logger::UseDirectory(__DIR__ . '/storage/logs');
Logger::SetMinimumLevel(LogLevel::INFO);
Logger::Info("User logged in", ["userId" => $id]);
Logger::Exception($throwable);
Logger::Read(lines: 100, level: LogLevel::ERROR);
```

### Conversion & misc

```php
use Wixnit\Utilities\{Convert, Reflection, Stopwatch, Url};

Convert::ToBool("yes"); Convert::BytesToHuman(1048576); // "1 MB"
Convert::ToXml($array); Convert::FromXml($xml);
Convert::NumbersToWords(1234.56);

Reflection::Properties(User::class);
Reflection::Attributes(User::class, property: 'email');

Stopwatch::Start('import'); /* ... */ Stopwatch::Stop('import'); // seconds elapsed

Url::Parse('https://example.com/path?x=1');
Url::Append($url, ['page' => 2]);
```

---

## 20. Exceptions

Every exception in `Wixnit\Exception` follows the same shape: no public constructor arguments to remember — instead, **named static factory methods** that build a fully-formed, actionable message.

```php
use Wixnit\Exception\DatabaseException;

throw DatabaseException::InvalidFieldName('emial', knownFields: ['email', 'name', 'id']);
// "Unknown field 'emial'. Did you mean 'email'? Known fields: email, name, id"
```

| Exception | Common factories |
|---|---|
| `DatabaseException` | `QueryFailed()`, `UnsafeIdentifier()`, `InvalidFieldName()`, `DuplicateEntry()`, `NotFound()` |
| `RelationException` | `EmptyRelationTarget()`, `NoConditionsProvided()`, `UnknownRelation()`, `MismatchedBelongsTo()` |
| `PropertyException` | `ImmutablePropertyChanged()`, `MixedFillableAndGuarded()`, `NoEncryptionKeyAvailable()` |
| `ValidationConfigurationException` | `UnknownRule()`, `MissingParameter()` |
| `ConcurrencyException` | `StaleWrite()` |
| `StateMachineException` | `IllegalTransition()`, `InvalidPersistedState()` |
| `QueueException` | `UnserializationFailed()`, `PushFailed()` |
| `ScheduleException` | `InvalidCronExpression()`, `CommandFailed()` |
| `ConsoleException` | `UnknownCommand()`, `MissingArgument()`, `DuplicateShortcut()` |
| `ConfigException` | `FileNotFound()`, `MissingRequiredKey()` |
| `CryptoException` | `OpenSSLNotAvailable()`, `DecryptionFailed()` |
| `InvalidEmailException`, `InvalidUsernameException`, `InvalidPhoneNumberException`, `InvalidBarcodeException`, `InvalidAmountException` | Thrown by the corresponding value object's strict factories |

`WixnitException` (the base class most of these extend) carries structured `$context` alongside the message, retrievable via `getContext()`/`getDetails()`.

---

## 21. Extension points (interfaces)

| Interface | Implement this to... |
|---|---|
| `ISerializable` | Make any custom class storable as a model property (`_dbType()`, `_serialize()`, `_deserialize()`) |
| `ICaster` | Write a `#[Cast(...)]` transform (`castIn`/`castOut`) |
| `IMasker` | Write a `#[Mask(...)]` masking strategy |
| `IRouteGuard` | Protect a route (`checkAccess()`, `onFail()`) |
| `IInterceptor` | Post-process a `Response` on a route |
| `ITranslator` | Plug in i18n for `View` output |
| `IJob` / `IShouldQueue` | Queueable work |
| `IQueueDriver` | A custom queue backend (Redis, SQS, ...) |
| `ILockStore` | A custom distributed lock backend for `Schedule::withoutOverlapping()` |
| `IListener` | An object-based event listener |
| `IStoppable` | An event that can halt further listener propagation |
| `PhoneNumberValidatorInterface` / `UsernameValidatorInterface` | Custom validation strategy for `PhoneNumber`/`Username` |

Example — a custom cast:

```php
use Wixnit\Interfaces\ICaster;

class LowercaseCast implements ICaster
{
    public static function castIn(mixed $raw): mixed { return strtolower((string)$raw); }
    public static function castOut(mixed $value): mixed { return $value; }
}

class User extends Model
{
    #[Cast(LowercaseCast::class)]
    public string $email = "";
}
```

---

## Appendix: Quick reference — a complete mini app

```php
use Wixnit\App\{Model, Controller};
use Wixnit\Data\{DBConfig, Filter, Unique, Fillable, HasMany, BelongsTo};
use Wixnit\Routing\{Router, Request, Response};
use Wixnit\Validation\Validation;

DBConfig::Init('localhost', 'root', '', 'blog');

class Post extends Model
{
    #[Fillable] public string $title;
    #[Fillable] public string $body;

    #[HasMany(Comment::class, 'postid')]
    public array $comments = [];
}

class Comment extends Model
{
    #[BelongsTo(Post::class)]
    public string $postid;

    #[Fillable] public string $body;
}

class PostController extends Controller
{
    public function get(Request $req, array $args = []): Response
    {
        $post = Post::First(new Filter(["id" => $args['id']]));
        return $post ? $this->response->json($post)
                      : $this->response->setStatusCode(\Wixnit\Enum\HTTPResponseCode::NOT_FOUND)->json(['error' => 'not found']);
    }

    public function create(Request $req, array $args = []): Response
    {
        $v = $req->validate(["title" => "string|required", "body" => "string|required"]);
        if($v->fails()) return $this->response->setStatusCode(\Wixnit\Enum\HTTPResponseCode::BAD_REQUEST)->json($v->getErrors());

        $post = (new Post())->fill($req->getPost());
        $post->save();
        return $this->response->json($post);
    }
}

$router = new Router();
$router->get('/posts/{id:int}', PostController::class);
$router->post('/posts', PostController::class);
$router->mapRoutes();
```

