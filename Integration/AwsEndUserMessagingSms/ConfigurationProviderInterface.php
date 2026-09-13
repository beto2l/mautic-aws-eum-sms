<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

interface ConfigurationProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array;
}
