<?php

declare(strict_types=1);

namespace Chester\Exceptions;

final class UnableToFetchLinksException extends \Exception
{
    protected $message = 'Unable to retrieve links';

    public function __construct()
    {
        parent::__construct($this->message);
    }
}