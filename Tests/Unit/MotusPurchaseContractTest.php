<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MotusPurchaseContractTest extends TestCase
{
    public function testConsultingPurchaseFixtureMatchesThePublishedContract(): void
    {
        $root = dirname(__DIR__, 2);
        $schema = json_decode((string) file_get_contents($root.'/docs/contracts/motus-purchase-event.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $event = json_decode((string) file_get_contents($root.'/Tests/Fixtures/motus-consulting-purchase-event.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('motus-purchase/v1', $event['schema_version']);
        foreach ($schema['required'] as $field) {
            self::assertArrayHasKey($field, $event);
        }
        self::assertContains($event['item_id'], $schema['properties']['item_id']['enum']);
        self::assertContains($event['item_type'], $schema['properties']['item_type']['enum']);
        self::assertContains($event['event_type'], $schema['properties']['event_type']['enum']);
        self::assertSame('consulting', $event['item_type']);
        self::assertSame('paid', $event['purchase_status']);
        self::assertNotSame($event['purchase_id'], $event['purchase_group_id']);
    }
}
