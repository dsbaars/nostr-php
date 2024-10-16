<?php

declare(strict_types=1);

namespace swentel\nostr\Relay;

use swentel\nostr\MessageInterface;
use swentel\nostr\RelayResponse\RelayResponse;
use swentel\nostr\RelaySetInterface;
use WebSocket;

class RelaySet implements RelaySetInterface
{
    /**
     * Array with Relay objects.
     *
     * @var array
     */
    protected array $relays;

    /**
     * The message to be sent to all relays.
     *
     * @var MessageInterface
     */
    private MessageInterface $message;

    /**
     * Are all relays connected in this relay set?
     *
     * @var bool
     */
    public bool $isConnected;

    /**
     * @inheritDoc
     */
    public function setRelays(array $relays): void
    {
        $this->relays = $relays;
    }

    /**
     * @inheritDoc
     */
    public function getRelays(): array
    {
        return $this->relays;
    }

    /**
     * @inheritDoc
     */
    public function addRelay(Relay $relay): void
    {
        $this->relays[] = $relay;
    }

    /**
     * @inheritDoc
     */
    public function removeRelay(Relay $relay): void
    {
        // TODO: Implement removeRelay() method.
    }

    /**
     * @inheritDoc
     */
    public function createFromUrls(array|string $urls): void
    {
        foreach ($urls as $url) {
            $relay = new Relay($url);
            $this->relays[] = $relay;
        }
    }

    /**
     * @inheritDoc
     */
    public function setMessage(MessageInterface $message): void
    {
        $this->message = $message;
    }

    /**
     * @inheritDoc
     */
    public function connect(): bool
    {
        // TODO: Implement connect() method.
        return $this->isConnected;
    }

    /**
     * @inheritDoc
     */
    public function disconnect(): bool
    {
        // TODO: Implement disconnect() method.
        return $this->isConnected;
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * @inheritDoc
     */
    public function send(): array
    {
        try {
            // Send message to each relay defined in this set.
            /** @var Relay $relay */
            foreach ($this->relays as $relay) {
                $client = new WebSocket\Client($relay->getUrl());
                $payload = $this->message->generate();
                $client->text($payload);
                $response = $client->receive();
                $client->disconnect();
                if ($response->getOpcode() === 'ping') {
                    continue;
                }
                if ($response === null) {
                    throw new \RuntimeException('Websocket client response is null');
                }
                $result[$relay->getUrl()] = RelayResponse::create(json_decode($response->getContent()));
            }
        } catch (WebSocket\Exception\ClientException $e) {
            $result = [
                'ERROR',
                '',
                false,
                $e->getMessage(),
            ];
        }
        return $result;
    }
}
