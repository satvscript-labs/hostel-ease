<?php

namespace App\Services\Backup;

/**
 * Reads a dump back and checks it is whole — without a database and without parsing SQL.
 *
 * The dumper's format makes that possible: one row per line, no raw newline inside a
 * value, and a footer that records how many rows each table was written with. So a
 * backup is trustworthy only if (1) it opens, (2) it carries the header, (3) it ends
 * with the completion line, and (4) the rows actually in the file match the footer,
 * table by table. A truncated download, a full disk or a half-written file fails one of
 * those; a backup you have never read back is a hope, not a backup.
 */
class SqlVerifier
{
    /**
     * @return array{ok: bool, message: string, tables: int, rows: int}
     */
    public function verify(string $path): array
    {
        $fail = fn (string $why) => ['ok' => false, 'message' => $why, 'tables' => 0, 'rows' => 0];

        if (! is_file($path) || filesize($path) === 0) {
            return $fail('The file is missing or empty.');
        }

        $seenHeader = false;
        $complete = false;
        $table = null;
        $inInsert = false;
        $counted = [];     // table => rows found in the file
        $declared = [];    // table => rows the footer says were written

        try {
            foreach (self::lines($path) as $line) {
                if (! $seenHeader) {
                    if ($line !== SqlDumper::HEADER) {
                        return $fail('This is not a HostelEase backup (missing header).');
                    }
                    $seenHeader = true;

                    continue;
                }

                if ($line === SqlDumper::MARKER) {
                    $inInsert = false;

                    continue;
                }

                if (str_starts_with($line, '-- Table: ')) {
                    $table = substr($line, 10);
                    $counted[$table] ??= 0;

                    continue;
                }

                if (preg_match('/^-- table: (.+) rows: (\d+)$/', $line, $m)) {
                    $declared[$m[1]] = (int) $m[2];

                    continue;
                }

                if ($line === SqlDumper::COMPLETE) {
                    $complete = true;

                    continue;
                }

                if (str_starts_with($line, 'INSERT INTO ')) {
                    $inInsert = true;

                    continue;
                }

                if ($inInsert && $table !== null && str_starts_with($line, '(')) {
                    $counted[$table]++;
                }
            }
        } catch (\Throwable $e) {
            return $fail('The file could not be read to the end ('.$e->getMessage().').');
        }

        if (! $seenHeader) {
            return $fail('The file has no content.');
        }
        if (! $complete) {
            return $fail('The file stops early: the completion line is missing, so the backup did not finish.');
        }

        foreach ($declared as $name => $rows) {
            if (($counted[$name] ?? 0) !== $rows) {
                return $fail("Table {$name}: the footer says {$rows} rows but the file holds ".($counted[$name] ?? 0).'.');
            }
        }

        return ['ok' => true, 'message' => 'Complete.', 'tables' => count($declared), 'rows' => array_sum($declared)];
    }

    /**
     * Lines of a plain or gzip file (gzopen reads both), without their line ending.
     *
     * @return \Generator<int, string>
     */
    public static function lines(string $path): \Generator
    {
        $handle = @gzopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('cannot open the file');
        }

        try {
            while (($line = gzgets($handle)) !== false) {
                yield rtrim($line, "\n");
            }

            // gzgets also returns false on a corrupt stream, not just at the end.
            if (! gzeof($handle)) {
                throw new \RuntimeException('the compressed data is damaged');
            }
        } finally {
            gzclose($handle);
        }
    }
}
