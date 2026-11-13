<?php

use App\Models\TechnicalSupportIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'test-model']);
});

test('protects the support pages and reporting endpoint from guests', function () {
    $this->get(route('technical-support.chat'))->assertRedirect(route('login'));
    $this->get(route('technical-support.index'))->assertRedirect(route('login'));
    $this->postJson(route('technical-support.store'), ['message' => 'Help'])->assertUnauthorized();
    Http::assertNothingSent();
});

test('classifies through the Responses schema and saves server-owned fields', function (?int $orderId) {
    $user = User::factory()->create();
    $classification = ['category' => 'payment_issue', 'order_id' => $orderId, 'priority' => 'high', 'requires_human' => false, 'has_issue_details' => true, 'order_id_unavailable' => $orderId === null, 'follow_up_question' => ''];
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_classify', 'status' => 'completed',
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($classification)]]]],
    ])]);

    $this->actingAs($user)->postJson(route('technical-support.store'), [
        'message' => 'Payment failed for order 105', 'user_id' => 999, 'status' => 'solved', 'priority' => 'low',
    ])->assertCreated()->assertJsonStructure(['ticket_id', 'message'])->assertJsonMissingPath('issue')->assertJsonMissingPath('priority');

    $this->assertDatabaseHas('technical_support_issues', [
        'user_id' => $user->id, 'message' => 'Payment failed for order 105', 'category' => 'payment_issue',
        'reported_order_id' => $orderId, 'priority' => 'high', 'requires_human' => $orderId === null, 'status' => 'open',
    ]);
    Http::assertSent(fn (Request $request): bool => $request['text']['format']['type'] === 'json_schema'
        && $request['text']['format']['strict'] === true
        && $request['text']['format']['schema']['additionalProperties'] === false
        && $request['text']['format']['schema']['required'] === ['category', 'order_id', 'priority', 'requires_human', 'has_issue_details', 'order_id_unavailable', 'follow_up_question']
        && $request['store'] === true && $request['model'] === 'test-model');
})->with(['reported order' => 105, 'missing order' => null]);

test('does not save an issue when classification fails', function (string $failure) {
    $user = User::factory()->create();
    $valid = ['category' => 'payment_issue', 'order_id' => 105, 'priority' => 'high', 'requires_human' => true, 'has_issue_details' => true, 'order_id_unavailable' => false, 'follow_up_question' => ''];
    $text = match ($failure) {
        'bad json' => 'not JSON',
        'bad category' => json_encode(array_replace($valid, ['category' => 'invented'])),
        'bad priority' => json_encode(array_replace($valid, ['priority' => 'urgent'])),
        'bad boolean' => json_encode(array_replace($valid, ['requires_human' => 'false'])),
        'bad order' => json_encode(array_replace($valid, ['order_id' => -1])),
        'extra field' => json_encode($valid + ['status' => 'solved']),
        'missing field' => json_encode(['category' => 'other', 'priority' => 'low', 'requires_human' => false]),
        default => json_encode($valid),
    };
    $payload = ['id' => 'resp_classify', 'status' => $failure === 'incomplete' ? 'incomplete' : 'completed',
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]]];
    if ($failure === 'refusal') {
        $payload['output'][0]['content'][] = ['type' => 'refusal', 'refusal' => 'Cannot classify'];
    }
    Http::fake(['https://api.openai.com/v1/responses' => match ($failure) {
        'connection' => Http::failedConnection(), 'provider error' => Http::response(['error' => 'private'], 500),
        default => Http::response($payload),
    }]);

    $this->actingAs($user)->postJson(route('technical-support.store'), ['message' => 'Help'])
        ->assertServiceUnavailable()->assertJsonPath('message', 'Classification is unavailable. No issue was saved. Please try again.');

    $this->assertDatabaseCount('technical_support_issues', 0);
    Http::assertSentCount(1);
})->with(['bad json', 'bad category', 'bad priority', 'bad boolean', 'bad order', 'extra field', 'missing field', 'refusal', 'incomplete', 'connection', 'provider error']);

test('rejects invalid report messages before contacting the provider', function (array $payload) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('technical-support.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('message');

    $this->assertDatabaseCount('technical_support_issues', 0);
    Http::assertNothingSent();
})->with(['missing' => [[]], 'wrong type' => [['message' => []]], 'too long' => [['message' => str_repeat('x', 2001)]]]);

