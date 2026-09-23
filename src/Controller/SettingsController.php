<?php

declare(strict_types=1);

namespace App\Controller;

use App\Bitrix24\Bitrix24FunnelService;
use App\Bitrix24\CurrentPortalResolver;
use App\Bitrix24\Model\PortalConfig;
use App\Bitrix24\PortalRepository;
use App\Domain\Enum\DealSemantic;
use App\Support\View;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The "panel z konfiguracją pól, lejków" — lets whoever installed the app
 * on their Bitrix24 portal choose which funnels (deal categories) feed the
 * dashboard, and correct a stage's won/lost/in-progress classification if
 * Bitrix24's own semantics don't match how they actually use it.
 */
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly CurrentPortalResolver $portalResolver,
        private readonly Bitrix24FunnelService $funnels,
        private readonly PortalRepository $portals,
        private readonly View $view,
    ) {
    }

    #[Route('/ustawienia', name: 'settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $portal = $this->portalResolver->current();

        if ($portal === null) {
            $html = $this->view->renderPage('settings/not_installed', [], 'settings', 'Ustawienia');

            return new Response($html);
        }

        $funnels = $this->funnels->listFunnels($portal);
        $stagesByFunnel = [];
        foreach ($funnels as $funnel) {
            $stagesByFunnel[$funnel['id']] = $this->funnels->listStages($portal, $funnel['id']);
        }

        $saved = false;
        if ($request->isMethod('POST')) {
            $selectedFunnelIds = array_map('intval', $request->request->all('funnels'));

            $overrides = [];
            foreach ($stagesByFunnel as $stages) {
                foreach ($stages as $stage) {
                    $submitted = $request->request->get('semantic_' . $stage->value);
                    if (is_string($submitted) && $submitted !== $stage->semantic->value) {
                        $overrides[$stage->value] = $submitted;
                    }
                }
            }

            $portal = $portal->withConfig(new PortalConfig($selectedFunnelIds, $overrides));
            $this->portals->save($portal);
            $saved = true;

            // Re-read stages so the form reflects the just-saved overrides
            // (a submitted override changes what "current semantic" means).
            $stagesByFunnel = [];
            foreach ($funnels as $funnel) {
                $stagesByFunnel[$funnel['id']] = $this->funnels->listStages($portal, $funnel['id']);
            }
        }

        $html = $this->view->renderPage('settings/index', [
            'funnels' => $funnels,
            'stagesByFunnel' => $stagesByFunnel,
            'config' => $portal->config,
            'semantics' => DealSemantic::cases(),
            'saved' => $saved,
        ], 'settings', 'Ustawienia');

        return new Response($html);
    }
}
