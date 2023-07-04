<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli;

use Generator;
use Kuvardin\FastMysqli\Exceptions\AlreadyExists;
use Kuvardin\FastMysqli\Exceptions\MysqliError;
use RuntimeException;

/**
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
abstract class TableRow
{
    public const COL_ID = 'id';
    public const COL_CREATION_DATE = 'creation_date';

    protected static Mysqli $mysqli;

    /**
     * @var self[][]
     */
    private static array $cache = [];

    protected int $id;
    protected int $creation_date;
    protected array $edited_fields = [];

    public function __construct(array $data)
    {
        $this->id = $data[self::COL_ID];
        $this->creation_date = $data[self::COL_CREATION_DATE];
    }

    final public static function setMysqli(Mysqli $mysqli): void
    {
        self::$mysqli = $mysqli;
    }

    /**
     * @throws Exceptions\MysqliError
     */
    final public static function checkExistsById(int $id): bool
    {
        return self::$mysqli->fast_check(static::getDatabaseTableName(), [self::COL_ID => $id]);
    }

    abstract public static function getDatabaseTableName(): string;

    /**
     * @throws MysqliError
     */
    final public static function requireByFieldsValues(
        array $data,
        string $ord = null,
        string $sort = null,
        int $offset = null,
    ): static
    {
        return self::makeByFieldsValues($data, $ord, $sort, $offset);
    }

    public static function getCache(): array
    {
        return self::$cache;
    }

    /**
     * @return static|null
     * @throws MysqliError
     */
    final public static function makeByFieldsValues(
        array $data,
        string $ord = null,
        string $sort = null,
        int $offset = null,
    ): ?self
    {
        $row = self::$mysqli
            ->fast_select(static::getDatabaseTableName(), $data, 1, $ord, $sort, $offset)
            ->fetch_assoc();
        if ($row === null) {
            return null;
        }

        if (empty(self::$cache[static::getDatabaseTableName()][$row[self::COL_ID]])) {
            self::$cache[static::getDatabaseTableName()][$row[self::COL_ID]] = new static($row);
        }

        return self::$cache[static::getDatabaseTableName()][$row[self::COL_ID]];
    }

    final public static function clearCache(): void
    {
        self::$cache[static::getDatabaseTableName()] = [];
    }

    final public static function clearAllCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return Generator|static[]
     * @throws MysqliError
     */
    final public static function getSelection(?SelectionData $selection_data, ?array $filters): Generator
    {
        $rows = $selection_data === null
            ? self::$mysqli->fast_select(static::getDatabaseTableName(), $filters)
            : self::$mysqli->fast_select(
                static::getDatabaseTableName(),
                $filters,
                $selection_data->getLimit(),
                $selection_data->getOrd(),
                $selection_data->getSort(),
                $selection_data->getOffset()
            );
        if (!$rows->num_rows) {
            return;
        }

        while ($row = $rows->fetch_assoc()) {
            yield self::getFromCache(new static($row));
        }
    }

    /**
     * @param TableRow $object
     * @return static
     */
    final protected static function getFromCache(self $object): self
    {
        if (empty(self::$cache[$object::getDatabaseTableName()][$object->getId()])) {
            self::$cache[$object::getDatabaseTableName()][$object->getId()] = $object;
        } else {
            $object = self::$cache[$object::getDatabaseTableName()][$object->getId()];
        }

        return $object;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @throws MysqliError
     */
    final public static function count(array|string $filters = null): int
    {
        return self::$mysqli->fast_count(static::getDatabaseTableName(), $filters);
    }

    /**
     * @throws AlreadyExists
     * @throws MysqliError
     */
    final protected static function createWithFieldsValues(?int $id, array $data, int $creation_date = null): static
    {
        $id === null ?: ($data[self::COL_ID] = $id);
        $data[self::COL_CREATION_DATE] = $creation_date ?? time();
        self::$mysqli->fast_add(static::getDatabaseTableName(), $data);
        return self::requireById($id ?? self::$mysqli->insert_id);
    }

    /**
     * @throws MysqliError
     */
    final public static function requireById(int $id): static
    {
        return static::makeById($id);
    }

    /**
     * @throws MysqliError
     */
    public static function cacheByIds(array $ids): void
    {
        foreach ($ids as $id) {
            if (!is_int($id) && !preg_match('|^-?\d+$|', $id)) {
                throw new RuntimeException("Not integer value: $id");
            }
        }

        $rows = self::$mysqli->fast_select(static::getDatabaseTableName(), [
            TableRow::COL_ID => $ids,
        ]);

        if ($rows->num_rows) {
            while ($row = $rows->fetch_assoc()) {
                self::$cache[static::getDatabaseTableName()][$row[TableRow::COL_ID]] = new static($row);
            }
        }
    }

    /**
     * @throws MysqliError
     */
    final public static function makeById(int $id): ?static
    {
        if (!empty(self::$cache[static::getDatabaseTableName()][$id])) {
            return self::$cache[static::getDatabaseTableName()][$id];
        }

        $row = self::$mysqli->fast_select(static::getDatabaseTableName(), [self::COL_ID => $id], 1)->fetch_assoc();
        if ($row !== null) {
            $instance = new static($row);
            self::$cache[static::getDatabaseTableName()][$id] = $instance;
            return $instance;
        }

        return null;
    }

    /**
     * @throws AlreadyExists
     * @throws MysqliError
     */
    protected static function requireUnique(array $data): void
    {
        if (self::$mysqli->fast_check(static::getDatabaseTableName(), $data)) {
            throw new AlreadyExists(static::class, $data);
        }
    }

    final public function getCreationDate(): int
    {
        return $this->creation_date;
    }

    final public function setCreationDate(int $creation_date): void
    {
        $this->setFieldValue(self::COL_CREATION_DATE, $this->creation_date, $creation_date);
    }

    final public function setFieldValue(
        string $field_name,
        mixed &$variable,
        mixed $new_value,
        bool $force = false,
    ): static
    {
        if ($new_value instanceof self) {
            $new_value = $new_value->getId();
        }

        if ($force || $variable !== $new_value) {
            $variable = $new_value;
            $this->edited_fields[$field_name] = $new_value;
        }

        return $this;
    }

    /**
     * @throws MysqliError
     */
    public function deleteFromCache(): void
    {
        if (isset(self::$cache[static::getDatabaseTableName()][$this->id])) {
            $this->save();
            unset(self::$cache[static::getDatabaseTableName()][$this->id]);
        }
    }

    /**
     * @throws MysqliError
     */
    public function __destruct()
    {
        $this->save();
    }

    /**
     * @throws MysqliError
     */
    public function save(): void
    {
        if (count($this->edited_fields)) {
            self::$mysqli->fast_update(static::getDatabaseTableName(), $this->edited_fields,
                [self::COL_ID => $this->id], 1);
            $this->edited_fields = [];
        }
    }

    public function getEditedFields(): array
    {
        return $this->edited_fields;
    }
}
