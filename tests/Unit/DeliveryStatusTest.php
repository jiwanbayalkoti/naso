<?php

namespace Tests\Unit;

use App\Helpers\DeliveryStatus;
use PHPUnit\Framework\TestCase;

class DeliveryStatusTest extends TestCase
{
    public function test_all_statuses_are_defined(): void
    {
        $this->assertCount(8, DeliveryStatus::all());
        $this->assertContains(DeliveryStatus::PENDING, DeliveryStatus::all());
        $this->assertContains(DeliveryStatus::COMPLETED, DeliveryStatus::all());
    }

    public function test_valid_transitions_are_allowed(): void
    {
        $this->assertTrue(DeliveryStatus::canTransition(DeliveryStatus::PENDING, DeliveryStatus::ASSIGNED));
        $this->assertTrue(DeliveryStatus::canTransition(DeliveryStatus::ASSIGNED, DeliveryStatus::ACCEPTED));
        $this->assertTrue(DeliveryStatus::canTransition(DeliveryStatus::DELIVERED, DeliveryStatus::COMPLETED));
        $this->assertTrue(DeliveryStatus::canTransition(DeliveryStatus::PENDING, DeliveryStatus::CANCELLED));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(DeliveryStatus::canTransition(DeliveryStatus::PENDING, DeliveryStatus::DELIVERED));
        $this->assertFalse(DeliveryStatus::canTransition(DeliveryStatus::COMPLETED, DeliveryStatus::PENDING));
        $this->assertFalse(DeliveryStatus::canTransition(DeliveryStatus::CANCELLED, DeliveryStatus::ASSIGNED));
    }

    public function test_terminal_statuses_are_identified(): void
    {
        $this->assertTrue(DeliveryStatus::isTerminal(DeliveryStatus::COMPLETED));
        $this->assertTrue(DeliveryStatus::isTerminal(DeliveryStatus::CANCELLED));
        $this->assertFalse(DeliveryStatus::isTerminal(DeliveryStatus::PENDING));
    }

    public function test_labels_exist_for_all_statuses(): void
    {
        foreach (DeliveryStatus::all() as $status) {
            $this->assertArrayHasKey($status, DeliveryStatus::labels());
        }
    }
}
