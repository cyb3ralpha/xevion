<?php
/**
 * Lightweight text screening for titles, custom categories, descriptions and names.
 * This is a first line of defence, not a substitute for human moderation: anything
 * with a custom category is always routed to the moderation queue as well.
 */
declare(strict_types=1);

function filter_normalize(string $text): string
{
    $t = mb_strtolower($text, 'UTF-8');
    $t = strtr($t, ['0' => 'o', '1' => 'i', '!' => 'i', '3' => 'e', '4' => 'a', '@' => 'a', '5' => 's', '$' => 's', '7' => 't', '+' => 't']);
    $t = preg_replace('/[^\p{L}\s]+/u', ' ', $t) ?? '';
    $t = preg_replace('/(\p{L})\1{2,}/u', '$1$1', $t) ?? ''; // "fatttt" -> "fatt"
    return ' ' . trim(preg_replace('/\s+/u', ' ', $t) ?? '') . ' ';
}

/** Returns the first blocked term found, or null when the text is acceptable. */
function find_blocked_term(string $text): ?string
{
    if (trim($text) === '') {
        return null;
    }
    $norm = filter_normalize($text);
    $compact = str_replace(' ', '', $norm);
    $terms = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)setting('blocked_terms', '')) ?: []));
    foreach ($terms as $term) {
        $t = trim(filter_normalize($term));
        if ($t === '') {
            continue;
        }
        if (str_contains($norm, ' ' . $t . ' ') || str_contains($norm, ' ' . $t . 's ')) {
            return $term;
        }
        // Spaced-out evasions ("s e x y") — only for longer terms to avoid false positives.
        if (mb_strlen($t) >= 6 && str_contains($compact, str_replace(' ', '', $t))) {
            return $term;
        }
    }
    return null;
}
