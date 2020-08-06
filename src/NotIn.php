<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli;

/**
 * Class NotIn
 *
 * @package Kuvardin\FastMysqli
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class NotIn
{
    /**
     * @var array
     */
    protected array $values;

    /**
     * NotIn constructor.
     *
     * @param array $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
