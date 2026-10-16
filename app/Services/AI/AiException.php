<?php

namespace App\Services\AI;

class AiException extends \RuntimeException
{
    public function __construct(string $message, public int $status = 502)
    {
        parent::__construct($message);
    }
}
