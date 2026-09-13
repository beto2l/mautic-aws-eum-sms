<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Tests\Unit;

use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\AwsGatewayInterface;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\MediaPreparer;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendBlockedException;
use PHPUnit\Framework\TestCase;

final class MediaPreparerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/mautic-mms-'.bin2hex(random_bytes(6));
        mkdir($this->root.'/media/images', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/media/images/*') ?: [] as $path) {
            unlink($path);
        }
        @rmdir($this->root.'/media/images');
        @rmdir($this->root.'/media');
        @rmdir($this->root);
    }

    public function testInvalidMediaTypeIsRejectedBeforeAwsUpload(): void
    {
        file_put_contents($this->root.'/media/images/not-image.txt', 'not an image');
        $gateway = $this->createMock(AwsGatewayInterface::class);
        $gateway->expects(self::never())->method('uploadMedia');

        $this->expectException(SendBlockedException::class);
        $this->expectExceptionMessage('JPEG, PNG, or GIF');

        $this->preparer($gateway)->prepare(
            ['https://mautic.example/media/images/not-image.txt'],
            $this->settings(),
        );
    }

    public function testLocalPngIsUploadedToConfiguredBucket(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        self::assertIsString($png);
        file_put_contents($this->root.'/media/images/promo.png', $png);

        $gateway = $this->createMock(AwsGatewayInterface::class);
        $gateway->expects(self::once())->method('mediaExists')->willReturn(false);
        $gateway->expects(self::once())->method('uploadMedia')->with(
            'us-west-2',
            'example-bucket',
            self::matchesRegularExpression('#^mautic-mms/[a-f0-9]{64}\.png$#'),
            (string) realpath($this->root.'/media/images/promo.png'),
            'image/png',
        );

        $uri = $this->preparer($gateway)->prepare(
            ['https://mautic.example/media/images/promo.png'],
            $this->settings(),
        );

        self::assertMatchesRegularExpression('#^s3://example-bucket/mautic-mms/[a-f0-9]{64}\.png$#', $uri);
    }

    private function preparer(AwsGatewayInterface $gateway): MediaPreparer
    {
        $paths = $this->createMock(PathsHelper::class);
        $paths->method('getLocalRoot')->willReturn($this->root);

        return new MediaPreparer($paths, $gateway);
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        return [
            'region'         => 'us-west-2',
            'mms_s3_bucket'  => 'example-bucket',
            'mms_s3_prefix'  => 'mautic-mms',
        ];
    }
}
