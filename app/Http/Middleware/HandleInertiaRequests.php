<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'created_category_id' => fn () => $request->session()->get('created_category_id'),
            ],
            'notifications' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return ['unread' => 0, 'items' => []];
                }

                $items = $user->alerts()->latest()->limit(20)->get();

                return [
                    'unread' => $items->whereNull('read_at')->count(),
                    'items' => $items->map(fn ($a) => [
                        'id' => $a->id,
                        'title' => $a->title,
                        'body' => $a->body,
                        'read' => $a->read_at !== null,
                        'time_ago' => $a->created_at->diffForHumans(),
                    ])->values(),
                ];
            },
        ];
    }
}
