<?php

declare(strict_types=1);

namespace App\Bitrix24\Model;

use DateTimeImmutable;

/**
 * One installed Bitrix24 portal. Keyed by member_id (Bitrix24's stable
 * portal identifier — survives domain renames, unlike the domain itself).
 */
final class Portal
{
    public function __construct(
        public readonly string $memberId,
        public readonly string $domain,
        public readonly string $clientEndpoint,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $installedAt,
        public readonly PortalConfig $config,
    ) {
    }

    public function isAccessTokenExpired(DateTimeImmutable $now): bool
    {
        // A small safety margin so we refresh slightly before Bitrix24
        // itself would reject the token.
        return $this->expiresAt <= $now->modify('+30 seconds');
    }

    public function withTokens(string $accessToken, string $refreshToken, DateTimeImmutable $expiresAt): self
    {
        return new self(
            $this->memberId,
            $this->domain,
            $this->clientEndpoint,
            $accessToken,
            $refreshToken,
            $expiresAt,
            $this->installedAt,
            $this->config,
        );
    }

    public function withConfig(PortalConfig $config): self
    {
        return new self(
            $this->memberId,
            $this->domain,
            $this->clientEndpoint,
            $this->accessToken,
            $this->refreshToken,
            $this->expiresAt,
            $this->installedAt,
            $config,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'memberId' => $this->memberId,
            'domain' => $this->domain,
            'clientEndpoint' => $this->clientEndpoint,
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresAt' => $this->expiresAt->format(DATE_ATOM),
            'installedAt' => $this->installedAt->format(DATE_ATOM),
            'config' => $this->config->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['memberId'],
            (string) $data['domain'],
            (string) $data['clientEndpoint'],
            (string) $data['accessToken'],
            (string) $data['refreshToken'],
            new DateTimeImmutable((string) $data['expiresAt']),
            new DateTimeImmutable((string) $data['installedAt']),
            PortalConfig::fromArray($data['config'] ?? []),
        );
    }
}
