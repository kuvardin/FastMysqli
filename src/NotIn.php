<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli;

/**
 * Class NotIn
 *
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class NotIn
{
    /**
     * @var array|null
     */
    protected ?array $values;

    /**
     * @param array|null $values
     */
    public function __construct(?array $values)
    {
        $this->values = $values;
    }

    /**
     * @return array|null
     */
    public function getValues(): ?array
    {
        return $this->values;
    }
}
