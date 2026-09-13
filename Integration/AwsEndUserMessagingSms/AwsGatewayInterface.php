<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

interface AwsGatewayInterface
{
    /** @param array<string, mixed> $payload */
    public function sendText(string $region, array $payload): string;

    /** @param array<string, mixed> $payload */
    public function sendMedia(string $region, array $payload): string;

    public function uploadMedia(
        string $region,
        string $bucket,
        string $key,
        string $path,
        string $contentType,
    ): void;
}
