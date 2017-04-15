<?php

namespace App\Listeners;

use App\Events\OrignateEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use phpari;
use channels;
use bridges;
use App\CallRequest;
use App\Originate;
use App\Events\OriginateEvent;

class OriginateEventListener
{
    private $appname = 'bridge-app';
    private $conn;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Handle the event.
     *
     * @param  OriginateEvent  $event
     * @return void
     */
    public function handle(OriginateEvent $event)
    {
        $this->conn = new phpari($this->appname);
        $channels = new channels($this->conn);
        $status = $this->createChannel($channels, $event);
    }

    private function hangUp($c)
    {
        $response = $conn->channels()->hangup($c);
    }

    private function createHoldingBridge(phpari $conn)
    {
        $response = $conn->bridges()->create("holding", null, null);
    }

    private function createChannel(channels $channel, OriginateEvent $event)
    {
        $responseA = $channel->originate(
            "SIP/22" . $event->call_request->endpointA . "@outgoingbox",
            null,
            [
                "app" => $this->appname,
                "appArgs" => "Abdullah",
                "callerId" => "2138797850",
                "timeout" => $event->call_request->timeout
            ],
            [
                "CALLERID(name)" => "Abdullah",
                "CALLERID(num)" => $event->call_request->endpointB
            ]
        );
        $responseB = $channel->originate(
            "SIP/22" . $event->call_request->endpointB . "@outgoingbox",
            null,
            [
                "app" => $this->appname,
                "appArgs" => "Abdullah",
                "callerId" => "2138797850",
                "timeout" => $event->call_request->timeout
            ],
            [
                "CALLERID(name)" => "Abdullah",
                "CALLERID(num)" => $event->call_request->endpointA
            ]
        );
        if($responseA && $responseB)
        {
            $originateA = new Originate();
            $originateA->id = $responseA["id"];
            $event->call_request->originates()->save($originateA);
            $originateB = new Originate();
            $originateB->id = $responseB["id"];
            $event->call_request->originates()->save($originateB);
            $event->call_request->status = "originated";
            $event->call_request->update();
            return true;
        } else {
            $event->call_request->status = "failed";
            $event->call_request->update();
            if($responseA) {
                $event->call_request->error = "endpointB is switched off or is unreachable";
                $event->call_request->update();
                $this->hangUp($responseA["id"]);
            } else {
                $event->call_request->error = "endpointA is switched off or is unreachable";
                $event->call_request->update();
                $this->hangUp($responseB["id"]);
            }
            return false;
        }
    }
}
