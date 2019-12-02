<?php

namespace Yiisoft\YiiDevTool\Component\Console;

use Yiisoft\YiiDevTool\Component\Package\Package;

/**
 * Determines whether to output messages in the current environment.
 * If a console command operates in a verbose mode, output all messages.
 * Otherwise, it output only those messages that are marked as important.
 */
class OutputManager
{
    /** @var YiiDevToolStyle */
    private $io;

    /** @var null|string */
    private $preparedPackageHeader;

    /** @var bool */
    private $nextMessageIsImportant = false;

    /** @var bool bool */
    private $outputDone = false;

    public function __construct(YiiDevToolStyle $io)
    {
        $this->io = $io;
    }

    public function hasColorSupport(): bool
    {
        return $this->io->hasColorSupport();
    }

    public function isVerbose(): bool
    {
        return $this->io->isVerbose();
    }

    /**
     * It only prepares a package header for output, but does not output it.
     * The header will be automatically displayed later before the first message that will require output.
     * If a console command operates in a verbose mode, a detailed header will be prepared, otherwise a short one.
     *
     * @param Package $package
     * @param string $header A detailed version of header.
     * Substring '{package}' will be replaced by the name of the package.
     * @return $this
     */
    public function preparePackageHeader(Package $package, string $header): self
    {
        $io = $this->io;

        $formattedPackageId = "<package>{$package->getId()}</package>";

        if ($io->isVerbose()) {
            $this->preparedPackageHeader = str_replace('{package}', $formattedPackageId, $header);
        } else {
            $this->preparedPackageHeader = $formattedPackageId;
        }

        return $this;
    }

    public function clearPreparedPackageHeader(): self
    {
        $this->preparedPackageHeader = null;

        return $this;
    }

    /**
     * Marks whether the next message is important or not.
     * Important messages are always displayed regardless of anything.
     *
     * @param bool $isImportant
     * @return $this
     */
    public function important($isImportant = true): self
    {
        $this->nextMessageIsImportant = $isImportant;

        return $this;
    }

    public function newLine($count = 1): self
    {
        $this->delegateOutputToIO('newLine', $count);

        return $this;
    }

    public function info($message): self
    {
        $this->delegateOutputToIO('writeln', $message);

        return $this;
    }

    public function warning($message): self
    {
        $this->delegateOutputToIO('warning', $message);

        return $this;
    }

    /**
     * Error messages are always displayed regardless of anything.
     * There is no need to mark them important.
     *
     * @param $message
     * @return $this
     */
    public function error($message): self
    {
        $this->delegateOutputToIO('error', $message, true);

        return $this;
    }

    public function success($message): self
    {
        $this->delegateOutputToIO('success', $message);

        return $this;
    }

    public function done(): self
    {
        $this->delegateOutputToIO('done');

        return $this;
    }

    public function nothingHasBeenOutput(): bool
    {
        return !$this->outputDone;
    }

    private function outputHeaderIfPrepared(): void
    {
        if ($this->preparedPackageHeader !== null) {
            $this->io->header($this->preparedPackageHeader);
            $this->preparedPackageHeader = null;
            $this->outputDone = true;
        }
    }

    private function delegateOutputToIO(string $method, $arg = null, bool $forceOutput = false): void
    {
        if ($forceOutput || $this->io->isVerbose() || $this->nextMessageIsImportant) {
            $this->outputHeaderIfPrepared();

            if ($arg === null) {
                $this->io->$method();
            } else {
                $this->io->$method($arg);
            }

            $this->outputDone = true;
            $this->nextMessageIsImportant = false;
        }
    }
}
