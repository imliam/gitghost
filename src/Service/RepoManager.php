<?php

namespace GitGhost\Service;

class RepoManager
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function processCommits(string $originalRepoPath): array
    {
        // Change to the original repo
        chdir($originalRepoPath);

        // Get commits filtered by author
        $filterAuthor = $this->config['filter_author'];
        $authorFilter = $filterAuthor ? "--author=$filterAuthor" : '';

        $logOutput = [];
        exec("git log $authorFilter --pretty=format:'%H|%s|%at'", $logOutput);

        // Process each commit
        $newCommits = [];
        foreach ($logOutput as $logEntry) {
            [$hash, $message, $timestamp] = explode('|', $logEntry);

            // Append commit hash to dummy repo file
            $dummyRepoPath = $this->config['dummy_repo_path'];
            $dummyFile = "$dummyRepoPath/repos/" . basename($originalRepoPath);

            if (!file_exists($dummyFile)) {
                file_put_contents($dummyFile, '');
            }

            $lines = file($dummyFile, FILE_IGNORE_NEW_LINES);
            if (!in_array($hash, $lines)) {
                file_put_contents($dummyFile, "$hash\n", FILE_APPEND);
                $this->commitToDummyRepo($dummyRepoPath, $hash, $message, $timestamp);
                $newCommits[] = ['hash' => $hash, 'message' => $message];
            }
        }

        return $newCommits;
    }

    private function commitToDummyRepo(string $dummyRepoPath, string $hash, string $message, int $timestamp)
    {
        chdir($dummyRepoPath);
        exec('git add .');
        exec("git commit -m '$message' --date='$timestamp'");
    }
}
