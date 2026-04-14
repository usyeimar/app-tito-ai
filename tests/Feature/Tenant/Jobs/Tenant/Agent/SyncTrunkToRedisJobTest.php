<?php

use App\Jobs\Tenant\Agent\SyncTrunkToRedisJob;
use Illuminate\Support\Facades\Queue;

it('is queued on the trunk-sync queue', function () {
    Queue::fake();

    SyncTrunkToRedisJob::dispatch('trunk-123');

    Queue::assertPushed(SyncTrunkToRedisJob::class);
});

it('has 3 tries and 30 second timeout', function () {
    $job = new SyncTrunkToRedisJob('trunk-123');

    expect($job->tries)->toBe(3);
    expect($job->timeout)->toBe(30);
});

it('has exponential backoff', function () {
    $job = new SyncTrunkToRedisJob('trunk-123');

    expect($job->backoff)->toBe([1, 5, 10]);
});

it('has correct queue name', function () {
    $job = new SyncTrunkToRedisJob('trunk-123');

    expect($job->queue)->toBe('trunk-sync');
});

it('accepts string trunk ID in constructor', function () {
    $trunkId = '01HX1234567890';
    $job = new SyncTrunkToRedisJob($trunkId);

    expect($job)->toBeInstanceOf(SyncTrunkToRedisJob::class);
});
