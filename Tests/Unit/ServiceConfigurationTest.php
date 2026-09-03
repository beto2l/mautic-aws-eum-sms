<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Tests\Unit;

use Mautic\CoreBundle\Helper\EncryptionHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;

final class ServiceConfigurationTest extends TestCase
{
    public function testEncryptionHelperUsesTheMauticCoreService(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2).'/Config/config.php';
        $services = $config['services'];
        $integrations = $services['integrations'];
        $integration = $integrations['mautic.integration.awsendusermessagingsms'];
        $arguments = $integration['arguments'];

        self::assertInstanceOf(Reference::class, $arguments[7]);
        self::assertSame(EncryptionHelper::class, (string) $arguments[7]);
        self::assertArrayNotHasKey('mautic.helper.encryption', $services['other']);
    }
}
