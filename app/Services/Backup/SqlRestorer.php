<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Loads a dump made by SqlDumper back into the database — in PHP, because on shared
 * hosting there may be no `mysql` client to run and proc_open is off.
 *
 * It refuses a file that does not verify, and it only ever executes what the dumper
 * wrote: statements are split on the marker line, never parsed. Every table in the
 * file is DROPPED and re-created, so a restore REPLACES the database — and re-running
 * a restore that stopped half-way is safe for the same reason.
 *
 * The same file also loads with `mysql < file` or phpMyAdmin; this is the way when
 * neither is to hand.
 */
class SqlRestorer
{
    private Connection $db;

    public function __construct(?string $connection = null, private ?SqlVerifier $verifier = null)
    {
        $this->db = DB::connection($connection);
        $this->verifier ??= new SqlVerifier;
    }

    /**
     * @param  callable(string): void|null  $progress  told the table as each one starts
     * @return array{statements: int, tables: int, rows: int}
     */
    public function restore(string $path, ?callable $progress = null): array
    {
        $check = $this->verifier->verify($path);
        if (! $check['ok']) {
            throw new \RuntimeException('Refusing to restore: '.$check['message']);
        }

        $driver = $this->db->getDriverName();
        $zone = $driver === 'sqlite' ? null : (string) ($this->db->selectOne('select @@session.time_zone as z')->z ?? 'SYSTEM');

        $buffer = [];
        $statements = 0;

        try {
            foreach (SqlVerifier::lines($path) as $line) {
                if ($line !== SqlDumper::MARKER) {
                    $buffer[] = $line;

                    continue;
                }

                $sql = $this->statementFrom($buffer, $progress);
                $buffer = [];

                if ($sql !== null) {
                    $this->db->unprepared($sql);
                    $statements++;
                }
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("Restore stopped at statement {$statements}: ".$e->getMessage().' — run it again to start over; every table is replaced.', 0, $e);
        } finally {
            // Whatever happened, leave the session the way we found it.
            $this->db->unprepared($driver === 'sqlite' ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
            if ($zone !== null) {
                $this->db->statement('SET time_zone = ?', [$zone]);
            }
        }

        return ['statements' => $statements, 'tables' => $check['tables'], 'rows' => $check['rows']];
    }

    /** The SQL in a block of lines, with comment lines dropped; null if nothing is left. */
    private function statementFrom(array $lines, ?callable $progress): ?string
    {
        $sql = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '-- Table: ')) {
                $progress && $progress(substr($line, 10));
            }
            if (! str_starts_with($line, '--')) {
                $sql[] = $line;
            }
        }

        $sql = trim(implode("\n", $sql));

        return $sql === '' ? null : rtrim($sql, ';');
    }
}
