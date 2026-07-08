<?php

    namespace Wixnit\Data;

    /**
     * Filters a parent Transactable by the existence of a matching row in a real
     * one-to-many child table (a table that owns a foreign key pointing back to the
     * parent) - the case auto-joined typed properties don't cover, since those only
     * handle a single related object per parent.
     *
     * Renders as a correlated EXISTS subquery rather than a JOIN, so parent rows are
     * never duplicated by matching children.
     *
     * Usage:
     *   User::Get(new WhereHas('wallettransaction', 'userid', new Filter(['amount' => new GreaterThan(100)])))
     *
     * "childTable" is the table storing the child rows. "foreignKey" is the column on
     * that table which stores the parent's business id (the parent's `{table}id` column).
     * The optional $condition further restricts which child rows count.
     */
    class WhereHas
    {
        public string $childTable;
        public string $foreignKey;
        public Filter|FilterBuilder|null $condition;

        function __construct(string $childTable, string $foreignKey, Filter|FilterBuilder $condition = null)
        {
            if(trim($childTable) === "")
            {
                throw \Wixnit\Exception\RelationException::EmptyRelationTarget("WhereHas()", "childTable");
            }
            if(trim($foreignKey) === "")
            {
                throw \Wixnit\Exception\RelationException::EmptyRelationTarget("WhereHas()", "foreignKey");
            }

            $this->childTable = Identifier::assertSafe($childTable);
            $this->foreignKey = Identifier::assertSafe($foreignKey);
            $this->condition = $condition;
        }
    }
