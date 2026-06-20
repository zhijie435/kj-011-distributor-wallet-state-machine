<?php

namespace App\Exceptions;

class BusinessException extends BaseException
{
    protected int $httpCode = 422;

    protected string $errorCode = 'BUSINESS_ERROR';

    public function __construct(string $message, array $details = [], int $httpCode = 422)
    {
        parent::__construct($message);
        $this->details = $details;
        $this->httpCode = $httpCode;
    }

    public static function withCode(
        string $message,
        string $errorCode = 'BUSINESS_ERROR',
        array $details = [],
        int $httpCode = 422,
    ): self {
        $instance = new self($message, $details, $httpCode);
        $instance->errorCode = $errorCode;

        return $instance;
    }
}
