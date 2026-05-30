<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use Yiisoft\YiiDevTool\Infrastructure\Version;

use function preg_match;

final class ReleaseDescription
{
    /**
     * @param string[] $releaseNotes
     */
    public function getBody(
        string $packageName,
        Version $previousVersion,
        Version $versionToRelease,
        array $releaseNotes,
        bool $hasUpgradeNotes = false
    ): string {
        $body = implode("\n", $releaseNotes);

        if ($previousVersion->asString() === '') {
            return $body;
        }

        $changelogUrl = "https://github.com/$packageName/compare/$previousVersion...$versionToRelease";
        $body .= "\n\n[Full changelog]($changelogUrl)";

        if ($hasUpgradeNotes && $this->isMajorRelease($versionToRelease)) {
            $upgradeNotesUrl = "https://github.com/$packageName/blob/$versionToRelease/UPGRADE.md";
            $body .= "\n\nSee [UPGRADE.md]($upgradeNotesUrl) for upgrade notes.";
        }

        return $body;
    }

    private function isMajorRelease(Version $version): bool
    {
        return preg_match('/^\d+\.0\.0$/', $version->asString()) === 1;
    }
}
