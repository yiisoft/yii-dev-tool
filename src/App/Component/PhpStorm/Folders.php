<?php

namespace Yiisoft\YiiDevTool\App\Component\PhpStorm;

final class Folders
{
    private string $ideaPath;

    private array $excludeFolders = [];
    private array $sourceFolders = [];
    private array $testsFolders = [];

    public function __construct(string $ideaPath)
    {
        if (!is_dir($ideaPath)) {
            throw new \InvalidArgumentException("No .idea at $ideaPath.");
        }
        $this->ideaPath = $ideaPath;
    }

    public function excludeFolder(string $path)
    {
        $this->excludeFolders[] = $path;
    }

    public function sourceFolder(string $path)
    {
        $this->sourceFolders[] = $path;
    }

    public function testsFolder(string $path)
    {
        $this->testsFolders[] = $path;
    }

    public function write(): void
    {
        $entries = '';

        foreach ($this->excludeFolders as $excludeFolder) {
            $entries .= sprintf("      <excludeFolder url=\"file://\$MODULE_DIR$/%s\" />\n", $excludeFolder);
        }

        foreach ($this->sourceFolders as $sourceFolder) {
            $entries .= sprintf("      <sourceFolder url=\"file://\$MODULE_DIR$/%s\" isTestSource=\"false\" />\n", $sourceFolder);
        }

        foreach ($this->testsFolders as $testsFolder) {
            $entries .= sprintf("      <sourceFolder url=\"file://\$MODULE_DIR$/%s\" isTestSource=\"true\" />\n", $testsFolder);
        }

        $modulesContent = file_get_contents($this->ideaPath . '/modules.xml');
        if (!preg_match('~<module.*?filepath="\$PROJECT_DIR\$/\.idea/(.*?.iml)"~', $modulesContent, $matches)) {
            throw new \RuntimeException('Can not find module path in modules.xml.');
        }

        $imlPath = $this->ideaPath . '/' . $matches[1];
        $imlContent = file_get_contents($imlPath);

        $imlContent = preg_replace(
            '~<content url="file://\$MODULE_DIR\$">.*</content>~s',
            "<content url=\"file://\$MODULE_DIR\$\">\n" . $entries . "    </content>",
            $imlContent
        );

        file_put_contents($imlPath, $imlContent);
    }
}
