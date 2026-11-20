<?php
declare(strict_types=1);

/**
 * Safe transport error messages
 *
 * @package CoCart\SDK\Http
 */

namespace CoCart\Http;

/**
 * Keeps request URLs' query strings out of transport error messages.
 *
 * HTTP libraries name the request URL in their errors (Guzzle ends with
 * "for https://…", PHP stream warnings start with "file_get_contents(https://…)"),
 * and its query string can carry the cart key. An app logging or showing
 * the exception message would leak it.
 */
final class SafeErrorMessage
{
    /**
     * Strip the query string and fragment from every URL in a message
     *
     * @param string $message The transport library's error message
     * @return string
     */
    public static function from(string $message): string
    {
        return preg_replace('~(https?://[^\s?#"\'<>()]*)[?#][^\s"\'<>()]*~i', '$1', $message) ?? $message;
    }
}
