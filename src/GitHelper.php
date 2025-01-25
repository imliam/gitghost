<?php

namespace GitGhost;

class GitHelper
{
    /**
     * Run a Git command in a specific directory using proc_open.
     *
     * @param string $command The Git command to run (e.g., "git log").
     * @param string $workingDirectory The directory where the command should be run.
     * @return array An array with 'success' (bool), 'output' (array of lines), and 'error' (string).
     */
    public static function run(string $command, string $workingDirectory): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'], // STDIN
            1 => ['pipe', 'w'], // STDOUT
            2 => ['pipe', 'w'], // STDERR
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);

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
            'output' => explode(PHP_EOL, trim($output)),
            'error' => trim($error),
        ];
    }
}
