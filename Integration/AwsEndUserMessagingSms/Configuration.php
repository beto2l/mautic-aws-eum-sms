<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSms;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\AwsEndUserMessagingSmsBundle\Integration\AwsEndUserMessagingSmsIntegration;

final class Configuration
{
    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function __construct(private readonly IntegrationHelper $integrationHelper)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        if (null !== $this->settings) {
            return $this->settings;
        }

        $integration = $this->integrationHelper->getIntegrationObject(AwsEndUserMessagingSmsIntegration::NAME);
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            throw new \RuntimeException('AWS End User Messaging SMS is not enabled in Mautic.');
        }

        $keys     = $integration->getDecryptedApiKeys();
        $features = $integration->getIntegrationSettings()->getFeatureSettings();
        $settings = array_merge($this->defaults(), $keys, $features);

        $settings['region']               = trim((string) $settings['region']);
        $settings['origination_identity'] = trim((string) $settings['origination_identity']);
        $settings['configuration_set_name'] = trim((string) $settings['configuration_set_name']);
        $settings['phone_field']          = trim((string) $settings['phone_field']);
        $settings['consent_field']        = trim((string) $settings['consent_field']);
        $settings['test_phone_number']    = trim((string) $settings['test_phone_number']);
        $settings['allowed_segment_ids']  = $this->parseSegmentIds((string) $settings['allowed_segment_ids']);
        $settings['daily_limit']          = max(1, min(100000, (int) $settings['daily_limit']));
        $settings['max_message_characters'] = max(1, min(1600, (int) $settings['max_message_characters']));
        $settings['require_consent']      = $this->toBool($settings['require_consent']);
        $settings['audience_consent_confirmed'] = $this->toBool($settings['audience_consent_confirmed']);
        $settings['reject_emoji']         = $this->toBool($settings['reject_emoji']);
        $settings['per_minute_limit']     = max(1, min(1000, (int) $settings['per_minute_limit']));

        if (!preg_match('/^[a-z]{2}(?:-gov)?-[a-z]+-\d$/', $settings['region'])) {
            throw new \RuntimeException('AWS region is invalid.');
        }

        if ('' === $settings['origination_identity'] || '' === $settings['configuration_set_name']) {
            throw new \RuntimeException('Origination identity and configuration set are required.');
        }

        if (!in_array($settings['message_type'], ['TRANSACTIONAL', 'PROMOTIONAL'], true)) {
            throw new \RuntimeException('AWS message type is invalid.');
        }

        if (!in_array($settings['delivery_mode'], ['locked', 'canary', 'production'], true)) {
            throw new \RuntimeException('Delivery mode is invalid.');
        }

        if ('canary' === $settings['delivery_mode'] && !$this->isE164($settings['test_phone_number'])) {
            throw new \RuntimeException('Canary mode requires a valid E.164 test phone number.');
        }

        if ('production' === $settings['delivery_mode'] && [] === $settings['allowed_segment_ids']) {
            throw new \RuntimeException('Production mode requires at least one approved segment ID.');
        }

        if ('production' === $settings['delivery_mode'] && !$settings['audience_consent_confirmed']) {
            throw new \RuntimeException('Production mode requires administrator confirmation that the approved audience opted in to SMS.');
        }

        if ('' === $settings['phone_field']) {
            throw new \RuntimeException('A normalized phone field alias is required.');
        }

        if ($settings['require_consent'] && '' === $settings['consent_field']) {
            throw new \RuntimeException('A consent field alias is required when consent is enforced.');
        }

        return $this->settings = $settings;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'region'                 => 'us-west-2',
            'origination_identity'   => '',
            'configuration_set_name' => '',
            'message_type'           => 'TRANSACTIONAL',
            'delivery_mode'          => 'locked',
            'test_phone_number'      => '',
            'phone_field'            => 'phone',
            'require_consent'        => true,
            'audience_consent_confirmed' => false,
            'consent_field'          => 'course_sms_optin',
            'allowed_segment_ids'    => '',
            'daily_limit'            => 25,
            'per_minute_limit'       => 10,
            'max_message_characters' => 480,
            'reject_emoji'           => true,
        ];
    }

    /** @return list<int> */
    private function parseSegmentIds(string $ids): array
    {
        $values = array_filter(
            array_map('trim', explode(',', $ids)),
            static fn (string $id): bool => ctype_digit($id) && (int) $id > 0,
        );

        return array_values(array_unique(array_map(static fn (string $id): int => (int) $id, $values)));
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function isE164(string $number): bool
    {
        return 1 === preg_match('/^\+[1-9][0-9]{7,14}$/', $number);
    }
}
