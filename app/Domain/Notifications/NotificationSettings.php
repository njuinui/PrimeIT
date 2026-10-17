<?php

namespace App\Domain\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\Notifications\Categories;

final class NotificationSettings
{
    /**
     * Channels Laravel should deliver on for this user + category.
     * An empty array means the user muted the category entirely.
     *
     * @return array<int, string>
     */
    public function channelsFor(User $user, string $category): array
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('category', $category)
            ->first();

        $defaults = Categories::defaults()[$category] ?? ['in_app' => true, 'email' => false];

        $inApp = $preference?->in_app ?? $defaults['in_app'];
        $email = $preference?->email ?? $defaults['email'];

        $channels = [];

        if ($inApp) {
            $channels[] = 'database';
        }

        if ($email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * The full merged list for the preferences UI.
     *
     * @return array<int, array{category: string, description: string, in_app: bool, email: bool}>
     */
    public function forUser(User $user): array
    {
        $rows = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('category');

        return collect(Categories::all())
            ->map(fn (string $description, string $category) => [
                'category'    => $category,
                'description' => $description,
                'in_app'      => (bool) ($rows[$category]?->in_app ?? Categories::defaults()[$category]['in_app']),
                'email'       => (bool) ($rows[$category]?->email ?? Categories::defaults()[$category]['email']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array{category: string, in_app: bool, email: bool}> $preferences
     */
    public function sync(User $user, array $preferences): void
    {
        foreach ($preferences as $preference) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'category' => $preference['category']],
                [
                    'in_app' => (bool) $preference['in_app'],
                    'email'  => (bool) $preference['email'],
                ],
            );
        }
    }
}