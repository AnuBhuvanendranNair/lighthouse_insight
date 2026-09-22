<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Tests\Unit\Service;

use Anubit\LighthouseInsight\Exception\PageSpeedApiException;
use Anubit\LighthouseInsight\Service\PageSpeedResponseParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageSpeedResponseParserTest extends TestCase
{
    #[Test]
    public function convertsScoresAndSeparatesFieldAndLabMetrics(): void
    {
        $result = (new PageSpeedResponseParser())->parse($this->loadFixture('pagespeed-success.json'), 'mobile');

        self::assertSame(82, $result->scores['performance']);
        self::assertSame(96, $result->scores['accessibility']);
        self::assertSame(100, $result->scores['bestPractices']);
        self::assertSame(91, $result->scores['seo']);
        self::assertSame('Total Blocking Time', $result->labMetrics[1]->title);
        self::assertSame('Interaction to Next Paint', $result->fieldMetrics[0]->title);
        self::assertCount(1, $result->opportunities);
        self::assertSame('unused-javascript', $result->opportunities[0]->id);
        self::assertCount(1, $result->diagnostics);
        self::assertSame('dom-size', $result->diagnostics[0]->id);
    }

    #[Test]
    public function toleratesMissingCategoriesAndAudits(): void
    {
        $result = (new PageSpeedResponseParser())->parse([
            'lighthouseResult' => [
                'categories' => [],
                'audits' => [],
            ],
        ], 'desktop');

        self::assertNull($result->scores['performance']);
        self::assertSame([], $result->labMetrics);
        self::assertSame([], $result->opportunities);
    }

    #[Test]
    public function rejectsMalformedPayload(): void
    {
        $this->expectException(PageSpeedApiException::class);

        (new PageSpeedResponseParser())->parse([], 'mobile');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $fileName): array
    {
        $json = file_get_contents(__DIR__ . '/../../Fixtures/' . $fileName);
        self::assertIsString($json);

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
