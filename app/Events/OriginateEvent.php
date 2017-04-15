<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

use App\CallRequest;

class OriginateEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call_request;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(CallRequest $call_request)
    {
        $this->call_request = $call_request;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
