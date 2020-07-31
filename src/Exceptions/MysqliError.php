<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli\Exceptions;

use Exception;
use Throwable;

/**
 * Class MysqliError
 *
 * @package Kuvardin\FastMysqli\Exceptions
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class MysqliError extends Exception
{
    /**
     * @var string
     */
    protected string $sqlstate;

    /**
     * @var string|null
     */
    protected ?string $query;

    /**
     * MysqliError constructor.
     *
     * @param int $code
     * @param string $sqlstate
     * @param string $message
     * @param string|null $query
     * @param Throwable|null $previous
     */
    public function __construct(int $code, string $sqlstate, string $message, ?string $query,
        Throwable $previous = null)
    {
        $this->sqlstate = $sqlstate;
        $this->query = $query;
        $message = "#$code ($sqlstate): $message";
        if ($query !== null) {
            $message .= " in query $query";
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getSqlstate(): string
    {
        return $this->sqlstate;
    }

    /**
     * @return string|null
     */
    public function getQuery(): ?string
    {
        return $this->query;
    }
}
