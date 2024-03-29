<?php

declare(strict_types=1);

namespace Kuvardin\FastMysqli\Exceptions;

use Exception;
use Throwable;

/**
 * Class AlreadyExists
 *
 * @author Maxim Kuvardin <maxim@kuvard.in>
 */
class AlreadyExists extends Exception
{
    /**
     * @var string|null
     */
    protected ?string $class_name;

    /**
     * @var array|null
     */
    protected ?array $fields_values;

    /**
     * @param string|null $class_name
     * @param array|null $fields_values
     * @param Throwable|null $previous
     */
    public function __construct(string $class_name = null, array $fields_values = null, Throwable $previous = null)
    {
        $this->class_name = $class_name;
        $this->fields_values = $fields_values;
        $message = "$class_name already exists";
        if ($fields_values !== null) {
            $message .= "$class_name already exists with fields values " . print_r($fields_values, true);
        }
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->class_name;
    }

    /**
     * @return array|null
     */
    public function getFieldsValues(): ?array
    {
        return $this->fields_values;
    }
}
