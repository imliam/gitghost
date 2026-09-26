<?php

namespace GitGhost;

class GitHelper
{
    /**
     * Run a Git command in a specific directory using proc_open.
     *
     * @param string $command The Git command to run (e.g., "git log").
     * @param string $workingDirectory The directory where the command should be run.
     * @param ?string $timestamp GIT_COMMITER_DATE as environment variable
     * @return array An array with 'success' (bool), 'output' (array of lines), and 'error' (string).
     */
    public static function run(string $command, string $workingDirectory, ?string $timestamp = null): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'], // STDIN
            1 => ['pipe', 'w'], // STDOUT
            2 => ['pipe', 'w'], // STDERR
        ];

        // Build environment instead of embedding it in command as Windows can't handle this
        $env = getenv();
        if ($timestamp !== null) {
            $env['GIT_COMMITTER_DATE'] = $timestamp;
        }

        $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory, $env);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => [],
                'error' => 'Failed to start process.',
            ];
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        return [
            'success' => $returnCode === 0,
            // Git always outputs as \n, even in Windows, still
            // Use a regex to match either \r\n, \r, or \n
            'output' => preg_split('/\r\n|\r|\n/', trim($output)),
            'error' => trim($error),
        ];
    }
}
