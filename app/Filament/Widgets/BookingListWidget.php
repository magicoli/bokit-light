<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Traits\GroupedBookings;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Base for the dashboard booking mini-lists: a fixed-sort, ten-row list of
 * booking titles linking to the booking page, with a payment-status dot
 * (colors injected in the panel head by AdminPanelProvider). One row per
 * group reservation; data is scoped to the user's properties.
 */
abstract class BookingListWidget extends TableWidget
{
    use GroupedBookings;

    protected int|string|array $columnSpan = 1;

    abstract protected function tableHeading(): string;

    /**
     * Status/date filter and fixed sort order of the mini-list.
     */
    abstract protected function scopeList(Builder $query): Builder;

    /**
     * Query parameters reproducing this widget's filter and sort on the
     * bookings list page (Filament reads ?filters[...] and ?sort=col:dir).
     *
     * @return array<string, mixed>
     */
    abstract protected function listParameters(): array;

    /**
     * Extra columns displayed after the title (amounts, dates…).
     *
     * @return array<TextColumn>
     */
    protected function extraColumns(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->tableHeading())
            ->query(fn (): Builder => $this->scopeList(
                self::groupRepresentatives(Booking::query()->forUser()->with(['unit', 'property']))
            )->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('')
                    ->wrap(),

                ...$this->extraColumns(),
            ])
            // Whole-row background by payment status (styles injected in
            // the panel head by AdminPanelProvider).
            ->recordClasses(fn (Booking $record): string => 'booking-status-'.$record->displayStatus())
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('seeAll')
                    ->label(__('booking.widget.see_all'))
                    ->link()
                    ->url(BookingResource::getUrl('index').'?'.http_build_query($this->listParameters())),
            ]);
    }

    /**
     * "paid / total" column for the stays widgets.
     */
    protected function amountsColumn(): TextColumn
    {
        return TextColumn::make('amounts')
            ->label('')
            ->state(fn (Booking $record): string => self::compactMoney($record->paidAmount())
                .' / '.self::compactMoney($record->totalAmount()))
            ->alignEnd()
            ->grow(false);
    }

    /**
     * Last modification date, for the pending options/quotes widgets.
     */
    protected function updatedAtColumn(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label('')
            ->date('d/m/Y')
            ->alignEnd()
            ->grow(false);
    }

    /**
     * Locale-aware money kept compact for mini-lists: whole amounts drop
     * their decimals ("1 400 €" instead of "1 400,00 €").
     */
    protected static function compactMoney(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = Number::currency($value, 'EUR', app()->getLocale());

        return fmod($value, 1.0) === 0.0
            ? preg_replace('/[.,]00(?=\D|$)/', '', $formatted)
            : $formatted;
    }
}
