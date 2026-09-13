<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\SmsBundle\Sms\MMSTransportInterface;
use Mautic\SmsBundle\Sms\TransportInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\AwsRequestException;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendBlockedException;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendPolicyInterface;
use Psr\Log\LoggerInterface;

final class Transport implements TransportInterface, MMSTransportInterface
{
    public function __construct(
        private readonly ConfigurationProviderInterface $configuration,
        private readonly SendPolicyInterface $sendPolicy,
        private readonly MediaPreparerInterface $mediaPreparer,
        private readonly AwsGatewayInterface $gateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return bool|string
     */
    public function sendSms(Lead $lead, $content, mixed $stat = null)
    {
        return $this->sendMessage($lead, (string) $content, [], false);
    }

    /** @param array<mixed> $media */
    public function sendMms(Lead $lead, string $content, array $media): bool|string
    {
        return $this->sendMessage($lead, $content, $media, true);
    }

    /**
     * @param array<mixed> $media
     *
     * @return bool|string
     */
    private function sendMessage(Lead $lead, string $content, array $media, bool $isMms)
    {
        $rateLimitAcquired = false;
        $channel           = $isMms ? 'MMS' : 'SMS';

        try {
            $settings = $this->configuration->get();
            $phone    = $isMms
                ? $this->sendPolicy->assertCanSendMms($lead, $content, $settings)
                : $this->sendPolicy->assertCanSend($lead, $content, $settings);
            $mediaUri = $isMms ? $this->mediaPreparer->prepare($media, $settings) : null;
            $this->sendPolicy->acquireRateLimit((int) $settings['per_minute_limit']);
            $rateLimitAcquired = true;

            if (!$isMms) {
                $messageId = $this->gateway->sendText((string) $settings['region'], [
                    'DestinationPhoneNumber' => $phone,
                    'OriginationIdentity'     => $settings['origination_identity'],
                    'MessageBody'             => $content,
                    'MessageType'             => $settings['message_type'],
                    'ConfigurationSetName'    => $settings['configuration_set_name'],
                ]);
            } else {
                $payload  = [
                    'DestinationPhoneNumber' => $phone,
                    'OriginationIdentity'     => $settings['origination_identity'],
                    'ConfigurationSetName'    => $settings['configuration_set_name'],
                    'MediaUrls'               => [(string) $mediaUri],
                    'Context'                 => [
                        'source'       => 'mautic',
                        'content_type' => 'mms',
                    ],
                ];
                if ('' !== trim($content)) {
                    $payload['MessageBody'] = $content;
                }

                $messageId = $this->gateway->sendMedia((string) $settings['region'], $payload);
            }

            $this->logger->info(sprintf('AWS EUM %s accepted by AWS.', $channel), [
                'channel'    => strtolower($channel),
                'message_id' => $messageId,
            ]);

            return true;
        } catch (AwsRequestException $exception) {
            $this->logger->error(sprintf('AWS EUM %s delivery failed.', $channel), [
                'channel'  => strtolower($channel),
                'aws_code' => $exception->getAwsCode(),
                'aws_type' => $exception->getAwsType(),
            ]);

            return sprintf('AWS End User Messaging rejected the %s request.', $channel);
        } catch (SendBlockedException|\RuntimeException $exception) {
            $this->logger->warning(sprintf('AWS EUM %s delivery blocked by configuration or policy.', $channel), [
                'channel' => strtolower($channel),
                'reason'  => $exception->getMessage(),
            ]);

            return $exception->getMessage();
        } finally {
            if ($rateLimitAcquired) {
                $this->sendPolicy->releaseRateLimit();
            }
        }
    }
}
