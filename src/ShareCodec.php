<?php

namespace NineteenNinetyFour\Wishlist;

/**
 * Packs a list's references into a URL parameter, for the browser driver's
 * share links, and unpacks them again. resources/js/wishlist.js does the
 * same in the browser; the two must agree.
 *
 * References are joined with ".". An entry's UUID becomes its 16 bytes in
 * base64url (22 characters, not 36). Anything else ("tags::red", an ID
 * that isn't a UUID) becomes "~" and the base64url of its text. All of it
 * is URL-safe, so the parameter needs no escaping.
 */
final class ShareCodec
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @param  iterable<string>  $references
     */
    public static function encode(iterable $references): string
    {
        $tokens = [];

        foreach ($references as $reference) {
            $reference = (string) $reference;

            $tokens[] = preg_match(self::UUID, $reference)
                ? self::base64url(hex2bin(str_replace('-', '', $reference)))
                : '~'.self::base64url($reference);
        }

        return implode('.', $tokens);
    }

    /**
     * Anything that can't be read is skipped, as are repeats.
     *
     * @return array<int, string>
     */
    public static function decode(?string $encoded, ?int $limit = null): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }

        $references = [];

        foreach (explode('.', $encoded) as $token) {
            $reference = str_starts_with($token, '~')
                ? self::textFrom(substr($token, 1))
                : self::uuidFrom($token);

            if ($reference === null || $reference === '' || in_array($reference, $references, true)) {
                continue;
            }

            $references[] = $reference;

            if ($limit !== null && count($references) >= $limit) {
                break;
            }
        }

        return $references;
    }

    private static function uuidFrom(string $token): ?string
    {
        if (strlen($token) !== 22) {
            return null;
        }

        $bytes = self::unbase64url($token);

        if ($bytes === null || strlen($bytes) !== 16) {
            return null;
        }

        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function unbase64url(string $text): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]*$/', $text)) {
            return null;
        }

        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private static function textFrom(string $token): ?string
    {
        $text = self::unbase64url($token);

        return $text !== null && mb_check_encoding($text, 'UTF-8') ? $text : null;
    }
}
