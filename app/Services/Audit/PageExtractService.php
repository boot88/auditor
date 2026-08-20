<?php

namespace App\Services\Audit;

use DOMDocument;
use DOMXPath;

class PageExtractService
{
    public function extract(string $inputUrl, string $finalUrl, ?int $httpStatus, string $html): array
    {
        $maxText = (int) env('AUDIT_MAX_TEXT_CHARS', 25000);

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        $title = $this->firstText($xpath, '//title');
        $metaDescription = $this->metaContent($xpath, 'description');
        $metaRobots = $this->metaContent($xpath, 'robots');
        $canonical = $this->linkRel($xpath, 'canonical');

        $h1 = $this->firstText($xpath, '//h1');
        $h2 = $this->manyText($xpath, '//h2', 20);
        $h3 = $this->manyText($xpath, '//h3', 20);

        // links
        [$internalCount, $externalCount, $samples] = $this->extractLinks($xpath, $finalUrl);

        // images alt
        [$imgCount, $missingAlt] = $this->extractImagesAlt($xpath);

        // main text (rough)
        $mainText = $this->extractMainText($xpath);
        if (mb_strlen($mainText) > $maxText) {
            $mainText = mb_substr($mainText, 0, $maxText);
        }

        // language guess (very rough)
        $lang = $this->htmlLang($xpath) ?: $this->guessLang($mainText);

        return [
            'url' => $inputUrl,
            'final_url' => $finalUrl,
            'http_status' => $httpStatus,
            'title' => $title,
            'meta_description' => $metaDescription,
            'meta_robots' => $metaRobots,
            'canonical' => $canonical,
            'language_guess' => $lang,
            'headings' => [
                'h1' => $h1,
                'h2' => $h2,
                'h3' => $h3,
            ],
            'links' => [
                'internal_count' => $internalCount,
                'external_count' => $externalCount,
                'samples' => $samples,
            ],
            'images' => [
                'count' => $imgCount,
                'missing_alt_count' => $missingAlt,
            ],
            'main_text' => $mainText,
        ];
    }

    private function firstText(DOMXPath $xp, string $query): ?string
    {
        $n = $xp->query($query)?->item(0);
        if (!$n) return null;
        $t = trim(preg_replace('/\s+/u', ' ', $n->textContent ?? ''));
        return $t !== '' ? $t : null;
    }

    private function manyText(DOMXPath $xp, string $query, int $limit): array
    {
        $out = [];
        $nodes = $xp->query($query);
        if (!$nodes) return $out;

        foreach ($nodes as $node) {
            $t = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? ''));
            if ($t !== '') $out[] = $t;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    private function metaContent(DOMXPath $xp, string $name): ?string
    {
        $q = sprintf('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%s"]/@content', strtolower($name));
        $n = $xp->query($q)?->item(0);
        $t = $n ? trim($n->nodeValue) : null;
        return $t !== '' ? $t : null;
    }

    private function linkRel(DOMXPath $xp, string $rel): ?string
    {
        $q = sprintf('//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="%s"]/@href', strtolower($rel));
        $n = $xp->query($q)?->item(0);
        $t = $n ? trim($n->nodeValue) : null;
        return $t !== '' ? $t : null;
    }

    private function extractLinks(DOMXPath $xp, string $baseUrl): array
    {
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $internal = 0;
        $external = 0;
        $samples = [];

        $nodes = $xp->query('//a[@href]');
        if ($nodes) {
            foreach ($nodes as $a) {
                $href = trim($a->getAttribute('href'));
                if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
                    continue;
                }

                $abs = $this->toAbsoluteUrl($href, $baseUrl);
                if (!$abs) continue;

                $host = parse_url($abs, PHP_URL_HOST);
                if (!$host) continue;

                $isInternal = ($baseHost && $host === $baseHost);
                if ($isInternal) $internal++; else $external++;

                if (count($samples) < 10) {
                    $samples[] = $abs;
                }
            }
        }

        return [$internal, $external, $samples];
    }

    private function extractImagesAlt(DOMXPath $xp): array
    {
        $nodes = $xp->query('//img');
        $count = 0;
        $missingAlt = 0;

        if ($nodes) {
            foreach ($nodes as $img) {
                $count++;
                $alt = trim($img->getAttribute('alt') ?? '');
                if ($alt === '') $missingAlt++;
            }
        }

        return [$count, $missingAlt];
    }

    private function extractMainText(DOMXPath $xp): string
    {
        // Remove script/style/noscript nodes by ignoring them in extraction
        $nodes = $xp->query('//body//*[not(self::script or self::style or self::noscript)]/text()');
        $chunks = [];
        if ($nodes) {
            foreach ($nodes as $n) {
                $t = trim(preg_replace('/\s+/u', ' ', $n->textContent ?? ''));
                if ($t !== '' && mb_strlen($t) >= 2) {
                    $chunks[] = $t;
                }
            }
        }
        // de-dup adjacent repeats
        $text = implode("\n", array_slice($chunks, 0, 4000));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function htmlLang(DOMXPath $xp): ?string
    {
        $n = $xp->query('//html/@lang')?->item(0);
        $t = $n ? trim($n->nodeValue) : null;
        return $t !== '' ? $t : null;
    }

    private function guessLang(string $text): ?string
    {
        // ultra-simple guess ru/en by charset presence
        $ru = preg_match('/[А-Яа-яЁё]/u', $text) ? 1 : 0;
        $en = preg_match('/[A-Za-z]/u', $text) ? 1 : 0;
        if ($ru && !$en) return 'ru';
        if ($en && !$ru) return 'en';
        return null;
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): ?string
    {
        // If already absolute:
        if (preg_match('~^https?://~i', $href)) return $href;

        // protocol-relative
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $href;
        }

        $baseParts = parse_url($baseUrl);
        if (!$baseParts || empty($baseParts['scheme']) || empty($baseParts['host'])) return null;

        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $path = $baseParts['path'] ?? '/';

        // root-relative
        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$port}{$href}";
        }

        // relative to current dir
        $dir = preg_replace('~/[^/]*$~', '/', $path);
        return "{$scheme}://{$host}{$port}{$dir}{$href}";
    }
}
