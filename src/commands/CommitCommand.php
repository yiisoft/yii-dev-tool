<?php

namespace Yiisoft\Dev\Commands;

use Yiisoft\Dev\Console;
use Yiisoft\Dev\Tools;

class CommitCommand implements CommandInterface
{
    private $package;
    private $message;

    public function __construct(string $message = null, string $package = null)
    {
        if ($message === null) {
            Console::stdOutLn('Message is required.');
            exit(1);
        }

        $this->package = $package;
        $this->message = $message;
    }

    public function run(): void
    {
        if ($this->package === null) {
            $this->commitAllPackage();
        } elseif (isset(Tools::getPackageList()[$this->package])) {
            $this->commitPackage($this->package);
        } else {
            Console::stdErrLn('Package ' . $this->package . ' not found in packages.php');
            exit(1);
        }
    }

    private function commitAllPackage(): void
    {
        foreach (Tools::getPackageList() as $package => $dir) {
            $this->commitPackage($package);
        }
    }

    private function commitPackage($package): void
    {
        Tools::gitCommitPackage($package, $this->message);
    }
}
