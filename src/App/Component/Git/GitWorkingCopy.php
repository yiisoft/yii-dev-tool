<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\App\Component\Git;

use Symfony\Component\Process\Process;

final class GitWorkingCopy
{
    public function __construct(
        private string $gitBinary,
        private string $directory,
    ) {
    }

    public function branch(mixed ...$argsOrOptions): string
    {
        return $this->run('branch', $argsOrOptions);
    }

    public function checkout(mixed ...$argsOrOptions): string
    {
        return $this->run('checkout', $argsOrOptions);
    }

    public function checkoutNewBranch(string $branch): string
    {
        return $this->checkout(['b' => true], $branch);
    }

    public function clean(string ...$argsOrOptions): string
    {
        return $this->run('clean', $argsOrOptions);
    }

    public function commit(mixed ...$argsOrOptions): string
    {
        if (isset($argsOrOptions[0]) && is_string($argsOrOptions[0]) && !isset($argsOrOptions[1])) {
            $argsOrOptions[0] = [
                'a' => true,
                'm' => $argsOrOptions[0],
            ];
        }

        return $this->run('commit', $argsOrOptions);
    }

    public function getBranches(): GitBranches
    {
        return new GitBranches($this);
    }

    public function getStatus(): string
    {
        return $this->run('status', ['-s']);
    }

    public function getRemoteUrl(string $remote, string $operation = 'fetch'): string
    {
        $arguments = ['get-url'];
        if ($operation === 'push') {
            $arguments[] = '--push';
        }
        $arguments[] = $remote;

        return trim($this->run('remote', $arguments));
    }

    public function hasChanges(): bool
    {
        return $this->getStatus() !== '';
    }

    public function pull(mixed ...$argsOrOptions): string
    {
        return $this->run('pull', $argsOrOptions);
    }

    public function push(mixed ...$argsOrOptions): string
    {
        return $this->run('push', $argsOrOptions);
    }

    public function pushTag(string $tag, string $repository = 'origin'): string
    {
        return $this->push($repository, 'tag', $tag);
    }

    public function reset(mixed ...$argsOrOptions): string
    {
        return $this->run('reset', $argsOrOptions);
    }

    /**
     * @param mixed[] $argsOrOptions
     */
    public function run(string $command, array $argsOrOptions = []): string
    {
        return $this->runCommand($command, $argsOrOptions);
    }

    /**
     * @param mixed[] $argsOrOptions
     */
    public function runWithOutput(string $command, array $argsOrOptions, callable $callback): string
    {
        return $this->runCommand($command, $argsOrOptions, $callback);
    }

    /**
     * @param mixed[] $argsOrOptions
     */
    private function runCommand(string $command, array $argsOrOptions = [], ?callable $callback = null): string
    {
        $process = new Process(
            array_merge([$this->gitBinary, $command], $this->buildArguments($argsOrOptions)),
            $this->directory
        );
        $process->setTimeout(null);
        $process->run($callback);

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() . $process->getOutput());
            throw new GitException($message === '' ? sprintf('Git command "%s" failed.', $command) : $message);
        }

        return $process->getOutput();
    }

    public function tag(mixed ...$argsOrOptions): string
    {
        return $this->run('tag', $argsOrOptions);
    }

    public function tags(): GitTags
    {
        return new GitTags($this);
    }

    /**
     * @param mixed[] $argsOrOptions
     *
     * @return string[]
     */
    private function buildArguments(array $argsOrOptions): array
    {
        $arguments = [];
        foreach ($argsOrOptions as $argOrOption) {
            if (is_array($argOrOption)) {
                array_push($arguments, ...$this->buildOptions($argOrOption));
                continue;
            }

            $arguments[] = (string) $argOrOption;
        }

        return $arguments;
    }

    /**
     * @param mixed[] $options
     *
     * @return string[]
     */
    private function buildOptions(array $options): array
    {
        $arguments = [];
        foreach ($options as $option => $values) {
            foreach ((array) $values as $value) {
                if (is_int($option)) {
                    $arguments[] = (string) $value;
                    continue;
                }

                $arguments[] = strlen((string) $option) === 1 ? "-$option" : "--$option";
                if ($value !== true) {
                    $arguments[] = (string) $value;
                }
            }
        }

        return $arguments;
    }
}
