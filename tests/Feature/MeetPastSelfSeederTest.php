<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MeetPastSelfSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sub_tools', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('main_tool_id');
            $table->string('slug')->unique();
            $table->text('prompt_template')->nullable();
            $table->string('endpoint')->nullable();
            $table->json('config')->nullable();
            $table->json('allowed_model_ids')->nullable();
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_tool_tranlations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sub_tool_id');
            $table->string('locale');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['sub_tool_id', 'locale']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sub_tool_tranlations');
        Schema::dropIfExists('sub_tools');

        parent::tearDown();
    }

    public function test_migration_configures_existing_subtool_without_creating_or_renaming_it(): void
    {
        DB::table('sub_tools')->insert([
            'id' => 33,
            'main_tool_id' => 7,
            'slug' => 'interview-your-past-self',
            'config' => json_encode(['existing_setting' => true], JSON_THROW_ON_ERROR),
            'allowed_model_ids' => json_encode([12], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_09_21_000000_add_meet_past_self_trend.php'
        );

        $migration->up();
        $migration->up();

        $tool = DB::table('sub_tools')->where('id', 33)->first();
        $config = json_decode($tool->config, true, flags: JSON_THROW_ON_ERROR);
        $allowedModelIds = json_decode($tool->allowed_model_ids, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, DB::table('sub_tools')->count());
        $this->assertSame(33, (int) $tool->id);
        $this->assertSame(7, (int) $tool->main_tool_id);
        $this->assertSame('interview-your-past-self', $tool->slug);
        $this->assertTrue($config['existing_setting']);
        $this->assertSame(46, $config['selected_model_id']);
        $this->assertSame('image_edit', $config['operation']);
        $this->assertSame([12, 46], $allowedModelIds);
        $this->assertSame(5, DB::table('sub_tool_tranlations')->count());
    }

    public function test_migration_fails_clearly_when_subtool_33_does_not_exist(): void
    {
        $migration = require database_path(
            'migrations/2026_09_21_000000_add_meet_past_self_trend.php'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Meet Your Past Self subtool (ID 33) does not exist in the sub_tools table.'
        );

        $migration->up();
    }
}
