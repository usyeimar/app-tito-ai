<?php

namespace App\Services\Tenant\Notifications;

use App\Events\Tenant\Notifications\TenantNotificationCreated;
use App\Models\Tenant\Auth\Authentication\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TenantNotificationService
{
    /**
     * @param  Collection<int, User>|array<int, User>  $users
     * @param  array<string, mixed>  $payload
     * @return Collection<int, DatabaseNotification>
     */
    public function createForUsers(Collection|array $users, array $payload): Collection
    {
        $users = $users instanceof Collection ? $users : collect($users);

        $notifications = $users
            ->unique(fn (User $user) => (string) $user->getKey())
            ->map(function (User $user) use ($payload): DatabaseNotification {
                $notification = DatabaseNotification::query()->create([
                    'id' => (string) Str::uuid(),
                    'type' => 'tenant.generic',
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => (string) $user->getKey(),
                    'data' => $payload,
                    'read_at' => null,
                ]);

                broadcast(new TenantNotificationCreated((string) $user->getKey(), [
                    'id' => (string) $notification->id,
                    'notifiable_type' => $notification->notifiable_type,
                    'notifiable_id' => $notification->notifiable_id,
                    'payload' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at?->toISOString(),
                ]));

                return $notification;
            })
            ->values();

        return $notifications;
    }
}
