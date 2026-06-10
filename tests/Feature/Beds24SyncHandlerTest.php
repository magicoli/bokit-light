<?php

use App\Contracts\SyncHandler;
use Modules\Beds24\Services\Beds24SyncHandler;
use Symfony\Component\Console\Output\BufferedOutput;

describe('Beds24SyncHandler', function () {
    it('implements SyncHandler', function () {
        expect(new Beds24SyncHandler)->toBeInstanceOf(SyncHandler::class);
    });

    it('has the correct label', function () {
        expect((new Beds24SyncHandler)->label())->toBe('Beds24 API');
    });

    it('reports no properties when none have Beds24 configured', function () {
        $output = new BufferedOutput;
        (new Beds24SyncHandler)->handle($output);

        expect($output->fetch())->toContain('No properties found with Beds24 configured');
    });

    it('reports no properties in dry-run mode too', function () {
        $output = new BufferedOutput;
        (new Beds24SyncHandler)->handle($output, dryRun: true);

        expect($output->fetch())->toContain('No properties found with Beds24 configured');
    });
});
