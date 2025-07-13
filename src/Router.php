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

    public function handle(): void
    {
        // Skip the homepage.
        if ($this->segments === ['']) {
            return;
        }

        if ($this->is_non_existent_php()) {
            $this->block();
        }

        if ($this->is_dot_slug()) {
            $this->respond();
        }

        if ($this->should_block_non_ascii_slug() && $this->is_non_ascii_slug()) {
            $this->respond();
        }

        if ($this->should_block_non_latin_slug() && $this->is_non_latin_slug()) {
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

        if ($this->segments[0] === 'robots.txt') {
            $this->render('text/plain', "User-agent: *\nDisallow:\n");
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
        header('Content-Type: ' . $mime, true);
        echo $content;

        exit;
    }

    protected function is_non_existent_php(): bool
    {
        return isset($_SERVER['REDIRECT_URL'])
            && stripos($_SERVER['REDIRECT_URL'], '.php') !== false;
    }

    protected function is_dot_slug(): bool
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
        if (preg_match(sprintf('#%s#', $sitemap_regexp), $this->segments[0]) === 1) {
            return false;
        }

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

    protected function should_block_non_latin_slug(): bool
    {
        return $this->block_non_latin_slug;
    }

    protected function should_block_non_ascii_slug(): bool
    {
        return $this->block_non_ascii_slug;
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
        $this->segments = explode('/', (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        // Path always begins with a slash.
        array_shift($this->segments);
    }

    protected function set_accepted_types(): void
    {
        $header_parts = explode(',', $_SERVER['HTTP_ACCEPT'] ?? '');

        $types = array_map(static function ($type) {
            return strtolower(trim(explode(';', $type)[0]));
        }, $header_parts);

        $this->accepted_types = array_filter($types);
    }

    protected function is_ajax_request(): bool
    {
        return ! empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
