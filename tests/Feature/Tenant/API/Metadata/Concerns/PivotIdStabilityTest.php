<?php

use App\Enums\LicenseStatus;
use App\Enums\ModuleType;
use App\Models\Tenant\CRM\Contacts\Contact;
use App\Models\Tenant\Metadata\LicenseType\LicenseType;
use App\Models\Tenant\Metadata\Tag\Tag;
use Illuminate\Support\Facades\DB;

it('keeps taggable pivot ids stable across sync calls', function () {
    $contact = Contact::factory()->create();

    $tagA = Tag::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create([
            'name' => 'pivot-stability-a',
            'color' => '#AABBCC',
            'is_active' => true,
        ]);

    $tagB = Tag::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create([
            'name' => 'pivot-stability-b',
            'color' => '#BBCCDD',
            'is_active' => true,
        ]);

    $tagIds = [(string) $tagA->getKey(), (string) $tagB->getKey()];

    $contact->syncTags($tagIds);

    $firstPivotIds = DB::table('metadata_taggables')
        ->where('taggable_type', $contact->getMorphClass())
        ->where('taggable_id', $contact->getKey())
        ->whereIn('tag_id', $tagIds)
        ->pluck('id', 'tag_id')
        ->map(fn ($id): string => (string) $id)
        ->all();

    $contact->syncTags($tagIds);

    $secondPivotIds = DB::table('metadata_taggables')
        ->where('taggable_type', $contact->getMorphClass())
        ->where('taggable_id', $contact->getKey())
        ->whereIn('tag_id', $tagIds)
        ->pluck('id', 'tag_id')
        ->map(fn ($id): string => (string) $id)
        ->all();

    expect($secondPivotIds)->toBe($firstPivotIds);
});

it('keeps licenseable pivot ids stable across sync calls', function () {
    $contact = Contact::factory()->create();

    $licenseType = LicenseType::factory()
        ->forModule(ModuleType::CONTACTS)
        ->create();

    $contact->syncLicenseTypes([[
        'license_type_id' => (string) $licenseType->getKey(),
        'license' => 'CGC123456',
        'issued_at' => '2021-01-01',
        'expires_at' => '2029-01-01',
        'issuing_authority' => 'Florida DBPR',
        'status' => LicenseStatus::ACTIVE->value,
        'notes' => 'Initial sync',
    ]]);

    $firstPivotId = DB::table('metadata_licenseables')
        ->where('licenseable_type', $contact->getMorphClass())
        ->where('licenseable_id', $contact->getKey())
        ->where('license_type_id', $licenseType->getKey())
        ->value('id');

    $contact->syncLicenseTypes([[
        'license_type_id' => (string) $licenseType->getKey(),
        'license' => 'CGC123456',
        'issued_at' => '2021-01-01',
        'expires_at' => '2029-01-01',
        'issuing_authority' => 'Florida DBPR',
        'status' => LicenseStatus::SUSPENDED->value,
        'notes' => 'Updated sync',
    ]]);

    $secondPivotId = DB::table('metadata_licenseables')
        ->where('licenseable_type', $contact->getMorphClass())
        ->where('licenseable_id', $contact->getKey())
        ->where('license_type_id', $licenseType->getKey())
        ->value('id');

    expect((string) $secondPivotId)->toBe((string) $firstPivotId);
});
