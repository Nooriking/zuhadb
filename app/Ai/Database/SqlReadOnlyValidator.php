<?php

namespace App\Ai\Database;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PhpMyAdmin\SqlParser\Statements\WithStatement;
use PhpMyAdmin\SqlParser\Token;
use Throwable;

/**
 * Strict read-only SQL validation using phpMyAdmin SQL parser + keyword denylist.
 * Fails closed on unknown or unsafe constructs.
 */
final class SqlReadOnlyValidator
{
    /** @var list<string> */
    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE',
        'RENAME', 'GRANT', 'REVOKE', 'EXECUTE', 'CALL', 'LOAD', 'HANDLER', 'LOCK', 'UNLOCK',
        'INTO', 'OUTFILE', 'DUMPFILE', 'DO', 'SET', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'USE',
        'ANALYZE', 'OPTIMIZE', 'REPAIR', 'FLUSH', 'RESET', 'PURGE', 'CHANGE', 'START',
        'COMMIT', 'ROLLBACK', 'SAVEPOINT', 'RELEASE', 'PREPARE', 'DEALLOCATE', 'XA',
    ];

    /** @var list<string> */
    private const DANGEROUS_FUNCTIONS = [
        'LOAD_FILE', 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK', 'IS_FREE_LOCK',
        'IS_USED_LOCK', 'UPDATEXML', 'EXTRACTVALUE', 'GTID_SUBSET', 'GTID_SUBTRACT',
    ];

    /**
     * @return array{
     *     ok: bool,
     *     sql: string,
     *     tables: list<string>,
     *     cte_names: list<string>,
     *     join_count: int,
     *     has_star: bool,
     *     select: SelectStatement|null,
     *     errors: list<string>
     * }
     */
    public function validate(string $sql): array
    {
        $errors = [];
        $sql = trim(str_replace("\0", '', $sql));
        $maxLen = max(100, (int) config('ai.database.max_sql_length', 5000));

        if ($sql === '') {
            return $this->fail(['SQL query is required.'], $sql);
        }

        if (mb_strlen($sql) > $maxLen) {
            return $this->fail(["SQL exceeds maximum length of {$maxLen} characters."], $sql);
        }

        if ($this->containsComments($sql)) {
            return $this->fail(['SQL comments are not allowed.'], $sql);
        }

        if ($this->containsMultipleStatements($sql)) {
            return $this->fail(['Multiple SQL statements are not allowed.'], $sql);
        }

        $upper = strtoupper($sql);
        foreach (['INTO OUTFILE', 'INTO DUMPFILE', 'FOR UPDATE', 'LOCK IN SHARE MODE', 'LOAD DATA'] as $needle) {
            if (str_contains($upper, $needle)) {
                return $this->fail(["SQL contains forbidden clause [{$needle}]."], $sql);
            }
        }

        try {
            $keywordCheck = $this->assertSafeTokens($sql);
            if ($keywordCheck !== null) {
                return $this->fail([$keywordCheck], $sql);
            }
        } catch (Throwable $e) {
            return $this->fail(['Unable to tokenize SQL safely.'], $sql);
        }

        try {
            $parser = new Parser($sql);
        } catch (Throwable) {
            return $this->fail(['SQL could not be parsed.'], $sql);
        }

        if ($parser->errors !== []) {
            return $this->fail(['SQL failed structural validation.'], $sql);
        }

        if (count($parser->statements) !== 1) {
            return $this->fail(['Exactly one SQL statement is required.'], $sql);
        }

        $statement = $parser->statements[0];
        $select = null;
        $cteNames = [];
        $tables = [];
        $joinCount = 0;
        $hasStar = false;

        if ($statement instanceof WithStatement) {
            $cteResult = $this->validateWithStatement($statement);
            if (! $cteResult['ok']) {
                return $this->fail($cteResult['errors'], $sql);
            }
            $select = $cteResult['select'];
            $cteNames = $cteResult['cte_names'];
            $tables = $cteResult['tables'];
            $joinCount = $cteResult['join_count'];
            $hasStar = $cteResult['has_star'];
        } elseif ($statement instanceof SelectStatement) {
            $selectResult = $this->inspectSelect($statement);
            if (! $selectResult['ok']) {
                return $this->fail($selectResult['errors'], $sql);
            }
            $select = $statement;
            $tables = $selectResult['tables'];
            $joinCount = $selectResult['join_count'];
            $hasStar = $selectResult['has_star'];
        } else {
            return $this->fail(['Only SELECT (and read-only WITH … SELECT) statements are allowed.'], $sql);
        }

        if ($select?->into !== null) {
            return $this->fail(['SELECT INTO is not allowed.'], $sql);
        }

        if ($select?->procedure !== null) {
            return $this->fail(['SELECT PROCEDURE is not allowed.'], $sql);
        }

        $maxJoins = max(0, (int) config('ai.database.max_joins', 8));
        if ($joinCount > $maxJoins) {
            return $this->fail(["Query exceeds maximum JOIN count of {$maxJoins}."], $sql);
        }

        // Remove CTE names from physical table list.
        $cteLookup = array_fill_keys(array_map('strtolower', $cteNames), true);
        $physicalTables = [];
        foreach ($tables as $table) {
            $lower = strtolower($table);
            if (! isset($cteLookup[$lower])) {
                $physicalTables[] = $lower;
            }
        }
        $physicalTables = array_values(array_unique($physicalTables));

        $systemSchemas = array_map('strtolower', (array) config('ai.database.deny_system_schemas', []));
        foreach ($physicalTables as $table) {
            if (str_contains($table, '.')) {
                $schema = strtolower(explode('.', $table, 2)[0]);
                if (in_array($schema, $systemSchemas, true)) {
                    return $this->fail(['Queries against system schemas are not allowed.'], $sql);
                }
            }
        }

        return [
            'ok' => true,
            'sql' => $sql,
            'tables' => $physicalTables,
            'cte_names' => $cteNames,
            'join_count' => $joinCount,
            'has_star' => $hasStar,
            'select' => $select,
            'errors' => [],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array{ok: false, sql: string, tables: list<string>, cte_names: list<string>, join_count: int, has_star: bool, select: null, errors: list<string>}
     */
    private function fail(array $errors, string $sql): array
    {
        return [
            'ok' => false,
            'sql' => $sql,
            'tables' => [],
            'cte_names' => [],
            'join_count' => 0,
            'has_star' => false,
            'select' => null,
            'errors' => $errors,
        ];
    }

    private function containsComments(string $sql): bool
    {
        // Reject SQL comment forms rather than stripping (anti-bypass).
        if (preg_match('/--/', $sql) === 1) {
            return true;
        }

        if (preg_match('/\/\*/', $sql) === 1) {
            return true;
        }

        // Hash comments outside of strings — approximate via lexer below; quick reject for `#` line comments.
        if (preg_match('/(^|[\s;(])#/', $sql) === 1) {
            return true;
        }

        try {
            $lexer = new Lexer($sql);
            foreach ($lexer->list->tokens as $token) {
                if ($token->type === Token::TYPE_COMMENT) {
                    return true;
                }
            }
        } catch (Throwable) {
            return true;
        }

        return false;
    }

    private function containsMultipleStatements(string $sql): bool
    {
        try {
            $lexer = new Lexer($sql);
            $semicolonIndexes = [];

            foreach ($lexer->list->tokens as $index => $token) {
                // TYPE_DELIMITER with null/empty value is the lexer EOF marker — not a statement split.
                if ($token->value === ';') {
                    $semicolonIndexes[] = $index;
                }
            }

            if ($semicolonIndexes === []) {
                return false;
            }

            // Allow a single trailing semicolon after the final meaningful token.
            if (count($semicolonIndexes) === 1) {
                $semiIndex = $semicolonIndexes[0];
                $tokens = $lexer->list->tokens;
                for ($i = $semiIndex + 1, $n = count($tokens); $i < $n; $i++) {
                    $t = $tokens[$i];
                    if ($t->type === Token::TYPE_WHITESPACE) {
                        continue;
                    }
                    // EOF delimiter after trailing semicolon is fine.
                    if ($t->type === Token::TYPE_DELIMITER && ($t->value === null || $t->value === '')) {
                        continue;
                    }

                    return true;
                }

                return false;
            }

            return true;
        } catch (Throwable) {
            return true;
        }
    }

    private function assertSafeTokens(string $sql): ?string
    {
        $lexer = new Lexer($sql);
        $tokens = $lexer->list->tokens;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->type === Token::TYPE_KEYWORD) {
                $kw = strtoupper((string) $token->keyword);
                if (in_array($kw, self::FORBIDDEN_KEYWORDS, true)) {
                    // INTO after SELECT is handled separately; bare INTO is forbidden.
                    return "Forbidden SQL keyword [{$kw}] is not allowed.";
                }
            }

            if ($token->type === Token::TYPE_NONE || $token->type === Token::TYPE_KEYWORD) {
                $name = strtoupper((string) $token->value);
                if (in_array($name, self::DANGEROUS_FUNCTIONS, true)) {
                    // Confirm function-call shape: NAME (
                    $j = $i + 1;
                    while ($j < $count && $tokens[$j]->type === Token::TYPE_WHITESPACE) {
                        $j++;
                    }
                    if ($j < $count && $tokens[$j]->value === '(') {
                        return "Forbidden SQL function [{$name}] is not allowed.";
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, select: SelectStatement|null, cte_names: list<string>, tables: list<string>, join_count: int, has_star: bool, errors: list<string>}
     */
    private function validateWithStatement(WithStatement $statement): array
    {
        $cteNames = [];
        $tables = [];
        $joinCount = 0;
        $hasStar = false;

        if ($statement->withers === []) {
            return [
                'ok' => false,
                'select' => null,
                'cte_names' => [],
                'tables' => [],
                'join_count' => 0,
                'has_star' => false,
                'errors' => ['WITH clause could not be validated. Use a descriptive CTE name (e.g. cte_users).'],
            ];
        }

        foreach ($statement->withers as $name => $wither) {
            $cteNames[] = is_string($name) ? $name : (string) ($wither->name ?? '');
            $subParser = $wither->statement ?? null;
            if (! $subParser instanceof Parser || ($subParser->statements[0] ?? null) === null) {
                return [
                    'ok' => false,
                    'select' => null,
                    'cte_names' => [],
                    'tables' => [],
                    'join_count' => 0,
                    'has_star' => false,
                    'errors' => ['Each CTE body must be a validated SELECT statement.'],
                ];
            }

            $body = $subParser->statements[0];
            if (! $body instanceof SelectStatement) {
                return [
                    'ok' => false,
                    'select' => null,
                    'cte_names' => [],
                    'tables' => [],
                    'join_count' => 0,
                    'has_star' => false,
                    'errors' => ['CTE bodies must be read-only SELECT statements.'],
                ];
            }

            $inspected = $this->inspectSelect($body);
            if (! $inspected['ok']) {
                return [
                    'ok' => false,
                    'select' => null,
                    'cte_names' => [],
                    'tables' => [],
                    'join_count' => 0,
                    'has_star' => false,
                    'errors' => $inspected['errors'],
                ];
            }

            $tables = array_merge($tables, $inspected['tables']);
            $joinCount += $inspected['join_count'];
            $hasStar = $hasStar || $inspected['has_star'];
        }

        $mainParser = $statement->cteStatementParser;
        if (! $mainParser instanceof Parser || ! (($mainParser->statements[0] ?? null) instanceof SelectStatement)) {
            return [
                'ok' => false,
                'select' => null,
                'cte_names' => [],
                'tables' => [],
                'join_count' => 0,
                'has_star' => false,
                'errors' => ['WITH query must end with a SELECT statement.'],
            ];
        }

        /** @var SelectStatement $mainSelect */
        $mainSelect = $mainParser->statements[0];
        $mainInspected = $this->inspectSelect($mainSelect);
        if (! $mainInspected['ok']) {
            return [
                'ok' => false,
                'select' => null,
                'cte_names' => [],
                'tables' => [],
                'join_count' => 0,
                'has_star' => false,
                'errors' => $mainInspected['errors'],
            ];
        }

        $tables = array_merge($tables, $mainInspected['tables']);
        $joinCount += $mainInspected['join_count'];
        $hasStar = $hasStar || $mainInspected['has_star'];

        return [
            'ok' => true,
            'select' => $mainSelect,
            'cte_names' => array_values(array_filter($cteNames)),
            'tables' => array_values(array_unique($tables)),
            'join_count' => $joinCount,
            'has_star' => $hasStar,
            'errors' => [],
        ];
    }

    /**
     * @return array{ok: bool, tables: list<string>, join_count: int, has_star: bool, errors: list<string>}
     */
    private function inspectSelect(SelectStatement $select): array
    {
        if ($select->union !== [] && $select->union !== null) {
            // UNION is read-only; allow but count as complexity — still OK for Phase 1E.
        }

        if ($select->into !== null) {
            return [
                'ok' => false,
                'tables' => [],
                'join_count' => 0,
                'has_star' => false,
                'errors' => ['SELECT INTO is not allowed.'],
            ];
        }

        $tables = [];
        $joinCount = is_array($select->join) ? count($select->join) : 0;

        foreach ($select->from ?? [] as $from) {
            $name = $this->expressionTableName($from);
            if ($name !== null) {
                $tables[] = $name;
            }
        }

        foreach ($select->join ?? [] as $join) {
            if (isset($join->expr)) {
                $name = $this->expressionTableName($join->expr);
                if ($name !== null) {
                    $tables[] = $name;
                }
            }
        }

        $hasStar = false;
        foreach ($select->expr ?? [] as $expr) {
            if ($this->expressionHasStar($expr)) {
                $hasStar = true;
            }
        }

        return [
            'ok' => true,
            'tables' => array_values(array_unique($tables)),
            'join_count' => $joinCount,
            'has_star' => $hasStar,
            'errors' => [],
        ];
    }

    private function expressionTableName(mixed $expr): ?string
    {
        if (! is_object($expr)) {
            return null;
        }

        if (isset($expr->table) && is_string($expr->table) && $expr->table !== '') {
            $table = $expr->table;
            if (isset($expr->database) && is_string($expr->database) && $expr->database !== '') {
                return strtolower($expr->database.'.'.$table);
            }

            return strtolower($table);
        }

        return null;
    }

    private function expressionHasStar(mixed $expr): bool
    {
        if (! $expr instanceof Expression && ! is_object($expr)) {
            return false;
        }

        $exprStr = isset($expr->expr) && is_string($expr->expr) ? $expr->expr : '';
        $column = isset($expr->column) && is_string($expr->column) ? $expr->column : '';

        return $exprStr === '*' || $column === '*' || str_ends_with($exprStr, '.*');
    }
}
