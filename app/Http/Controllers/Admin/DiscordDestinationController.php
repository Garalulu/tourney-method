<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscordChannel;
use App\Services\AuditLogger;
use App\Services\DiscordService;
use Illuminate\Http\RedirectResponse;

class DiscordDestinationController extends Controller
{
    public function testSend(DiscordChannel $discordDestination, DiscordService $discordService, AuditLogger $auditLogger): RedirectResponse
    {
        if (empty($discordDestination->webhook_url)) {
            return back()->with('error', 'This destination has no webhook URL.');
        }

        $result = $discordService->sendTestMessage($discordDestination->webhook_url, auth()->user()->username ?? 'Admin');

        $auditLogger->log('discord_destination.tested', DiscordChannel::class, $discordDestination->id, [
            'destination' => $this->auditDetails($discordDestination),
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
        ]);

        if (! $result['success']) {
            return back()->with('error', 'Discord test failed: '.($result['error'] ?? 'Unknown error'));
        }

        return back()->with('success', 'Discord test message sent.');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditDetails(DiscordChannel $destination): array
    {
        return [
            'channel_name' => $destination->channel_name,
            'server_name' => $destination->server?->server_name,
            'mode' => $destination->mode,
            'is_badge' => $destination->is_badge,
            'is_active' => $destination->is_active,
            'webhook_url' => $this->maskWebhookUrl($destination->webhook_url),
        ];
    }

    private function maskWebhookUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_replace(
            '#(https://discord\.com/api/webhooks/\d{5})\d+/[\w-]+#',
            '$1***/***',
            $url
        ) ?? '[masked]';
    }
}
