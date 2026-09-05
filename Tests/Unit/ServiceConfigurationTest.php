<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Tests\Unit;

use Mautic\CoreBundle\DependencyInjection\Compiler\ServicePass;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

final class ServiceConfigurationTest extends TestCase
{
    public function testIntegrationDescriptionUsesAccountNeutralSupportText(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Integration/AwsEndUserMessagingSmsIntegration.php');

        self::assertIsString($source);
        self::assertStringContainsString('email address linked to your account', $source);
        self::assertStringNotContainsString('contact@opin-x.com', $source);
    }

    public function testEncryptionHelperCompilesThroughMauticServicePass(): void
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2).'/Config/config.php';
        $container = new ContainerBuilder();
        $container->setParameter('mautic.bundles', []);
        $container->setParameter('mautic.plugin.bundles', [['config' => $config]]);

        foreach ([
            'event_dispatcher',
            'mautic.helper.cache_storage',
            'doctrine.orm.entity_manager',
            'request_stack',
            'router',
            'translator',
            'monolog.logger.mautic',
            'mautic.lead.model.lead',
            'mautic.lead.model.company',
            'mautic.helper.paths',
            'mautic.core.model.notification',
            'mautic.lead.model.field',
            'mautic.plugin.model.integration_entity',
            'mautic.lead.model.dnc',
            'mautic.lead.field.fields_with_unique_identifier',
            'mautic.helper.integration',
            'doctrine.dbal.default_connection',
            'mautic.helper.core_parameters',
            'mautic.cipher.openssl',
        ] as $serviceId) {
            $container->setDefinition($serviceId, new Definition(\stdClass::class));
        }

        $container->setDefinition(
            EncryptionHelper::class,
            new Definition(
                EncryptionHelper::class,
                [
                    new Reference('mautic.helper.core_parameters'),
                    new Reference('mautic.cipher.openssl'),
                ]
            )
        );

        (new ServicePass())->process($container);

        $argument = $container
            ->getDefinition('mautic.integration.awsendusermessagingsms')
            ->getArgument(7);

        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('mautic.aws_eum_sms.encryption', (string) $argument);
        self::assertSame(EncryptionHelper::class, (string) $container->getAlias('mautic.aws_eum_sms.encryption'));

        $container->compile();
        $dump = (new PhpDumper($container))->dump();

        self::assertStringContainsString(
            'new \\Mautic\\CoreBundle\\Helper\\EncryptionHelper(new \\stdClass(), new \\stdClass())',
            $dump
        );
        self::assertStringNotContainsString(
            'new \\Mautic\\CoreBundle\\Helper\\EncryptionHelper()',
            $dump
        );
    }
}
