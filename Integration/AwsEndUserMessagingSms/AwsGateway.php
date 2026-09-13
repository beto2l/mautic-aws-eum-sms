<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

use Aws\Exception\AwsException;
use Aws\PinpointSMSVoiceV2\PinpointSMSVoiceV2Client;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

final class AwsGateway implements AwsGatewayInterface
{
    /** @var array<string, PinpointSMSVoiceV2Client> */
    private array $messagingClients = [];

    /** @var array<string, S3Client> */
    private array $s3Clients = [];

    public function sendText(string $region, array $payload): string
    {
        try {
            return (string) $this->messagingClient($region)->sendTextMessage($payload)->get('MessageId');
        } catch (AwsException $exception) {
            throw $this->wrap($exception);
        }
    }

    public function sendMedia(string $region, array $payload): string
    {
        try {
            return (string) $this->messagingClient($region)->sendMediaMessage($payload)->get('MessageId');
        } catch (AwsException $exception) {
            throw $this->wrap($exception);
        }
    }

    public function mediaExists(string $region, string $bucket, string $key): bool
    {
        try {
            $this->s3Client($region)->headObject(['Bucket' => $bucket, 'Key' => $key]);

            return true;
        } catch (S3Exception $exception) {
            if (404 === $exception->getStatusCode() || 'NotFound' === $exception->getAwsErrorCode()) {
                return false;
            }

            throw $this->wrap($exception);
        }
    }

    public function uploadMedia(
        string $region,
        string $bucket,
        string $key,
        string $path,
        string $contentType,
    ): void {
        try {
            $this->s3Client($region)->putObject([
                'Bucket'               => $bucket,
                'Key'                  => $key,
                'SourceFile'           => $path,
                'ContentType'          => $contentType,
                'ServerSideEncryption' => 'AES256',
            ]);
        } catch (AwsException $exception) {
            throw $this->wrap($exception);
        }
    }

    private function messagingClient(string $region): PinpointSMSVoiceV2Client
    {
        return $this->messagingClients[$region] ??= new PinpointSMSVoiceV2Client([
            'version' => 'latest',
            'region'  => $region,
        ]);
    }

    private function s3Client(string $region): S3Client
    {
        return $this->s3Clients[$region] ??= new S3Client([
            'version' => 'latest',
            'region'  => $region,
        ]);
    }

    private function wrap(AwsException $exception): AwsRequestException
    {
        return new AwsRequestException(
            'AWS rejected the request.',
            $exception->getAwsErrorCode(),
            $exception->getAwsErrorType(),
            $exception,
        );
    }
}
