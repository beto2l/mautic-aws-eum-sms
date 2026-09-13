<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Tests\Unit;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\AwsGatewayInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\ConfigurationProviderInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\MediaPreparerInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\Transport;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendPolicyInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\AwsRequestException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TransportTest extends TestCase
{
    public function testSmsRegressionUsesSendTextMessagePayload(): void
    {
        [$transport, $policy, $media, $gateway] = $this->transport();
        $lead = $this->createMock(Lead::class);

        $policy->expects(self::once())->method('assertCanSend')->willReturn('+12125550123');
        $policy->expects(self::never())->method('assertCanSendMms');
        $policy->expects(self::once())->method('acquireRateLimit')->with(10);
        $policy->expects(self::once())->method('releaseRateLimit');
        $media->expects(self::never())->method('prepare');
        $gateway->expects(self::once())->method('sendText')->with('us-west-2', [
            'DestinationPhoneNumber' => '+12125550123',
            'OriginationIdentity'     => 'example-origin',
            'MessageBody'             => 'Text message',
            'MessageType'             => 'PROMOTIONAL',
            'ConfigurationSetName'    => 'example-config',
        ])->willReturn('sms-message-id');
        $gateway->expects(self::never())->method('sendMedia');

        self::assertTrue($transport->sendSms($lead, 'Text message'));
    }

    public function testValidMmsUsesPreparedS3MediaAndSendMediaMessage(): void
    {
        [$transport, $policy, $media, $gateway] = $this->transport();
        $lead = $this->createMock(Lead::class);

        $policy->expects(self::once())->method('assertCanSendMms')->willReturn('+12125550123');
        $policy->expects(self::once())->method('acquireRateLimit')->with(10);
        $policy->expects(self::once())->method('releaseRateLimit');
        $media->expects(self::once())->method('prepare')
            ->with(['https://mautic.example/media/images/promo.png'], self::isType('array'))
            ->willReturn('s3://example-bucket/mautic-mms/hash.png');
        $gateway->expects(self::once())->method('sendMedia')->with('us-west-2', [
            'DestinationPhoneNumber' => '+12125550123',
            'OriginationIdentity'     => 'example-origin',
            'ConfigurationSetName'    => 'example-config',
            'MediaUrls'               => ['s3://example-bucket/mautic-mms/hash.png'],
            'Context'                 => ['source' => 'mautic', 'content_type' => 'mms'],
            'MessageBody'             => 'Promotion with image',
        ])->willReturn('mms-message-id');
        $gateway->expects(self::never())->method('sendText');

        self::assertTrue($transport->sendMms(
            $lead,
            'Promotion with image',
            ['https://mautic.example/media/images/promo.png'],
        ));
    }

    public function testAwsFailureReturnsGenericMmsErrorAndReleasesRateLimit(): void
    {
        [$transport, $policy, $media, $gateway] = $this->transport();
        $lead = $this->createMock(Lead::class);

        $policy->method('assertCanSendMms')->willReturn('+12125550123');
        $policy->expects(self::once())->method('acquireRateLimit');
        $policy->expects(self::once())->method('releaseRateLimit');
        $media->method('prepare')->willReturn('s3://example-bucket/mautic-mms/hash.png');
        $gateway->method('sendMedia')->willThrowException(
            new AwsRequestException('AWS rejected the request.', 'ValidationException', 'Sender')
        );

        self::assertSame(
            'AWS End User Messaging rejected the MMS request.',
            $transport->sendMms($lead, 'Promotion', ['https://mautic.example/media/images/promo.png']),
        );
    }

    /**
     * @return array{Transport, SendPolicyInterface&\PHPUnit\Framework\MockObject\MockObject, MediaPreparerInterface&\PHPUnit\Framework\MockObject\MockObject, AwsGatewayInterface&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function transport(): array
    {
        $configuration = $this->createMock(ConfigurationProviderInterface::class);
        $policy        = $this->createMock(SendPolicyInterface::class);
        $media         = $this->createMock(MediaPreparerInterface::class);
        $gateway       = $this->createMock(AwsGatewayInterface::class);
        $logger        = $this->createMock(LoggerInterface::class);

        $configuration->method('get')->willReturn([
            'region'                 => 'us-west-2',
            'origination_identity'   => 'example-origin',
            'configuration_set_name' => 'example-config',
            'message_type'           => 'PROMOTIONAL',
            'per_minute_limit'       => 10,
        ]);

        return [new Transport($configuration, $policy, $media, $gateway, $logger), $policy, $media, $gateway];
    }
}
