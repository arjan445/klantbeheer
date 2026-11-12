<?php

/**
 * Small view helpers.
 *
 *   post_link($url, $label, $confirm)  a link-looking button that POSTs (with CSRF)
 */

if (! function_exists('post_link')) {
    /**
     * A button that looks like a link and sends a POST request with the CSRF
     * token. Use it for anything that changes data (delete, switch on/off):
     * a plain <a href> is a GET and can be triggered from another website.
     *
     * Never place it inside another <form>.
     *
     * @param string      $url     Where to post to (site_url / base_url)
     * @param string      $label   HTML for the button (escape user data yourself)
     * @param string|null $confirm Optional confirm() question
     * @param string      $class   CSS classes for the button
     */
    function post_link(string $url, string $label, ?string $confirm = null, string $class = 'text-danger'): string
    {
        $onsubmit = $confirm === null ? '' : ' onsubmit="return confirm(' . esc(json_encode($confirm), 'attr') . ');"';

        return '<form action="' . esc($url, 'attr') . '" method="post" class="d-inline"' . $onsubmit . '>'
            . csrf_field()
            . '<button type="submit" class="btn btn-link p-0 align-baseline ' . esc($class, 'attr') . '">' . $label . '</button>'
            . '</form>';
    }
}

if (! function_exists('klantmodus')) {
    /** Klantmodus aan? Dan worden inkoopprijzen, marges en leveranciers verborgen (cookie per browser). */
    function klantmodus(): bool
    {
        return ($_COOKIE['klantmodus'] ?? '') === '1';
    }
}
