<?php

declare(strict_types=1);

namespace Anubit\LighthouseInsight\Dto;

final readonly class LocalLighthouseEnvironment
{
    /**
     * @param list<string> $missingRequirements
     * @param list<string> $messages
     */
    public function __construct(
        public bool $available,
        public array $missingRequirements,
        public array $messages,
    ) {}
}
