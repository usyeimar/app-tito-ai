<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Activity;

use App\Http\Requests\Tenant\Activity\IndexActivityRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use ReflectionMethod;
use Tests\TestCase;

final class IndexActivityRequestTest extends TestCase
{
    public function test_it_accepts_required_activity_filters(): void
    {
        $validator = $this->validatorFor([
            'filter' => [
                'subject_type' => $this->knownType(),
                'subject_id' => '01HZZ1TESTSUBJECTID000000000',
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_it_rejects_unknown_filter_keys(): void
    {
        $validator = $this->validatorFor([
            'filter' => [
                'subject_type' => $this->knownType(),
                'subject_id' => '01HZZ1TESTSUBJECTID000000000',
                'unexpected' => 'value',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            ['Unsupported filter keys: unexpected'],
            $validator->errors()->get('filter'),
        );
    }

    public function test_it_requires_supported_subject_type_and_subject_id(): void
    {
        $validator = $this->validatorFor([
            'filter' => [
                'subject_type' => 'unsupported_type',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('filter.subject_type', $validator->errors()->toArray());
        $this->assertArrayHasKey('filter.subject_id', $validator->errors()->toArray());
    }

    public function test_it_rejects_top_level_include_parameter(): void
    {
        $validator = $this->validatorFor([
            'filter' => [
                'subject_type' => $this->knownType(),
                'subject_id' => '01HZZ1TESTSUBJECTID000000000',
            ],
            'include' => 'relations',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('include', $validator->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatorFor(array $payload): ValidatorContract
    {
        /** @var IndexActivityRequest $request */
        $request = IndexActivityRequest::create('/tenant/api/activity', 'GET', $payload);
        $validator = validator()->make($request->all(), $request->rules());

        $method = new ReflectionMethod($request, 'withValidator');
        $method->setAccessible(true);
        $method->invoke($request, $validator);

        return $validator;
    }

    private function knownType(): string
    {
        return (string) array_key_first((array) config('activity-log.types', []));
    }
}
