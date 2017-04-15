<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use phpari;
use App\Originate;

class BridgeApp extends Command
{
    private $ariEndpoint; 
    private $stasisClient; 
    private $stasisLoop; 
    private $phpariObject; 
    private $stasisChannelID; 
    private $dtmfSequence = ""; 
    public $stasisLogger;

    private $channelStorage = [];

    private $appname = 'bridge-app';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'start:app';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        try { 

            if (is_null($this->appname)) {
                throw new Exception("[" . __FILE__ . ":" . __LINE__ . "] Stasis application name must be defined!", 500); 
            }

            $this->phpariObject = new phpari($this->appname);
            $this->ariEndpoint  = $this->phpariObject->ariEndpoint;
            $this->stasisClient = $this->phpariObject->stasisClient;
            $this->stasisLoop   = $this->phpariObject->stasisLoop;
            $this->stasisLogger = $this->phpariObject->stasisLogger;
            $this->stasisEvents = $this->phpariObject->stasisEvents;

        } catch (Exception $e) {
            $this->info($e->getMessage());
        }
        parent::__construct();
    }

    public function StasisAppConnectionHandlers()
    {
        try {
            $this->stasisClient->on("request", function ($headers) {
                $this->stasisLogger->notice("Request received!");
            });

            $this->stasisClient->on("handshake", function () {
                $this->stasisLogger->notice("Handshake received!");
            });

            $this->stasisClient->on("message", function ($message) {
                $event = json_decode($message->getData());
                $this->stasisLogger->notice('Received event: ' . $event->type);
                $this->stasisEvents->emit($event->type, array($event));
            });

        } catch (Exception $e) {
            $this->info($e->getMessage());
            exit(99);
        }
    }

    public function _execute()
    {
        try {
            $this->stasisClient->open();
            $this->stasisLoop->run();
        } catch (Exception $e) {
            $this->info($e->getMessage());
            exit(99);
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->stasisLogger->info("Starting Stasis Program... Waiting for handshake...");
        $this->StasisAppEventHandler();

        $this->stasisLogger->info("Initializing Handlers... Waiting for handshake...");
        $this->StasisAppConnectionHandlers();

        $this->stasisLogger->info("Connecting... Waiting for handshake...");
        $this->_execute();
    }

    public function StasisAppEventHandler()
    {
        $this->stasisEvents->on('StasisStart', function($event) {
            // Step 1: Create bridge to either channel hold
            $this->phpariObject->channels()->answer($event->channel->id);
            $this->info("Channel: " . $event->channel->id . "entered into our application");
            $endpoint = Originate::findOrFail($event->channel->id);
            $endpoint->answered = true;
            $endpoint->update();

            $calls = $endpoint->call_request->originates;

            try {
                if($calls[0]->answered && $calls[1]->answered) {
                    $bridge = $this->createBridge('mixing');
                    if($event->channel->id == $calls[0]->id) {
                        $this->stopMoh($calls[1]->id);
                    } else {
                        $this->stopMoh($calls[0]->id);
                    }
                    $this->addToBridge($bridge, $calls[0]->id);
                    $this->addToBridge($bridge, $calls[1]->id);
                } else {
                    $this->playMoh($event->channel->id);
                    //$this->playSound($event->channel->id);
                }
            } catch (\Exception $e) {
                $this->info($e->getTraceAsString());
            }

            //$this->info(print_r($calls));
        });

        $this->stasisEvents->on('ChannelHangupRequest', function($event) {
            $originate = Originate::findOrFail($event->channel->id);
            $endpoints = $originate->call_request->originates;
            $originate->call_request->status = "completed";
            $originate->call_request->update();
            foreach ($endpoints as $key => $endpoint) {
                # code...
                $this->hangUpChannel($endpoint->id);
            }
        });

        $this->stasisEvents->on('ChannelDestroyed', function($event) {
            $originate = Originate::findOrFail($event->channel->id);
            $endpoints = $originate->call_request->originates;
            foreach ($endpoints as $key => $endpoint) {
                # code...
                $this->hangUpChannel($endpoint->id);
            }
        });
    }

    public function playMoh($channelId, $mohClass = 'default')
    {
        return $this->phpariObject->channels()->mohStart($channelId, $mohClass);
    }

    public function stopMoh($channelId)
    {
        return $this->phpariObject->channels()->mohStop($channelId);
    }

    public function playSound($channelId)
    {
        return $this->phpariObject->channels()->playback($channelId, 'sound:queue-periodic-announce');
    }

    public function hangUpChannel($channelId)
    {
        if(!$this->phpariObject->channels()->delete($channelId))
            $this->info($this->phpariObject->lasterror);
        else
            $this->info("Channel: " . $channelId . " has been hungup");
    }

    public function createBridge($type)
    {
        $bridge = $this->phpariObject->bridges()->create($type, "bridge_" . str_random(10), null);
        return $bridge;
    }

    public function addToBridge($bridge, $channel)
    {
        $this->phpariObject->bridges()->addchannel($bridge["id"], $channel);
    }
}
