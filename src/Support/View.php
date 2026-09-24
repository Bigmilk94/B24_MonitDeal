<?php

declare(strict_types=1);

namespace App\Support;

use App\Bitrix24\CurrentPortalResolver;

/**
 * Tiny plain-PHP templating helper: renders a template file into a string
 * with $data extracted as local variables, then wraps it in the shared
 * layout. No template engine dependency — just output buffering.
 */
final class View
{
    public function __construct(
        private readonly string $templatesDir,
        private readonly CurrentPortalResolver $portalResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->templatesDir . '/' . $template . '.php';

        return (string) ob_get_clean();
    }

    /**
     * Renders a page template wrapped in the shared layout and returns the
     * resulting HTML string — the controller wraps it in a Response.
     *
     * @param array<string, mixed> $data
     */
    public function renderPage(string $template, array $data, string $activeNav, string $pageTitle): string
    {
        $content = $this->render($template, $data);

        return $this->render('layout', array_merge($data, [
            'content' => $content,
            'activeNav' => $activeNav,
            'pageTitle' => $pageTitle,
            // Bitrix24 otwiera aplikację w iframe i pokazuje własny ekran
            // "Ładowanie aplikacji", dopóki strona w środku nie zawoła
            // BX24.init() — layout.php dołącza SDK tylko gdy faktycznie
            // jesteśmy otwarci z poziomu zainstalowanego portalu.
            'bitrix24Embedded' => $this->portalResolver->current() !== null,
        ]));
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render('partials/' . $template, $data);
    }
}
