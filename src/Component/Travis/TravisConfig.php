<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Component\Travis;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class TravisConfig
{
    private string $path;
    private string $content;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException("File does not exists. Path: $path");
        }

        $this->path = $path;
        $this->content = file_get_contents($path);

        if ($this->content === false) {
            throw new RuntimeException("Unable to read file $path");
        }
    }

    public function updateSlackNotificationsConfig(array $data): void
    {
        $notificationsSectionContent = $this->getNotificationsSectionContent();

        if ($notificationsSectionContent) {
            $sectionData = Yaml::parse($notificationsSectionContent);
        } else {
            $sectionData = ['notifications' => ['slack' => null]];
        }

        $sectionData['notifications']['slack'] = $data;
        $newNotificationsSectionContent = trim(Yaml::dump($sectionData, 100, 2));

        $this->replaceNotificationsSectionContent($newNotificationsSectionContent);
        $this->saveContentToFile();
    }

    private function getNotificationsSectionLines(): array
    {
        $contentLines = preg_split('#\R#', $this->content);

        $sectionLines = [];
        $sectionFound = false;
        foreach ($contentLines as $contentLineIndex => $contentLine) {
            if (mb_strpos($contentLine, 'notifications:', 0, 'UTF-8') === 0) {
                // This is the first line of notifications section
                $sectionFound = true;
                $sectionLines[$contentLineIndex] = $contentLine;
                continue;
            }

            if ($sectionFound) {
                if (mb_substr($contentLine, 0, 1, 'UTF-8') !== ' ') {
                    // This is the last line of the section
                    break;
                }

                $sectionLines[$contentLineIndex] = $contentLine;
            }
        }

        return $sectionLines;
    }

    private function getNotificationsSectionContent(): string
    {
        $sectionLines = $this->getNotificationsSectionLines();

        return implode("\n", $sectionLines);
    }

    private function replaceNotificationsSectionContent(string $newSectionContent): void
    {
        $contentLines = preg_split('#\R#', $this->content);
        $sectionLines = $this->getNotificationsSectionLines();
        $newSectionLines = preg_split('#\R#', $newSectionContent);

        $sectionLinesCount = count($sectionLines);
        if ($sectionLinesCount > 0) {
            // Case of replacing an existing section
            $firstSectionLineIndex = array_key_first($sectionLines);
            array_splice($contentLines, $firstSectionLineIndex, $sectionLinesCount, $newSectionLines);
        } else {
            // Case of creating a new section
            array_push($newSectionLines, '');
            array_push($contentLines, ...$newSectionLines);
        }

        $this->content = implode("\n", $contentLines);
    }

    private function saveContentToFile(): void
    {
        if (file_put_contents($this->path, $this->content) === false) {
            throw new RuntimeException("Unable to save file {$this->path}");
        }
    }
}
