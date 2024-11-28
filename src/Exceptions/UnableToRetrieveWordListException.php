<?php

declare(strict_types=1);

namespace Chester\Exceptions;

use Exception;

final class UnableToRetrieveWordListException extends Exception
{
    protected $message = 'Unable to retrieve word list';

    public function __construct()
    {
        parent::__construct($this->message);
    }
}