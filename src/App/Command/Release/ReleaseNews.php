<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use Yiisoft\YiiDevTool\Infrastructure\Changelog;

final class ReleaseNews
{
    /**
     * @param string[] $releaseNotes
     *
     * @return string[]
     */
    public function getChanges(array $releaseNotes): array
    {
        $changes = [];

        foreach ($releaseNotes as $note) {
            $note = trim($note);
            if ($note === '') {
                continue;
            }

            $note = preg_replace('~\R\s*~', ' ', $note);
            $note = preg_replace(
                '~^- (?:' . implode('|', Changelog::TYPES) . ')(?: #\d+(?:, #\d+)*)?:\s+~',
                '',
                $note
            );
            $note = preg_replace('~^- \s*~', '', $note);
            $note = preg_replace('~\s+\(@[^)]*\)$~', '', $note);

            $changes[] = '- ' . $note;
        }

        return $changes;
    }
}
