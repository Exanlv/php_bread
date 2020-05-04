<?php
require('vendor/autoload.php');

use WebSocket\Client;
use RestCord\DiscordClient as RestCord;

class DiscordClient
{
    /**
     * @var Client
     */
    private $websocket_client;
    
    /**
     * @var number
     */
    private $heartbeat_interval;

    /**
     * @var number
     */
    private $next_beat;

    /**
     * @var RestCord
     */
    private $restcord;

    public function __construct($token)
    {
        $this->websocket_client = new Client("wss://gateway.discord.gg/?v=6&encoding=json");

        $this->restcord = new RestCord(['token' => $token]);

        $initial_data = json_decode($this->websocket_client->receive(), true);

        $this->heartbeat_interval = $initial_data['d']['heartbeat_interval'];

        $this->websocket_client->send(json_encode([
            'op' => 2,
            'd' => [
                'token' => trim(file_get_contents('.token')),
                'properties' => [
                    '$os' => 'linux',
                    '$browser' => 'php',
                    '$device' => 'pc'
                ],
            ],
        ]));

        $this->initiate_event_handler();
    }

    public function initiate_event_handler()
    {
        while (true) {
            $this->handle_heartbeat();

            try {
                $d = $this->websocket_client->receive();
                $this->handle_event($d);
            } catch (\Exception $e) {
            }
        }
    }

    public function handle_event($e)
    {
        $data = json_decode($e, true);

        if ($data['t'] === 'MESSAGE_CREATE')
            $this->handle_message($data);
    }

    public function handle_heartbeat()
    {
        if ($this->next_beat === null) {
            $this->next_beat = time() + $this->heartbeat_interval / 1000;
        } elseif ($this->next_beat <= time()) {
            $this->websocket_client->send(json_encode([
                'op' => 1,
                'd' => null
            ]));

            $this->next_beat = time() + $this->heartbeat_interval / 1000;

            echo 'heartbeat send' . "\n";
        }
    }

    public function handle_message($data)
    {
        $channel = $this->restcord->channel->getChannel(['channel.id' => (int) $data['d']['channel_id']]);
        
        if (strpos(strtolower($channel->name), 'bread') !== false || strpos(strtolower($channel->name), '🍞') !== false) {
            $this->restcord->channel->createReaction([
                'channel.id' => (int) $data['d']['channel_id'],
                'message.id' => (int) $data['d']['id'],
                'emoji' => '🍞'
            ]);
        }
    }
}

$client = new DiscordClient(trim(file_get_contents('.token')));
