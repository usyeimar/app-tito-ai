<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Activity;

use App\Models\Tenant\CRM\Leads\Lead;
use App\Services\Tenant\Activity\DTOs\ActivityContext;
use App\Services\Tenant\Activity\Support\ActivityContextStore;
use App\Services\Tenant\Activity\Support\ChangesMapBuilder;
use App\Services\Tenant\Activity\Support\KnownMorphTypes;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ActivitySupportTest extends TestCase
{
    public function test_known_morph_types_reads_activity_type_config(): void
    {
        $types = new KnownMorphTypes;

        $this->assertSame(Lead::class, $types->modelClassForType('lead'));

        $lead = new Lead;
        $lead->setAttribute('id', 'lead_1');
        $lead->setAttribute('name', 'Lead One');

        $this->assertSame('lead', $types->typeForModel($lead));
        $this->assertContains('email', $types->labelFields('email'));
    }

    public function test_changes_map_builder_only_returns_changed_fields(): void
    {
        $builder = new ChangesMapBuilder;

        $before = [
            'name' => 'Before',
            'count' => 3,
            'at' => CarbonImmutable::parse('2026-02-20 10:00:00'),
        ];

        $after = [
            'name' => 'After',
            'count' => 3,
            'at' => CarbonImmutable::parse('2026-02-20 10:00:00'),
            'status' => 'active',
        ];

        $changes = $builder->build($before, $after);

        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('status', $changes);
        $this->assertArrayNotHasKey('count', $changes);
        $this->assertArrayNotHasKey('at', $changes);
        $this->assertSame('Before', $changes['name']['from']);
        $this->assertSame('After', $changes['name']['to']);
    }

    public function test_activity_context_store_scopes_context_lifecycle(): void
    {
        $first = new ActivityContext(origin: 'api', requestId: 'req_1');
        $second = new ActivityContext(origin: 'job', requestId: 'req_2');

        $this->assertNull(ActivityContextStore::current());

        ActivityContextStore::runWith($first, function () use ($second): void {
            $this->assertSame('req_1', ActivityContextStore::current()?->requestId);

            ActivityContextStore::runWith($second, function (): void {
                $this->assertSame('req_2', ActivityContextStore::current()?->requestId);
            });

            $this->assertSame('req_1', ActivityContextStore::current()?->requestId);
        });

        $this->assertNull(ActivityContextStore::current());
    }
}
