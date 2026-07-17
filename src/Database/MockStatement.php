<?php

declare(strict_types=1);

namespace Recipes\Database;

use PDOStatement;

/**
 * A stand-in PDOStatement handed back by Database::query() when a mock result
 * has been registered, so unit tests can exercise query paths without a
 * database connection.
 *
 * This exists as a named class rather than an anonymous one so its rows are a
 * declared property: PHP 8.2 deprecates dynamic properties, and static
 * analysis cannot reason about them.
 */
class MockStatement extends PDOStatement
{
    /** @var array<int, array<int, mixed>> */
    private array $rows;

    private int $cursor = 0;

    /**
     * @param array<int, array<int, mixed>> $rows Rows to hand out, in order.
     */
    public function __construct(array $rows = [])
    {
        $this->rows = $rows;
    }

    /**
     * Return the next row, or false once the rows are exhausted — mirroring
     * what PDOStatement::fetch() does at the end of a result set.
     *
     * @return array<int, mixed>|false
     */
    public function nextRow(): array|false
    {
        $row = $this->rows[$this->cursor] ?? false;
        if ($row !== false) {
            $this->cursor++;
        }
        return $row;
    }
}
