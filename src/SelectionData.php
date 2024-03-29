<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli;

use Error;

/**
 * Class SelectionData
 *
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class SelectionData
{
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    /**
     * @var int|null
     */
    public ?int $total_amount = null;

    /**
     * @var int|null
     */
    protected ?int $limit = null;

    /**
     * @var int|null
     */
    protected ?int $offset = null;

    /**
     * @var string|null
     */
    protected ?string $ord = null;

    /**
     * @var string|null
     */
    protected ?string $sort = null;

    /**
     * @var int|null
     */
    protected ?int $limit_max = null;

    /**
     * @var string[]|null
     */
    protected ?array $ord_variants = null;

    /**
     * SelectionData constructor.
     *
     * @param int|null $limit_max
     * @param array|null $ord_variants
     */
    public function __construct(int $limit_max = null, array $ord_variants = null)
    {
        $this->limit_max = $limit_max;
        $this->limit = $limit_max ?? null;
        $this->ord_variants = $ord_variants;
    }

    /**
     * @param string $sort
     * @return bool
     */
    public static function checkSort(string $sort): bool
    {
        return $sort === self::SORT_ASC ||
            $sort === self::SORT_DESC;
    }

    /**
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $ord
     * @param string|null $sort
     * @return static
     */
    public static function make(int $limit = null, int $offset = null, string $ord = null, string $sort = null): self
    {
        return (new SelectionData(null, $ord === null ? null : [$ord]))
            ->setLimit($limit)
            ->setOffset($offset)
            ->setOrd($ord)
            ->setSort($sort);
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * @param int|null $limit
     * @param bool $no_limit
     * @return $this
     */
    public function setLimit(?int $limit, bool $no_limit = false): self
    {
        if ($limit !== null && $limit <= 0) {
            throw new Error('Limit must be greater than zero');
        }

        if ($limit !== null && !$no_limit && $this->limit_max !== null && $limit > $this->limit_max) {
            $limit = $this->limit_max;
        }

        $this->limit = $limit;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @param int|null $offset
     * @return $this
     */
    public function setOffset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getOrd(): ?string
    {
        if ($this->ord !== null) {
            return array_key_exists($this->ord, $this->ord_variants)
                ? $this->ord_variants[$this->ord]
                : $this->ord;
        }

        return null;
    }

    /**
     * @param string|null $ord
     * @return $this
     */
    public function setOrd(?string $ord): self
    {
        if ($ord !== null && $this->ord_variants !== null && !array_key_exists($ord, $this->ord_variants)
            && !in_array($ord, $this->ord_variants, true)) {
            throw new Error("Unknown ord field: $ord");
        }
        $this->ord = $ord;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSort(): ?string
    {
        return $this->sort;
    }

    /**
     * @param string|null $sort
     * @return $this
     * @throws Error
     */
    public function setSort(?string $sort): self
    {
        if ($sort !== null && !self::checkSort($sort)) {
            throw new Error("Unknown sort: $sort (must be self::SORT_*)");
        }
        $this->sort = $sort;
        return $this;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        if ($this->limit === null) {
            throw new Error('Limit must be not null');
        }

        if ($this->total_amount === null) {
            throw new Error('Total amount must not be null');
        }

        if (empty($this->offset)) {
            return 1;
        }

        return (int)($this->offset / $this->limit) + 1;
    }

    /**
     * @return int
     */
    public function getPagesNumber(): int
    {
        if ($this->limit === null) {
            throw new Error('Limit must be not null');
        }

        if ($this->total_amount === null) {
            throw new Error('Total amount must not be null');
        }

        $result = (int)($this->total_amount/$this->limit);
        if ($this->total_amount % $this->limit) {
            $result++;
        }

        return $result;
    }

    /**
     * @param int $page
     * @return int
     */
    public function setPage(int $page): int
    {
        if ($this->limit === null) {
            throw new Error('Limit must be not null');
        }

        if ($this->total_amount === null) {
            throw new Error('Total amount must not be null');
        }

        if ($this->total_amount === 0) {
            $this->offset = 0;
        } else {
            $offset = $this->limit * ($page - 1);
            if ($offset >= $this->total_amount) {
                $page = (int)($this->total_amount / $this->limit);
                $this->offset = $this->limit * ($page - 1);
            } else {
                $this->offset = $offset;
            }
        }

        return $page;
    }
}
