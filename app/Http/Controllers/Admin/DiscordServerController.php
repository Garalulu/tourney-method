<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscordChannel;
use App\Models\DiscordServer;
use App\Services\AuditLogger;
use App\Services\CldrService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DiscordServerController extends Controller
{
    private const MODES = ['osu', 'taiko', 'catch', 'mania'];

    public function index(): View
    {
        return view('admin.discord-servers.index', [
            'servers' => DiscordServer::query()->withCount('destinations')->orderBy('server_name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.discord-servers.create', [
            'server' => new DiscordServer([
                'role_mappings' => [],
                'countries' => [],
                'send_unmatched_rank_alerts' => true,
            ]),
            'modes' => self::MODES,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $payload = $this->validated($request);

        DB::transaction(function () use ($payload, $auditLogger): void {
            $server = DiscordServer::query()->create($payload['server']);
            $this->syncDestinations($server, $payload['destinations'], $auditLogger);
            $server->load('destinations');

            $auditLogger->log('discord_server.created', DiscordServer::class, $server->id, [
                'server' => $this->auditDetails($server),
            ]);
        });

        return redirect()->route('admin.discord-servers.index')->with('success', 'Discord server created.');
    }

    public function edit(DiscordServer $discordServer): View
    {
        return view('admin.discord-servers.edit', [
            'server' => $discordServer->load('destinations'),
            'modes' => self::MODES,
        ]);
    }

    public function update(Request $request, DiscordServer $discordServer, AuditLogger $auditLogger): RedirectResponse
    {
        $discordServer->load('destinations');
        $before = $this->auditDetails($discordServer);
        $payload = $this->validated($request, $discordServer);

        DB::transaction(function () use ($discordServer, $payload, $auditLogger, $before): void {
            $discordServer->update($payload['server']);
            $this->syncDestinations($discordServer, $payload['destinations'], $auditLogger);
            $discordServer->refresh()->load('destinations');

            $auditLogger->log('discord_server.updated', DiscordServer::class, $discordServer->id, [
                'before' => $before,
                'after' => $this->auditDetails($discordServer),
            ]);
        });

        return redirect()->route('admin.discord-servers.index')->with('success', 'Discord server updated.');
    }

    public function destroy(DiscordServer $discordServer, AuditLogger $auditLogger): RedirectResponse
    {
        if ($discordServer->destinations()->exists()) {
            return back()->with('error', 'Move or delete this server\'s destinations first.');
        }

        $details = $this->auditDetails($discordServer);
        $id = $discordServer->id;
        $discordServer->delete();

        $auditLogger->log('discord_server.deleted', DiscordServer::class, $id, [
            'server' => $details,
        ]);

        return redirect()->route('admin.discord-servers.index')->with('success', 'Discord server deleted.');
    }

    /**
     * @return array{server: array<string, mixed>, destinations: list<array<string, mixed>>}
     */
    private function validated(Request $request, ?DiscordServer $server = null): array
    {
        $request->merge([
            'role_mappings' => $this->submittedRoleMappings($request),
            'countries' => $this->submittedCountries($request),
            'destinations' => $this->submittedDestinations($request),
            'send_unmatched_rank_alerts' => $request->has('send_unmatched_rank_alerts')
                ? $request->boolean('send_unmatched_rank_alerts')
                : ($server !== null ? $server->send_unmatched_rank_alerts : true),
        ]);

        $validated = $request->validate([
            'server_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('discord_servers', 'server_name')->ignore($server),
            ],
            'role_mappings' => ['array'],
            'role_mappings.*.threshold' => ['required', 'integer', 'min:1'],
            'role_mappings.*.role_id' => ['required', 'string', 'regex:/^\d+$/'],
            'send_unmatched_rank_alerts' => ['boolean'],
            'countries' => ['array'],
            'countries.*' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! app(CldrService::class)->isValidTerritoryCode((string) $value)) {
                        $fail("The {$attribute} must be a valid CLDR territory code.");
                    }
                },
            ],
            'destinations' => ['array'],
            'destinations.*.id' => ['nullable', 'integer'],
            'destinations.*.channel_name' => ['required', 'string', 'max:255'],
            'destinations.*.webhook_url' => [
                'nullable',
                'string',
                'regex:#^https://discord\.com/api/webhooks/\d+/[\w-]+$#',
            ],
            'destinations.*.mode' => ['required', Rule::in(self::MODES)],
            'destinations.*.is_badge' => ['boolean'],
            'destinations.*.is_active' => ['boolean'],
        ]);

        $this->validateDestinationRows($validated['destinations'] ?? [], $server);

        $validated['role_mappings'] = collect($validated['role_mappings'] ?? [])
            ->map(fn (array $row): array => [
                'threshold' => (int) $row['threshold'],
                'role_id' => (string) $row['role_id'],
            ])
            ->sortBy('threshold')
            ->values()
            ->all();
        $validated['countries'] = collect($validated['countries'] ?? [])
            ->map(fn (string $country): string => strtoupper($country))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $validated['destinations'] = collect($validated['destinations'] ?? [])
            ->map(fn (array $row): array => [
                'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                'channel_name' => (string) $row['channel_name'],
                'webhook_url' => filled($row['webhook_url'] ?? null) ? (string) $row['webhook_url'] : null,
                'mode' => (string) $row['mode'],
                'is_badge' => (bool) ($row['is_badge'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? false),
            ])
            ->values()
            ->all();

        return [
            'server' => [
                'server_name' => $validated['server_name'],
                'role_mappings' => $validated['role_mappings'],
                'countries' => $validated['countries'],
                'send_unmatched_rank_alerts' => $validated['send_unmatched_rank_alerts'],
            ],
            'destinations' => $validated['destinations'],
        ];
    }

    /**
     * @return list<array{threshold: mixed, role_id: mixed}>
     */
    private function submittedRoleMappings(Request $request): array
    {
        return collect($request->input('role_mappings', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'threshold' => $row['threshold'] ?? null,
                'role_id' => $row['role_id'] ?? null,
            ])
            ->filter(fn (array $row): bool => filled($row['threshold']) || filled($row['role_id']))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function submittedCountries(Request $request): array
    {
        $countries = $request->input('countries', []);

        if ($countries === null || $countries === '') {
            return [];
        }

        if (is_string($countries)) {
            $countries = preg_split('/[\s,]+/', $countries) ?: [];
        }

        if (! is_array($countries)) {
            return [];
        }

        return collect($countries)
            ->map(fn (mixed $country): string => strtoupper((string) $country))
            ->filter(fn (string $country): bool => filled($country))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: mixed, channel_name: mixed, webhook_url: mixed, mode: mixed, is_badge: bool, is_active: bool}>
     */
    private function submittedDestinations(Request $request): array
    {
        return collect($request->input('destinations', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'id' => $row['id'] ?? null,
                'channel_name' => $row['channel_name'] ?? null,
                'webhook_url' => $row['webhook_url'] ?? null,
                'mode' => $row['mode'] ?? null,
                'is_badge' => filter_var($row['is_badge'] ?? false, FILTER_VALIDATE_BOOL),
                'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOL),
            ])
            ->filter(fn (array $row): bool => filled($row['id'])
                || filled($row['channel_name'])
                || filled($row['webhook_url'])
                || filled($row['mode']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $destinations
     */
    private function validateDestinationRows(array $destinations, ?DiscordServer $server): void
    {
        $validator = Validator::make([], []);

        $validator->after(function ($validator) use ($destinations, $server): void {
            $seenNames = [];

            foreach ($destinations as $index => $destination) {
                $id = filled($destination['id'] ?? null) ? (int) $destination['id'] : null;
                $name = (string) ($destination['channel_name'] ?? '');
                $isActive = (bool) ($destination['is_active'] ?? false);

                if ($isActive && blank($destination['webhook_url'] ?? null)) {
                    $validator->errors()->add("destinations.{$index}.webhook_url", 'The webhook URL field is required when the destination is active.');
                }

                if ($id !== null) {
                    $belongsToServer = $server !== null
                        && DiscordChannel::query()
                            ->whereKey($id)
                            ->where('discord_server_id', $server->id)
                            ->exists();

                    if (! $belongsToServer) {
                        $validator->errors()->add("destinations.{$index}.id", 'The selected destination is invalid.');
                    }
                }

                $normalizedName = mb_strtolower($name);
                if (isset($seenNames[$normalizedName])) {
                    $validator->errors()->add("destinations.{$index}.channel_name", 'Destination names must be unique.');
                }
                $seenNames[$normalizedName] = true;

                $nameExists = DiscordChannel::query()
                    ->where('channel_name', $name)
                    ->when($id !== null, fn ($query) => $query->whereKeyNot($id))
                    ->exists();

                if ($nameExists) {
                    $validator->errors()->add("destinations.{$index}.channel_name", 'The destination name has already been taken.');
                }
            }
        });

        $validator->validate();
    }

    /**
     * @param  list<array<string, mixed>>  $destinations
     */
    private function syncDestinations(DiscordServer $server, array $destinations, AuditLogger $auditLogger): void
    {
        $existing = $server->destinations()->get()->keyBy('id');
        $submittedIds = collect($destinations)
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $existing
            ->reject(fn (DiscordChannel $destination): bool => in_array($destination->id, $submittedIds, true))
            ->each(function (DiscordChannel $destination) use ($auditLogger): void {
                $details = $this->destinationAuditDetails($destination);
                $id = $destination->id;

                $destination->delete();

                $auditLogger->log('discord_destination.deleted', DiscordChannel::class, $id, [
                    'destination' => $details,
                ]);
            });

        foreach ($destinations as $row) {
            $attributes = [
                'channel_name' => $row['channel_name'],
                'webhook_url' => $row['webhook_url'],
                'mode' => $row['mode'],
                'is_badge' => $row['is_badge'],
                'is_active' => $row['is_active'],
            ];

            if (! empty($row['id']) && $existing->has((int) $row['id'])) {
                $destination = $existing->get((int) $row['id']);
                $before = $this->destinationAuditDetails($destination);

                $destination->fill($attributes);

                if (! $destination->isDirty()) {
                    continue;
                }

                $destination->save();
                $destination->refresh();

                $auditLogger->log('discord_destination.updated', DiscordChannel::class, $destination->id, [
                    'before' => $before,
                    'after' => $this->destinationAuditDetails($destination),
                ]);

                continue;
            }

            $destination = $server->destinations()->create($attributes + [
                'role_mappings' => [],
            ]);

            $auditLogger->log('discord_destination.created', DiscordChannel::class, $destination->id, [
                'destination' => $this->destinationAuditDetails($destination),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditDetails(DiscordServer $server): array
    {
        return [
            'server_name' => $server->server_name,
            'role_mapping_count' => count($server->role_mappings ?? []),
            'countries' => $server->countries ?? [],
            'destination_count' => $server->destinations()->count(),
            'send_unmatched_rank_alerts' => $server->send_unmatched_rank_alerts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function destinationAuditDetails(DiscordChannel $destination): array
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
