<?php

/**
 * Class FastMysqli
 * @author Maxim Kuvardin <kuvard.in@mail.ru>
 */
class FastMysqli extends Mysqli
{
    public const NOT_NULL = 'IC&4j440Ï*M&yl8аÑX(H%41b%L)sA(ca0Z9&ab52YpX#16*1O%x';

    /**
     * @var int
     */
    private $queries_counter = 0;

    /**
     * @param string $table
     * @param array|string $row
     * @param array|string|null $where
     * @param int|null $limit
     * @return bool|mysqli_result
     * @throws Error
     */
    public function fast_update(string $table, $row, $where = null, int $limit = null)
    {
        $query_string = 'UPDATE `' . $this->filter($table) . '` SET ' . $this->fast_datalist_gen($row);

        if ($where !== null) {
            $query_string .= ' WHERE ' . $this->fast_where_gen($where);
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        return $this->q($query_string);
    }

    /**
     * Deleting table rows
     * @param string $table
     * @param array|string|null $where
     * @param int|null $limit
     * @return bool|mysqli_result
     * @throws Error
     */
    public function fast_delete(string $table, $where = null, int $limit = null)
    {
        $query_string = 'DELETE FROM `' . $this->filter($table) . '`';

        if ($where !== null) {
            $query_string .= ' WHERE ' . $this->fast_where_gen($where);
        }

        if ($limit !== null) {
            $query_string .= ' LIMIT ' . $limit;
        }

        return $this->q($query_string);
    }

    /**
     * Select table rows
     * @param string $table
     * @param array|string|null $where
     * @param int|null $limit
     * @param string|null $ord
     * @param string|null $sort
     * @param int|null $offset
     * @return bool|mysqli_result
     * @throws Error
     */
    public function fast_select(string $table, $where = null, int $limit = null, string $ord = null, string $sort = null, int $offset = null)
    {
        $query_string = 'SELECT * FROM `' . $this->filter($table) . '`';

        if ($where !== null) {
            $query_string .= ' WHERE ' . $this->fast_where_gen($where);
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
            $query_string .= ' WHERE ' . $this->fast_where_gen($where);
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
    public function fast_add(string $table, $row)
    {
        $query_string = 'INSERT INTO `' . $this->filter($table) . '` SET ' . $this->fast_datalist_gen($row);
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
        $query_string = 'SELECT COUNT(*) FROM `' . $this->filter($table) . '` WHERE ' . $this->fast_where_gen($where) . ' LIMIT 1';
        return (bool)$this->q($query_string)->fetch_array()[0];
    }

    /**
     * @param $data
     * @param string $operation
     * @return string
     * @throws Error
     */
    public function fast_where_gen($data, string $operation = 'AND'): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            $result = '';
            $data_length = count($data);
            if ($data_length === 0) {
                $result .= '1';
            } else {
                $s = $index = 0;
                foreach ($data as $where_key => $where_value) {
                    if ($where_key === $index) {
                        $result .= $where_value . ' ';
                        $index++;
                    } else {
                        $result .= "`{$this->filter($where_key)}` ";
                        if ($where_value === null) {
                            $result .= 'IS NULL ';
                        } elseif ($where_value === self::NOT_NULL) {
                            $result .= 'IS NOT NULL ';
                        } else {
                            $result .= "= '{$this->filter($where_value)}' ";
                        }
                    }

                    if (++$s < $data_length) {
                        $result .= $operation;
                    }
                }
            }
            return $result;
        }

        $data_type = gettype($data);
        throw new Error("Data must be array or string, {$data_type} given");
    }

    /**
     * @param array|string $data
     * @return string
     * @throws Error
     */
    private function fast_datalist_gen($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            $result = '';
            $index = 0;

            foreach ($data as $key => $value) {
                if ($key === $index) {
                    $result .= ' ' . $value . ',';
                    $index++;
                } else {
                    $result .= ' `' . $this->filter($key) . '` = ' . ($value === null ? 'NULL' : '\'' . $this->filter($value) . '\'') . ',';
                }
            }

            $result = rtrim($result, ',');
            return $result;
        }

        $data_type = gettype($data);
        throw new Error("Data must be array or string, {$data_type} given");
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
     * @param string $table_name
     * @param array|string $identify_data
     * @param array|string $adding_data
     * @return array|null
     * @throws Error
     */
    public function fast_get_row_or_create(string $table_name, $identify_data, $adding_data = []): array
    {
        while (!$row = $this->fast_select($table_name, $identify_data, 1)->fetch_assoc()) {
            $row_data = is_array($identify_data) ? array_merge($adding_data, $identify_data) : $adding_data;
            $this->fast_add($table_name, $row_data);
        }
        return $row;
    }
}