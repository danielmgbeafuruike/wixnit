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
    use Wixnit\Validation\Validation;
    use Wixnit\Exception\ValidationConfigurationException;

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

    echo "\nValidation\n";

    // the exact example from the conversation
    $validation = new Validation([
        "name" => "Ada Lovelace",
        "password" => "abc",
        "phone" => "08012345678",
        "email" => "not-an-email",
    ]);
    $validation->addValues([
        "name" => "string|required",
        "password" => "string|required|min:6",
        "phone" => "phone|required|max:12",
        "email" => "email|required",
    ]);
    $result = $validation->test();

    check("fails when password is too short and email is invalid", $result === false);
    check("password min:6 catches the short password", $validation->firstError("password") !== null);
    check("email rule catches the invalid address", $validation->firstError("email") !== null);
    check("name and phone pass and aren't in the error list", !in_array("name", $validation->getErrorValues()) && !in_array("phone", $validation->getErrorValues()));

    $good = new Validation([
        "name" => "Ada Lovelace",
        "password" => "supersecret",
        "phone" => "08012345678",
        "email" => "ada@example.com",
    ]);
    $good->addValues([
        "name" => "string|required",
        "password" => "string|required|min:6",
        "phone" => "phone|required|max:12",
        "email" => "email|required",
    ]);
    check("passes when every field is valid", $good->test() === true);
    check("validated() returns exactly the declared fields", count($good->validated()) === 4);

    // optional field that's simply absent shouldn't fail without "required"
    $optional = Validation::make(["name" => "Ada"], ["name" => "string|required", "bio" => "string|max:500"]);
    check("a non-required, absent field doesn't fail", $optional->passes() === true);

    // custom messages and labels
    $custom = Validation::make(["age" => "12"], ["age" => "integer|min:18"]);
    $custom->setLabel("age", "your age");
    $custom->setMessage("age", "min", "You must be at least 18 years old.");
    $custom->test();
    check("custom message overrides the default", $custom->firstError("age") === "You must be at least 18 years old.");

    // confirmed / same
    $pw = Validation::make(
        ["password" => "hunter2", "password_confirmation" => "hunter3"],
        ["password" => "string|required|confirmed"]
    );
    check("confirmed catches a mismatched confirmation field", $pw->fails() === true);

    // in / not_in
    $role = Validation::make(["role" => "superadmin"], ["role" => "in:admin,editor,viewer"]);
    check("in: rejects a value outside the list", $role->fails() === true);

    try
    {
        Validation::make(["age" => "20"], ["age" => "min"])->test();
        check("a parameterized rule used without its param throws a configuration error", false, "no exception thrown");
    }
    catch(ValidationConfigurationException $e)
    {
        check("a parameterized rule used without its param throws a configuration error", true);
    }

    try
    {
        Validation::make(["age" => "20"], ["age" => "positive_integer"])->test();
        check("an unknown rule name throws a configuration error", false, "no exception thrown");
    }
    catch(ValidationConfigurationException $e)
    {
        check("an unknown rule name throws a configuration error", true);
    }

    // custom rule via extend()
    Validation::extend(
        "even",
        fn($v, $p, $c) => ((int)$v % 2) === 0,
        fn($p, $c) => "{$c['label']} must be an even number"
    );
    $even = Validation::make(["count" => "3"], ["count" => "integer|even"]);
    check("a custom rule registered with extend() runs correctly", $even->fails() === true);

    echo "\nHasMany / BelongsTo relation validation (no DB needed - pure reflection)\n";

    // fixture classes, defined only to be reflected over - never instantiated/connected to a DB
    class HMTestChild extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\BelongsTo(\HMTestParentGood::class)]
        public string $parentid = "";
    }

    class HMTestParentGood extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\HasMany(\HMTestChild::class, 'parentid')]
        public array $children = [];

        #[\Wixnit\Data\HasMany(\HMTestChild::class, 'parentid')]
        public \Wixnit\Data\HasManyCollection $lazyChildren;
    }

    class HMTestParentBadForeignKey extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\HasMany(\HMTestChild::class, 'nonexistentColumn')]
        public array $children = [];
    }

    class HMTestParentBadType extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\HasMany(\HMTestChild::class, 'parentid')]
        public string $children = "";
    }

    class HMTestParentBadRelatedClass extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\HasMany(\stdClass::class, 'parentid')]
        public array $children = [];
    }

    $definitions = \Wixnit\Data\RelationMap::forClass(HMTestParentGood::class);
    check("a valid HasMany/BelongsTo pairing resolves without throwing", count($definitions) === 2);

    $kinds = array_map(fn($d) => $d->kind, $definitions);
    check("array-typed relation resolves to kind 'array'", in_array("array", $kinds, true));
    check("HasManyCollection-typed relation resolves to kind 'collection'", in_array("collection", $kinds, true));

    try
    {
        \Wixnit\Data\RelationMap::forClass(HMTestParentBadForeignKey::class);
        check("HasMany pointing at a foreign key with no matching BelongsTo throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\RelationException $e)
    {
        check("HasMany pointing at a foreign key with no matching BelongsTo throws", true);
    }

    try
    {
        \Wixnit\Data\RelationMap::forClass(HMTestParentBadType::class);
        check("HasMany on a non-array/HasManyCollection property throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\RelationException $e)
    {
        check("HasMany on a non-array/HasManyCollection property throws", true);
    }

    try
    {
        \Wixnit\Data\RelationMap::forClass(HMTestParentBadRelatedClass::class);
        check("HasMany targeting a non-Transactable class throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\RelationException $e)
    {
        check("HasMany targeting a non-Transactable class throws", true);
    }

    echo "\nBelongsToMany / HasManyThrough relation validation (no DB needed)\n";

    class MTMTag extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\BelongsToMany(\MTMPost::class, pivot: 'mtm_post_tag', localKey: 'tagid', relatedKey: 'postid')]
        public array $posts = [];
    }

    class MTMPost extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\BelongsToMany(\MTMTag::class, pivot: 'mtm_post_tag', localKey: 'postid', relatedKey: 'tagid')]
        public \Wixnit\Data\BelongsToManyCollection $tags;
    }

    class MTMPostBadPivotKeys extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\BelongsToMany(\MTMTag::class, pivot: 'mtm_post_tag', localKey: 'sameid', relatedKey: 'sameid')]
        public array $tags = [];
    }

    $mtmDefs = \Wixnit\Data\RelationMap::forClass(MTMPost::class);
    check("a valid BelongsToMany resolves without throwing", count($mtmDefs) === 1 && $mtmDefs[0]->relationType === "belongsToMany");
    check("BelongsToManyCollection-typed relation resolves to kind 'collection'", $mtmDefs[0]->kind === "collection");

    try
    {
        \Wixnit\Data\RelationMap::forClass(MTMPostBadPivotKeys::class);
        check("BelongsToMany with identical localKey/relatedKey throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\RelationException $e)
    {
        check("BelongsToMany with identical localKey/relatedKey throws", true);
    }

    class OTLine extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\BelongsTo(\OTOrder::class)]
        public string $orderid = "";

        #[\Wixnit\Data\BelongsTo(\OTProduct::class)]
        public string $productid = "";

        public int $quantity = 1;
    }

    class OTProduct extends \Wixnit\App\Model
    {
    }

    class OTOrder extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\HasMany(\OTLine::class, 'orderid')]
        public array $lines = [];

        #[\Wixnit\Data\HasManyThrough(\OTProduct::class, through: \OTLine::class, throughLocalKey: 'orderid', throughRelatedKey: 'productid')]
        public array $products = [];
    }

    $throughDefs = \Wixnit\Data\RelationMap::forClass(OTOrder::class);
    $throughDef = null;
    foreach($throughDefs as $d) { if($d->relationType === "hasManyThrough") $throughDef = $d; }

    check("a valid HasManyThrough resolves without throwing", $throughDef !== null);
    check("HasManyThrough correctly identifies the final related class", $throughDef !== null && $throughDef->related === "OTProduct");

    class OTOrderBadThroughType extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\HasManyThrough(\OTProduct::class, through: \OTLine::class, throughLocalKey: 'orderid', throughRelatedKey: 'productid')]
        public \Wixnit\Data\HasManyCollection $products;
    }

    try
    {
        \Wixnit\Data\RelationMap::forClass(OTOrderBadThroughType::class);
        check("HasManyThrough on a non-array property throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\RelationException $e)
    {
        check("HasManyThrough on a non-array property throws", true);
    }

    echo "\nValue-object properties (Money, FlagSet, JsonDocument, HashedPassword)\n";

    $price = \Wixnit\Data\Money::fromMajorUnits(19.99);
    check("Money::fromMajorUnits stores exact minor units", $price->minorUnits() === 1999);
    check("Money::format renders major units with no currency symbol", $price->format() === "19.99");

    $tax = \Wixnit\Data\Money::fromMinorUnits(165);
    $total = $price->add($tax);
    check("Money::add stays in integer minor units", $total->minorUnits() === 2164);
    check("Money round-trips through ISerializable _serialize/_deserialize", (function() use ($total) {
        $copy = new \Wixnit\Data\Money();
        $copy->_deserialize($total->_serialize());
        return $copy->equals($total);
    })());
    check("Money::jsonSerialize returns major units, not minor units", $price->jsonSerialize() === 19.99);

    $flags = new \Wixnit\Data\FlagSet();
    $flags->bindNames(['edit', 'publish', 'delete']);
    $flags->add('edit')->add('delete');
    check("FlagSet::add/has work by name", $flags->has('edit') && $flags->has('delete') && !$flags->has('publish'));
    $flags->remove('edit');
    check("FlagSet::remove clears just that bit", !$flags->has('edit') && $flags->has('delete'));
    check("FlagSet::toArray lists active flag names", $flags->toArray() === ['delete']);
    check("FlagSet::jsonSerialize matches toArray", $flags->jsonSerialize() === ['delete']);

    try
    {
        (new \Wixnit\Data\FlagSet())->bindNames(array_fill(0, 64, 'x'));
        check("FlagSet rejects more than 63 names", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\PropertyException $e)
    {
        check("FlagSet rejects more than 63 names", true);
    }

    $doc = new \Wixnit\Data\JsonDocument();
    $doc->set('dimensions.width', 12)->set('dimensions.height', 8);
    check("JsonDocument dot-path set/get works", $doc->get('dimensions.width') === 12);
    check("JsonDocument::has works for a nested path", $doc->has('dimensions.height') && !$doc->has('dimensions.depth'));
    $doc->forget('dimensions.height');
    check("JsonDocument::forget removes just that path", !$doc->has('dimensions.height') && $doc->has('dimensions.width'));

    $password = new \Wixnit\Data\HashedPassword();
    $password->set('correct horse battery staple');
    check("HashedPassword::verify accepts the right plaintext", $password->verify('correct horse battery staple'));
    check("HashedPassword::verify rejects the wrong plaintext", !$password->verify('wrong password'));
    check("HashedPassword::__toString never exposes the hash", $password->__toString() === '[redacted]');
    check("HashedPassword::jsonSerialize is null - the deliberate exception", $password->jsonSerialize() === null);

    echo "\nValuePropertyMap resolution (no DB needed)\n";

    class VPMOrder extends \Wixnit\App\Model
    {
        public \Wixnit\Data\Counter $views;
        public \Wixnit\Data\LazyText $notes;

        #[\Wixnit\Data\Flags('edit', 'publish')]
        public \Wixnit\Data\FlagSet $permissions;
    }

    $vpmEntries = \Wixnit\Data\ValuePropertyMap::forClass(VPMOrder::class);
    $kinds = array_column($vpmEntries, 'kind', 'property');

    check("ValuePropertyMap finds the Counter property", ($kinds['views'] ?? null) === "counter");
    check("ValuePropertyMap finds the LazyText property", ($kinds['notes'] ?? null) === "lazyText");
    check("ValuePropertyMap finds the FlagSet property with its #[Flags] names", ($kinds['permissions'] ?? null) === "flagSet");
    check("lazyTextNames() returns just the LazyText property", \Wixnit\Data\ValuePropertyMap::lazyTextNames(VPMOrder::class) === ['notes']);

    echo "\nModel property attributes (no DB needed - pure reflection)\n";

    class TrimCast implements \Wixnit\Interfaces\ICaster
    {
        public static function castIn(mixed $raw): mixed { return trim((string)$raw); }
        public static function castOut(mixed $value): mixed { return trim((string)$value); }
    }

    class PAUser extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\Unique]
        public string $email = "";

        #[\Wixnit\Data\Fillable]
        public string $name = "";

        public bool $isAdmin = false; // not fillable - PAUser is in allow-list mode

        #[\Wixnit\Data\Redacted]
        public string $apiKey = "";

        #[\Wixnit\Data\Exclude]
        public string $fullName = "";

        #[\Wixnit\Data\Searchable]
        public string $searchableName = "";

        #[\Wixnit\Data\Immutable]
        public string $createdBy = "";

        #[\Wixnit\Data\Cast(TrimCast::class)]
        public string $slug = "";
    }

    class PAMixedStrategy extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\Fillable]
        public string $a = "";

        #[\Wixnit\Data\Guarded]
        public string $b = "";
    }

    class PANoFillStrategy extends \Wixnit\App\Model
    {
        public string $a = "";
    }

    check("#[Unique] is resolved", \Wixnit\Data\PropertyMap::isUnique(PAUser::class, 'email'));
    check("#[Fillable] property is fillable", \Wixnit\Data\PropertyMap::isFillable(PAUser::class, 'name'));
    check("non-#[Fillable] property is NOT fillable when class is in allow-list mode", !\Wixnit\Data\PropertyMap::isFillable(PAUser::class, 'isAdmin'));
    check("#[Redacted] is resolved", \Wixnit\Data\PropertyMap::isRedacted(PAUser::class, 'apiKey'));
    check("#[Exclude] is resolved", \Wixnit\Data\PropertyMap::isExcluded(PAUser::class, 'fullName'));
    check("#[Immutable] is resolved", \Wixnit\Data\PropertyMap::isImmutable(PAUser::class, 'createdBy'));
    check("#[Searchable] narrows searchableNames()", \Wixnit\Data\PropertyMap::searchableNames(PAUser::class) === ['searchableName']);
    check("#[Cast] resolves to the right Caster class", \Wixnit\Data\PropertyMap::casterFor(PAUser::class, 'slug') === TrimCast::class);
    check("TrimCast actually trims", TrimCast::castIn("  hi  ") === "hi");

    try
    {
        \Wixnit\Data\PropertyMap::isFillable(PAMixedStrategy::class, 'a');
        check("mixing #[Fillable] and #[Guarded] on one class throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\PropertyException $e)
    {
        check("mixing #[Fillable] and #[Guarded] on one class throws", true);
    }

    check("a class with no fill strategy reports none declared", !\Wixnit\Data\PropertyMap::hasFillStrategy(PANoFillStrategy::class));

    try
    {
        (new PANoFillStrategy(new mysqli('localhost', 'root', '', 'hms')))->fill(['a' => 'x']);
        check("fill() on a class with no #[Fillable]/#[Guarded] throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\PropertyException $e)
    {
        check("fill() on a class with no #[Fillable]/#[Guarded] throws", true);
    }

    echo "\nMasked (no DB needed)\n";

    class MaskUser extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\Mask(\Wixnit\Data\EmailMasker::class)]
        public \Wixnit\Data\Masked $email;

        public \Wixnit\Data\Masked $generic; // no #[Mask] - defaults to GenericMasker
    }

    class MaskBadType extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\Mask(\Wixnit\Data\EmailMasker::class)]
        public string $email = ""; // wrong type - should throw
    }

    class NotAMasker
    {
    }

    class MaskBadMasker extends \Wixnit\App\Model
    {
        #[\Wixnit\Data\Mask(NotAMasker::class)]
        public \Wixnit\Data\Masked $email;
    }

    check("EmailMasker masks the local part, keeps the domain", \Wixnit\Data\EmailMasker::mask("ada@example.com") === "a**@example.com");
    check("EmailMasker falls back to GenericMasker for a non-email value", \Wixnit\Data\EmailMasker::mask("notanemail") === "n********l");
    check("PhoneMasker keeps the last 4 characters", \Wixnit\Data\PhoneMasker::mask("+2348012345678") === str_repeat("*", 10) . "5678");
    check("GenericMasker keeps first/last, masks the middle", \Wixnit\Data\GenericMasker::mask("secretvalue") === "s*********e");
    check("GenericMasker fully masks very short values", \Wixnit\Data\GenericMasker::mask("ab") === "**");

    $masked = new \Wixnit\Data\Masked();
    $masked->bindMasker(\Wixnit\Data\EmailMasker::class);
    $masked->set("ada@example.com");
    check("Masked::value() returns the real value", $masked->value() === "ada@example.com");
    check("Masked::__toString() returns the masked value", (string)$masked === "a**@example.com");
    check("Masked::jsonSerialize() returns the masked value", $masked->jsonSerialize() === "a**@example.com");

    try
    {
        \Wixnit\Data\ValuePropertyMap::forClass(MaskBadType::class);
        check("#[Mask] on a non-Masked property throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\PropertyException $e)
    {
        check("#[Mask] on a non-Masked property throws", true);
    }

    try
    {
        \Wixnit\Data\ValuePropertyMap::forClass(MaskBadMasker::class);
        check("#[Mask] pointing at a non-Masker class throws", false, "no exception thrown");
    }
    catch(\Wixnit\Exception\PropertyException $e)
    {
        check("#[Mask] pointing at a non-Masker class throws", true);
    }

    $mapped = \Wixnit\Data\ValuePropertyMap::forClass(MaskUser::class);
    $maskerFor = [];
    foreach($mapped as $entry) { $maskerFor[$entry['property']] = $entry['masker'] ?? null; }
    check("#[Mask] resolves the configured masker class", ($maskerFor['email'] ?? null) === \Wixnit\Data\EmailMasker::class);
    check("a Masked property with no #[Mask] defaults to GenericMasker", ($maskerFor['generic'] ?? null) === \Wixnit\Data\GenericMasker::class);

    if(extension_loaded('sodium'))
    {
        echo "\nEncrypted (sodium extension available)\n";

        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        $enc = new \Wixnit\Data\Encrypted();
        $enc->set("sk_live_abc123", $key);
        check("Encrypted::decrypt() with the same explicit key recovers the plaintext", $enc->decrypt($key) === "sk_live_abc123");
        check("Encrypted::__toString() never reveals the real value", (string)$enc === "[encrypted]");
        check("Encrypted::jsonSerialize() is null - redacted", $enc->jsonSerialize() === null);

        try
        {
            $enc->decrypt();
            check("decrypt() with no key and no EncryptionConfig throws", false, "no exception thrown");
        }
        catch(\Wixnit\Exception\PropertyException $e)
        {
            check("decrypt() with no key and no EncryptionConfig throws", true);
        }

        $wrongKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        try
        {
            $enc->decrypt($wrongKey);
            check("decrypt() with the wrong key throws", false, "no exception thrown");
        }
        catch(\Wixnit\Exception\PropertyException $e)
        {
            check("decrypt() with the wrong key throws", true);
        }

        \Wixnit\Data\EncryptionConfig::Init($key);
        $enc2 = new \Wixnit\Data\Encrypted();
        $enc2->set("uses the global key");
        check("Encrypted::set() uses EncryptionConfig's key when none is passed", $enc2->decrypt() === "uses the global key");

        \Wixnit\Data\EncryptionConfig::reset();
    }
    else
    {
        echo "\nEncrypted - skipped, sodium extension not available in this environment\n";
    }

    echo "\n" . str_repeat("-", 40) . "\n";
    echo "$passed passed, $failures failed\n";

    exit($failures > 0 ? 1 : 0);
