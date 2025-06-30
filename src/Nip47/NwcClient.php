<?php

declare(strict_types=1);

namespace swentel\nostr\Nip47;

use swentel\nostr\Nip47\Command\CommandInterface;
use swentel\nostr\Nip47\Response\ResponseInterface;
use swentel\nostr\Nip47\Response\PayInvoiceResponse;
use swentel\nostr\Nip47\Response\GetBalanceResponse;
use swentel\nostr\Nip47\Response\GetInfoResponse;
use swentel\nostr\Nip47\Response\MakeInvoiceResponse;
use swentel\nostr\Nip47\Event\RequestEvent;
use swentel\nostr\Nip47\Event\ResponseEvent;
use swentel\nostr\Nip47\Event\InfoEvent;
use swentel\nostr\Nip47\Exception\CommandException;
use swentel\nostr\Nip47\Exception\NwcException;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Request\Request;
use swentel\nostr\Request\PersistentConnection;
use swentel\nostr\Subscription\Subscription;

/**
 * Nostr Wallet Connect Client.
 *
 * Handles client-side interactions with NWC wallet services.
 */
class NwcClient
{
    private NwcUri $nwcUri;
    private Key $keyService;
    private Sign $signService;
    private RelaySet $relaySet;
    private string $clientPrivkey;
    private string $clientPubkey;

    /**
     * Create a new NWC client.
     *
     * @param string|NwcUri $nwcUri NWC connection URI or parsed URI object
     * @throws \swentel\nostr\Nip47\Exception\InvalidUriException
     */
    public function __construct(string|NwcUri $nwcUri)
    {
        $this->keyService = new Key();
        $this->signService = new Sign();

        // Parse URI if string provided
        if (is_string($nwcUri)) {
            $this->nwcUri = new NwcUri($nwcUri);
        } else {
            $this->nwcUri = $nwcUri;
        }

        // Setup client keys from URI secret
        $this->clientPrivkey = $this->nwcUri->getSecret();
        $this->clientPubkey = $this->keyService->getPublicKey($this->clientPrivkey);

        // Setup relay set
        $this->setupRelays();
    }

    /**
     * Setup relays from NWC URI.
     */
    private function setupRelays(): void
    {
        $this->relaySet = new RelaySet();
        $relays = [];

        foreach ($this->nwcUri->getRelays() as $relayUrl) {
            $relays[] = new Relay($relayUrl);
        }

        $this->relaySet->setRelays($relays);
    }

    /**
     * Get wallet info and capabilities.
     *
     * @return GetInfoResponse
     * @throws NwcException
     */
    public function getWalletInfo(): GetInfoResponse
    {
        // First try to get the info event (kind 13194)
        try {
            $infoEvent = $this->fetchInfoEvent();
            if ($infoEvent) {
                // Convert info event to GetInfoResponse format
                $result = [
                    'methods' => $infoEvent->getSupportedMethods(),
                    'notifications' => $infoEvent->getSupportedNotifications(),
                ];

                return new GetInfoResponse('get_info', $result);
            }
        } catch (\Exception $e) {
            // If info event fetch fails, continue to try get_info command
        }

        // Fallback to get_info command
        $command = new Command\GetInfoCommand();
        return $this->executeCommand($command, GetInfoResponse::class);
    }

    /**
     * Fetch the info event from relays.
     *
     * @return InfoEvent|null
     */
    private function fetchInfoEvent(): ?InfoEvent
    {
        $filter = new Filter();
        $filter->setKinds([InfoEvent::KIND]);
        $filter->setAuthors([$this->nwcUri->getWalletPubkey()]);
        $filter->setLimit(1);

        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getId(), [$filter]);
        $request = new Request($this->relaySet, $requestMessage);

