<?php

use App\Contracts\SyncHandler;
use App\Services\BookingSyncIcal;
use App\Services\IcalSyncHandler;
use Symfony\Component\Console\Output\BufferedOutput;

describe('IcalSyncHandler', function () {
    it('implements SyncHandler', function () {
        expect(new IcalSyncHandler(mock(BookingSyncIcal::class)))
            ->toBeInstanceOf(SyncHandler::class);
    });

    it('has the correct label', function () {
        expect((new IcalSyncHandler(mock(BookingSyncIcal::class)))->label())
            ->toBe('iCal feeds');
    });

    it('reports no sources and never calls syncSource when there are none active', function () {
        $parser = mock(BookingSyncIcal::class);
        $parser->shouldNotReceive('syncSource');

        $output = new BufferedOutput;
        (new IcalSyncHandler($parser))->handle($output);

        expect($output->fetch())->toContain('No active iCal sources found');
    });
});
