<?php

namespace Tests\__Application__\Http\Requests;

use App\__Application__\Http\Requests\ProspectSearchRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Tests unitaires pour ProspectSearchRequest
 */
class ProspectSearchRequestTest extends TestCase
{
    public function test_validates_required_query(): void
    {
        $data = [];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    public function test_validates_query_minimum_length(): void
    {
        $data = ['query' => 'a']; // Too short
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    public function test_validates_query_maximum_length(): void
    {
        $data = ['query' => str_repeat('a', 256)]; // Too long
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('query', $validator->errors()->toArray());
    }

    public function test_accepts_valid_query(): void
    {
        $data = ['query' => 'restaurant'];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertFalse($validator->fails());
    }

    public function test_validates_filters_is_array(): void
    {
        $data = [
            'query' => 'restaurant',
            'filters' => 'invalid'
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('filters', $validator->errors()->toArray());
    }

    public function test_validates_filter_values(): void
    {
        $data = [
            'query' => 'restaurant',
            'filters' => [
                'location' => str_repeat('a', 256), // Too long
                'radius' => 0, // Too small
                'limit' => 101, // Too large
                'sector' => str_repeat('a', 256) // Too long
            ]
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();
        
        $this->assertArrayHasKey('filters.location', $errors);
        $this->assertArrayHasKey('filters.radius', $errors);
        $this->assertArrayHasKey('filters.limit', $errors);
        $this->assertArrayHasKey('filters.sector', $errors);
    }

    public function test_accepts_valid_filters(): void
    {
        $data = [
            'query' => 'restaurant',
            'filters' => [
                'location' => 'Paris',
                'sector' => 'restaurant',
                'radius' => 5000,
                'postal_code' => '75001',
                'limit' => 20
            ]
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertFalse($validator->fails());
    }

    public function test_validates_sources_array(): void
    {
        $data = [
            'query' => 'restaurant',
            'sources' => 'invalid'
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sources', $validator->errors()->toArray());
    }

    public function test_validates_source_values(): void
    {
        $data = [
            'query' => 'restaurant',
            'sources' => ['invalid_source', 'another_invalid']
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();
        
        $this->assertArrayHasKey('sources.0', $errors);
        $this->assertArrayHasKey('sources.1', $errors);
    }

    public function test_accepts_valid_sources(): void
    {
        $data = [
            'query' => 'restaurant',
            'sources' => ['google_maps', 'nominatim']
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        $this->assertFalse($validator->fails());
    }

    public function test_validates_save_search_boolean(): void
    {
        $data = [
            'query' => 'restaurant',
            'save_search' => 'invalid'
        ];
        
        $validator = Validator::make($data, (new ProspectSearchRequest())->rules());
        
        // Boolean validation should fail for non-boolean values
        $this->assertTrue($validator->fails());
    }

    public function test_helper_methods(): void
    {
        $request = new ProspectSearchRequest();
        
        // Mock validated data
        $request->replace([
            'query' => 'restaurant',
            'filters' => ['city' => 'Paris'],
            'sources' => ['google_maps'],
            'save_search' => false
        ]);
        
        // Mock the validation to return our test data
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        
        // We can't easily test the helper methods without mocking the validation
        // But we can test the structure is correct
        $this->assertIsArray($request->rules());
        $this->assertIsArray($request->messages());
    }

    public function test_custom_validation_messages(): void
    {
        $request = new ProspectSearchRequest();
        $messages = $request->messages();
        
        $this->assertArrayHasKey('query.required', $messages);
        $this->assertArrayHasKey('query.min', $messages);
        $this->assertArrayHasKey('query.max', $messages);
        $this->assertArrayHasKey('sources.*.in', $messages);
        
        $this->assertEquals('Le terme de recherche est obligatoire', $messages['query.required']);
        $this->assertTrue(str_contains($messages['sources.*.in'], 'Sources supportées'));
    }

    public function test_all_validation_rules_covered(): void
    {
        $request = new ProspectSearchRequest();
        $rules = $request->rules();
        
        // Verify all expected rules are present
        $expectedRules = [
            'query',
            'filters',
            'filters.location',
            'filters.sector',
            'filters.radius',
            'filters.limit',
            'sources',
            'sources.*',
            'save_search'
        ];
        
        foreach ($expectedRules as $expectedRule) {
            $this->assertArrayHasKey($expectedRule, $rules, "Rule '{$expectedRule}' is missing");
        }
    }
}