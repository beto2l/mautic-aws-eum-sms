<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

interface MediaPreparerInterface
{
    /**
     * @param array<mixed>         $media
     * @param array<string, mixed> $settings
     */
    public function prepare(array $media, array $settings): string;
}
