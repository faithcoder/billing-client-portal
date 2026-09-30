<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PortalException extends HttpException
{
    public function __construct(public readonly string $errorCode, int $status = 503)
    {
        parent::__construct($status, $errorCode);
    }
}
