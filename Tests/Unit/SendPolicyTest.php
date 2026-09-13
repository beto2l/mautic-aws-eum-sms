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

    public function testMmsDisabledConfigurationIsBlocked(): void
    {
        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('MMS is disabled');

        $this->policy()->assertCanSendMms(
            $this->leadWithPhone('2125550123'),
            'MMS message',
            $this->settings(),
        );
    }

    public function testMmsIneligibleIdentityIsBlocked(): void
    {
        $settings = $this->settings([
            'mms_enabled'                    => true,
            'mms_campaign_approved'          => true,
            'mms_identity_capable'           => false,
            'aws_managed_opt_outs_confirmed' => true,
        ]);

        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('MMS-capable');

        $this->policy()->assertCanSendMms($this->leadWithPhone('2125550123'), 'MMS message', $settings);
    }

    public function testMmsWithoutAwsManagedOptOutsIsBlocked(): void
    {
        $settings = $this->settings([
            'mms_enabled'                    => true,
            'mms_campaign_approved'          => true,
            'mms_identity_capable'           => true,
            'aws_managed_opt_outs_confirmed' => false,
        ]);

        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('opt-outs');

        $this->policy()->assertCanSendMms($this->leadWithPhone('2125550123'), 'MMS message', $settings);
    }

    public function testMmsCanaryPassesWhenEverySafetyGateIsConfirmed(): void
    {
        $settings = $this->settings([
            'delivery_mode'                  => 'canary',
            'test_phone_number'              => '+12125550123',
            'mms_enabled'                    => true,
            'mms_campaign_approved'          => true,
            'mms_identity_capable'           => true,
            'aws_managed_opt_outs_confirmed' => true,
        ]);

        self::assertSame(
            '+12125550123',
            $this->policy()->assertCanSendMms($this->leadWithPhone('2125550123'), 'MMS message', $settings),
        );
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
            'mms_enabled'                => false,
            'mms_campaign_approved'      => false,
            'mms_identity_capable'       => false,
            'aws_managed_opt_outs_confirmed' => false,
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
