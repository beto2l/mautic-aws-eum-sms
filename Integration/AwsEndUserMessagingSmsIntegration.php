<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;

final class AwsEndUserMessagingSmsIntegration extends AbstractIntegration
{
    public const NAME = 'AwsEndUserMessagingSms';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return 'AWS End User Messaging SMS';
    }

    public function getDescription(): string
    {
        return 'Uses the IAM role attached to the Mautic server. AWS access keys are never stored in Mautic.';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    /**
     * These values are saved by Mautic in its encrypted integration settings.
     * They are identifiers, not AWS credentials.
     *
     * @return array<string, string>
     */
    public function getRequiredKeyFields(): array
    {
        return [
            'region'                 => 'AWS region',
            'origination_identity'   => 'Origination identity (phone number, pool, or ARN)',
            'configuration_set_name' => 'Configuration set name or ARN',
        ];
    }

    /**
     * @param Form|FormBuilder $builder
     * @param array<string, mixed> $data
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('features' !== $formArea) {
            return;
        }

        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        $builder
            ->add('message_type', ChoiceType::class, [
                'label'       => 'AWS message type',
                'choices'     => [
                    'Transactional' => 'TRANSACTIONAL',
                    'Promotional'   => 'PROMOTIONAL',
                ],
                'data'        => $featureSettings['message_type'] ?? 'TRANSACTIONAL',
                'required'    => true,
                'placeholder' => false,
            ])
            ->add('delivery_mode', ChoiceType::class, [
                'label'       => 'Delivery mode',
                'choices'     => [
                    'Locked - do not send'              => 'locked',
                    'Canary - test phone only'          => 'canary',
                    'Production - approved audiences'   => 'production',
                ],
                'data'        => $featureSettings['delivery_mode'] ?? 'locked',
                'required'    => true,
                'placeholder' => false,
            ])
            ->add('test_phone_number', TextType::class, [
                'label'       => 'Canary test phone number (E.164)',
                'data'        => $featureSettings['test_phone_number'] ?? '',
                'required'    => false,
                'attr'        => ['placeholder' => '+12125550123'],
            ])
            ->add('phone_field', TextType::class, [
                'label'       => 'Normalized phone field alias',
                'data'        => $featureSettings['phone_field'] ?? 'phone',
                'required'    => true,
                'attr'        => ['placeholder' => 'phone'],
            ])
            ->add('require_consent', CheckboxType::class, [
                'label'       => 'Require SMS consent before delivery',
                'data'        => $featureSettings['require_consent'] ?? true,
                'required'    => false,
            ])
            ->add('consent_field', TextType::class, [
                'label'       => 'SMS consent field alias',
                'data'        => $featureSettings['consent_field'] ?? 'course_sms_optin',
                'required'    => false,
                'attr'        => ['placeholder' => 'course_sms_optin'],
            ])
            ->add('allowed_segment_ids', TextType::class, [
                'label'       => 'Approved segment IDs (comma-separated)',
                'data'        => $featureSettings['allowed_segment_ids'] ?? '',
                'required'    => false,
                'attr'        => ['placeholder' => '31,45'],
            ])
            ->add('daily_limit', IntegerType::class, [
                'label'       => 'Maximum SMS deliveries per day',
                'data'        => $featureSettings['daily_limit'] ?? 25,
                'required'    => true,
                'attr'        => ['min' => 1, 'max' => 100000],
            ])
            ->add('max_message_characters', IntegerType::class, [
                'label'       => 'Maximum message characters',
                'data'        => $featureSettings['max_message_characters'] ?? 480,
                'required'    => true,
                'attr'        => ['min' => 1, 'max' => 1600],
            ])
            ->add('reject_emoji', CheckboxType::class, [
                'label'       => 'Block messages containing emoji',
                'data'        => $featureSettings['reject_emoji'] ?? true,
                'required'    => false,
            ]);
    }
}
