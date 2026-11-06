<?php

declare(strict_types=1);

namespace Bilge\Tracing\Performance;

final class NeverSampler implements Sampler
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function shouldSample(array $context): bool
    {
        return false;
    }
}
