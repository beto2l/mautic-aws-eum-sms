<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Security;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\Lead;

final class SendPolicy
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function assertCanSend(Lead $lead, string $content, array $settings): string
    {
        $phone = trim((string) $lead->getFieldValue((string) $settings['phone_field']));
        if (!$this->isE164($phone)) {
            throw new SendBlockedException('The configured phone field is missing or is not normalized to E.164.');
        }

        if ('' === trim($content)) {
            throw new SendBlockedException('SMS content cannot be empty.');
        }

        if (mb_strlen($content) > $settings['max_message_characters']) {
            throw new SendBlockedException('SMS content exceeds the configured maximum length.');
        }

        if ($settings['reject_emoji'] && $this->containsEmoji($content)) {
            throw new SendBlockedException('SMS content contains emoji, which is blocked by this integration.');
        }

        if ('locked' === $settings['delivery_mode']) {
            throw new SendBlockedException('AWS SMS delivery is locked in plugin settings.');
        }

        if ('canary' === $settings['delivery_mode']) {
            if (!hash_equals((string) $settings['test_phone_number'], $phone)) {
                throw new SendBlockedException('Canary mode permits delivery only to the configured test phone number.');
            }

            return $phone;
        }

        if ($settings['require_consent'] && !$this->hasConsent($lead, (string) $settings['consent_field'])) {
            throw new SendBlockedException('The contact does not have the required SMS consent.');
        }

        if (!$this->isInApprovedSegment($lead->getId(), $settings['allowed_segment_ids'])) {
            throw new SendBlockedException('The contact is not in an approved SMS segment.');
        }

        if ($this->todayDeliveredCount() >= $settings['daily_limit']) {
            throw new SendBlockedException('The configured SMS daily limit has been reached.');
        }

        return $phone;
    }

    private function hasConsent(Lead $lead, string $field): bool
    {
        $value = $lead->getFieldValue($field);

        return true === filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /** @param list<int> $segmentIds */
    private function isInApprovedSegment(int $leadId, array $segmentIds): bool
    {
        if ([] === $segmentIds) {
            return false;
        }

        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM lead_lists_leads WHERE lead_id = :lead_id AND manually_removed = 0 AND leadlist_id IN (:segment_ids) LIMIT 1',
            ['lead_id' => $leadId, 'segment_ids' => $segmentIds],
            ['lead_id' => \PDO::PARAM_INT, 'segment_ids' => ArrayParameterType::INTEGER],
        );
    }

    private function todayDeliveredCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sms_message_stats WHERE date_sent >= CURRENT_DATE() AND is_failed = 0',
        );
    }

    private function isE164(string $number): bool
    {
        return 1 === preg_match('/^\+[1-9][0-9]{7,14}$/', $number);
    }

    private function containsEmoji(string $content): bool
    {
        return 1 === preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $content);
    }
}
