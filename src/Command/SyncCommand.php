<?php

namespace GitGhost\Command;

use GitGhost\ConfigManager;
use GitGhost\GitHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sync',
    description: 'Sync commits from an original repo to the dummy repo.',
)]
class SyncCommand extends Command
{
    protected function configure()
    {
        $this
            ->setHelp('This command syncs commits from an original repo to the dummy repo.')
            ->addArgument('originalRepoPath', InputArgument::REQUIRED, 'Path to the original Git repository');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load config
        ConfigManager::load();
        $config = ConfigManager::get();

        if (empty($config)) {
            $io->error('GitGhost is not set up. Please run the setup command first.');
            return Command::FAILURE;
        }

        $originalRepoPath = realpath($input->getArgument('originalRepoPath'));
        $dummyRepoPath = ConfigManager::get('dummy_repo_path');
        $filterAuthors = ConfigManager::get('filter_authors', []);

        // Verify paths
        if (!is_dir($originalRepoPath . '/.git')) {
            $io->error("The specified original repo path ($originalRepoPath) is not a valid Git repository.");
            return Command::FAILURE;
        }

        if (!is_dir($dummyRepoPath . '/.git')) {
            $io->error("The dummy repo path ($dummyRepoPath) is not a valid Git repository. Please run setup again.");
            return Command::FAILURE;
        }

        // Inside the execute method, after verifying the original repo path
        if (!in_array($originalRepoPath, ConfigManager::get('synced_repos', []))) {
            // Add the repo path to the synced_repos list
            $syncedRepos = ConfigManager::get('synced_repos', []);
            $syncedRepos[] = $originalRepoPath;

            ConfigManager::save(['synced_repos' => array_unique($syncedRepos)]);
            $io->success("Added $originalRepoPath to the list of synced repositories.");
        }


        // Sync commits
        $io->section("Syncing commits from $originalRepoPath to $dummyRepoPath");
        $newCommits = $this->syncCommits($originalRepoPath, $dummyRepoPath, $filterAuthors, $io, $output);

        if (empty($newCommits)) {
            $io->success("No new commits to sync.");
        } else {
            $io->success("Synced " . count($newCommits) . " new commit(s) to the dummy repo.");
        }

        return Command::SUCCESS;
    }

    private function syncCommits(
        string $originalRepoPath,
        string $dummyRepoPath,
        array $filterAuthors,
        SymfonyStyle $io,
        OutputInterface $output
    ): array {
        $newCommits = [];

        // Get commits from the original repo
        $authorFilter = empty($filterAuthors) ? '' : '--author=' . implode(' --author=', $filterAuthors);
        $gitLogCommand = "git log $authorFilter --pretty=format:'%H|%at'";
        $result = GitHelper::run($gitLogCommand, $originalRepoPath);

        if (!$result['success']) {
            $io->error("Failed to retrieve commits from the original repo: " . $result['error']);
            return $newCommits;
        }

        $logOutput = $result['output'];
        if (empty($logOutput)) {
            $io->text("No matching commits found in the original repo.");
            return $newCommits;
        }

        // Process commits
        $dummyRepoFile = $dummyRepoPath . '/repos/' . basename($originalRepoPath);
        if (!is_dir(dirname($dummyRepoFile))) {
            mkdir(dirname($dummyRepoFile), 0755, true);
        }

        $existingCommits = file_exists($dummyRepoFile) ? file($dummyRepoFile, FILE_IGNORE_NEW_LINES) : [];

        // Filter out already synced commits
        $filteredCommits = array_filter($logOutput, function ($logEntry) use ($existingCommits) {
            [$hash] = explode('|', $logEntry);
            return !in_array($hash, $existingCommits);
        });

        // Reverse the commits to process them in chronological order
        $filteredCommits = array_reverse($filteredCommits);

        $totalCommits = count($filteredCommits);
        if ($totalCommits === 0) {
            $io->text("No new commits to process.");
            return $newCommits;
        }

        // Create a progress bar
        $progressBar = new ProgressBar($output, $totalCommits);
        $progressBar->start();

        foreach ($filteredCommits as $logEntry) {
            [$hash, $timestamp] = explode('|', $logEntry);

            // Append commit ID to dummy repo file
            file_put_contents($dummyRepoFile, $hash . PHP_EOL, FILE_APPEND);

            // Create a dummy commit in the dummy repo with proper dates
            $escapedMessage = escapeshellarg("Dummy commit for $hash");
            $gitAddResult = GitHelper::run("git add repos/", $dummyRepoPath);
            if (!$gitAddResult['success']) {
                $io->error("Failed to stage changes: " . $gitAddResult['error']);
                $progressBar->advance();
                continue;
            }

            $commitCommand = "GIT_COMMITTER_DATE='$timestamp' git commit -m $escapedMessage --date='$timestamp'";
            $commitResult = GitHelper::run($commitCommand, $dummyRepoPath);

            if (!$commitResult['success']) {
                $io->error("Failed to create commit: " . $commitResult['error']);
                $progressBar->advance();
                continue;
            }

            $newCommits[] = $hash;
            $progressBar->advance();
        }

        // Push changes
        $pushResult = GitHelper::run("git push origin main", $dummyRepoPath);

        if (!$pushResult['success']) {
            $io->error("Failed to push changes to the dummy repo: " . $pushResult['error']);
        }

        $progressBar->finish();
        $io->newLine(2);

        return $newCommits;
    }
}
