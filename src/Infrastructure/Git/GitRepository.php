<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Git;

use Gitonomy\Git\Exception\ProcessException;
use Gitonomy\Git\Reference\Branch;
use Gitonomy\Git\Reference\Tag;
use Gitonomy\Git\Repository;

/**
 * A wrapper around Gitonomy\Git\Repository that provides methods similar to
 * symplify/git-wrapper's GitWorkingCopy class.
 *
 * This adapter class maintains API compatibility during the migration from
 * symplify/git-wrapper to gitonomy/gitlib.
 */
final class GitRepository
{
    private Repository $repository;
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->repository = new Repository($path, [
            'inherit_environment_variables' => true,
        ]);
    }

    /**
     * Returns the path to the repository.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the underlying Gitonomy repository.
     */
    public function getRepository(): Repository
    {
        return $this->repository;
    }

    /**
     * Returns the branch information for this repository.
     */
    public function getBranches(): GitBranches
    {
        $references = $this->repository->getReferences();
        $branchNames = [];

        /** @var Branch $branch */
        foreach ($references->getLocalBranches() as $branch) {
            $branchNames[] = $branch->getName();
        }

        $currentBranch = trim($this->repository->run('rev-parse', ['--abbrev-ref', 'HEAD']));

        return new GitBranches($branchNames, $currentBranch);
    }

    /**
     * Returns the tag information for this repository.
     */
    public function tags(): GitTags
    {
        $references = $this->repository->getReferences();
        $tagNames = [];

        /** @var Tag $tag */
        foreach ($references->getTags() as $tag) {
            $tagNames[] = $tag->getName();
        }

        return new GitTags($tagNames);
    }

    /**
     * Checks if there are uncommitted changes in the working copy.
     */
    public function hasChanges(): bool
    {
        $status = $this->repository->run('status', ['--porcelain']);
        return trim($status) !== '';
    }

    /**
     * Returns the status output of the repository.
     */
    public function getStatus(): string
    {
        return $this->repository->run('status');
    }

    /**
     * Resets the working copy.
     *
     * @param array<string, bool|string> $options Reset options (e.g., ['hard' => true])
     */
    public function reset(array $options = []): string
    {
        $args = [];
        foreach ($options as $option => $value) {
            if ($value === true) {
                $args[] = "--$option";
            } elseif (is_string($value)) {
                $args[] = "--$option=$value";
            }
        }

        return $this->repository->run('reset', $args);
    }

    /**
     * Cleans untracked files and directories.
     */
    public function clean(string ...$options): string
    {
        return $this->repository->run('clean', $options);
    }

    /**
     * Checks out a branch or commit.
     */
    public function checkout(string $branch): string
    {
        return $this->repository->run('checkout', [$branch]);
    }

    /**
     * Creates and checks out a new branch.
     */
    public function checkoutNewBranch(string $branch): string
    {
        return $this->repository->run('checkout', ['-b', $branch]);
    }

    /**
     * Pulls changes from the remote.
     */
    public function pull(): string
    {
        return $this->repository->run('pull');
    }

    /**
     * Pushes changes to the remote.
     */
    public function push(?string $remote = null, ?string $branch = null): string
    {
        $args = [];
        if ($remote !== null) {
            $args[] = $remote;
        }
        if ($branch !== null) {
            $args[] = $branch;
        }

        try {
            return $this->repository->run('push', $args);
        } catch (ProcessException $e) {
            throw GitException::fromProcessException($e);
        }
    }

    /**
     * Pushes a tag to the remote.
     */
    public function pushTag(string $tag, string $remote = 'origin'): string
    {
        return $this->repository->run('push', [$remote, $tag]);
    }

    /**
     * Converts an associative array of options to command-line arguments.
     *
     * @param array<string, bool|string|int|float> $options Options array
     * @return string[] Command-line arguments
     */
    private function buildCommandArgs(array $options): array
    {
        $args = [];
        foreach ($options as $option => $value) {
            // Skip false values - they indicate the option should be omitted
            if ($value === false) {
                continue;
            }

            if (strlen($option) === 1) {
                // Short option
                $args[] = "-$option";
                if ($value !== true) {
                    $args[] = (string) $value;
                }
            } else {
                // Long option
                if ($value === true) {
                    $args[] = "--$option";
                } else {
                    $args[] = "--$option=" . (string) $value;
                }
            }
        }
        return $args;
    }

    /**
     * Creates a commit with the given options.
     *
     * @param array<string, bool|string|int|float> $options Commit options
     */
    public function commit(array $options = []): string
    {
        return $this->repository->run('commit', $this->buildCommandArgs($options));
    }

    /**
     * Creates a tag with the given options.
     *
     * @param array<string, bool|string|int|float> $options Tag options
     */
    public function tag(array $options = []): string
    {
        return $this->repository->run('tag', $this->buildCommandArgs($options));
    }

    /**
     * Executes a branch command with the given options.
     *
     * @param array<string, bool|string|int|float> $options Branch options
     */
    public function branch(array $options = []): string
    {
        return $this->repository->run('branch', $this->buildCommandArgs($options));
    }

    /**
     * Gets the remote URL for the given remote.
     */
    public function getRemoteUrl(string $remote): string
    {
        return trim($this->repository->run('remote', ['get-url', $remote]));
    }

    /**
     * Executes a raw git command.
     *
     * This method is intended for internal use only where the command string is
     * constructed from trusted, hardcoded values. The command string is split on
     * whitespace to separate the git subcommand from its arguments. Arguments are
     * passed to the underlying Gitonomy library which uses Symfony Process for
     * proper escaping.
     *
     * @internal
     * @param string $command The git command to execute (without 'git' prefix)
     * @param string|null $workingDir Optional working directory for the command
     */
    public function git(string $command, ?string $workingDir = null): string
    {
        // Parse the command string into command and arguments
        // Note: This simple splitting is safe because this method is only used internally
        // with hardcoded command strings from the codebase
        $parts = preg_split('/\s+/', $command, -1, PREG_SPLIT_NO_EMPTY);
        $gitCommand = array_shift($parts);
        $args = $parts;

        // If a working directory is specified, we need to create a new repository instance
        if ($workingDir !== null && $workingDir !== $this->path) {
            $repo = new Repository($workingDir, [
                'inherit_environment_variables' => true,
            ]);
            return $repo->run($gitCommand, $args);
        }

        return $this->repository->run($gitCommand, $args);
    }
}
