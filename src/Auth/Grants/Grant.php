<?php

namespace Pipedrive\Client\Auth\Grants;

interface Grant
{
    /**
     * @return array<string, string>
     */
    public function parameters(): array;
}
