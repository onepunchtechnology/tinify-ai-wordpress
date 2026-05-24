<?php
// tests/Unit/ProcessorTest.php
declare(strict_types=1);
namespace TinifyAI\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use TinifyAI\{Processor, ApiClient, MetaManager, Replacer, Scheduler, Settings};
use TinifyAI\Exception\InsufficientCreditsException;

class ProcessorTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); \Mockery::close(); parent::tearDown(); }

    private function makeMocks(): array
    {
        return [
            'api'       => \Mockery::mock(ApiClient::class),
            'meta'      => \Mockery::mock(MetaManager::class),
            'replacer'  => \Mockery::mock(Replacer::class),
            'scheduler' => \Mockery::mock(Scheduler::class),
            'settings'  => \Mockery::mock(Settings::class),
        ];
    }

    public function test_run_completes_full_pipeline(): void
    {
        ['api' => $api, 'meta' => $meta, 'replacer' => $replacer,
         'scheduler' => $scheduler, 'settings' => $settings] = $this->makeMocks();

        Functions\expect('get_attached_file')->with(42)->andReturn('/uploads/photo.jpg');
        Functions\when('file_exists')->justReturn(true);
        Functions\when('filesize')->justReturn(100000);
        Functions\when('wp_tempnam')->justReturn('/tmp/tinify_out.jpg');
        Functions\when('file_put_contents')->justReturn(1000);

        $api->shouldReceive('upload')->once()->andReturn('tmp-abc');
        $api->shouldReceive('process')->once()->andReturn('job-xyz');
        $api->shouldReceive('pollJob')->andReturn(
            ['status' => 'processing'],
            ['status' => 'completed', 'processed_size' => 32000, 'seo_alt_text' => 'A cat']
        );
        $api->shouldReceive('downloadProcessedFile')->once()->andReturn('fake binary data');

        $meta->shouldReceive('getStatus')->andReturn('pending');
        $meta->shouldReceive('setStatus')->with(42, 'processing')->once();
        $meta->shouldReceive('setJobId')->with(42, 'job-xyz')->once();
        $meta->shouldReceive('saveResults')->once();

        $replacer->shouldReceive('swap')->once();
        $settings->shouldReceive('getPipelineSettings')->andReturn([]);
        $settings->shouldReceive('isSeoAltTextEnabled')->andReturn(true);

        $processor = new Processor($api, $meta, $replacer, $scheduler, $settings);
        $processor->run(42);
    }

    public function test_run_pauses_and_reschedules_on_429(): void
    {
        ['api' => $api, 'meta' => $meta, 'replacer' => $replacer,
         'scheduler' => $scheduler, 'settings' => $settings] = $this->makeMocks();

        Functions\expect('get_attached_file')->with(42)->andReturn('/uploads/photo.jpg');
        Functions\when('file_exists')->justReturn(true);

        $api->shouldReceive('upload')->once()->andReturn('tmp-abc');
        $api->shouldReceive('process')->once()->andThrow(
            new InsufficientCreditsException('No credits', '2026-06-01T00:05:00Z')
        );

        $meta->shouldReceive('getStatus')->andReturn('pending');
        $meta->shouldReceive('setStatus')->with(42, 'processing')->once();
        $meta->shouldReceive('setStatus')->with(42, 'paused')->once();

        $scheduler->shouldReceive('rescheduleAt')->once();
        $settings->shouldReceive('getPipelineSettings')->andReturn([]);
        $settings->shouldReceive('isSeoAltTextEnabled')->andReturn(false);

        Functions\when('set_transient')->justReturn(true);
        Functions\when('update_post_meta')->justReturn(true);

        // Mock wpdb for the pauseAllPendingJobs query
        $wpdb = \Mockery::mock('wpdb');
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT ...');
        $wpdb->shouldReceive('get_col')->andReturn([99]);

        $GLOBALS['wpdb'] = $wpdb;

        $processor = new Processor($api, $meta, $replacer, $scheduler, $settings);
        $processor->run(42);
    }
}
