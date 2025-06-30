<?php

declare(strict_types=1);

namespace swentel\nostr\Nip47\Response;

/**
 * Interface for all NWC responses.
 */
interface ResponseInterface
{
    /**
     * Get the result type.
     *
     * @return string
     */
    public function getResultType(): string;

    /**
     * Check if the response indicates success.
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * Check if the response indicates an error.
     *
     * @return bool
     */
    public function isError(): bool;

    /**
     * Get the error information if any.
     *
     * @return array|null
     */
    public function getError(): ?array;

    /**
     * Get the result data if successful.
     *
     * @return array|null
     */
    public function getResult(): ?array;

    /**
     * Convert response to array representation.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Create response from decrypted JSON data.
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): static;
}