        try {
            $results = $request->send();

            foreach ($results as $relayUrl => $responses) {
                if (is_array($responses)) {
                    foreach ($responses as $response) {
                        if (isset($response->event) && $response->event->kind === InfoEvent::KIND) {
                            $infoEvent = new InfoEvent();
                            $infoEvent->setId($response->event->id);
                            $infoEvent->setPublicKey($response->event->pubkey);
                            $infoEvent->setCreatedAt($response->event->created_at);
                            $infoEvent->setKind($response->event->kind);
                            $infoEvent->setContent($response->event->content);
                            $infoEvent->setTags($response->event->tags ?? []);
                            $infoEvent->setSignature($response->event->sig);

                            return $infoEvent;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Return null if fetch fails
        }

        return null;
    }

    /**
     * Get wallet balance.
     *
     * @return GetBalanceResponse
     * @throws NwcException
     */
    public function getBalance(): GetBalanceResponse
    {
        $command = new Command\GetBalanceCommand();
        return $this->executeCommand($command, GetBalanceResponse::class);
    }

    /**
     * Pay a Lightning invoice.
     *
     * @param string $invoice The bolt11 invoice to pay
     * @param int|null $amount Optional amount in millisatoshis for zero-amount invoices
     * @return PayInvoiceResponse
     * @throws NwcException
     */
    public function payInvoice(string $invoice, ?int $amount = null): PayInvoiceResponse
    {
        $command = new Command\PayInvoiceCommand($invoice, $amount);
        return $this->executeCommand($command, PayInvoiceResponse::class);
    }

    /**
     * Create a Lightning invoice.
     *
     * @param int $amount Amount in millisatoshis
     * @param string|null $description Invoice description
     * @param string|null $descriptionHash Invoice description hash
     * @param int|null $expiry Expiry in seconds from creation
     * @return MakeInvoiceResponse
     * @throws NwcException
     */
    public function makeInvoice(int $amount, ?string $description = null, ?string $descriptionHash = null, ?int $expiry = null): MakeInvoiceResponse
    {
        $command = new Command\MakeInvoiceCommand($amount, $description, $descriptionHash, $expiry);
        return $this->executeCommand($command, MakeInvoiceResponse::class);
    }

    /**
     * Lookup an invoice by payment hash or invoice string.
     *
     * @param string|null $paymentHash Payment hash of the invoice
     * @param string|null $invoice Invoice string to lookup
     * @return \swentel\nostr\Nip47\Response\LookupInvoiceResponse
     * @throws NwcException
     */
    public function lookupInvoice(?string $paymentHash = null, ?string $invoice = null): Response\LookupInvoiceResponse
    {
        $command = new Command\LookupInvoiceCommand($paymentHash, $invoice);
        return $this->executeCommand($command, Response\LookupInvoiceResponse::class);
    }

    /**
     * Execute a command and return the response.
     *
     * @param CommandInterface $command The command to execute
     * @param string $responseClass The expected response class
     * @return ResponseInterface
     * @throws NwcException
     */
    public function executeCommand(CommandInterface $command, string $responseClass): ResponseInterface
    {
        // Validate command
        $command->validate();

        // Create request event
        $requestEvent = new RequestEvent($command, $this->clientPrivkey, $this->nwcUri->getWalletPubkey());

        // Sign the event
        $this->signService->signEvent($requestEvent, $this->clientPrivkey);

        // Setup subscription filter for responses
        $responseFilter = new Filter();
        $responseFilter->setKinds([ResponseEvent::KIND]);
        $responseFilter->setAuthors([$this->nwcUri->getWalletPubkey()]);
        $responseFilter->setLowercasePTags([$this->clientPubkey]);
        $responseFilter->setSince(time() - 60); // Look for responses from last minute

        $subscription = new Subscription();
        $subscriptionMessage = new RequestMessage($subscription->getId(), [$responseFilter]);

        try {
            // Use the first relay for now (could be enhanced to try multiple relays)
            $relays = $this->relaySet->getRelays();
            if (empty($relays)) {
                throw new CommandException("No relays available");
            }

            $relay = $relays[0];

            // Start persistent connection to listen for responses BEFORE sending command
            $responseConnection = new PersistentConnection($relay, $subscriptionMessage);

            // Setup response capture with a timeout mechanism
            $capturedResponse = null;
            $responseReceived = false;
            $startTime = time();
            $timeoutSeconds = 30;

            $responseConnection->onReceive(function ($response) use (&$capturedResponse, &$responseReceived, $responseClass, $responseConnection, $startTime, $timeoutSeconds) {
                if (isset($response->event) && $response->event->kind === ResponseEvent::KIND) {
                    try {
                        // Decrypt and parse response
                        $decryptedContent = \swentel\nostr\Encryption\Nip04::decrypt(
                            $response->event->content,
                            $this->clientPrivkey,
                            $this->nwcUri->getWalletPubkey(),
                        );
                        $decryptedData = json_decode($decryptedContent, true);

                        // Create the response object
                        $capturedResponse = $responseClass::fromArray($decryptedData);
                        $responseReceived = true;

                        // Stop the connection once we have the response
                        $responseConnection->close();
                    } catch (\Exception $e) {
                        // Skip this response if decryption fails
                    }
                }

                // Also close connection if timeout reached
                if (time() - $startTime >= $timeoutSeconds) {
                    $responseConnection->close();
                }
            });

            $responseConnection->setPrintMessages(false);

            // Send the command first, then start listening
            $eventMessage = new EventMessage($requestEvent);
            $request = new Request($this->relaySet, $eventMessage);

            // Send the command
            $results = $request->send();
            $commandSent = false;

            // Check if command was successfully sent
            foreach ($results as $relayUrl => $responses) {
                if (is_array($responses)) {
                    foreach ($responses as $response) {
                        if (isset($response->type) && $response->type === 'OK' && $response->status === true) {
                            $commandSent = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$commandSent) {
                throw new CommandException("Failed to send command to any relay");
            }

            // Now start listening for the response (this will block until response received or timeout)
            try {
                $responseConnection->transmit();
            } catch (\Exception $e) {
                // Connection closed or error occurred
            }

            // Check if we got a response
            if ($responseReceived && $capturedResponse !== null) {
                return $capturedResponse;
            }

            throw new CommandException("Timeout waiting for response");

        } catch (\Exception $e) {
            throw new CommandException("Failed to execute command: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the NWC URI.
     *
     * @return NwcUri
     */
    public function getNwcUri(): NwcUri
    {
        return $this->nwcUri;
    }

    /**
     * Get the client public key.
     *
     * @return string
     */
    public function getClientPubkey(): string
    {
        return $this->clientPubkey;
    }

    /**
     * Get the wallet service public key.
     *
     * @return string
     */
    public function getWalletPubkey(): string
    {
        return $this->nwcUri->getWalletPubkey();
    }
}
