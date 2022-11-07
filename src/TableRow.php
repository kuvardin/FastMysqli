<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli;

use DateTime;
use DateTimeZone;
use Generator;
use Kuvardin\FastMysqli\Exceptions\AlreadyExists;
use Kuvardin\FastMysqli\Exceptions\MysqliError;

/**
 * Class TableRow
 *
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
abstract class TableRow
{
    public const COL_ID = 'id';
    public const COL_CREATION_DATE = 'creation_date';

    /**
     * @var Mysqli
     */
    protected static Mysqli $mysqli;

    /**
     * @var self[][]
     */
    private static array $cache = [];

    /**
     * @var int
     */
    protected int $id;

    /**
     * @var int
     */
    protected int $creation_date;

    /**
     * @var array
     */
    private array $edited_fields = [];

    /**
     * @var DateTimeZone|null
     */
    private static ?DateTimeZone $timezone = null;

    /**
     * TableRow constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->id = $data[self::COL_ID];
        $this->creation_date = $data[self::COL_CREATION_DATE];
    }

    /**
     * @param Mysqli $mysqli
     */
    final public static function setMysqli(Mysqli $mysqli): void
    {
        self::$mysqli = $mysqli;
    }

    /**
     * @param DateTimeZone|null $date_time_zone
     */
    final public static function setDateTimeZone(?DateTimeZone $date_time_zone): void
    {
        self::$timezone = $date_time_zone;
    }

    /**
     * @param int $id
     * @return bool
     * @throws Exceptions\MysqliError
     */
    final public static function checkExistsById(int $id): bool
    {
        return self::$mysqli->fast_check(static::getDatabaseTableName(), [self::COL_ID => $id]);
    }

    /**
     * @return string
     */
    abstract public static function getDatabaseTableName(): string;

    /**
     * @param array $data
     * @param string|null $ord
     * @param string|null $sort
     * @param int|null $offset
     * @return static
     * @throws MysqliError
     */
    final public static function requireByFieldsValues(array $data, string $ord = null, string $sort = null,
        int $offset = null): self
    {
        return self::makeByFieldsValues($data, $ord, $sort, $offset);
    }

    /**
     * @param array $data
     * @param string|null $ord
     * @param string|null $sort
     * @param int|null $offset
     * @return static|null
     * @throws MysqliError
     */
    final public static function makeByFieldsValues(array $data, string $ord = null, string $sort = null,
        int $offset = null): ?self
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
     * @param SelectionData|null $selection_data
     * @param array|null $filters
     * @return Generator|static[]
     * @throws MysqliError
     */
    final public static function getSelection(?SelectionData $selection_data, ?array $filters): Generator
    {
        $rows = $selection_data === null
            ? self::$mysqli->fast_select(static::getDatabaseTableName(), $filters)
            : self::$mysqli->fast_select(static::getDatabaseTableName(), $filters, $selection_data->getLimit(),
                $selection_data->getOrd(), $selection_data->getSort(), $selection_data->getOffset());
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

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param array|string|null $filters
     * @return int
     * @throws MysqliError
     */
    final public static function count(array|string $filters = null): int
    {
        return self::$mysqli->fast_count(static::getDatabaseTableName(), $filters);
    }

    /**
     * @param int|null $id
     * @param array $data
     * @param int|null $creation_date
     * @return static
     * @throws AlreadyExists
     * @throws MysqliError
     */
    final protected static function createWithFieldsValues(?int $id, array $data, int $creation_date = null): self
    {
        $id === null ?: ($data[self::COL_ID] = $id);
        $data[self::COL_CREATION_DATE] = $creation_date ?? time();
        self::$mysqli->fast_add(static::getDatabaseTableName(), $data);
        return self::requireById($id ?? self::$mysqli->insert_id);
    }

    /**
     * @param int $id
     * @return static
     * @throws MysqliError
     */
    final public static function requireById(int $id): self
    {
        return static::makeById($id);
    }

    /**
     * @param int $id
     * @return static|null
     * @throws MysqliError
     */
    final public static function makeById(int $id): ?self
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
     * @param array $data
     * @throws AlreadyExists
     * @throws MysqliError
     */
    protected static function requireUnique(array $data): void
    {
        if (self::$mysqli->fast_check(static::getDatabaseTableName(), $data)) {
            throw new AlreadyExists(static::class, $data);
        }
    }

    /**
     * @return int
     */
    final public function getCreationDate(): int
    {
        return $this->creation_date;
    }

    /**
     * @param int $creation_date
     * @return $this
     */
    final public function setCreationDate(int $creation_date): self
    {
        $this->setFieldValue(self::COL_CREATION_DATE, $this->creation_date, $creation_date);
        return $this;
    }

    /**
     * @param string $field_name
     * @param $variable
     * @param $new_value
     * @return $this
     */
    final public function setFieldValue(string $field_name, &$variable, $new_value): self
    {
        if ($new_value instanceof self) {
            $new_value = $new_value->getId();
        }

        if ($variable !== $new_value) {
            $variable = $new_value;
            $this->edited_fields[$field_name] = $new_value;
        }

        return $this;
    }

    /**
     * @return DateTime
     */
    final public function getCreationDateTime(): DateTime
    {
        $result = new DateTime("@{$this->creation_date}", self::$timezone);
        if (self::$timezone !== null) {
            $result->setTimezone(self::$timezone);
        }
        return $result;
    }

    /**
     * @return void
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
     * @return $this
     * @throws MysqliError
     */
    public function save(): self
    {
        if (count($this->edited_fields)) {
            self::$mysqli->fast_update(static::getDatabaseTableName(), $this->edited_fields,
                [self::COL_ID => $this->id], 1);
            $this->edited_fields = [];
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getEditedFields(): array
    {
        return $this->edited_fields;
    }
}
