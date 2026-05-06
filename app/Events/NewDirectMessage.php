<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDirectMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $receiverId;

    public function __construct(array $message, $receiverId)
    {
        $this->message = $message;
        $this->receiverId = $receiverId;
    }

    public function broadcastOn()
    {
        // Broadcast on a global private channel for the receiver, or a general user channel
        return new PrivateChannel('user.' . $this->receiverId);
    }
    
    public function broadcastAs()
    {
        return 'dm.created';
    }
}
