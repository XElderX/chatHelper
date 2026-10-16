<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    public function generate(string $task, array $input, string $mode): array;
}
