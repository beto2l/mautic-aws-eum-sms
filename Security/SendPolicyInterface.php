<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Security;

use Mautic\LeadBundle\Entity\Lead;

interface SendPolicyInterface
{
    /** @param array<string, mixed> $settings */
    public function assertCanSend(Lead $lead, string $content, array $settings): string;

    /** @param array<string, mixed> $settings */
    public function assertCanSendMms(Lead $lead, string $content, array $settings): string;

    public function acquireRateLimit(int $limit): void;

    public function releaseRateLimit(): void;
}
