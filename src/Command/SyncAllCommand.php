<?php

namespace GitGhost\Command;

use GitGhost\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'syncall',
    description: 'Sync commits for all synced repositories.',
)]
class SyncAllCommand extends Command
{
    protected function configure()
    {
        $this->setHelp('This command runs the sync process for all repositories in the synced_repos list.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load synced repositories from config
        ConfigManager::load();
        $syncedRepos = ConfigManager::get('synced_repos', []);

        if (empty($syncedRepos)) {
            $io->warning('No repositories have been added to the synced_repos list. Use the sync command to add one.');
            return Command::SUCCESS;
        }

        $io->title('GitGhost - Sync All Repositories');
        $io->text('Syncing the following repositories:');
        $io->listing($syncedRepos);

        foreach ($syncedRepos as $repoPath) {
            $io->section("Syncing $repoPath...");

            $command = $this->getApplication()->find('sync');
            $arguments = ['originalRepoPath' => $repoPath];

            $returnCode = $command->run(
                new \Symfony\Component\Console\Input\ArrayInput($arguments),
                $output
            );

            if ($returnCode !== Command::SUCCESS) {
                $io->error("Failed to sync repository: $repoPath");
            } else {
                $io->success("Successfully synced repository: $repoPath");
            }
        }

        $io->success('All repositories have been synced.');
        return Command::SUCCESS;
    }
}
