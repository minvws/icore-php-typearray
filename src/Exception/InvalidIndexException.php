<?php

declare(strict_types=1);

namespace MinVWS\TypeArray\Exception;

class InvalidIndexException extends \Exception
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Invalid index: "%s"', $path));
    }
}
