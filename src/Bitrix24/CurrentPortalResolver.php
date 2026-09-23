<?php

declare(strict_types=1);

namespace App\Bitrix24;

use App\Bitrix24\Model\Portal;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Figures out which installed Bitrix24 portal the current HTTP request
 * belongs to. The app is a normal server-rendered, multi-page site, but it
 * runs inside a Bitrix24 iframe: only the very first load of that iframe
 * carries Bitrix24's auth POST params (see Bitrix24InstallController) —
 * every link clicked afterwards is a plain GET from within the iframe. A
 * session (established on that first load) is what lets those later
 * requests still know which portal they're serving.
 */
final class CurrentPortalResolver
{
    private const SESSION_KEY = 'b24_member_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PortalRepository $portals,
    ) {
    }

    public function current(): ?Portal
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $memberId = $request->getSession()->get(self::SESSION_KEY);

        return is_string($memberId) ? $this->portals->find($memberId) : null;
    }

    public function activate(Portal $portal): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $request?->getSession()->set(self::SESSION_KEY, $portal->memberId);
    }
}
