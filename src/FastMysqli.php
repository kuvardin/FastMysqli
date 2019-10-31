<?php

/**
 * Class FastMysqli
 *
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class FastMysqli extends Mysqli
{
    public const NOT_NULL = NAN;

    public const BOOL_OPERATION_AND = 'AND';
    public const BOOL_OPERATION_OR = 'OR';

    /**
     * @var int
     */
    private $queries_counter = 0;

    /**
     * @var string|null
     */
    private $log_file_path;

    /**
     * @param string $log_file_path
     * @return $this
     */
    public function enable_logging(string $log_file_path): self
    {
        $this->log_file_path = $log_file_path;
        return $this;
    }

    /**
     * @return $this
     */
    public function disable_logging(): self
    {
        $this->log_file_path = null;
        return $this;
    }

    /**
     * @param string $table
     * @param array|string $row
     * @param array|string|null $where
     * @param int|null $limit
     * @return bool
     * @throws Error
     */
    public function fast_update(string $table, $row, $where = null, int $limit = null): bool
    {
        $query_string = 'UPDATE `' . $this->filter($table) . '`' .
            'SET ' . (is_string($row) ? $this->filter($row) : $this->fast_datalist_gen($row));

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        return $this->q($query_string);
    }

    /**
     * Deleting table rows
     *
     * @param string $table
     * @param array|string|null $where
     * @param int|null $limit
     * @return bool
     * @throws Error
     */
    public function fast_delete(string $table, $where = null, int $limit = null): bool
    {
        $query_string = 'DELETE FROM `' . $this->filter($table) . '`';

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        return $this->q($query_string);
    }

    /**
     * Select table rows
     *
     * @param string $table
     * @param array|string|null $where
     * @param int|null $limit
     * @param string|null $ord
     * @param string|null $sort
     * @param int|null $offset
     * @return mysqli_result
     * @throws Error
     */
    public function fast_select(string $table, $where = null, int $limit = null, string $ord = null, string $sort = null, int $offset = null): mysqli_result
    {
        $query_string = 'SELECT * FROM `' . $this->filter($table) . '`';

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        if ($ord !== null) {
            $query_string .= ' ORDER BY `' . $ord . '` ' . ($sort ?? 'ASC');
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        if ($offset !== null && ((int)$offset) !== 0) {
            $query_string .= ' OFFSET ' . $offset;
        }

        return $this->q($query_string);
    }

    /**
     * @param string $table
     * @param array|string|null $where
     * @return int
     * @throws Error
     */
    public function fast_count(string $table, $where = null): int
    {
        $query_string = 'SELECT COUNT(*) FROM `' . $this->filter($table) . '`';

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        $response = $this->q($query_string);
        return $response === false ? 0 : (int)$response->fetch_array()[0];
    }

    /**
     * @param string $table
     * @param array|string $row
     * @return bool|mysqli_result
     * @throws Error
     */
    public function fast_add(string $table, $row): bool
    {
        $query_string = 'INSERT INTO `' . $this->filter($table) . '`' .
            ' SET ' . (is_string($row) ? $this->filter($row) : $this->fast_datalist_gen($row));
        return $this->q($query_string);
    }

    /**
     * @param string $table
     * @param array|string $where
     * @return bool
     * @throws Error
     */
    public function fast_check(string $table, $where): bool
    {
        $query_string = 'SELECT COUNT(*) FROM `' . $this->filter($table) . '`' .
            ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where)) .
            ' LIMIT 1';
        return (bool)$this->q($query_string)->fetch_array()[0];
    }

    /**
     * @param array $data
     * @param string $operation
     * @return string
     */
    private function fast_where_gen(array $data, string $operation = self::BOOL_OPERATION_AND): string
    {
        if (!self::checkBoolOperation($operation)) {
            throw new Error("Unknown bool operation: $operation");
        }

        if (!isset($data[0])) {
            return '1';
        }

        $result = '';
        $s = $index = 0;
        $data_length = count($data);
        foreach ($data as $where_key => $where_value) {
            if ($where_key === $index) {
                $result .= $where_value;
                $index++;
            } else {
                $result .= "`{$this->filter($where_key)}` ";
                if ($where_value === null) {
                    $result .= 'IS NULL';
                } elseif (is_nan($where_value)) {
                    $result .= 'IS NOT NULL';
                } else {
                    $result .= '= ';
                    if (is_bool($where_value)) {
                        $result .= $where_value ? '1' : '0';
                    } elseif (is_string($where_value) && strpos($where_value, '(') === 0) {
                        $result .= $this->filter($where_value);
                    } else {
                        $result .= '\'' . $this->filter($where_value) . '\'';
                    }
                }
            }

            if (++$s < $data_length) {
                $result .= ' ' . $operation . ' ';
            }
        }

        return $result;
    }

    /**
     * @param array|string $data
     * @return string
     * @throws Error
     */
    private function fast_datalist_gen(array $data): string
    {
        $index = 0;
        $result = '';
        foreach ($data as $key => $value) {
            if ($key === $index) {
                $result .= ' ' . $value . ',';
                $index++;
            } else {
                if ($value === null) {
                    $value = 'NULL';
                } elseif (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif ($value === 0) {
                    $value = '0';
                } elseif (!is_numeric($value)) {
                    $value = '\'' . $this->filter($value) . '\'';
                }

                $result .= ' `' . $this->filter($key) . '` = ' . $value . ',';
            }
        }

        $result = rtrim($result, ',');
        return $result;

    }

    /**
     * @param string $text
     * @return string
     */
    public function filter(string $text): string
    {
        return $this->real_escape_string($text);
    }

    /**
     * @param string $query
     * @param int $resultmode
     * @return bool|mysqli_result
     */
    public function q(string $query, int $resultmode = MYSQLI_STORE_RESULT)
    {
        $this->queries_counter++;

        if ($this->log_file_path !== null) {
            $log_file = fopen($this->log_file_path, 'w');
            fwrite($log_file, "\n" . date('[Y.m.d H:i:s] ') . $query);
            fclose($log_file);
        }

        $result = $this->query($query, $resultmode);

        if ($result === false) {
            throw new Error("Mysql error \"{$this->error}\" in query \"{$query}\"");
        }


        return $result;
    }

    /**
     * @return int
     */
    public function fast_queries_counter(): int
    {
        return $this->queries_counter;
    }

    /**
     * @param string $table_name
     * @param string|null $index
     * @param string|array|null $where
     * @return array
     * @throws Error
     */
    public function fast_get_all_rows(string $table_name, string $index = null, $where = null): array
    {
        $result = [];
        $rows = $this->fast_select($table_name, $where);
        while ($row = $rows->fetch_assoc()) {
            if ($index === null) {
                $result[] = $row;
            } else {
                $result[$row[$index]] = $row;
            }
        }
        return $result;
    }

    /**
     * @param string $query
     * @param bool $search_all_words
     * @param string ...$columns
     * @return string|null
     */
    public function fast_search_exp_gen(string $query, bool $search_all_words, string ...$columns): ?string
    {
        $query = preg_replace('/([\s\n\t ]+)/', ' ', $query);
        $words = explode(' ', $query);
        $words_count = count($words);
        if ($words_count === 0) {
            return null;
        }

        $i = 0;
        $operator = $search_all_words ? 'AND' : 'OR';
        $columns_count = count($columns);
        $result = '(';
        foreach ($words as $word) {
            $j = 0;
            $word = $this->filter($word);
            $result .= '(';
            foreach ($columns as $column) {
                $result .= '`' . $column . '` LIKE \'%' . $word . '%\'';
                if (++$j !== $columns_count) {
                    $result .= ' OR ';
                }
            }
            $result .= ')';
            if (++$i !== $words_count) {
                $result .= ' ' . $operator . ' ';
            }
        }

        $result .= ')';
        return $result;
    }

    /**
     * @param string $table_name
     * @param array|string $identify_data
     * @param array|string $adding_data
     * @return array|null
     * @throws Error
     */
    public function fast_get_or_create(string $table_name, $identify_data, $adding_data = []): array
    {
        while (!$row = $this->fast_select($table_name, $identify_data, 1)->fetch_assoc()) {
            $row_data = is_array($identify_data) ? array_merge($adding_data, $identify_data) : $adding_data;
            $this->fast_add($table_name, $row_data);
        }
        return $row;
    }

    /**
     * @param string $bool_operation
     * @return bool
     */
    private static function checkBoolOperation(string $bool_operation): bool
    {
        return $bool_operation === self::BOOL_OPERATION_AND ||
            $bool_operation === self::BOOL_OPERATION_OR;
    }
}