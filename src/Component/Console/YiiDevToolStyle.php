<?php

namespace Yiisoft\YiiDevTool\Component\Console;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class YiiDevToolStyle extends SymfonyStyle
{
    /** @var bool */
    private $hasColorSupport;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        parent::__construct($input, $output);

        $formatter = $output->getFormatter();

        $formatter->setStyle('package', new OutputFormatterStyle('cyan', null, ['bold']));
        $formatter->setStyle('file', new OutputFormatterStyle('blue', null, ['bold']));
        $formatter->setStyle('error', new OutputFormatterStyle('red', null, ['bold']));
        $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('success', new OutputFormatterStyle('green'));
        $formatter->setStyle('header', new OutputFormatterStyle('white', null, ['bold']));
        $formatter->setStyle('cmd', new OutputFormatterStyle('green', null, ['bold']));

        $this->hasColorSupport = $formatter->isDecorated();
    }

    public function hasColorSupport(): bool
    {
        return $this->hasColorSupport;
    }

    protected function wrap($message, string $tag)
    {
        if (!is_array($message)) {
            $message = [$message];
        }

        $count = count($message);
        if ($count) {
            $message[0] = "{$tag}{$message[0]}";
            $message[$count-1] .= '</>';
        }

        return $message;
    }

    public function error($message): void
    {
        $this->writeln($this->wrap($message, '<error>'));
        $this->newLine();
    }

    public function warning($message): void
    {
        $this->writeln($this->wrap($message, '<warning>'));
        $this->newLine();
    }

    public function success($message): void
    {
        $this->writeln($this->wrap($message, '<success>'));
        $this->newLine();
    }

    public function done(): void
    {
        $this->success('✔ Done.');
    }

    public function header(string $message): void
    {
        $this->writeln([
            '<header>▶ ' . $message . '</header>',
        ]);

        $this->newLine();
    }
}
