<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendBlockedException;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendPolicy;
use PHPUnit\Framework\TestCase;

final class SendPolicyTest extends TestCase
{
    public function testLockedModeBlocksDelivery(): void
    {
        $lead = $this->leadWithPhone('2125550123');

        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('delivery is locked');

        $this->policy()->assertCanSend($lead, 'Test message', $this->settings());
    }

    public function testInvalidPhoneIsBlocked(): void
    {
        $lead = $this->leadWithPhone('not-a-phone');

        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('not normalized to E.164');

        $this->policy()->assertCanSend($lead, 'Test message', $this->settings());
    }

    public function testCanaryModeNormalizesTenDigitPhone(): void
    {
        $lead = $this->leadWithPhone('2125550123');
        $settings = $this->settings([
            'delivery_mode'    => 'canary',
            'test_phone_number' => '+12125550123',
        ]);

        self::assertSame('+12125550123', $this->policy()->assertCanSend($lead, 'Test message', $settings));
    }

    public function testEmojiIsBlocked(): void
    {
        $lead = $this->leadWithPhone('2125550123');
        $settings = $this->settings([
            'delivery_mode' => 'canary',
            'test_phone_number' => '+12125550123',
        ]);

        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('contains emoji');

        $this->policy()->assertCanSend($lead, 'Test message 👍', $settings);
    }

    /** @param array<string, mixed> $overrides */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'phone_field'                => 'phone',
            'max_message_characters'     => 480,
            'reject_emoji'               => true,
            'delivery_mode'              => 'locked',
            'test_phone_number'          => '',
            'require_consent'            => false,
            'audience_consent_confirmed' => false,
            'consent_field'              => 'sms_opt_in',
            'allowed_segment_ids'        => [37],
            'daily_limit'                => 10000,
        ], $overrides);
    }

    private function policy(): SendPolicy
    {
        return new SendPolicy($this->createMock(Connection::class));
    }

    private function leadWithPhone(string $phone): Lead
    {
        $lead = $this->createMock(Lead::class);
        $lead->method('getFieldValue')->with('phone')->willReturn($phone);
        $lead->method('getId')->willReturn(1);

        return $lead;
    }
}
