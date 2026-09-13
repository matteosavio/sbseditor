<?php

declare(strict_types=1);

/**
 * Allow-list HTML sanitizer for editor content.
 *
 * Stored HTML is written straight back into the page on the next load, so the
 * server never trusts what the browser sends: anything outside the allow-list
 * below is unwrapped, and every surviving attribute is re-validated.
 */
final class Sanitizer
{
    /** Tag => attributes that may survive on it. */
    private const ALLOWED = [
        'p' => ['style'],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'del' => [],
        'code' => ['class'],
        'pre' => [],
        'blockquote' => [],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'h5' => ['style'],
        'h6' => ['style'],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'hr' => [],
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    /** Tags removed together with everything inside them. */
    private const DROP_SUBTREE = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'link', 'meta',
        'base', 'svg', 'math', 'noscript', 'template', 'audio', 'video',
        'source', 'track', 'canvas', 'frame', 'frameset',
    ];

    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    private const ALLOWED_ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            return self::cleanWithoutDom($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            . '</head><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($body);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= (string) $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }

            if (!$node instanceof DOMElement) {
                $parent->removeChild($node);
                continue;
            }

            $name = strtolower($node->nodeName);

            if (in_array($name, self::DROP_SUBTREE, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (!array_key_exists($name, self::ALLOWED)) {
                self::cleanChildren($node);
                self::unwrap($node);
                continue;
            }

            self::cleanAttributes($node, self::ALLOWED[$name]);
            self::cleanChildren($node);
        }
    }

    /** Replaces an element with its children, keeping the text intact. */
    private static function unwrap(DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    /**
     * @param list<string> $allowed
     */
    private static function cleanAttributes(DOMElement $node, array $allowed): void
    {
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;

            if (!in_array($name, $allowed, true)) {
                $node->removeAttribute($attribute->nodeName);
                continue;
            }

            switch ($name) {
                case 'href':
                    $value = self::cleanUrl($value);
                    break;
                case 'style':
                    $value = self::cleanStyle($value);
                    break;
                case 'class':
                    $value = self::cleanClass($value);
                    break;
                case 'target':
                    $target = strtolower(trim($value));
                    $value = in_array($target, ['_blank', '_self'], true) ? $target : null;
                    break;
                case 'start':
                    $value = ctype_digit(trim($value)) ? (string) (int) trim($value) : null;
                    break;
                default:
                    $value = trim($value);
                    break;
            }

            if ($value === null || $value === '') {
                $node->removeAttribute($attribute->nodeName);
                continue;
            }

            $node->setAttribute($name, $value);
        }

        if (strtolower($node->nodeName) === 'a' && $node->getAttribute('target') === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    private static function cleanUrl(string $value): ?string
    {
        $value = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $value);
        if ($value === '') {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme === false) {
            return null;
        }

        if ($scheme === null) {
            return $value;
        }

        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true) ? $value : null;
    }

    /** Text alignment is the only declaration the editor is allowed to store. */
    private static function cleanStyle(string $value): ?string
    {
        $kept = [];

        foreach (explode(';', $value) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $setting = strtolower(trim($parts[1]));

            if ($property === 'text-align' && in_array($setting, self::ALLOWED_ALIGNMENTS, true)) {
                $kept[] = 'text-align: ' . $setting;
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }

    /** Only the `language-*` hints emitted by code blocks survive. */
    private static function cleanClass(string $value): ?string
    {
        $classes = preg_split('/\s+/', trim($value)) ?: [];
        $kept = array_filter(
            $classes,
            static fn (string $class): bool => preg_match('/^language-[A-Za-z0-9#+._-]{1,30}$/', $class) === 1
        );

        return $kept === [] ? null : implode(' ', $kept);
    }

    /**
     * Degraded path for builds without ext-dom: keep the allowed tags but drop
     * every attribute, since we cannot validate them reliably.
     */
    private static function cleanWithoutDom(string $html): string
    {
        $allowed = '<' . implode('><', array_keys(self::ALLOWED)) . '>';
        $html = strip_tags($html, $allowed);

        return (string) preg_replace('#<\s*([a-z0-9]+)(?:\s[^>]*?)?(/?)>#i', '<$1$2>', $html);
    }
}
