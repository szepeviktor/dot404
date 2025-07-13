<?php

declare(strict_types=1);

namespace SzepeViktor\WordPress\Dot404;

class Router
{
    protected const PIXEL_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMB/6W3WQAAAABJRU5ErkJggg==';

    protected bool $block_non_latin_slug;
    protected bool $block_non_ascii_slug;
    /** @var list<string> */
    protected array $segments;
    /** @var list<string> */
    protected array $accepted_types;

    public function __construct()
    {
        $this->block_non_latin_slug = true;
        $this->block_non_ascii_slug = false;

        $this->set_segments();
        $this->set_accepted_types();
    }

    public function allow_non_latin_slug(): void
    {
        $this->block_non_latin_slug = false;
    }

    public function disallow_non_ascii_slug(): void
    {
        $this->block_non_ascii_slug = true;
    }

    public function handle(): void
    {
        // Skip the homepage.
        if ($this->segments === ['']) {
            return;
        }

        if ($this->is_non_existent_php()) {
            $this->block();
        }

        if ($this->is_core_dot_slug()) {
            return;
        }

        if ($this->is_dot_slug()) {
            $this->respond();
        }

        if ($this->block_non_latin_slug && $this->is_non_ascii_slug()) {
            $this->respond();
        }

        if ($this->block_non_ascii_slug && $this->is_non_latin_slug()) {
            $this->respond();
        }

        // Let core handle the request.
    }

    /**
     * @return never
     */
    protected function respond(): void
    {
        if ($this->is_ajax_request()) {
            if ($this->accepts('application/json')) {
                $this->render('application/json', '{"error":"Post not found"}');
            } elseif ($this->accepts('application/xml')) {
                $this->render('application/xml', '<?xml version="1.0"?><response><error>Post not found</error></response>');
            } elseif ($this->accepts('text/plain')) {
                $this->render('text/plain', 'Post not found');
            } else {
                $this->render('text/html', '<h1>404<h1><p>Post not found</p>');
            }
        }

        if ($this->segments[0] === 'favicon.ico') {
            $this->render('image/png', base64_decode(self::PIXEL_B64));
        }

        if (
            preg_match('/\.(jpe?g|png|gif|ico|webp|bmp)$/', end($this->segments)) === 1
            || $this->accepts('image/*')
        ) {
            $this->render('image/png', base64_decode(self::PIXEL_B64));
        }

        // Default response
        $this->render('text/html', '<h1>404<h1><p>Post not found</p>');
    }

    /**
     * @return never
     */
    protected function block(): void
    {
        error_log('Break-in attempt detected: dot404_nonexistent_php');

        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden';

        exit;
    }

    /**
     * @return never
     */
    protected function render(string $mime, string $content): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: ' . $mime);

        echo $content;

        exit;
    }

    protected function is_non_existent_php(): bool
    {
        $redirect_url = filter_input(INPUT_SERVER, 'REDIRECT_URL', FILTER_SANITIZE_URL);

        return is_string($redirect_url) && stripos($redirect_url, '.php') !== false;
    }

    protected function is_core_dot_slug(): bool
    {
        // wp rewrite list --fields=match | grep --fixed-strings '\.'
        $sitemap_regexp = implode('|', [
            'sitemap_index\.xml$',
            '([^/]+?)-sitemap([0-9]+)?\.xml$',
            '([a-z]+)?-?sitemap\.xsl$',
            '^wp-sitemap\.xml$',
            '^wp-sitemap\.xsl$',
            '^wp-sitemap-index\.xsl$',
            '^wp-sitemap-([a-z]+?)-([a-z\d_-]+?)-(\d+?)\.xml$',
            '^wp-sitemap-([a-z]+?)-(\d+?)\.xml$',
        ]);

        return preg_match(sprintf('#%s#', $sitemap_regexp), $this->segments[0]) === 1;
    }

    protected function is_dot_slug(): bool
    {
        foreach ($this->segments as $segment) {
            if (preg_match('/\./', $segment) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function is_non_ascii_slug(): bool
    {
        foreach ($this->segments as $segment) {
            if (preg_match('/[^0-9A-Z_a-z-]/', $segment) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function is_non_latin_slug(): bool
    {
        foreach ($this->segments as $segment) {
            if (preg_match('/[^\p{N}\p{L}]/u', $segment) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function accepts(string $mime): bool
    {
        foreach ($this->accepted_types as $type) {
            if ($type === '*/*') {
                continue;
            }

            if ($type === $mime || fnmatch($type, $mime)) {
                return true;
            }
        }

        return false;
    }

    protected function set_segments(): void
    {
        $request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);

        $this->segments = explode(
            '/',
            (string) parse_url(is_string($request_uri) ? urldecode($request_uri) : '/', PHP_URL_PATH)
        );
        // Path always begins with a slash.
        array_shift($this->segments);
    }

    protected function set_accepted_types(): void
    {
        $header = filter_input(INPUT_SERVER, 'HTTP_ACCEPT', FILTER_UNSAFE_RAW);
        $header_parts = explode(',', is_string($header) ? $header : '');

        $types = array_map(static function ($type) {
            return strtolower(trim(explode(';', $type)[0]));
        }, $header_parts);

        $this->accepted_types = array_filter($types);
    }

    protected function is_ajax_request(): bool
    {
        $header = filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH', FILTER_UNSAFE_RAW);

        return is_string($header) && strtolower($header) === 'xmlhttprequest';
    }
}
