<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Dto;

final readonly class PageSpeedResult
{
    /**
     * @param array<string, int|null> $scores
     * @param array<string, string|null> $scoreRatings
     * @param list<LighthouseMetric> $labMetrics
     * @param list<FieldMetric> $fieldMetrics
     * @param list<LighthouseAudit> $opportunities
     * @param list<LighthouseAudit> $diagnostics
     * @param list<LighthouseAudit> $passedAudits
     */
    public function __construct(
        public string $analyzedUrl,
        public string $strategy,
        public string $engine,
        public \DateTimeImmutable $fetchedAt,
        public array $scores,
        public array $scoreRatings,
        public array $labMetrics,
        public array $fieldMetrics,
        public array $opportunities,
        public array $diagnostics,
        public array $passedAudits,
    ) {}
}
