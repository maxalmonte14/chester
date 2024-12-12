<?php

declare(strict_types=1);

namespace Chester\Exceptions;

use Exception;

final class UnableToFetchLinksException extends Exception
{
    /**
     * @var string
     */
    protected $message = 'Unable to retrieve links';

    public function __construct()
    {
        parent::__construct($this->message);
    }
}