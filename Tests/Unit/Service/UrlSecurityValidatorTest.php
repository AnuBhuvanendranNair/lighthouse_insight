<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Tests\Unit\Service;

use Anubit\LighthouseInsight\Service\UrlSecurityValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlSecurityValidatorTest extends TestCase
{
    #[Test]
    public function rejectsLocalAndPrivateTargets(): void
    {
        $validator = new UrlSecurityValidator();

        self::assertFalse($validator->isAllowedPublicHttpUrl('http://localhost/'));
        self::assertFalse($validator->isAllowedPublicHttpUrl('http://127.0.0.1/'));
        self::assertFalse($validator->isAllowedPublicHttpUrl('http://10.0.0.1/'));
        self::assertFalse($validator->isAllowedPublicHttpUrl('file:///etc/passwd'));
    }

    #[Test]
    public function allowsPublicHttpTargets(): void
    {
        self::assertTrue((new UrlSecurityValidator())->isAllowedPublicHttpUrl('https://8.8.8.8/'));
    }
}
