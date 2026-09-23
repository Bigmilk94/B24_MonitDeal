<?php

declare(strict_types=1);

namespace App\Bitrix24;

use App\Bitrix24\Model\Portal;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around Bitrix24's REST API for one portal at a time: signs
 * every call with the portal's current access token, transparently
 * refreshes it via oauth.bitrix.info when expired (or when Bitrix24 itself
 * reports the token invalid), and pages through list methods automatically.
 *
 * This is the *only* place in the app that speaks HTTP to Bitrix24 —
 * Bitrix24CrmService and the settings/install controllers all go through it.
 */
final class Bitrix24Client
{
    private const OAUTH_TOKEN_URL = 'https://oauth.bitrix.info/oauth/token/';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly PortalRepository $portals,
        private readonly ClockInterface $clock,
        private readonly string $bitrix24ClientId,
        private readonly string $bitrix24ClientSecret,
    ) {
    }

    /**
     * Calls a single Bitrix24 REST method and returns its "result" payload.
     *
     * @param array<string, mixed> $params
     * @return mixed
     */
    public function call(Portal $portal, string $method, array $params = [])
    {
        $portal = $this->ensureFreshToken($portal);

        $data = $this->request($portal, $method, $params);

        if (isset($data['error']) && in_array($data['error'], ['expired_token', 'invalid_token'], true)) {
            $portal = $this->refresh($portal);
            $data = $this->request($portal, $method, $params);
        }

        if (isset($data['error'])) {
            throw new Bitrix24ApiException(sprintf(
                'Bitrix24 (%s): %s',
                $data['error'],
                $data['error_description'] ?? 'brak opisu błędu',
            ));
        }

        return $data['result'];
    }

    /**
     * Calls a list method and pages through every result (Bitrix24 caps
     * list responses at 50 rows per page and reports the next offset).
     *
     * @param array<string, mixed> $params
     * @return list<mixed>
     */
    public function callList(Portal $portal, string $method, array $params = []): array
    {
        $portal = $this->ensureFreshToken($portal);

        $all = [];
        $start = 0;

        do {
            $data = $this->request($portal, $method, $params + ['start' => $start]);

            if (isset($data['error']) && in_array($data['error'], ['expired_token', 'invalid_token'], true)) {
                $portal = $this->refresh($portal);
                $data = $this->request($portal, $method, $params + ['start' => $start]);
            }

            if (isset($data['error'])) {
                throw new Bitrix24ApiException(sprintf(
                    'Bitrix24 (%s): %s',
                    $data['error'],
                    $data['error_description'] ?? 'brak opisu błędu',
                ));
            }

            $result = $data['result'];
            // Some list methods (e.g. crm.activity.list) wrap rows one level deeper.
            $rows = array_is_list($result) ? $result : ($result['items'] ?? $result);
            foreach ($rows as $row) {
                $all[] = $row;
            }

            $next = $data['next'] ?? null;
            $start = $next !== null ? (int) $next : null;
        } while ($start !== null);

        return $all;
    }

    /**
     * Confirms a freshly-presented (access token, domain) pair is actually
     * valid against Bitrix24's real servers — used by the install/open
     * handshake before trusting an incoming POST enough to start a session
     * for it. A forged POST can shape the fields correctly, but it can't
     * make this call succeed without a real, currently-valid token.
     */
    public function verify(string $clientEndpoint, string $accessToken): bool
    {
        try {
            $response = $this->http->request('POST', $clientEndpoint . 'profile', [
                'body' => ['auth' => $accessToken],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
        } catch (\Throwable) {
            return false;
        }

        return !isset($data['error']) && isset($data['result']);
    }

    private function ensureFreshToken(Portal $portal): Portal
    {
        return $portal->isAccessTokenExpired($this->clock->now()) ? $this->refresh($portal) : $portal;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(Portal $portal, string $method, array $params): array
    {
        $response = $this->http->request('POST', $portal->clientEndpoint . $method, [
            'body' => $params + ['auth' => $portal->accessToken],
            'timeout' => 20,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        return $data;
    }

    public function refresh(Portal $portal): Portal
    {
        $response = $this->http->request('GET', self::OAUTH_TOKEN_URL, [
            'query' => [
                'grant_type' => 'refresh_token',
                'client_id' => $this->bitrix24ClientId,
                'client_secret' => $this->bitrix24ClientSecret,
                'refresh_token' => $portal->refreshToken,
            ],
            'timeout' => 20,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        if (isset($data['error'])) {
            throw new Bitrix24ApiException(
                'Nie udało się odświeżyć tokenu Bitrix24: ' . ($data['error_description'] ?? $data['error']),
            );
        }

        $expiresInSeconds = (int) ($data['expires_in'] ?? 3600);
        $updated = $portal->withTokens(
            (string) $data['access_token'],
            (string) $data['refresh_token'],
            $this->clock->now()->modify("+{$expiresInSeconds} seconds"),
        );

        $this->portals->save($updated);

        return $updated;
    }
}
