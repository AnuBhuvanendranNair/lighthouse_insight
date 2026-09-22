<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Dto;

final readonly class LighthouseMetric
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $displayValue,
        public ?float $numericValue,
        public ?string $numericUnit,
    ) {}
}
