<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Command\Release;

use Yiisoft\YiiDevTool\Infrastructure\Version;

final class ReleaseDescription
{
    /**
     * @param string[] $releaseNotes
     */
    public function getBody(
        string $packageName,
        Version $previousVersion,
        Version $versionToRelease,
        array $releaseNotes
    ): string {
        $body = implode("\n", $releaseNotes);

        if ($previousVersion->asString() === '') {
            return $body;
        }

        $changelogUrl = "https://github.com/$packageName/compare/$previousVersion...$versionToRelease";

        return $body . "\n\n[Full changelog]($changelogUrl)";
    }
}
