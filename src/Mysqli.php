<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli;

use DateTime;
use Error;
use Kuvardin\FastMysqli\Exceptions\MysqliError;
use mysqli_result;

/**
 * Class Mysqli
 *
 * @package Kuvardin\FastMysqli
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class Mysqli extends \Mysqli
{
    /**
     * @var NotNull|null
     */
    private static ?NotNull $not_null = null;

    /**
     * @var IsNull|null
     */
    private static ?IsNull $is_null = null;

    /**
     * @var int
     */
    private int $queries_counter = 0;

    /**
     * @var string|null
     */
    private ?string $log_file_path = null;

    /**
     * Mysqli constructor.
     *
     * @param string|null $host
     * @param string|null $username
     * @param string|null $password
     * @param string|null $dbname
     * @param null $port
     * @param null $socket
     * @throws MysqliError
     */
    public function __construct(string $host = null, string $username = null, string $password = null,
        string $dbname = null, $port = null, $socket = null)
    {
        parent::__construct($host, $username, $password, $dbname, $port, $socket);
        if ($this->connect_errno) {
            throw new MysqliError($this->connect_errno, $this->sqlstate, $this->connect_error, null);
        }
    }

    /**
     * @param bool|null $is_null
     * @return IsNull|NotNull|null
     */
    public static function get_null(?bool $is_null)
    {
        if ($is_null === null) {
            return null;
        }

        return $is_null ? self::is_null() : self::not_null();
    }

    /**
     * @return IsNull
     */
    public static function is_null(): IsNull
    {
        return self::$is_null ?? (self::$is_null = new IsNull());
    }

    /**
     * @return NotNull
     */
    public static function not_null(): NotNull
    {
        return self::$not_null ?? (self::$not_null = new NotNull());
    }

    /**
     * @param bool|null $not_null
     * @return IsNull|NotNull|null
     */
    public static function get_not_null(?bool $not_null)
    {
        if ($not_null === null) {
            return null;
        }

        return $not_null ? self::not_null() : self::is_null();
    }

    /**
     * @param array $values
     * @return NotIn
     */
    public static function not_in_array(array $values): NotIn
    {
        return new NotIn($values);
    }

    /**
     * @param array $values
     * @return In
     */
    public static function in_array(array $values): In
    {
        return new In($values);
    }

    /**
     * @param int|null $int
     * @return bool|null
     */
    public static function get_bool(?int $int): ?bool
    {
        if ($int === null) {
            return null;
        }
        return $int !== 0;
    }

    /**
     * @param int $int
     * @return bool
     */
    public static function require_bool(int $int): bool
    {
        return $int !== 0;
    }

    /**
     * @param string $log_file_path
     */
    public function enable_logging(string $log_file_path): void
    {
        $this->log_file_path = $log_file_path;
    }

    public function disable_logging(): void
    {
        $this->log_file_path = null;
    }

    /**
     * @param string $table
     * @param array|string $row
     * @param array|string|null $where
     * @param int|null $limit
     * @return bool
     * @throws MysqliError
     */
    public function fast_update(string $table, $row, $where = null, int $limit = null): bool
    {
        $query_string = "UPDATE `{$this->filter($table)}`";

        $query_string .= ' SET ' . (is_string($row) ? $row : $this->fast_datalist_gen($row));

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        return $this->q($query_string);
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
     * @param array $data
     * @return string
     */
    private function fast_datalist_gen(array $data): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $pairs[] = $value;
                continue;
            }

            if ($value === null) {
                $value = 'NULL';
            } elseif ($value instanceof TableRow) {
                $value = $value->getId();
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_string($value)) {
                $value = "'{$this->filter($value)}'";
            } elseif (!is_numeric($value)) {
                $type = gettype($value);
                throw new Error("Unknown field $key type $type with value: " . print_r($value, true));
            }

            $pairs[] = "`{$this->filter($key)}` = $value";
        }

        return implode(', ', $pairs);

    }

    /**
     * @param array $data
     * @param string $operation
     * @return string
     */
    public function fast_where_gen(array $data, string $operation = 'AND'): string
    {
        if (empty($data)) {
            return '1';
        }

        $expressions = [];
        foreach ($data as $where_key => $where_value) {
            if ($where_value === null) {
                continue;
            }

            if (is_int($where_key)) {
                $expressions[] = $where_value;
            } else {
                $expression = "`{$this->filter($where_key)}` ";
                if ($where_value instanceof NotNull) {
                    $expression .= 'IS NOT NULL';
                } elseif ($where_value instanceof IsNull) {
                    $expression .= 'IS NULL';
                } elseif ($where_value instanceof NotIn) {
                    $array_items = [];
                    foreach ($where_value as $array_item) {
                        $array_items[] = $this->filter_scalar_value($array_item);
                    }
                    $expression .= 'NOT IN (' . implode(', ', $array_items) . ')';
                } elseif ($where_value instanceof In) {
                    $array_items = [];
                    foreach ($where_value as $array_item) {
                        $array_items[] = $this->filter_scalar_value($array_item);
                    }
                    $expression .= 'IN (' . implode(', ', $array_items) . ')';
                } else {
                    $expression .= '= ' . $this->filter_scalar_value($where_value);
                }
                $expressions[] = $expression;
            }
        }

        return $expressions === [] ? '1' : implode(" $operation ", $expressions);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function filter_scalar_value($value)
    {
        if ($value instanceof TableRow) {
            return $value->getId();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return $value;
        }

        return "'{$this->filter($value)}'";
    }

    /**
     * @param string $query
     * @param int|null $result_mode
     * @return bool|mysqli_result
     * @throws MysqliError
     */
    public function q(string $query, int $result_mode = null)
    {
        $this->queries_counter++;

        if ($this->log_file_path !== null) {
            $start_time = microtime(true);
            $result = $this->query($query, $result_mode ?? MYSQLI_STORE_RESULT);
            $time = (microtime(true) - $start_time) * 1000;
            $date_time = (new DateTime())->format('Y.m.d H:i:s:u');
            $log_text = "[$date_time|$time] " . str_replace(PHP_EOL, '\\n', $query) . PHP_EOL;
            $f = fopen($this->log_file_path, 'ab');
            fwrite($f, $log_text);
            fclose($f);
        } else {
            $result = $this->query($query, $result_mode ?? MYSQLI_STORE_RESULT);
        }

        if (!empty($this->error_list)) {
            $errors_count = count($this->error_list);
            $mysqli_error = null;
            foreach ($this->error_list as $error_array) {
                $mysqli_error = new MysqliError(
                    $error_array['errno'],
                    $error_array['sqlstate'],
                    $error_array['error'],
                    --$errors_count === 0 ? $query : null,
                    $mysqli_error
                );
            }
            throw $mysqli_error;
        }

        return $result;
    }

    /**
     * @param string $table
     * @param string|array|null $where
     * @param int|null $limit
     * @return bool
     * @throws MysqliError
     */
    public function fast_delete(string $table, $where = null, int $limit = null): bool
    {
        $query_string = "DELETE FROM `{$this->filter($table)}`";

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        return $this->q($query_string);
    }

    /**
     * @param string $table
     * @param string|array|null $where
     * @return int
     * @throws MysqliError
     */
    public function fast_count(string $table, $where = null): int
    {
        $query_string = "SELECT COUNT(*) FROM `{$this->filter($table)}`";

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        $response = $this->q($query_string);
        return $response === false ? 0 : (int)$response->fetch_array()[0];
    }

    /**
     * @param string $table
     * @param string|array $where
     * @return bool
     * @throws MysqliError
     */
    public function fast_check(string $table, $where): bool
    {
        $query_string = "SELECT COUNT(*) FROM `{$this->filter($table)}`" .
            ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where)) .
            ' LIMIT 1';
        return (bool)$this->q($query_string)->fetch_array()[0];
    }

    /**
     * @param array $data
     * @param string $operation
     * @return string
     * @throws Error
     */
    public function fast_generate_where(array $data, string $operation = 'AND'): string
    {
        if (empty($data)) {
            return '1';
        }

        $result = '';
        $s = 0;
        $data_length = count($data);
        foreach ($data as $where_key => $where_value) {
            if (is_string($where_value)) {
                $result .= $where_value;
            } else {
                $raw = $where_value[3] ?? false;
                $result .= '`' . $where_value[0] . '` ' . $where_value[1] . ' ';
                if (is_bool($where_value[2])) {
                    $result .= $where_value[2] ? '1' : '0';
                } elseif (is_int($where_value[2]) || is_float($where_value[2])) {
                    $result .= $where_value[2];
                } elseif ($where_value[2] instanceof TableRow) {
                    $result .= $where_value[2]->getId();
                } elseif (is_string($where_value[2])) {
                    $result .= $raw ? $where_value[2]
                        : ('\'' . $this->filter($where_value[2]) . '\'');
                } elseif (is_array($where_value[2])) {
                    $where_items = [];
                    foreach ($where_value[2] as $item_value) {
                        if (is_string($item_value)) {
                            $where_items[] = '\'' . ($raw ? $item_value : $this->filter($item_value)) . '\'';
                        } elseif (is_bool($item_value)) {
                            $where_items[] = $item_value ? '1' : '0';
                        } elseif (is_int($item_value) || is_float($item_value)) {
                            $where_items[] = (string)$item_value;
                        } elseif ($item_value instanceof TableRow) {
                            $where_items[] = (string)$item_value->getId();
                        } else {
                            $type = gettype($item_value);
                            throw new Error("Incorrect set item typed $type with value " .
                                print_r($item_value, true));
                        }
                    }

                    $result .= '(' . implode(', ', $where_items) . ')';
                } else {
                    $type = gettype($where_value[2]);
                    throw new Error("Unknown field {$where_value[0]} typed $type with value " .
                        print_r($where_value[2], true));
                }
            }

            if (++$s < $data_length) {
                $result .= ' ' . $operation . ' ';
            }
        }

        return $result;
    }

    /**
     * @return int
     */
    public function fast_queries_number(): int
    {
        return $this->queries_counter;
    }

    /**
     * @param string $table
     * @param string|array|null $where
     * @param int|null $limit
     * @param string|null $ord
     * @param string|null $sort
     * @param int|null $offset
     * @return mysqli_result
     * @throws MysqliError
     */
    public function fast_select(string $table, $where = null, int $limit = null, string $ord = null,
        string $sort = null, int $offset = null): mysqli_result
    {
        $query_string = "SELECT * FROM `{$this->filter($table)}`";

        if ($where !== null) {
            $query_string .= ' WHERE ' . (is_string($where) ? $where : $this->fast_where_gen($where));
        }

        if ($ord !== null) {
            $query_string .= " ORDER BY `$ord` $sort ";
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        if ($offset !== null && $offset !== 0) {
            $query_string .= ' OFFSET ' . $offset;
        }

        return $this->q($query_string);
    }

    /**
     * @param string $query
     * @param bool $search_all_words
     * @param string[] $columns
     * @return string|null
     */
    public function fast_search_exp_gen(string $query, bool $search_all_words, array $columns): ?string
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
                $result .= "`$column` LIKE '%$word%'";
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
     * @param string $table
     * @param string|array $row
     * @return bool
     * @throws MysqliError
     */
    public function fast_add(string $table, $row): bool
    {
        $query_string = "INSERT INTO `{$this->filter($table)}`";
        $query_string .= ' SET ' . (is_string($row) ? $row : $this->fast_datalist_gen($row));
        return $this->q($query_string);
    }
}
