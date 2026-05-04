<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Match title + description from `config/match.php` / `.env` (MATCH_TITLE, MATCH_DESCRIPTION).
 */
final class MatchNotificationCopy
{
    public static function title(): string
    {
        return (string) config('match.title');
    }

    /**
     * Description with optional `:game` replaced when a game name is known.
     */
    public static function description(string $trimmedGameName = ''): string
    {
        $template = (string) config('match.description');
        $game = trim($trimmedGameName);
        if (! str_contains($template, ':game')) {
            return $template;
        }
        if ($game !== '') {
            return str_replace(':game', $game, $template);
        }

        return trim(str_replace(':game', '', $template));
    }
}
