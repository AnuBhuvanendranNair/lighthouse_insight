<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Dto;

final readonly class LighthouseAudit
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public ?float $score,
        public ?string $displayValue,
        public ?float $numericValue,
        public ?string $numericUnit,
        public ?string $savings,
    ) {}

    public function isPassed(): bool
    {
        return $this->score === 1.0;
    }
}
