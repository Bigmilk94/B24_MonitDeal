<?php

declare(strict_types=1);

namespace App\Bitrix24;

use App\Bitrix24\Model\Portal;
use App\Bitrix24\Model\PortalConfig;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bitrix24 opens a local application by POSTing fresh auth credentials
 * (AUTH_ID/REFRESH_ID/member_id/DOMAIN/...) straight to the app's handler
 * URL — every single time the iframe loads, not just on first install.
 * This listener runs before routing: it recognizes that POST shape,
 * verifies the token is real (not just correctly-shaped), upserts the
 * portal record and starts a session for it, then rewrites the request to
 * look like a normal GET so the ordinary dashboard route renders it.
 *
 * Every other request (plain navigation clicks inside the iframe) has none
 * of these fields and passes through untouched.
 */
final class Bitrix24HandshakeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Bitrix24Client $client,
        private readonly PortalRepository $portals,
        private readonly CurrentPortalResolver $resolver,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Higher than RouterListener's default priority (32), so the method
        // rewrite below happens *before* the route is matched.
        return [KernelEvents::REQUEST => ['onKernelRequest', 40]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->isMethod('POST')) {
            return;
        }

        $request = $event->getRequest();
        // Bitrix24 splits the handshake across both: DOMAIN/PROTOCOL/LANG/
        // APP_SID arrive on the query string, while AUTH_ID/REFRESH_ID/
        // member_id/AUTH_EXPIRES arrive in the POST body — confirmed
        // against Bitrix24's own local-application docs (a literal
        // $_REQUEST dump would blur this, but Symfony keeps them apart).
        $authId = $request->request->get('AUTH_ID');
        $refreshId = $request->request->get('REFRESH_ID');
        $memberId = $request->request->get('member_id');
        $domain = $request->query->get('DOMAIN');

        if (!is_string($authId) || !is_string($refreshId) || !is_string($memberId) || !is_string($domain) || $authId === '' || $domain === '') {
            return;
        }

        $protocol = $request->query->get('PROTOCOL', '1') === '0' ? 'http' : 'https';
        $clientEndpoint = "{$protocol}://{$domain}/rest/";

        if (!$this->client->verify($clientEndpoint, $authId)) {
            $this->logger->warning('Odrzucono niepoprawny handshake Bitrix24 (token nie zweryfikował się w portalu).', [
                'domain' => $domain,
                'member_id' => $memberId,
            ]);

            return;
        }

        $expiresIn = (int) $request->request->get('AUTH_EXPIRES', 3600);
        $existing = $this->portals->find($memberId);

        $portal = new Portal(
            $memberId,
            $domain,
            $clientEndpoint,
            $authId,
            $refreshId,
            $this->clock->now()->modify("+{$expiresIn} seconds"),
            $existing?->installedAt ?? $this->clock->now(),
            $existing?->config ?? new PortalConfig(),
        );

        $this->portals->save($portal);
        $this->resolver->activate($portal);

        // The handshake POST always targets the app's registered handler
        // path (our dashboard route); make it look like the GET that route
        // actually expects, now that the side effects above are done.
        $request->setMethod('GET');
    }
}
