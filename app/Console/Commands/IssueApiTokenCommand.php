<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Minimal token issuance for the MCP server (dev/project-bokit-mcp-server.md) and any other
 * Sanctum-authenticated API consumer — a Filament "API tokens" UI can come later, once there is
 * a real need for self-service; an admin running this command is enough for now.
 */
class IssueApiTokenCommand extends Command
{
    protected $signature = 'bokit:issue-api-token
        {email : The user to issue the token for}
        {name=api : A label for the token, shown when the user reviews their own tokens}';

    protected $description = 'Issue a Sanctum personal access token for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error("No user with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $token = $user->createToken($this->argument('name'));

        $this->components->info('Token issued — shown once, not recoverable afterwards:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
