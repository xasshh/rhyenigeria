<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An agent's latest position changed.
 *
 * Sent on one private channel per audience so a coordinator subscribes to a
 * single channel for their area:
 *
 *   tracking.all            super admins
 *   tracking.state.{id}     the agent's assigned state
 *   tracking.lga.{id}       the agent's assigned LGA
 *   tracking.ward.{id}      the agent's assigned ward
 *   tracking.agent.{id}     this agent only
 *
 * Dispatched from a queued job, so it is broadcast immediately rather than
 * being queued a second time.
 */
class AgentPositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<string, mixed> $position See AgentPosition::toMapArray(). */
    public function __construct(public array $position) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        $agent = $this->position['agent'];

        $channels = [
            new PrivateChannel('tracking.all'),
            new PrivateChannel('tracking.agent.'.$agent['id']),
        ];

        foreach (['state', 'lga', 'ward'] as $level) {
            if (! empty($agent[$level.'_id'])) {
                $channels[] = new PrivateChannel("tracking.{$level}.".$agent[$level.'_id']);
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'agent.position.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->position;
    }
}
