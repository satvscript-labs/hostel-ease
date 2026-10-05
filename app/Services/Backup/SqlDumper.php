<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Streams a whole database out as plain SQL, in PHP, over the app's own connection.
 *
 * Why this exists: the old backup ran `mysqldump` through Symfony Process, and
 * Hostinger's shared hosting disables proc_open — so every backup there failed. This
 * needs nothing but PDO.
 *
 * What it guarantees, and why each line is here:
 *
 *  - ONE SNAPSHOT. Everything is read inside a single transaction, so a branch being
 *    renewed while the dump runs cannot leave an order without its lines (the same
 *    reason mysqldump has --single-transaction).
 *  - GENERATED COLUMNS ARE LEFT OUT. `invoices.balance` is `amount - paid_amount`;
 *    MySQL refuses an INSERT that names it, so a naive dump cannot be restored.
 *  - NO RAW NEWLINES INSIDE A VALUE, and one row per line. That is what lets the
 *    verifier count rows without parsing SQL, and the restorer split statements
 *    without a SQL parser. (MySQL strings are backslash-escaped; SQLite ones that
 *    hold control characters go out as CAST(X'..' AS TEXT).)
 *  - TIMESTAMPS ARE READ IN UTC, and the file says so. A TIMESTAMP column shows
 *    whatever the session zone is; restoring under a different zone would silently
 *    shift every created_at. mysqldump does the same (--tz-utc).
 *  - Bytes that are not valid UTF-8 go out as hex, never as a mangled string.
 *
 * The output is ordinary SQL, so it also loads with `mysql < file` and phpMyAdmin.
 */
class SqlDumper
{
    public const MARKER = '-- @@';            // statement separator the restorer splits on

    public const HEADER = '-- HostelEase backup v1';

    public const COMPLETE = '-- HostelEase backup complete';

    private const MAX_INSERT_BYTES = 512 * 1024;

    private Connection $db;

    private string $driver;

    /** @var array<string, int> rows written per table */
    private array $rowCounts = [];

