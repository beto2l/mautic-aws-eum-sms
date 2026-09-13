<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendBlockedException;

final class MediaPreparer implements MediaPreparerInterface
{
    private const MAX_IMAGE_BYTES = 2_000_000;

    /** @var array<string, string> */
    private array $prepared = [];

    public function __construct(
        private readonly PathsHelper $pathsHelper,
        private readonly AwsGatewayInterface $gateway,
    ) {
    }

    public function prepare(array $media, array $settings): string
    {
        if (1 !== count($media) || !is_string(reset($media))) {
            throw new SendBlockedException('AWS MMS requires exactly one image.');
        }

        $bucket = trim((string) ($settings['mms_s3_bucket'] ?? ''));
        $prefix = trim((string) ($settings['mms_s3_prefix'] ?? 'mautic-mms'), '/');
        $region = (string) ($settings['region'] ?? '');
        $url    = trim((string) reset($media));

        $this->assertBucket($bucket);
        $this->assertPrefix($prefix);

        $path = $this->resolveLocalPath($url);
        $size = filesize($path);
        if (false === $size || $size < 1 || $size > self::MAX_IMAGE_BYTES) {
            throw new SendBlockedException('MMS image must be between 1 byte and 2 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extensions = [
            'image/gif'  => 'gif',
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new SendBlockedException('MMS media must be a JPEG, PNG, or GIF image.');
        }

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new SendBlockedException('MMS image could not be read safely.');
        }

        $cacheKey = $bucket.'|'.$prefix.'|'.$hash;
        if (isset($this->prepared[$cacheKey])) {
            return $this->prepared[$cacheKey];
        }

        $key = ('' !== $prefix ? $prefix.'/' : '').$hash.'.'.$extensions[$mime];
        $this->gateway->uploadMedia($region, $bucket, $key, $path, $mime);

        return $this->prepared[$cacheKey] = sprintf('s3://%s/%s', $bucket, $key);
    }

    private function resolveLocalPath(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['path'])) {
            throw new SendBlockedException('MMS image must be a local Mautic media URL.');
        }

        $urlPath = rawurldecode((string) $parts['path']);
        if (str_contains($urlPath, "\0")) {
            throw new SendBlockedException('MMS image path is invalid.');
        }

        $root = realpath($this->pathsHelper->getLocalRoot());
        $path = false === $root ? false : realpath($root.'/'.ltrim($urlPath, '/'));
        if (false === $root || false === $path || !is_file($path) || !is_readable($path)) {
            throw new SendBlockedException('MMS image must exist in the local Mautic media library.');
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $rootPrefix)) {
            throw new SendBlockedException('MMS image is outside the Mautic installation.');
        }

        return $path;
    }

    private function assertBucket(string $bucket): void
    {
        if (strlen($bucket) < 3 || strlen($bucket) > 63 || !preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $bucket)) {
            throw new SendBlockedException('A valid MMS S3 bucket is required.');
        }
    }

    private function assertPrefix(string $prefix): void
    {
        if ('' === $prefix || strlen($prefix) > 128 || !preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#', $prefix) || str_contains($prefix, '..')) {
            throw new SendBlockedException('MMS S3 prefix is invalid.');
        }
    }
}
