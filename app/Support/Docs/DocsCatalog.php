<?php

namespace App\Support\Docs;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The public documentation: Markdown files in resources/docs/{locale}/NN-slug.md with a small header
 * (title, summary, status, experts). The English edition is the reference; a missing translation falls back to it.
 */
class DocsCatalog
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $memo = [];

    public function locale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return is_dir($this->dir($locale)) ? $locale : 'en';
    }

    /** @return list<array{slug: string, order: int, title: string, summary: string, status: string, experts: list<string>}> */
    public function pages(?string $locale = null): array
    {
        $locale = $this->locale($locale);

        return $this->memo[$locale] ??= $this->load($locale);
    }

    /**
     * @return array{slug: string, order: int, title: string, summary: string, status: string, experts: list<string>, html: string, headings: list<array{id: string, text: string}>, fallback: bool}|null
     */
    public function page(string $slug, ?string $locale = null): ?array
    {
        $requested = $locale ?? app()->getLocale();
        $wanted = $this->locale($requested);
        $fallback = $wanted !== $requested;
        $meta = $this->find($wanted, $slug);

        if ($meta === null && $wanted !== 'en') {
            $meta = $this->find('en', $slug);
            $fallback = $meta !== null;
        }

        if ($meta === null) {
            return null;
        }

        [$html, $headings] = $this->render($meta['body']);
        unset($meta['body']);

        return [...$meta, 'html' => $html, 'headings' => $headings, 'fallback' => $fallback];
    }

    /** @return list<string> */
    public function slugs(): array
    {
        return array_column($this->pages('en'), 'slug');
    }

    private function dir(string $locale): string
    {
        return rtrim((string) config('docs.path'), '\\/').DIRECTORY_SEPARATOR.$locale;
    }

    /** @return list<array<string, mixed>> */
    private function load(string $locale): array
    {
        $pages = [];

        foreach (glob($this->dir($locale).DIRECTORY_SEPARATOR.'*.md') ?: [] as $file) {
            $pages[] = $this->parse($file);
        }

        usort($pages, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(static function (array $page): array {
            unset($page['body']);

            return $page;
        }, $pages);
    }

    /** @return array<string, mixed>|null */
    private function find(string $locale, string $slug): ?array
    {
        foreach (glob($this->dir($locale).DIRECTORY_SEPARATOR.'*.md') ?: [] as $file) {
            if (preg_match('/^\d+-(.+)\.md$/', basename($file), $m) === 1 && $m[1] === $slug) {
                return $this->parse($file);
            }
        }

        return null;
    }

    /** @return array{slug: string, order: int, title: string, summary: string, status: string, experts: list<string>, body: string} */
    private function parse(string $file): array
    {
        preg_match('/^(\d+)-(.+)\.md$/', basename($file), $m);
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        $meta = [];
        $body = $raw;

        if (preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $parts) === 1) {
            foreach (explode("\n", $parts[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $meta[trim($key)] = trim($value);
                }
            }
            $body = $parts[2];
        }

        return [
            'slug' => $m[2],
            'order' => (int) $m[1],
            'title' => $meta['title'] ?? $m[2],
            'summary' => $meta['summary'] ?? '',
            'status' => in_array($meta['status'] ?? '', ['available', 'partial', 'research', 'planned'], true) ? $meta['status'] : 'available',
            'experts' => array_values(array_filter(array_map('trim', explode(',', $meta['experts'] ?? '')))),
            'body' => $body,
        ];
    }

    /** @return array{0: string, 1: list<array{id: string, text: string}>} */
    private function render(string $markdown): array
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['apply_id_to_heading' => true, 'insert' => 'none', 'id_prefix' => '', 'fragment_prefix' => ''],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $html = (string) (new MarkdownConverter($environment))->convert($markdown);
        $headings = [];

        if (preg_match_all('/<h2 id="([^"]+)">(.*?)<\/h2>/s', $html, $found, PREG_SET_ORDER) > 0) {
            foreach ($found as $h) {
                $headings[] = ['id' => $h[1], 'text' => trim(strip_tags($h[2]))];
            }
        }

        return [$html, $headings];
    }
}
