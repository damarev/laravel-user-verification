<?php

namespace Jrean\UserVerification\Exceptions;

use Exception;

class UserAlreadyValidatedException extends Exception
{
    /**
     * The exception description.
     *
     * @var string
     */
    protected $message = 'User already validated.';
}
