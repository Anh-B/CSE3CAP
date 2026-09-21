<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Reflection;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Makes sure the demo data still seeds cleanly against the current schema,
 * and that every competency score it creates is a valid 1-5 value.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_valid_sample_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(6, Reflection::count());
        $this->assertSame(4, Assessment::count());

        $keys = ['contribution', 'communication', 'collaboration', 'agile', 'continuous', 'leadership'];

        foreach (Reflection::all()->concat(Assessment::all()) as $row) {
            $this->assertEqualsCanonicalizing($keys, array_keys($row->scores));
            foreach ($row->scores as $value) {
                $this->assertGreaterThanOrEqual(1, $value);
                $this->assertLessThanOrEqual(5, $value);
            }
        }
    }
}
