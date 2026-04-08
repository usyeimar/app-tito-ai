<?php

use App\Services\Tenant\Commons\Files\TenantAssetPathBuilder;
use App\Services\Tenant\Commons\ProfilePictures\ProfilePictureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);

    $this->service = new ProfilePictureService(new TenantAssetPathBuilder);
});

it('stores an uploaded image and returns the path', function (): void {
    $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

    $path = $this->service->store($file, 'company', '01HXYZ');

    expect($path)->toBe('profile-images/company/01HXYZ.webp');
    Storage::disk('s3')->assertExists($path);
});

it('stores image at correct path for contacts', function (): void {
    $file = UploadedFile::fake()->image('photo.png', 50, 50);

    $path = $this->service->store($file, 'contact', '01HABC');

    expect($path)->toBe('profile-images/contact/01HABC.webp');
    Storage::disk('s3')->assertExists($path);
});

it('stores image at correct path for users', function (): void {
    $file = UploadedFile::fake()->image('user.jpg', 300, 300);

    $path = $this->service->store($file, 'users', '01HUSER');

    expect($path)->toBe('profile-images/users/01HUSER.webp');
    Storage::disk('s3')->assertExists($path);
});

it('overwrites existing file at the same path', function (): void {
    $first = UploadedFile::fake()->image('v1.jpg', 100, 100);
    $second = UploadedFile::fake()->image('v2.jpg', 200, 200);

    $path1 = $this->service->store($first, 'company', '01HXYZ');
    $path2 = $this->service->store($second, 'company', '01HXYZ');

    expect($path1)->toBe($path2);
    Storage::disk('s3')->assertExists($path2);
});

it('deletes an existing picture from disk', function (): void {
    Storage::disk('s3')->put('profile-images/company/01HXYZ.webp', 'fake-image-data');

    $this->service->delete('profile-images/company/01HXYZ.webp');

    Storage::disk('s3')->assertMissing('profile-images/company/01HXYZ.webp');
});

it('does not throw when deleting a non-existent path', function (): void {
    $this->service->delete('profile-images/company/nonexistent.webp');
})->throwsNoExceptions();

it('replaces an existing picture and returns new path', function (): void {
    Storage::disk('s3')->put('profile-images/company/01HOLD.webp', 'old-image-data');

    $file = UploadedFile::fake()->image('new.jpg', 100, 100);

    $path = $this->service->replace('profile-images/company/01HOLD.webp', $file, 'company', '01HNEW');

    expect($path)->toBe('profile-images/company/01HNEW.webp');
    Storage::disk('s3')->assertExists('profile-images/company/01HNEW.webp');
    Storage::disk('s3')->assertMissing('profile-images/company/01HOLD.webp');
});

it('stores new picture when current path is null', function (): void {
    $file = UploadedFile::fake()->image('first.jpg', 100, 100);

    $path = $this->service->replace(null, $file, 'lead', '01HLEAD');

    expect($path)->toBe('profile-images/lead/01HLEAD.webp');
    Storage::disk('s3')->assertExists($path);
});

it('stores new picture when current path is empty string', function (): void {
    $file = UploadedFile::fake()->image('first.jpg', 100, 100);

    $path = $this->service->replace('', $file, 'lead', '01HLEAD');

    expect($path)->toBe('profile-images/lead/01HLEAD.webp');
    Storage::disk('s3')->assertExists($path);
});
