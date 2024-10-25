<?php

declare(strict_types=1);

use swentel\nostr\Event\Event;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\RelayResponse\RelayResponseEvent;
use swentel\nostr\Request\Request;
use swentel\nostr\Subscription\Subscription;

require __DIR__ . '/../../vendor/autoload.php';

try {
    $subscription = new Subscription();
    $subscriptionId = $subscription->setId();
    $filter1 = new Filter();
    $filter1->setAuthors([
        'npub1qe3e5wrvnsgpggtkytxteaqfprz0rgxr8c3l34kk3a9t7e2l3acslezefe',
    ]);
    $filter1->setKinds([1]);
    $filter1->setLimit(3);
    $filters = [$filter1];
    $requestMessage = new RequestMessage($subscriptionId, $filters);
    $relay = new Relay('wss://jingle.nostrver.se');
    //$relay = new Relay('wss://hotrightnow.nostr1.com');
    $request = new Request($relay, $requestMessage);
    $response = $request->send();

    foreach ($response as $relay => $messages) {
        print 'Received ' . count($response[$relay]) . ' message(s) received from relay ' . $relay . PHP_EOL;
        foreach ($messages as $message) {
            print $message->type . ': ' . $message->message . PHP_EOL;
            if ($message instanceof RelayResponseEvent) {
                $rawEvent = $message->event;
                $event = new Event();
                $event->setId($rawEvent->id);
                $event->setPublicKey($rawEvent->pubkey);
                $event->setCreatedAt($rawEvent->created_at);
                $event->setKind($rawEvent->kind);
                $event->setTags($rawEvent->tags);
                $event->setContent($rawEvent->content);
                $event->setSignature($rawEvent->sig);
                if ($event->verify() === true) {
                    var_dump($event->getContent());
                }
            }
        }
    }
} catch (Exception $e) {
    print $e->getMessage() . PHP_EOL;
}
