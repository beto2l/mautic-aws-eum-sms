<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Security;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\Lead;

final class SendPolicy implements SendPolicyInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function assertCanSend(Lead $lead, string $content, array $settings): string
    {
        return $this->assertCommon($lead, $content, $settings, false);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function assertCanSendMms(Lead $lead, string $content, array $settings): string
    {
        if (empty($settings['mms_enabled'])) {
            throw new SendBlockedException('AWS MMS is disabled in plugin settings.');
        }

        if (empty($settings['mms_campaign_approved'])) {
            throw new SendBlockedException('AWS MMS campaign approval has not been confirmed.');
        }

        if (empty($settings['mms_identity_capable'])) {
            throw new SendBlockedException('The AWS origination identity has not been confirmed as active and MMS-capable.');
        }

        if (empty($settings['aws_managed_opt_outs_confirmed'])) {
            throw new SendBlockedException('AWS-managed SMS/MMS opt-outs must be confirmed before MMS delivery.');
        }

        return $this->assertCommon($lead, $content, $settings, true);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function assertCommon(Lead $lead, string $content, array $settings, bool $isMms): string
    {
        $channel = $isMms ? 'MMS' : 'SMS';
        $phone   = $this->normalizePhone((string) $lead->getFieldValue((string) $settings['phone_field']));
        if (!$this->isE164($phone)) {
            throw new SendBlockedException('The configured phone field is missing or is not normalized to E.164.');
        }

        if (!$isMms && '' === trim($content)) {
            throw new SendBlockedException('SMS content cannot be empty.');
        }

        $maxCharacters = $isMms ? 1600 : (int) $settings['max_message_characters'];
        if (mb_strlen($content) > $maxCharacters) {
            throw new SendBlockedException(sprintf('%s content exceeds the configured maximum length.', $channel));
        }

        if ($settings['reject_emoji'] && $this->containsEmoji($content)) {
            throw new SendBlockedException(sprintf('%s content contains emoji, which is blocked by this integration.', $channel));
        }

        if ('locked' === $settings['delivery_mode']) {
            throw new SendBlockedException(sprintf('AWS %s delivery is locked in plugin settings.', $channel));
        }

        if ('canary' === $settings['delivery_mode']) {
            if (!hash_equals((string) $settings['test_phone_number'], $phone)) {
                throw new SendBlockedException('Canary mode permits delivery only to the configured test phone number.');
            }

            return $phone;
        }

        if ($settings['require_consent'] && !$settings['audience_consent_confirmed'] && !$this->hasConsent($lead, (string) $settings['consent_field'])) {
            throw new SendBlockedException('The contact does not have the required SMS/MMS consent.');
        }

        if (!$this->isInApprovedSegment($lead->getId(), $settings['allowed_segment_ids'])) {
            throw new SendBlockedException('The contact is not in an approved SMS/MMS segment.');
        }

        // Mautic creates the current message stat before invoking the transport.
        if ($this->todayDeliveredCount() >= $settings['daily_limit']) {
            throw new SendBlockedException('The configured SMS/MMS daily limit has been reached.');
        }

        return $phone;
    }

    public function acquireRateLimit(int $limit): void
    {
        if ($limit < 1) {
            throw new SendBlockedException('The configured SMS per-minute limit must be positive.');
        }

        do {
            $acquired = $this->connection->fetchOne(
                'SELECT GET_LOCK(:lock_name, 1)',
                ['lock_name' => 'mautic-aws-eum-sms-rate'],
            );

            if (1 !== (int) $acquired) {
                sleep(1);
            }
        } while (1 !== (int) $acquired);

        // Hold the database advisory lock through the actual AWS request. This keeps
        // concurrent Mautic workers at the configured rate even before stats are flushed.
        usleep((int) ceil(60000000 / $limit));

        while ($this->recentDeliveredCount() >= $limit) {
            $oldest = $this->connection->fetchOne(
                'SELECT MIN(date_sent) FROM sms_message_stats WHERE date_sent >= UTC_TIMESTAMP() - INTERVAL 60 SECOND AND is_failed = 0',
            );

            if (false === $oldest || null === $oldest) {
                return;
            }

            $oldestTimestamp = strtotime((string) $oldest);
            $waitSeconds     = false === $oldestTimestamp ? 1 : max(1, 61 - (time() - $oldestTimestamp));

            sleep(min($waitSeconds, 60));
        }
    }

    public function releaseRateLimit(): void
    {
        $this->connection->fetchOne(
            'SELECT RELEASE_LOCK(:lock_name)',
            ['lock_name' => 'mautic-aws-eum-sms-rate'],
        );
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

    private function recentDeliveredCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sms_message_stats WHERE date_sent >= UTC_TIMESTAMP() - INTERVAL 60 SECOND AND is_failed = 0',
        );
    }

    private function isE164(string $number): bool
    {
        return 1 === preg_match('/^\+[1-9][0-9]{7,14}$/', $number);
    }

    private function normalizePhone(string $number): string
    {
        $number = trim($number);

        if (preg_match('/^[0-9]{10}$/', $number)) {
            return '+1'.$number;
        }

        if (preg_match('/^1[0-9]{10}$/', $number)) {
            return '+'.$number;
        }

        return $number;
    }

    private function containsEmoji(string $content): bool
    {
        return 1 === preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $content);
    }
}
