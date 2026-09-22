<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tiny plain-PHP templating helper: renders a template file into a string
 * with $data extracted as local variables, then wraps it in the shared
 * layout. No template engine dependency — just output buffering.
 */
final class View
{
    public function __construct(private readonly string $templatesDir)
    {
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
     * @param array<string, mixed> $data
     */
    public function renderPage(string $template, array $data, string $activeNav, string $pageTitle): void
    {
        $content = $this->render($template, $data);
        echo $this->render('layout', array_merge($data, [
            'content' => $content,
            'activeNav' => $activeNav,
            'pageTitle' => $pageTitle,
        ]));
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render('partials/' . $template, $data);
    }
}
