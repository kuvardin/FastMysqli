<?php

/**
 * Class FastMysqli
 * @author Maxim Kuvardin <kuvard.in@mail.ru>
 */
class FastMysqli extends Mysqli
{
    /**
     * @var int $queries_counter Number of mysql-requests
     */
    private $queries_counter = 0;

    const NOT_NULL = 'это очень-очень длинная строка, которую нельзя повторить случайным образом';

    /**
     * Update table rows
     * @param string $table
     * @param string|array $row
     * @param string|array|null $where
     * @param int|null $limit
     * @return mysqli_result
     */
    public function fast_update_row(string $table, $row, $where = null, int $limit = null)
    {
        $query_string = "UPDATE `{$this->filter($table)}` SET {$this->fast_datalist_gen($row)}";
        if (!is_null($where)) {
            $query_string .= ' WHERE ' . $this->fast_where_gen($where);
        }
        if (!is_null($limit)) {
            $query_string .= ' LIMIT ' . intval($limit);
        }
        return $this->q($query_string);
    }

    /**
     * Deleting table rows
     * @param string $table
     * @param null $where
     * @param int|null $limit
     * @return bool|mysqli_result
     * @throws \Exception
     */
    public function fast_delete_row(string $table, $where = null, int $limit = null)
    {
        $query_string = "DELETE FROM `" . $this->filter($table) . "` WHERE " . $this->fast_where_gen($where);
        if (!is_null($limit)) {
            $query_string .= " LIMIT " . intval($limit);
        }
        return $this->q($query_string);
    }

    /**
     * Select table rows
     * @param string $table
     * @param null $where
     * @param int|null $limit
     * @param string|null $ord
     * @param string|null $sort
     * @param int|null $offset
     * @return bool|mysqli_result
     * @throws Exception
     */
    public function fast_select_row(string $table, $where = null, int $limit = null, string $ord = null, string $sort = null, int $offset = null)
    {
        $query_string = "SELECT * FROM `" . $this->filter($table) . "`";
        if (!is_null($where) && !empty($where)) {
            $query_string .= " WHERE " . $this->fast_where_gen($where);
        }

        if (!is_null($ord)) {
            $query_string .= " ORDER BY `{$ord}` " . (!is_null($sort) ? $sort : 'ASC');
        }

        if (!is_null($limit)) {
            $query_string .= " LIMIT " . (!is_null($offset) ? $offset . ',' . $limit : $limit);
        }

        return $this->q($query_string);
    }

    public function fast_count_row(string $table, $where = null)
    {
        $query_string = "SELECT COUNT(*) FROM `" . $this->filter($table) . "`";
        if (!is_null($where) && !empty($where)) {
            $query_string .= " WHERE " . $this->fast_where_gen($where);
        }
        $result = intval($this->q($query_string)->fetch_array()[0]);
        return $result;
    }

    public function fast_add_row(string $table, $row)
    {
        return $this->q("INSERT INTO `" . $this->filter($table) . "` SET " . $this->fast_datalist_gen($row));
    }

    public function fast_check_row(string $table, array $where)
    {
        $result = (bool)$this->q("SELECT COUNT(*) FROM `" . $this->filter($table) . "` WHERE " . $this->fast_where_gen($where) . " LIMIT 1")->fetch_array()[0];
        return $result;
    }

    public function fast_where_gen($data, string $operation = 'AND')
    {
        if (is_string($data)) {
            return $data;
        } elseif (!is_array($data)) {
            throw new \Exception("Data array musb be array or string, given " . gettype($data));
        }

        $result = '';
        $s = 0;
        $data_length = count($data);

        $index = 0;
        foreach ($data as $where_key => $where_value) {
            if ($where_key === $index) {
                $result .= $where_value . ' ';
                $index++;
            } else {
                $result .= '`' . $this->filter($where_key) . '` ';
                if (is_null($where_value)) {
                    $result .= 'IS NULL ';
                } elseif ($where_value === self::NOT_NULL) {
                    $result .= 'IS NOT NULL ';
                } else {
                    $result .= "= '" . $this->filter($where_value) . "' ";
                }
            }

            if (++$s < $data_length) {
                $result .= $operation;
            }
        }

        return $result;
    }

    private function fast_datalist_gen($data)
    {
        if (is_string($data)) {
            return $data;
        }

        $datalist = '';
        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $datalist .= " `" . $this->filter($key) . "` = NULL,";
            } else {
                $datalist .= " `" . $this->filter($key) . "` = '" . $this->filter($value) . "',";
            }

        }
        $datalist = trim($datalist, ',');
        return $datalist;
    }

    public function filter(string $text)
    {
        return $this->real_escape_string(strval($text));
    }

    public function q(string $query_text)
    {
        $this->queries_counter++;
        $result = $this->query($query_text);
        if ($result === false) {
            throw new Exception("Mysql error \"{$this->error}\" in query \"{$query_text}\"");
        }

        return $result;
    }

    public function get_queries_counter()
    {
        return $this->queries_counter;
    }

    public function get_all_rows(string $table_name, string $index = null, $where = null)
    {
        $result = [];
        $rows = $this->fast_select_row($table_name, $where);
        while ($row = $rows->fetch_assoc()) {
            if (!empty($index)) {
                $result[$row[$index]] = $row;
            } else {
                $result[] = $row;
            }
        }
        return $result;
    }

    public function fast_get_all_rows(string $table_name, string $index = null, $where = null)
    {
        $result = [];
        $rows = $this->fast_select_row($table_name, $where);
        while ($row = $rows->fetch_assoc()) {
            if (!empty($index)) {
                $result[$row[$index]] = $row;
            } else {
                $result[] = $row;
            }
        }
        return $result;
    }

    public function fast_get_row_or_create(string $table_name, $identify_data, array $adding_data = [])
    {
        while (!$row = $this->fast_select_row($table_name, $identify_data, 1)->fetch_assoc()) {
            $row_data = is_array($identify_data) ? array_merge($adding_data, $identify_data) : $adding_data;
            $this->fast_add_row($table_name, $row_data);
        }
        return $row;
    }
}