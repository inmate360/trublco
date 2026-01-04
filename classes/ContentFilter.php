<?php
/**
 * Content Filter Class for Lustifieds
 * Detects and blocks racist, hate speech, and offensive content
 */

class ContentFilter {

    private $offensive_patterns = [
        // Racial slurs (obfuscated patterns to catch variations)
        '/n[i1!]gg[e3]r/i',
        '/n[i1!]gg[aA@4]/i',
        '/ch[i1!]nk/i',
        '/sp[i1!]c/i',
        '/k[i1!]ke/i',
        '/w[e3]tb[a@4]ck/i',
        '/b[e3][a@4]n[e3]r/i',
        '/r[a@4]gh[e3][a@4]d/i',
        '/t[o0]w[e3]lh[e3][a@4]d/i',
        '/s[a@4]nd\s*n[i1!]gg[e3]r/i',

        // Hate speech patterns
        '/k[i1!]ll\s*(all|the)\s*(jews|blacks|muslims|gays)/i',
        '/white\s*supremacy/i',
        '/h[i1!]tl[e3]r\s*w[a@4]s\s*r[i1!]ght/i',
        '/gas\s*the\s*jews/i',
        '/lynch(ing)?/i',

        // Generic offensive patterns
        '/f[a@4]gg[o0]t/i',
        '/tr[a@4]nn(y|ie)/i',
        '/d[i1!]k[e3]s?/i',
        '/r[e3]t[a@4]rd/i',
    ];

    private $context_patterns = [
        // Context-sensitive patterns (need manual review)
        '/white\s*power/i',
        '/black\s*power/i',
        '/14\s*words/i',
        '/88/i', // Neo-Nazi code
        '/swastika/i',
    ];

    public function isOffensive($text) {
        // Normalize text for checking
        $normalized = $this->normalizeText($text);

        // Check against offensive patterns
        foreach($this->offensive_patterns as $pattern) {
            if(preg_match($pattern, $normalized)) {
                return true;
            }
        }

        // Check context patterns (stricter - multiple matches trigger block)
        $context_matches = 0;
        foreach($this->context_patterns as $pattern) {
            if(preg_match($pattern, $normalized)) {
                $context_matches++;
            }
        }

        if($context_matches >= 2) {
            return true;
        }

        return false;
    }

    public function getFilteredText($text) {
        if($this->isOffensive($text)) {
            return null; // Block completely
        }
        return $text;
    }

    private function normalizeText($text) {
        // Remove excessive whitespace and special chars used to bypass filters
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(['*', '_', '-', '.'], '', $text);
        return $text;
    }

    public function getBlockReason() {
        return 'Your submission contains offensive or discriminatory language that violates our community guidelines.';
    }
}
?>
