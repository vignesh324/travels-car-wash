<?php

namespace App\Services;

use Generator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSqlExporter
{
    public function lines(): Generator
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        yield "-- Travels & Car Wash database export\n";
        yield '-- Generated at '.now()->toIso8601String()."\n\n";

        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            yield from $this->mysqlLines($connection, $pdo);

            return;
        }

        if ($connection->getDriverName() === 'sqlite') {
            yield from $this->sqliteLines($connection, $pdo);

            return;
        }

        throw new RuntimeException('SQL export is supported only for MySQL and SQLite databases.');
    }

    private function mysqlLines(Connection $connection, \PDO $pdo): Generator
    {
        yield "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        foreach ($tables as $tableRow) {
            $table = array_values((array) $tableRow)[0];
            $quotedTable = $this->quoteMysqlIdentifier($table);
            $createTable = array_values((array) $connection->selectOne("SHOW CREATE TABLE {$quotedTable}"))[1] ?? null;

            if (! is_string($createTable)) {
                throw new RuntimeException("Unable to read the definition for table {$table}.");
            }

            yield 'DROP TABLE IF EXISTS '.$quotedTable.";\n";
            yield $createTable.";\n\n";

            $columns = array_map(
                static fn (object $column): string => $column->Field,
                $connection->select("SHOW COLUMNS FROM {$quotedTable}")
            );

            foreach ($connection->cursor("SELECT * FROM {$quotedTable}") as $row) {
                $values = array_map(
                    fn (string $column): string => $this->quoteValue($row->{$column}, $pdo),
                    $columns
                );
                $quotedColumns = array_map([$this, 'quoteMysqlIdentifier'], $columns);

                yield 'INSERT INTO '.$quotedTable.' ('.implode(', ', $quotedColumns).') VALUES ('
                    .implode(', ', $values).");\n";
            }

            yield "\n";
        }

        yield "SET FOREIGN_KEY_CHECKS=1;\n";
    }

    private function sqliteLines(Connection $connection, \PDO $pdo): Generator
    {
        yield "PRAGMA foreign_keys=OFF;\n\n";

        $objects = $connection->select(
            "SELECT type, name, sql FROM sqlite_master
             WHERE type IN ('table', 'view', 'index', 'trigger')
               AND sql IS NOT NULL
               AND name NOT LIKE 'sqlite_%'
             ORDER BY CASE type
                 WHEN 'table' THEN 0
                 WHEN 'view' THEN 1
                 WHEN 'index' THEN 2
                 ELSE 3
             END"
        );

        foreach ($objects as $object) {
            $quotedName = $this->quoteSqliteIdentifier($object->name);

            if ($object->type === 'table') {
                yield "DROP TABLE IF EXISTS {$quotedName};\n";
            } elseif ($object->type === 'view') {
                yield "DROP VIEW IF EXISTS {$quotedName};\n";
            }

            yield $object->sql.";\n";

            if ($object->type !== 'table') {
                yield "\n";

                continue;
            }

            $columns = array_map(
                static fn (object $column): string => $column->name,
                $connection->select('PRAGMA table_info('.$quotedName.')')
            );
            $quotedColumns = array_map([$this, 'quoteSqliteIdentifier'], $columns);

            foreach ($connection->cursor("SELECT * FROM {$quotedName}") as $row) {
                $values = array_map(
                    fn (string $column): string => $this->quoteValue($row->{$column}, $pdo),
                    $columns
                );

                if ($values !== []) {
                    yield 'INSERT INTO '.$quotedName.' ('.implode(', ', $quotedColumns).') VALUES ('
                        .implode(', ', $values).");\n";
                }
            }

            yield "\n";
        }

        yield "PRAGMA foreign_keys=ON;\n";
    }

    private function quoteValue(mixed $value, \PDO $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $quoted = $pdo->quote((string) $value);

        if ($quoted === false) {
            throw new RuntimeException('Unable to encode a database value for the SQL export.');
        }

        return $quoted;
    }

    private function quoteMysqlIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteSqliteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
