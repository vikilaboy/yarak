<?php

namespace Yarak\Commands;

use Yarak\Migrations\Migrator;
use Yarak\Output\SymfonyOutput;
use Yarak\DB\Seeders\SeedRunner;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends YarakCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this->setName('migrate')
            ->setDescription('Run the database migrations.')
            ->setHelp('This command allows you to run migrations.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getMigrator(new SymfonyOutput($output))->run();
    }
}
