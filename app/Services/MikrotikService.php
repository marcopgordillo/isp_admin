<?php

namespace App\Services;

use App\Models\MikrotikServer;
use App\Models\PppoeUser;
use Exception;

class MikrotikService
{
    protected MikrotikServer $server;
    protected $connection;

    public function __construct(MikrotikServer $server)
    {
        $this->server = $server;
    }

    public function connect(): bool
    {
        try {
            $this->connection = true;
            
            $this->server->update([
                'is_connected' => true,
                'last_sync' => now(),
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->server->update(['is_connected' => false]);
            return false;
        }
    }

    public function createPppoeUser(PppoeUser $pppoeUser): bool
    {
        $this->connect();
        return true;
    }

    public function deletePppoeUser(PppoeUser $pppoeUser): bool
    {
        $this->connect();
        return true;
    }

    public function blockPppoeUser(PppoeUser $pppoeUser): bool
    {
        $this->connect();
        $pppoeUser->update(['is_blocked' => true]);
        return true;
    }

    public function unblockPppoeUser(PppoeUser $pppoeUser): bool
    {
        $this->connect();
        $pppoeUser->update(['is_blocked' => false]);
        return true;
    }

    public function getActiveSessions(): array
    {
        $this->connect();
        return [];
    }

    public function syncUsers(): int
    {
        $this->connect();
        return 0;
    }
}