    public function __construct(?string $connection = null)
    {
        $this->db = DB::connection($connection);
        $this->driver = $this->db->getDriverName();

        if (! in_array($this->driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new \RuntimeException("Backups do not support the [{$this->driver}] database driver.");
        }
    }

    public function driverName(): string
    {
        return $this->driver;
    }

    /**
     * Write the dump through $write (called with each chunk of text).
     *
     * @param  list<string>  $structureOnly  tables whose rows are not wanted
     * @return array<string, int> rows per table
     */
    public function dump(callable $write, array $structureOnly = [], int $rowsPerInsert = 200): array
    {
        $this->rowCounts = [];
        $previousZone = $this->useUtc();

        try {
            // A read transaction = a consistent snapshot of every table.
            $this->db->transaction(function () use ($write, $structureOnly, $rowsPerInsert) {
                $write($this->header());

                foreach ($this->tables() as $table) {
                    $this->dumpTable($table, $write, ! in_array($table, $structureOnly, true), $rowsPerInsert);
                }
            });

            $footer = '';
            foreach ($this->restoreSession() as $sql) {
                $footer .= $this->statement($sql);
            }
            foreach ($this->rowCounts as $table => $rows) {
                $footer .= "-- table: {$table} rows: {$rows}\n";
            }
            $write($footer.self::COMPLETE."\n");
        } finally {
            $this->restoreZone($previousZone);
        }

        return $this->rowCounts;
    }

    /** @return list<string> */
    private function tables(): array
    {
        $names = array_map(
            fn ($t) => (string) (is_array($t) ? $t['name'] : $t->name),
            Schema::connection($this->db->getName())->getTables(),
        );
        sort($names);

        return $names;
    }

    private function dumpTable(string $table, callable $write, bool $withRows, int $rowsPerInsert): void
    {
        $schema = Schema::connection($this->db->getName());
        $columns = collect($schema->getColumns($table))
            ->reject(fn ($c) => ! empty($c['generation']))    // computed by the database
            ->pluck('name')
            ->all();

        $q = $this->ident($table);
        $out = "-- Table: {$table}\n";
        $out .= $this->statement("DROP TABLE IF EXISTS {$q}");
        foreach ($this->createStatements($table) as $sql) {
            $out .= $this->statement($sql);
        }
        $write($out);

        $this->rowCounts[$table] = 0;
        if (! $withRows || $columns === []) {
            return;
        }

        $list = implode(',', array_map(fn ($c) => $this->ident($c), $columns));
        $batch = [];
        $bytes = 0;

        foreach ($this->rows($table, $columns) as $row) {
            $line = '('.implode(',', array_map(fn ($v) => $this->literal($v), array_values((array) $row))).')';
            $batch[] = $line;
            $bytes += strlen($line);
            $this->rowCounts[$table]++;

            // Flush on row count OR size, so a table of big text rows never builds a
            // statement larger than the server's max_allowed_packet.
            if (count($batch) >= $rowsPerInsert || $bytes >= self::MAX_INSERT_BYTES) {
                $write($this->insert($q, $list, $batch));
                $batch = [];
                $bytes = 0;
            }
        }

        if ($batch !== []) {
            $write($this->insert($q, $list, $batch));
        }
    }

    private function insert(string $table, string $columns, array $rows): string
    {
        return $this->statement("INSERT INTO {$table} ({$columns}) VALUES\n".implode(",\n", $rows));
    }

    /** @return list<string> the CREATE TABLE (+ CREATE INDEX on SQLite) for a table */
    private function createStatements(string $table): array
    {
        if ($this->driver === 'sqlite') {
            return collect($this->db->select(
                "select sql from sqlite_master where tbl_name = ? and sql is not null and type in ('table', 'index') order by type = 'table' desc, name",
                [$table],
            ))->pluck('sql')->all();
        }

        $row = (array) $this->db->selectOne('SHOW CREATE TABLE '.$this->ident($table));

        return [(string) ($row['Create Table'] ?? array_values($row)[1])];
    }

    /**
     * Rows in a stable order, a page at a time — never the whole table in memory.
     * A single-column primary key pages by "id > last" (cheap and immune to rows
     * arriving mid-dump); anything else pages by offset over every column.
     *
     * @param  list<string>  $columns
     * @return \Generator<int, object>
     */
    private function rows(string $table, array $columns): \Generator
    {
        $page = 1000;
        $key = $this->singleKey($table, $columns);

        if ($key !== null) {
            $last = null;
            while (true) {
                $rows = $this->db->table($table)->select($columns)
                    ->when($last !== null, fn ($q) => $q->where($key, '>', $last))
                    ->orderBy($key)->limit($page)->get();

                foreach ($rows as $row) {
                    yield $row;
                }

                if ($rows->count() < $page) {
                    return;
                }
                $last = $rows->last()->{$key};
            }
        }

        for ($offset = 0; ; $offset += $page) {
            $query = $this->db->table($table)->select($columns);
            foreach ($columns as $c) {
                $query->orderBy($c);
            }
            $rows = $query->offset($offset)->limit($page)->get();

            foreach ($rows as $row) {
                yield $row;
            }

            if ($rows->count() < $page) {
                return;
            }
        }
    }

    /** The table's one-column primary key, if it has exactly that. */
    private function singleKey(string $table, array $columns): ?string
    {
        foreach (Schema::connection($this->db->getName())->getIndexes($table) as $index) {
            if (! empty($index['primary']) && count($index['columns']) === 1 && in_array($index['columns'][0], $columns, true)) {
                return $index['columns'][0];
            }
        }

        return null;
    }

    // ── literals ─────────────────────────────────────────────────────────

    public function literal(mixed $v): string
    {
        return match (true) {
            $v === null => 'NULL',
            is_bool($v) => $v ? '1' : '0',
            is_int($v) => (string) $v,
            is_float($v) => is_finite($v) ? var_export($v, true) : 'NULL',
            default => $this->string((string) $v),
        };
    }

    private function string(string $s): string
    {
        $binary = ! mb_check_encoding($s, 'UTF-8');

        if ($this->driver === 'sqlite') {
            if ($binary) {
                return "X'".bin2hex($s)."'";
            }
            if (preg_match('/[\x00-\x1f]/', $s)) {
                return "CAST(X'".bin2hex($s)."' AS TEXT)";
            }

            return "'".str_replace("'", "''", $s)."'";
        }

        if ($binary) {
            return '0x'.bin2hex($s);
        }

        // What mysql_real_escape_string does, so the result never contains a raw
        // newline and works the same in every client that loads it.
        return "'".strtr($s, [
            '\\' => '\\\\', "'" => "\\'", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z',
        ])."'";
    }

    private function ident(string $name): string
    {
        return $this->driver === 'sqlite'
            ? '"'.str_replace('"', '""', $name).'"'
            : '`'.str_replace('`', '``', $name).'`';
    }

    // ── file framing ─────────────────────────────────────────────────────

    private function statement(string $sql): string
    {
        return $sql.";\n".self::MARKER."\n";
    }

    private function header(): string
    {
        $head = self::HEADER."\n"
            .'-- driver: '.$this->driver."\n"
            .'-- database: '.basename((string) $this->db->getDatabaseName())."\n"
            .'-- created: '.now()->toIso8601String()."\n";

        $setup = $this->driver === 'sqlite'
            ? ['PRAGMA foreign_keys = OFF']
            : ['SET NAMES utf8mb4', "SET time_zone = '+00:00'", 'SET FOREIGN_KEY_CHECKS = 0', 'SET UNIQUE_CHECKS = 0', "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'"];

        // One statement per SET: restoring never depends on multi-statement execution.
        return $head.implode('', array_map(fn ($sql) => $this->statement($sql), $setup));
    }

    /** @return list<string> */
    private function restoreSession(): array
    {
        return $this->driver === 'sqlite'
            ? ['PRAGMA foreign_keys = ON']
            : ['SET FOREIGN_KEY_CHECKS = 1', 'SET UNIQUE_CHECKS = 1'];
    }

    /** MySQL: read in UTC for the duration of the dump, then put the session back. */
    private function useUtc(): ?string
    {
        if ($this->driver === 'sqlite') {
            return null;
        }

        $previous = (string) ($this->db->selectOne('select @@session.time_zone as z')->z ?? 'SYSTEM');
        $this->db->statement("SET time_zone = '+00:00'");

        return $previous;
    }

    private function restoreZone(?string $previous): void
    {
        if ($previous !== null) {
            $this->db->statement('SET time_zone = ?', [$previous]);
        }
    }
}
