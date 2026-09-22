<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Service;

use Anubit\LighthouseInsight\Dto\FieldMetric;
use Anubit\LighthouseInsight\Dto\LighthouseAudit;
use Anubit\LighthouseInsight\Dto\LighthouseMetric;
use Anubit\LighthouseInsight\Dto\PageSpeedResult;
use Anubit\LighthouseInsight\Exception\PageSpeedApiException;
use Anubit\LighthouseInsight\Utility\Translator;

final readonly class PageSpeedResponseParser
{
    private const LAB_METRICS = [
        'largest-contentful-paint' => 'Largest Contentful Paint',
        'cumulative-layout-shift' => 'Cumulative Layout Shift',
        'total-blocking-time' => 'Total Blocking Time',
        'first-contentful-paint' => 'First Contentful Paint',
        'speed-index' => 'Speed Index',
    ];

    private const FIELD_METRICS = [
        'FIRST_CONTENTFUL_PAINT_MS' => ['First Contentful Paint', 'ms'],
        'LARGEST_CONTENTFUL_PAINT_MS' => ['Largest Contentful Paint', 'ms'],
        'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['Cumulative Layout Shift', ''],
        'INTERACTION_TO_NEXT_PAINT' => ['Interaction to Next Paint', 'ms'],
        'EXPERIMENTAL_INTERACTION_TO_NEXT_PAINT' => ['Interaction to Next Paint', 'ms'],
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload, string $strategy, string $engine = 'google_api'): PageSpeedResult
    {
        $lighthouse = $payload['lighthouseResult'] ?? $payload;
        if (!is_array($lighthouse)) {
            throw new PageSpeedApiException(Translator::translate('error.parserMalformedResponse'));
        }

        $audits = is_array($lighthouse['audits'] ?? null) ? $lighthouse['audits'] : [];
        $categories = is_array($lighthouse['categories'] ?? null) ? $lighthouse['categories'] : [];
        $auditGroupsById = $this->buildAuditGroupMap($categories);

        $scores = [
            'performance' => $this->extractScore($categories, 'performance'),
            'accessibility' => $this->extractScore($categories, 'accessibility'),
            'bestPractices' => $this->extractScore($categories, 'best-practices'),
            'seo' => $this->extractScore($categories, 'seo'),
        ];

        return new PageSpeedResult(
            analyzedUrl: (string)($lighthouse['finalDisplayedUrl'] ?? $lighthouse['finalUrl'] ?? $payload['id'] ?? ''),
            strategy: $strategy,
            engine: $engine,
            fetchedAt: new \DateTimeImmutable(),
            scores: $scores,
            scoreRatings: array_map($this->scoreRating(...), $scores),
            labMetrics: $this->extractLabMetrics($audits),
            fieldMetrics: $this->extractFieldMetrics($payload),
            opportunities: $this->extractAuditsByGroup($audits, $auditGroupsById, 'load-opportunities'),
            diagnostics: $this->extractAuditsByGroup($audits, $auditGroupsById, 'diagnostics'),
            passedAudits: $this->extractPassedAudits($audits),
        );
    }

    /**
     * @param array<string, mixed> $categories
     */
    private function extractScore(array $categories, string $id): ?int
    {
        $score = $categories[$id]['score'] ?? null;
        if (!is_int($score) && !is_float($score)) {
            return null;
        }

        return (int)round($score * 100);
    }

    /**
     * Lighthouse's own thresholds for the 0-100 category score: >=90 pass (green),
     * >=50 average (orange), below that fail (red).
     */
    private function scoreRating(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 90 => 'good',
            $score >= 50 => 'average',
            default => 'poor',
        };
    }

    /**
     * @param array<string, mixed> $audits
     * @return list<LighthouseMetric>
     */
    private function extractLabMetrics(array $audits): array
    {
        $metrics = [];
        foreach (self::LAB_METRICS as $id => $fallbackTitle) {
            $audit = $audits[$id] ?? null;
            if (!is_array($audit)) {
                continue;
            }

            $metrics[] = new LighthouseMetric(
                id: $id,
                title: (string)($audit['title'] ?? $fallbackTitle),
                displayValue: isset($audit['displayValue']) ? (string)$audit['displayValue'] : null,
                numericValue: $this->toFloatOrNull($audit['numericValue'] ?? null),
                numericUnit: isset($audit['numericUnit']) ? (string)$audit['numericUnit'] : null,
            );
        }

        return $metrics;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<FieldMetric>
     */
    private function extractFieldMetrics(array $payload): array
    {
        $metrics = [];
        foreach (['loadingExperience', 'originLoadingExperience'] as $sourceKey) {
            $source = $payload[$sourceKey]['metrics'] ?? null;
            if (!is_array($source)) {
                continue;
            }

            foreach (self::FIELD_METRICS as $id => [$title, $unit]) {
                $metric = $source[$id] ?? null;
                if (!is_array($metric)) {
                    continue;
                }

                $metrics[] = new FieldMetric(
                    id: $id,
                    title: $sourceKey === 'originLoadingExperience' ? $title . ' (origin)' : $title,
                    category: isset($metric['category']) ? (string)$metric['category'] : null,
                    percentile: $this->toFloatOrNull($metric['percentile'] ?? null),
                    unit: $unit,
                );
            }
        }

        return $metrics;
    }

    /**
     * Lighthouse does not put the group on the audit itself: it's only listed in each
     * category's `auditRefs`, mapping an audit id to the group it belongs to (e.g.
     * "load-opportunities", "diagnostics"). Build an id => group lookup from that.
     *
     * @param array<string, mixed> $categories
     * @return array<string, string>
     */
    private function buildAuditGroupMap(array $categories): array
    {
        $groupsById = [];
        foreach ($categories as $category) {
            if (!is_array($category) || !is_array($category['auditRefs'] ?? null)) {
                continue;
            }
            foreach ($category['auditRefs'] as $auditRef) {
                if (is_array($auditRef) && isset($auditRef['id'], $auditRef['group'])) {
                    $groupsById[(string)$auditRef['id']] = (string)$auditRef['group'];
                }
            }
        }

        return $groupsById;
    }

    /**
     * @param array<string, mixed> $audits
     * @param array<string, string> $auditGroupsById
     * @return list<LighthouseAudit>
     */
    private function extractAuditsByGroup(array $audits, array $auditGroupsById, string $group): array
    {
        $items = [];
        foreach ($audits as $id => $audit) {
            if (!is_array($audit) || ($auditGroupsById[(string)$id] ?? null) !== $group) {
                continue;
            }
            if (($audit['score'] ?? null) === 1) {
                continue;
            }
            $items[] = $this->buildAudit((string)$id, $audit);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $audits
     * @return list<LighthouseAudit>
     */
    private function extractPassedAudits(array $audits): array
    {
        $items = [];
        foreach ($audits as $id => $audit) {
            if (!is_array($audit) || ($audit['score'] ?? null) !== 1) {
                continue;
            }
            $items[] = $this->buildAudit((string)$id, $audit);
        }

        return array_slice($items, 0, 20);
    }

    /**
     * @param array<string, mixed> $audit
     */
    private function buildAudit(string $id, array $audit): LighthouseAudit
    {
        return new LighthouseAudit(
            id: $id,
            title: (string)($audit['title'] ?? $id),
            description: isset($audit['description']) ? (string)$audit['description'] : null,
            score: $this->toFloatOrNull($audit['score'] ?? null),
            displayValue: isset($audit['displayValue']) ? (string)$audit['displayValue'] : null,
            numericValue: $this->toFloatOrNull($audit['numericValue'] ?? null),
            numericUnit: isset($audit['numericUnit']) ? (string)$audit['numericUnit'] : null,
            savings: $this->extractSavings($audit),
        );
    }

    /**
     * @param array<string, mixed> $audit
     */
    private function extractSavings(array $audit): ?string
    {
        $details = $audit['details'] ?? null;
        if (!is_array($details)) {
            return null;
        }

        $overallSavingsMs = $this->toFloatOrNull($details['overallSavingsMs'] ?? null);
        if ($overallSavingsMs !== null && $overallSavingsMs > 0) {
            return sprintf('Potential savings: %d ms', (int)round($overallSavingsMs));
        }

        $overallSavingsBytes = $this->toFloatOrNull($details['overallSavingsBytes'] ?? null);
        if ($overallSavingsBytes !== null && $overallSavingsBytes > 0) {
            return sprintf('Potential savings: %s', $this->formatBytes($overallSavingsBytes));
        }

        return null;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        return null;
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.1f MB', $bytes / 1048576);
        }

        return sprintf('%d KB', (int)round($bytes / 1024));
    }
}