test('only shows the customers own reports and escapes message HTML', function () {
    $user = User::factory()->create();
    TechnicalSupportIssue::factory()->create(['user_id' => $user->id, 'message' => '<script>alert(1)</script>']);
    TechnicalSupportIssue::factory()->create(['message' => 'Private other customer issue']);

    $this->actingAs($user)->get(route('technical-support.chat'))->assertOk()
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Private other customer issue');
});

test('forbids customers from accessing or changing the staff queue', function () {
    $user = User::factory()->create();
    $issue = TechnicalSupportIssue::factory()->create();

    $this->actingAs($user)->get(route('technical-support.index'))->assertForbidden();
    $this->patch(route('technical-support.update', $issue), ['status' => 'solved'])->assertForbidden();

    expect($issue->fresh()->status)->toBe('open');
});

test('staff can filter the queue and solve then reopen issues', function () {
    $this->freezeTime();
    $staff = User::factory()->create(['is_support_staff' => true]);
    $issue = TechnicalSupportIssue::factory()->create(['message' => 'Open payment problem', 'priority_escalated_at' => now()->subDays(3)]);
    TechnicalSupportIssue::factory()->create(['status' => 'solved', 'message' => 'Already solved problem']);

    $this->actingAs($staff)->get(route('technical-support.index', ['status' => 'open']))->assertOk()
        ->assertSee('Open payment problem')->assertDontSee('Already solved problem');
    $this->patch(route('technical-support.update', $issue), ['status' => 'solved'])->assertRedirect();
    expect($issue->fresh()->status)->toBe('solved');
    $this->patch(route('technical-support.update', $issue), ['status' => 'open'])->assertRedirect();
    expect($issue->fresh()->status)->toBe('open');
    expect($issue->fresh()->priority_escalated_at->timestamp)->toBe(now()->timestamp);
});

test('rejects invalid staff status updates', function () {
    $staff = User::factory()->create(['is_support_staff' => true]);
    $issue = TechnicalSupportIssue::factory()->create();

    $this->actingAs($staff)->patchJson(route('technical-support.update', $issue), ['status' => 'deleted'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    expect($issue->fresh()->status)->toBe('open');
});

test('escalates only eligible unresolved issues and safely catches up missed days', function (string $priority, string $status, int $hours, string $expected) {
    $this->freezeTime();
    $issue = TechnicalSupportIssue::factory()->create([
        'priority' => $priority, 'status' => $status, 'priority_escalated_at' => now()->subHours($hours),
    ]);

    $this->artisan('support-issues:escalate')->assertSuccessful();
    $this->artisan('support-issues:escalate')->assertSuccessful();

    expect($issue->fresh()->priority)->toBe($expected);
})->with([
    'one day' => ['low', 'open', 24, 'medium'],
    'next step' => ['medium', 'open', 24, 'high'],
    'catch up' => ['low', 'open', 72, 'high'],
    'too new' => ['low', 'open', 23, 'low'],
    'solved' => ['low', 'solved', 72, 'low'],
    'capped' => ['high', 'open', 72, 'high'],
]);

test('escalates again after another day', function () {
    $this->freezeTime();
    $issue = TechnicalSupportIssue::factory()->create(['priority_escalated_at' => now()->subDay()]);
    $this->artisan('support-issues:escalate')->assertSuccessful();
    $this->travel(1)->days();

    $this->artisan('support-issues:escalate')->assertSuccessful();

    expect($issue->fresh()->priority)->toBe('high');
});

test('grants staff access only to an existing account through the console', function () {
    $user = User::factory()->create();

    $this->artisan('support-staff:grant', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->is_support_staff)->toBeTrue();
    $this->artisan('support-staff:grant', ['email' => 'missing@example.test'])->assertFailed();
    $this->assertDatabaseCount('users', 1);
});

test('orders the staff queue by priority then oldest issue', function () {
    $staff = User::factory()->create(['is_support_staff' => true]);
    TechnicalSupportIssue::factory()->create(['priority' => 'low', 'message' => 'Low priority issue']);
    TechnicalSupportIssue::factory()->create(['priority' => 'high', 'message' => 'Older high issue']);
    TechnicalSupportIssue::factory()->create(['priority' => 'high', 'message' => 'Newer high issue']);

    $this->actingAs($staff)->get(route('technical-support.index'))->assertOk()
        ->assertSeeInOrder(['Older high issue', 'Newer high issue', 'Low priority issue']);
});
