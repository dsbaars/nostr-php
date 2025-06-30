# NIP-47 Nostr Wallet Connect Implementation

This directory contains a complete client-side implementation of [NIP-47 Nostr Wallet Connect](https://github.com/nostr-protocol/nips/blob/master/47.md) for PHP.

## Overview

Nostr Wallet Connect (NWC) allows applications to connect to Lightning wallets over the Nostr protocol in a secure, decentralized way. This implementation provides:

- **Client-side functionality** for connecting to NWC wallet services
- **Complete command support** for all major Lightning operations
- **Secure encryption** using NIP-04 for wallet communications
- **Comprehensive error handling** with specific exception types
- **Event-driven architecture** following Nostr patterns

## Key Features

### 🔗 URI Parsing & Validation
- Parse and validate NWC connection URIs
- Support for multiple relays and optional Lightning addresses
- Secure parameter validation and format checking

### 💰 Lightning Operations
- **Get Balance** - Check wallet balance
- **Pay Invoice** - Pay Lightning invoices  
- **Make Invoice** - Create Lightning invoices
- **Lookup Invoice** - Query invoice status
- **Get Info** - Retrieve wallet capabilities

### 🔐 Security
- NIP-04 encryption for all wallet communications
- Proper key management and validation
- Secure event signing and verification

### ⚡ Events
- **Request Events** (kind 23194) - Encrypted commands to wallets
- **Response Events** (kind 23195) - Encrypted responses from wallets  
- **Info Events** (kind 13194) - Public wallet capability announcements

## Quick Start

### 1. Basic Connection

```php
use swentel\nostr\Nip47\NwcClient;
use swentel\nostr\Nip47\NwcUri;

// Parse NWC URI
$nwcUri = 'nostr+walletconnect://wallet_pubkey?relay=wss://relay.com&secret=client_secret';
$client = new NwcClient($nwcUri);

// Check wallet capabilities
$info = $client->getWalletInfo();
echo "Supported methods: " . implode(', ', $info->getMethods());
```

### 2. Balance Check

```php
$balance = $client->getBalance();
if ($balance->isSuccess()) {
    echo "Balance: " . $balance->getBalance() . " msats";
    echo "(" . $balance->getBalanceInSats() . " sats)";
}
```

### 3. Create Invoice

```php
$invoice = $client->makeInvoice(
    amount: 1000,        // 1000 msats = 1 sat
    description: "Test payment",
    expiry: 3600         // 1 hour
);

if ($invoice->isSuccess()) {
    echo "Invoice: " . $invoice->getInvoice();
    echo "Payment hash: " . $invoice->getPaymentHash();
}
```

### 4. Pay Invoice

```php
$payment = $client->payInvoice("lnbc1000n1...");
if ($payment->isPaymentSuccessful()) {
    echo "Payment successful!";
    echo "Preimage: " . $payment->getPreimage();
}
```

## Directory Structure

```
src/Nip47/
├── NwcClient.php              # Main client implementation
├── NwcUri.php                 # URI parsing and validation
├── NwcUriInterface.php        # URI interface
├── ErrorCode.php              # NWC error codes enum
│
├── Command/                   # Command implementations
│   ├── CommandInterface.php
│   ├── AbstractCommand.php
│   ├── GetBalanceCommand.php
│   ├── GetInfoCommand.php
│   ├── PayInvoiceCommand.php
│   ├── MakeInvoiceCommand.php
│   └── LookupInvoiceCommand.php
│
├── Response/                  # Response implementations
│   ├── ResponseInterface.php
│   ├── AbstractResponse.php
│   ├── GetBalanceResponse.php
│   ├── GetInfoResponse.php
│   ├── PayInvoiceResponse.php
│   ├── MakeInvoiceResponse.php
│   └── LookupInvoiceResponse.php
│
├── Event/                     # Nostr event implementations
│   ├── RequestEvent.php       # kind 23194
│   ├── ResponseEvent.php      # kind 23195
│   └── InfoEvent.php          # kind 13194
│
└── Exception/                 # Exception hierarchy
    ├── NwcException.php
    ├── InvalidUriException.php
    ├── CommandException.php
    ├── PaymentFailedException.php
    ├── InsufficientBalanceException.php
    ├── UnauthorizedException.php
    └── RateLimitedException.php
```

## Examples

### Running Examples

```bash
# Basic client usage
php src/Examples/nwc/client-example.php

# URI parsing and validation  
php src/Examples/nwc/uri-parsing-example.php

# Complete payment flow
php src/Examples/nwc/payment-flow-example.php
```

### Example Output

```
NWC Payment Flow Example
========================

1. Connecting to wallet...
   ✓ Connected to wallet: b889ff5b1513b641...

2. Checking wallet capabilities...
   ✓ Supported methods: get_balance, pay_invoice, make_invoice, lookup_invoice
   ✓ All required methods are supported

3. Checking initial balance...
   ✓ Current balance: 100,000 msats
     (100 sats)

4. Creating a test invoice...
   ✓ Invoice created successfully!
     - Amount: 1000 msats
     - Description: Test invoice for NWC payment flow
     - Payment Hash: a1b2c3d4e5f6789...
     - Invoice: lnbc10n1pjw8...
```

## Error Handling

The implementation includes comprehensive error handling:

```php
use swentel\nostr\Nip47\Exception\PaymentFailedException;
use swentel\nostr\Nip47\Exception\InsufficientBalanceException;

try {
    $payment = $client->payInvoice($invoice);
} catch (PaymentFailedException $e) {
    echo "Payment failed: " . $e->getMessage();
} catch (InsufficientBalanceException $e) {
    echo "Insufficient balance: " . $e->getMessage();
} catch (NwcException $e) {
    echo "General NWC error: " . $e->getMessage();
}
```

## NIP-47 Compliance

This implementation supports:

- ✅ All required event kinds (23194, 23195, 13194)
- ✅ All standard commands (get_info, get_balance, pay_invoice, make_invoice, lookup_invoice)
- ✅ Proper NIP-04 encryption
- ✅ Error code handling per specification  
- ✅ URI format validation
- ✅ Relay communication
- ✅ Event signing and verification

### Event Kinds

| Kind | Description | Implementation |
|------|-------------|----------------|
| 13194 | Wallet info event | `InfoEvent.php` |
| 23194 | Request event | `RequestEvent.php` |  
| 23195 | Response event | `ResponseEvent.php` |

### Commands

| Command | Description | Implementation |
|---------|-------------|----------------|
| `get_info` | Get wallet capabilities | `GetInfoCommand.php` |
| `get_balance` | Get wallet balance | `GetBalanceCommand.php` |
| `pay_invoice` | Pay Lightning invoice | `PayInvoiceCommand.php` |
| `make_invoice` | Create Lightning invoice | `MakeInvoiceCommand.php` |
| `lookup_invoice` | Lookup invoice status | `LookupInvoiceCommand.php` |

## Security Considerations

- **Encryption**: All communications are encrypted using NIP-04
- **Authentication**: Events are signed with client private keys
- **Validation**: All inputs are validated before processing
- **Error Handling**: Sensitive information is not leaked in errors
- **Timeouts**: Commands have reasonable timeout limits
- **Rate Limiting**: Built-in support for rate limit handling

## Testing

⚠️ **Important**: Be careful when testing with real wallets and real Bitcoin!

1. Use testnet wallets for development
2. Start with small amounts
3. Verify all parameters before executing payments
4. Check wallet balance limits and capabilities

## Integration

To integrate NWC into your application:

1. **Parse the NWC URI** from user input or configuration
2. **Create an NwcClient** instance  
3. **Check wallet capabilities** with `getWalletInfo()`
4. **Implement your payment flow** using the available commands
5. **Handle errors gracefully** with proper exception handling

## Contributing

When contributing to the NWC implementation:

- Follow PSR-12 coding standards
- Add comprehensive tests for new features  
- Update documentation for API changes
- Ensure NIP-47 compliance for new commands
- Include example usage for new functionality 