<?php

declare(strict_types=1);

namespace Pogo;

interface JobInterface
{
    /**
     * @param  array<mixed>  $args
     */
    public function handle(array $args): mixed;
}
