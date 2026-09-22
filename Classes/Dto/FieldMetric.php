<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Dto;

final readonly class FieldMetric
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $category,
        public ?float $percentile,
        public ?string $unit,
    ) {}
}
