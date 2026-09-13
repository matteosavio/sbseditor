<?php

declare(strict_types=1);

/**
 * File-backed store for the editor documents.
 *
 * Each pane is an HTML file named after its pane id (left.html, right.html).
 * The document language is the lang attribute on the root <html> element.
 * The last-updated time is the file's mtime. Writes are flock'd so two
 * overlapping autosaves cannot tear a snapshot.
 */
final class DocumentStore
{
    /** @var array<string, array<string, mixed>> */
    private array $panes;

    /** @var array<string, string> */
    private array $languages;

    /**
     * @param array<string, array<string, mixed>> $panes
     * @param array<string, string> $languages
     */
    public function __construct(
        private readonly string $directory,
        array $panes,
        array $languages,
        private readonly int $maxBytes,
    ) {
        $this->panes = $panes;
        $this->languages = $languages;
    }

    public function isKnownPane(string $id): bool
    {
        return isset($this->panes[$id]) && preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
    }

    public function isKnownLanguage(string $lang): bool
    {
        return isset($this->languages[$lang]);
    }

    /**
     * @return array<string, array{id: string, html: string, lang: string, updated_at: ?string}>
     */
    public function readAll(): array
    {
        $documents = [];

        foreach (array_keys($this->panes) as $id) {
            $documents[$id] = $this->read($id);
        }

        return $documents;
    }

    /**
     * @return array{id: string, html: string, lang: string, updated_at: ?string}
     */
    public function read(string $id): array
    {
        $this->assertPane($id);
        $this->ensureDirectory();

        $defaultLang = $this->defaultLang($id);
        $path = $this->pathFor($id);
        if (!is_file($path)) {
            return ['id' => $id, 'html' => '', 'lang' => $defaultLang, 'updated_at' => null];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open document for reading.');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Unable to lock document for reading.');
            }

            $raw = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($raw === false) {
            return ['id' => $id, 'html' => '', 'lang' => $defaultLang, 'updated_at' => null];
        }

        $parsed = $this->parseStored($raw, $defaultLang);

        return [
            'id' => $id,
            'html' => $parsed['html'],
            'lang' => $parsed['lang'],
            'updated_at' => $this->mtimeIso($path),
        ];
    }

    /**
     * @return array{id: string, html: string, lang: string, updated_at: string}
     */
    public function write(string $id, string $html, string $lang): array
    {
        $this->assertPane($id);

        if (strlen($html) > $this->maxBytes) {
            throw new InvalidArgumentException('Document is larger than the configured limit.');
        }

        $lang = $this->normalizeLang($lang, $this->defaultLang($id));
        $clean = Sanitizer::clean($html);
        $payload = $this->wrapDocument($clean, $lang);

        $this->ensureDirectory();
        $path = $this->pathFor($id);
        $handle = fopen($path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to open document for writing.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock document for writing.');
            }

            ftruncate($handle, 0);
            rewind($handle);
            if (fwrite($handle, $payload) === false) {
                throw new RuntimeException('Unable to write document.');
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return [
            'id' => $id,
            'html' => $clean,
            'lang' => $lang,
            'updated_at' => $this->mtimeIso($path) ?? gmdate('c'),
        ];
    }

    /**
     * @return array{html: string, lang: string}
     */
    private function parseStored(string $raw, string $defaultLang): array
    {
        if (preg_match('/<html\b/i', $raw) !== 1 || !class_exists(DOMDocument::class)) {
            return ['html' => Sanitizer::clean($raw), 'lang' => $defaultLang];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $htmlElement = $document->getElementsByTagName('html')->item(0);
        $lang = $defaultLang;
        if ($htmlElement instanceof DOMElement) {
            $lang = $this->normalizeLang($htmlElement->getAttribute('lang'), $defaultLang);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        $fragment = '';
        if ($body instanceof DOMElement) {
            foreach ($body->childNodes as $child) {
                $fragment .= (string) $document->saveHTML($child);
            }
        }

        return [
            'html' => Sanitizer::clean($fragment),
            'lang' => $lang,
        ];
    }

    private function wrapDocument(string $html, string $lang): string
    {
        return '<!DOCTYPE html>' . "\n"
            . '<html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '">' . "\n"
            . '<head><meta charset="utf-8"></head>' . "\n"
            . '<body>' . "\n"
            . $html . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    private function defaultLang(string $id): string
    {
        $configured = isset($this->panes[$id]['lang']) && is_string($this->panes[$id]['lang'])
            ? $this->panes[$id]['lang']
            : 'en';

        return $this->normalizeLang($configured, 'en');
    }

    private function normalizeLang(string $lang, string $fallback): string
    {
        $lang = strtolower(trim($lang));
        if ($this->isKnownLanguage($lang)) {
            return $lang;
        }

        return $this->isKnownLanguage($fallback) ? $fallback : (array_key_first($this->languages) ?? 'en');
    }

    private function assertPane(string $id): void
    {
        if (!$this->isKnownPane($id)) {
            throw new InvalidArgumentException('Unknown editor pane.');
        }
    }

    private function pathFor(string $id): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.html';
    }

    private function mtimeIso(string $path): ?string
    {
        $mtime = filemtime($path);
        return $mtime === false ? null : gmdate('c', $mtime);
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create the storage directory.');
        }
    }
}
