<?php

namespace App\Filament\Pages;

use App\Models\Booking;
use App\Models\Property;
use App\Traits\TimezoneTrait;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;

class Calendar extends Page
{
    use TimezoneTrait;

    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-date-range';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $viewType;

    public Carbon $currentDate;

    public Carbon $startDate;

    public Carbon $endDate;

    /** @var array<int, Carbon> */
    public array $days;

    public Carbon $prevYear;

    public Carbon $nextYear;

    public Carbon $prevPeriod;

    public Carbon $nextPeriod;

    public bool $canNavigateForward;

    public bool $canNavigateYearForward;

    public Collection $properties;

    public string $displayTimezone;

    public string $displayTimezoneShort;

    public bool $showCancelled;

    public bool $showQuotes;

    public string $filterQuery;

    public function getTitle(): string
    {
        return __('app.calendar');
    }

    public function getHeading(): string
    {
        return '';
    }

    public static function getNavigationLabel(): string
    {
        return __('app.calendar');
    }

    /**
     * Reads the same query-string parameters CalendarController::index() used to (view, date,
     * cancelled, quotes) — plain <a href="?..."> links, no Livewire URL binding, so a period
     * change is a normal navigation exactly like it was as a controller action.
     */
    public function mount(): void
    {
        $request = request();

        $this->viewType = $request->get('view', 'month');

        // The current tenant's own timezone, not the blanket app-wide default (Property::timezone()
        // already falls back to the app-wide default itself when the property has none of its
        // own) — this is what makes calendar.blade.php's own "this property differs from the
        // page's displayTimezone" badges meaningful instead of permanently dormant.
        $tzString = Filament::getTenant()->timezone();
        $this->displayTimezone = $tzString;
        $this->displayTimezoneShort = self::timezoneShort($tzString);

        $dateParam = $request->get('date');
        $this->currentDate = $dateParam ? Carbon::parse($dateParam) : Carbon::now($tzString);

        switch ($this->viewType) {
            case 'week':
                $this->startDate = $this->currentDate->copy()->startOfWeek();
                $this->endDate = $this->startDate->copy()->addDays(6);
                $this->prevPeriod = $this->startDate->copy()->subWeek();
                $this->nextPeriod = $this->startDate->copy()->addWeek();
                break;
            case '2weeks':
                $this->startDate = $this->currentDate->copy()->startOfWeek();
                $this->endDate = $this->startDate->copy()->addDays(13);
                $this->prevPeriod = $this->startDate->copy()->subWeeks(2);
                $this->nextPeriod = $this->startDate->copy()->addWeeks(2);
                break;
            case 'month':
            default:
                $this->startDate = $this->currentDate->copy()->startOfMonth();
                $this->endDate = $this->currentDate->copy()->endOfMonth();
                $this->prevPeriod = $this->currentDate->copy()->subMonth();
                $this->nextPeriod = $this->currentDate->copy()->addMonth();
                break;
        }

        $this->prevYear = $this->currentDate->copy()->subYear();
        $this->nextYear = $this->currentDate->copy()->addYear();

        $maxFutureDate = Carbon::now()->addYears(2);
        $this->canNavigateForward = $this->nextPeriod->lte($maxFutureDate);
        $this->canNavigateYearForward = $this->nextYear->lte($maxFutureDate);

        $days = [];
        $day = $this->startDate->copy();
        while ($day <= $this->endDate) {
            $days[] = $day->copy();
            $day->addDay();
        }
        $this->days = $days;

        // Cancelled bookings are hidden by default, quotes (priced but not blocking) shown by default.
        $this->showCancelled = $request->boolean('cancelled');
        $this->showQuotes = $request->boolean('quotes', true);

        $hiddenStatuses = $this->showCancelled ? [] : Booking::CANCELLED_STATUSES;
        if (! $this->showQuotes) {
            $hiddenStatuses[] = 'quote';
        }

        $query = Property::where('is_active', true)->with([
            'units' => fn ($query) => $query->where('is_active', true),
            'units.bookings' => function ($query) use ($hiddenStatuses) {
                $query
                    ->with(['unit', 'property']) // Eager-load for timezone() accessor
                    ->where('check_out', '>=', $this->startDate->format('Y-m-d'))
                    ->where('check_in', '<=', $this->endDate->format('Y-m-d'))
                    ->when($hiddenStatuses, fn ($q) => $q->whereNotIn('status', $hiddenStatuses));
            },
        ]);

        $this->properties = $query->forUser()->get();

        $this->filterQuery = ($this->showCancelled ? '&cancelled=1' : '').($this->showQuotes ? '' : '&quotes=0');
    }
}
