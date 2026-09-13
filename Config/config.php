<?php

declare(strict_types=1);

use Mautic\CoreBundle\Helper\EncryptionHelper;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\AwsGateway;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\Configuration;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\MediaPreparer;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms\Transport;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSmsIntegration;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Security\SendPolicy;

return [
    'name'        => 'AWS End User Messaging SMS/MMS',
    'description' => 'Secure, consent-aware SMS and MMS delivery through AWS End User Messaging.',
    'version'     => '1.1.2',
    'author'      => 'OPIN X LLC',

    'services' => [
        'integrations' => [
            'mautic.integration.awsendusermessagingsms' => [
                'class'     => AwsEndUserMessagingSmsIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.aws_eum_sms.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                    'mautic.lead.field.fields_with_unique_identifier',
                ],
            ],
        ],
        'other' => [
            // ServicePass adds this scalar alias without replacing the core definition.
            EncryptionHelper::class => [
                'class'         => EncryptionHelper::class,
                'serviceAliases' => ['mautic.aws_eum_sms.encryption'],
            ],
            'mautic.aws_eum_sms.configuration' => [
                'class'     => Configuration::class,
                'arguments' => ['mautic.helper.integration'],
            ],
            'mautic.aws_eum_sms.send_policy' => [
                'class'     => SendPolicy::class,
                'arguments' => ['doctrine.dbal.default_connection'],
            ],
            'mautic.aws_eum_sms.aws_gateway' => [
                'class' => AwsGateway::class,
            ],
            'mautic.aws_eum_sms.media_preparer' => [
                'class'     => MediaPreparer::class,
                'arguments' => [
                    'mautic.helper.paths',
                    'mautic.aws_eum_sms.aws_gateway',
                ],
            ],
            'mautic.aws_eum_sms.transport' => [
                'class'     => Transport::class,
                'arguments' => [
                    'mautic.aws_eum_sms.configuration',
                    'mautic.aws_eum_sms.send_policy',
                    'mautic.aws_eum_sms.media_preparer',
                    'mautic.aws_eum_sms.aws_gateway',
                    'monolog.logger.mautic',
                ],
                'tag'          => 'mautic.sms_transport',
                'tagArguments' => [
                    'alias'              => 'aws_eum_sms',
                    'integrationAlias'   => AwsEndUserMessagingSmsIntegration::NAME,
                ],
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.aws_eum_sms.smses' => [
                    'route'  => 'mautic_sms_index',
                    'access' => ['sms:smses:viewown', 'sms:smses:viewother'],
                    'parent' => 'mautic.core.channels',
                    'checks' => [
                        'integration' => [
                            AwsEndUserMessagingSmsIntegration::NAME => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                    'priority' => 70,
                ],
            ],
        ],
    ],
];
