<?php

use App\Services\Tenant\Commons\Files\TenantAssetPathBuilder;

beforeEach(function (): void {
    $this->builder = new TenantAssetPathBuilder;
});

// --- Basic path construction ---

it('builds a path with module only', function (): void {
    expect($this->builder->buildPath('files'))->toBe('files');
});

it('builds a path with module and single id', function (): void {
    expect($this->builder->buildPath('files', 'abc123'))->toBe('files/abc123');
});

it('builds a path with all segments', function (): void {
    expect($this->builder->buildPath('files', 'abc123', 'documents', 'report.pdf'))
        ->toBe('files/abc123/documents/report.pdf');
});

// --- Array inputs ---

it('builds a path with array of ids', function (): void {
    expect($this->builder->buildPath('files', ['contact', '01HXYZ']))
        ->toBe('files/contact/01HXYZ');
});

it('builds a path with array of types', function (): void {
    expect($this->builder->buildPath('workflows', '01HXYZ', ['documents', 'pdf']))
        ->toBe('workflows/01HXYZ/documents/pdf');
});

// --- Null and empty handling ---

it('skips null ids', function (): void {
    expect($this->builder->buildPath('files', null, 'media', 'photo.jpg'))
        ->toBe('files/media/photo.jpg');
});

it('skips null type', function (): void {
    expect($this->builder->buildPath('files', 'abc123', null, 'photo.jpg'))
        ->toBe('files/abc123/photo.jpg');
});

it('skips null file', function (): void {
    expect($this->builder->buildPath('files', 'abc123', 'media'))
        ->toBe('files/abc123/media');
});

it('skips empty array ids', function (): void {
    expect($this->builder->buildPath('files', [], 'media'))
        ->toBe('files/media');
});

// --- Slash handling in segments ---

it('splits slashes within a single string segment', function (): void {
    expect($this->builder->buildPath('third-party/ringcentral/voicemails', 'msg123', 'media', 'att456.mp3'))
        ->toBe('third-party/ringcentral/voicemails/msg123/media/att456.mp3');
});

it('strips leading and trailing slashes from segments', function (): void {
    expect($this->builder->buildPath('/files/', '/abc123/', '/media/'))
        ->toBe('files/abc123/media');
});

// --- Sanitization ---

it('replaces spaces with underscores', function (): void {
    expect($this->builder->buildPath('files', 'abc123', null, 'my report.pdf'))
        ->toBe('files/abc123/my_report.pdf');
});

it('replaces special characters with underscores', function (): void {
    expect($this->builder->buildPath('files', 'abc123', null, 'file@name#1.pdf'))
        ->toBe('files/abc123/file_name_1.pdf');
});

it('preserves dots, hyphens, and underscores', function (): void {
    expect($this->builder->buildPath('files', 'abc-123', 'type_a', 'file.name_v2.pdf'))
        ->toBe('files/abc-123/type_a/file.name_v2.pdf');
});

it('sanitizes special-only segments to underscores', function (): void {
    expect($this->builder->buildPath('files', '###'))->toBe('files/___');
});

it('drops segments that are empty after trimming', function (): void {
    expect($this->builder->buildPath('files', '   '))->toBe('files');
});

// --- Module fallback ---

it('falls back to module when module is empty string', function (): void {
    expect($this->builder->buildPath('', 'abc123'))->toBe('module/abc123');
});

it('sanitizes non-alphanumeric module to underscores', function (): void {
    expect($this->builder->buildPath('$$$', 'abc123'))->toBe('___/abc123');
});

// --- Real-world patterns from consumers ---

it('builds file storage path like FileService', function (): void {
    expect($this->builder->buildPath('files', ['contact', '01HXYZ']))
        ->toBe('files/contact/01HXYZ');
});

it('builds profile image path like FileService', function (): void {
    expect($this->builder->buildPath('profile-images', ['contact', '01HXYZ']))
        ->toBe('profile-images/contact/01HXYZ');
});

it('builds contact import path with file', function (): void {
    expect($this->builder->buildPath(
        module: 'contact-imports',
        ids: '01HXYZ',
        file: 'report.json',
    ))->toBe('contact-imports/01HXYZ/report.json');
});

it('builds outbound email attachment path', function (): void {
    expect($this->builder->buildPath('outbound-emails', '01HXYZ', 'attachments'))
        ->toBe('outbound-emails/01HXYZ/attachments');
});

it('builds voicemail media path with nested module', function (): void {
    expect($this->builder->buildPath(
        module: 'third-party/ringcentral/voicemails',
        ids: 'msg-001',
        type: 'media',
        file: 'att-001.mp3',
    ))->toBe('third-party/ringcentral/voicemails/msg-001/media/att-001.mp3');
});

it('builds workflow document path', function (): void {
    expect($this->builder->buildPath(
        module: 'workflows',
        ids: '01HXYZ',
        type: 'documents',
        file: 'output.pdf',
    ))->toBe('workflows/01HXYZ/documents/output.pdf');
});

it('builds ai audio path without file', function (): void {
    expect($this->builder->buildPath('ai/audio', '01HXYZ'))
        ->toBe('ai/audio/01HXYZ');
});

it('builds document template path', function (): void {
    expect($this->builder->buildPath(
        module: 'document-templates',
        ids: '01HXYZ',
        file: 'template.docx',
    ))->toBe('document-templates/01HXYZ/template.docx');
});

// --- Path traversal protection ---

it('neutralizes dot-dot traversal in ids', function (): void {
    expect($this->builder->buildPath('files', '..'))
        ->toBe('files');
});

it('neutralizes single dot in ids', function (): void {
    expect($this->builder->buildPath('files', '.'))
        ->toBe('files');
});

it('neutralizes traversal sequences embedded in slashes', function (): void {
    expect($this->builder->buildPath('files', '../../../etc/passwd'))
        ->toBe('files/etc/passwd');
});

it('neutralizes dot-dot traversal in module', function (): void {
    expect($this->builder->buildPath('..', 'abc123'))
        ->toBe('module/abc123');
});

// --- defaultDisk ---

it('exposes defaultDisk as a public method', function (): void {
    expect(method_exists($this->builder, 'defaultDisk'))->toBeTrue();
});
