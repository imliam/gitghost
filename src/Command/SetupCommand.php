<?php

namespace GitGhost\Command;

use GitGhost\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'setup',
    description: 'Setup GitGhost configuration and prepare the dummy repo.',
)]
class SetupCommand extends Command
{
    protected function configure()
    {
        $this->setHelp('This command helps you configure GitGhost by setting paths, authors, and initializing the dummy repo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load existing configuration
        ConfigManager::load();
        $config = ConfigManager::get();

        $io->title('GitGhost Setup');

        // Prompt for the dummy repo path with a default value
        $defaultDummyRepoPath = $config['dummy_repo_path'] ?? ConfigManager::getDefaultDummyRepoPath();
        $dummyRepoPath = $io->ask('Enter the path where the dummy repo should be created/located', $defaultDummyRepoPath);

        // Initialize the dummy repo
        if (!is_dir($dummyRepoPath . '/.git')) {
            $io->section("Creating dummy repo...");
            mkdir($dummyRepoPath, 0755, true);
            chdir($dummyRepoPath);
            exec('git init');
        } else {
            $io->text("Dummy repo already exists at $dummyRepoPath");
        }

        // Ask for the GitHub remote URL with a default value
        $defaultRemoteUrl = $config['remote_url'] ?? null;
        $remoteUrl = null;
        while (empty($remoteUrl)) {
            $remoteUrl = $io->ask(
                'Enter the git remote URL for the dummy repo (e.g., https://github.com/yourusername/dummy-repo.git). If using GitHub, you can create a new repo at https://github.com/new?name=gitghost&visibility=private',
                $defaultRemoteUrl
            );
        }

        // Set the remote URL
        chdir($dummyRepoPath);
        exec("git remote add origin $remoteUrl");

        // Ask for the name/email for ghost commits
        $defaultAuthorName = $config['author_name'] ?? null;
        $authorName = $io->ask('Enter the name for ghost commits', $defaultAuthorName);

        $defaultAuthorEmail = $config['author_email'] ?? null;
        $authorEmail = $io->ask('Enter the email for ghost commits', $defaultAuthorEmail);

        // Collect multiple authors for filtering commits
        $authors = $config['filter_authors'] ?? [];
        $io->section('Filter Authors');
        if (!empty($authors)) {
            $io->text('Existing authors: ' . implode(', ', $authors));
        }

        do {
            $newAuthor = $io->ask('Add a commit author to filter by (leave blank to finish)', null);
            if ($newAuthor) {
                $authors[] = $newAuthor;
            }
        } while (!empty($newAuthor));

        $authors = array_unique($authors);

        // Save configuration using ConfigManager
        ConfigManager::save([
            'dummy_repo_path' => $dummyRepoPath,
            'remote_url' => $remoteUrl,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'filter_authors' => $authors,
        ]);

        $io->success('GitGhost setup is complete!');
        $io->text("Dummy repo location: $dummyRepoPath");

        return Command::SUCCESS;
    }
}
