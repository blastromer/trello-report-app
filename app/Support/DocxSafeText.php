<?php

namespace App\Support;

/**
 * Sanitize strings for PHPWord / Office Open XML.
 *
 * PHPWord's chart writer uses writeRaw() for several nodes; unescaped &lt; &amp; etc.
 * from Trello card titles break document.xml ("Illegal name character" in Word).
 */
final class DocxSafeText
{
    public static function stripIllegalXmlCharacters(string $s): string
    {
        if ($s === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($converted !== false) {
                $s = $converted;
            }
        }

        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
    }

    /**
     * For PHPWord chart XML inserted via writeRaw() (title, axis title, series name).
     */
    public static function escapeForChartRawXml(string $s): string
    {
        $s = self::stripIllegalXmlCharacters($s);

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
