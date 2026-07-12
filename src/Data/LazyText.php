<?php

    namespace Wixnit\Data;

    use Wixnit\Enum\DBFieldType;
    use Wixnit\Interfaces\ISerializable;
    use Wixnit\Exception\DatabaseException;

    /**
     * A large TEXT/LONGTEXT column that isn't fetched until actually touched - the same
     * idea HasManyCollection already proved out for relations, applied to a single
     * column instead.
     *
     * Usage:
     *   class Article extends Model { public string $title = ""; public LazyText $body; }
     *
     *   $articles = Article::Get();     // title fetched for every row - body isn't
     *   echo $articles[0]->body;         // loads *now*, one query, only for this one row
     *
     * Excluded from the default SELECT field list the same way #[HasMany] relation
     * properties already are - see DB::Connect(). Can be forced eager per query with
     * With('body'), or per already-loaded instance with $article->hydrate('body').
     *
     * IMPORTANT write-side behavior: toDBObject() only writes this column on an UPDATE
     * if set() was actually called this request (isDirty() true) - merely reading it
     * does not mark it dirty. Without this, saving a row for an unrelated reason would
     * silently blank out a large text column that was never loaded. New rows (INSERT)
     * always write it, since there's nothing to clobber yet.
     *
     * Implements ISerializable for the column type + the "already loaded" read path
     * (same interface Date/Time/Duration/Color already use), but is also bound to its
     * parent object after construction - like HasManyCollection - since it needs the
     * parent's id/table/connection to run its own deferred SELECT.
     */
    class LazyText implements ISerializable, \JsonSerializable
    {
        private ?string $value = null;
        private bool $loaded = false;
        private bool $dirty = false;

        private ?Transactable $parent = null;
        private ?string $field = null;

        function __construct()
        {
        }

        /**
         * Called once by the framework, right after construction. Not intended to be
         * called from application code.
         * @param Transactable $parent
         * @param string $field
         * @return void
         */
        public function bind(Transactable $parent, string $field): void
        {
            $this->parent = $parent;
            $this->field = $field;
        }

        /**
         * @return string loads if needed, returns the text
         */
        public function get(): string
        {
            $this->load();
            return $this->value ?? "";
        }

        /**
         * @param string $value
         * @return void
         */
        public function set(string $value): void
        {
            $this->value = $value;
            $this->loaded = true;
            $this->dirty = true;
        }

        public function isLoaded(): bool
        {
            return $this->loaded;
        }

        /**
         * @return bool whether set() has been called this request - see the class-level
         *              note on why toDBObject() checks this before writing
         */
        public function isDirty(): bool
        {
            return $this->dirty;
        }

        /**
         * Forces the deferred SELECT to run right now, if it hasn't already. Called
         * automatically by get()/__toString() - exposed directly for With()/hydrate()
         * to call after priming with an already-fetched value (see primeWith()).
         * @return void
         */
        public function load(): void
        {
            if($this->loaded || ($this->parent === null) || ($this->parent->id === ""))
            {
                return;
            }

            $conn = $this->parent->getConnection();
            $sql = "SELECT ".$this->field." FROM ".$this->parent->getTableName()." WHERE ".$this->parent->getIdColumn()."=?";

            $stmt = $conn->prepare($sql);

            if($stmt === false)
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [], $conn->error, $conn->errno);
            }

            $id = $this->parent->id;
            $stmt->bind_param("s", $id);

            if(!$stmt->execute())
            {
                throw DatabaseException::QueryFailed(__METHOD__, $sql, [$id], $stmt->error, $stmt->errno);
            }

            $row = $stmt->get_result()->fetch_assoc();

            $this->value = $row[$this->field] ?? "";
            $this->loaded = true;
            //loading is not the same as changing - deliberately not marking dirty here
        }

        /**
         * Marks this value as already loaded, without running a query - used when a
         * batched With('field') fetch (or hydrate()) already retrieved the value as
         * part of a larger query.
         * @param string $value
         * @return void
         */
        public function primeWith(string $value): void
        {
            $this->value = $value;
            $this->loaded = true;
        }

        public function __toString(): string
        {
            return $this->get();
        }

        //#region ISerializable

        public function _dbType(): DBFieldType
        {
            return DBFieldType::LONG_TEXT;
        }

        /**
         * Only meaningful for a brand-new row (INSERT always includes it) or a dirty
         * value on an existing row - toDBObject() decides whether to call this at all
         * for an UPDATE, skipping it entirely when neither is true.
         */
        public function _serialize()
        {
            return $this->value ?? "";
        }

        /**
         * Only reached if this column WAS included in the SELECT (i.e. via With()) -
         * the default, excluded case never calls this at all.
         */
        public function _deserialize($data): void
        {
            $this->value = (string)$data;
            $this->loaded = true;
        }

        //#endregion

        /**
         * @return string|null the loaded text, or null if not loaded - deliberately does
         *                       NOT trigger a load just to serialize to JSON, so an API
         *                       response doesn't pay for a body it didn't ask for. Use
         *                       With('field') on the query if the JSON response should
         *                       include it.
         */
        public function jsonSerialize(): mixed
        {
            return $this->loaded ? $this->value : null;
        }
    }
