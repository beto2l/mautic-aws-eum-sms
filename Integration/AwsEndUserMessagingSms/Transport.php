<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

use Aws\Exception\AwsException;
use Aws\PinpointSMSVoiceV2\PinpointSMSVoiceV2Client;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\SmsBundle\Sms\TransportInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendBlockedException;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendPolicy;
use Psr\Log\LoggerInterface;

final class Transport implements TransportInterface
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly SendPolicy $sendPolicy,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool|string
     */
    public function sendSms(Lead $lead, $content, mixed $stat = null)
    {
        try {
            $settings = $this->configuration->get();
            $phone    = $this->sendPolicy->assertCanSend($lead, (string) $content, $settings);

            $payload = [
                'DestinationPhoneNumber' => $phone,
                'OriginationIdentity'     => $settings['origination_identity'],
                'MessageBody'             => (string) $content,
                'MessageType'             => $settings['message_type'],
                'ConfigurationSetName'    => $settings['configuration_set_name'],
            ];

            $response = $this->client((string) $settings['region'])->sendTextMessage($payload);
            $this->logger->info('AWS EUM SMS accepted by AWS.', [
                'contact_id' => $lead->getId(),
                'message_id' => $response->get('MessageId'),
            ]);

            return true;
        } catch (SendBlockedException|\RuntimeException $exception) {
            $this->logger->warning('AWS EUM SMS delivery blocked by configuration or policy.', [
                'contact_id' => $lead->getId(),
                'reason'     => $exception->getMessage(),
            ]);

            return $exception->getMessage();
        } catch (AwsException $exception) {
            $this->logger->error('AWS EUM SMS delivery failed.', [
                'contact_id' => $lead->getId(),
                'aws_code'   => $exception->getAwsErrorCode(),
                'aws_type'   => $exception->getAwsErrorType(),
            ]);

            return 'AWS End User Messaging rejected the SMS request.';
        }
    }

    private function client(string $region): PinpointSMSVoiceV2Client
    {
        return new PinpointSMSVoiceV2Client([
            'version' => 'latest',
            'region'  => $region,
        ]);
    }
}
