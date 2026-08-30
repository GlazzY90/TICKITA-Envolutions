<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
Logic:
Verifies notification retrieval/read behavior is scoped to the
authenticated user.

Structure:
These are HTTP feature tests because notification privacy must hold at
the API boundary, not only inside PHP methods.

DSA:
Queries are bounded or single-row indexed lookups.
*/
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_own_notifications(): void
    {
        $user =
            User::factory()
                ->supportAgent()
                ->create();

        $user->notify(
            new TicketActivityNotification(
                ticketId: 123,
                type: 'test',
                title: 'Test notification',
                message: 'Example message.',
            )
        );

        $this->actingAs($user, 'web');

        $this->getJson(
            '/api/notifications'
        )
            ->assertOk()
            ->assertJsonPath(
                'unread_count',
                1
            )
            ->assertJsonPath(
                'data.0.title',
                'Test notification'
            );
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user =
            User::factory()
                ->supportAgent()
                ->create();

        $user->notify(
            new TicketActivityNotification(
                ticketId: 123,
                type: 'test',
                title: 'Test',
                message: 'Test',
            )
        );

        $notification =
            $user->notifications()
                ->firstOrFail();

        $this->actingAs($user, 'web');

        $this->postJson(
            "/api/notifications/{$notification->id}/read"
        )->assertOk();

        $this->assertNotNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner =
            User::factory()
                ->supportAgent()
                ->create();

        $otherUser =
            User::factory()
                ->supportAgent()
                ->create();

        $owner->notify(
            new TicketActivityNotification(
                ticketId: 123,
                type: 'test',
                title: 'Private',
                message: 'Private notification',
            )
        );

        $notification =
            $owner->notifications()
                ->firstOrFail();

        $this->actingAs(
            $otherUser,
            'web'
        );

        $this->postJson(
            "/api/notifications/{$notification->id}/read"
        )->assertNotFound();
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user =
            User::factory()
                ->supportAgent()
                ->create();

        $user->notify(
            new TicketActivityNotification(
                1,
                'test',
                'One',
                'One'
            )
        );

        $user->notify(
            new TicketActivityNotification(
                2,
                'test',
                'Two',
                'Two'
            )
        );

        $this->actingAs($user, 'web');

        $this->postJson(
            '/api/notifications/read-all'
        )->assertOk();

        $this->assertSame(
            0,
            $user
                ->unreadNotifications()
                ->count()
        );
    }
}
