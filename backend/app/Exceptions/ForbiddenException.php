<?php

namespace App\Exceptions;

class ForbiddenException extends BaseException
{
    protected int $httpCode = 403;

    protected string $errorCode = 'FORBIDDEN';

    public function __construct(string $message = '没有执行该操作的权限', array $details = [])
    {
        parent::__construct($message);
        $this->details = $details;
    }
}
