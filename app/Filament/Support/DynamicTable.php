<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds Filament table columns and actions dynamically from model configuration.
 *
 * Reads $list_columns, $casts, $searchable, $sortable from the model's
 * ModelConfigTrait::getConfig() and generates matching Filament columns.
 * Labels use translation keys: "{langPrefix}.field.{column}".
 */
class DynamicTable
{
    /**
     * Build table columns from model's $list_columns config.
     *
     * @param  class-string<Model>  $modelClass  The Eloquent model class
     * @param  string  $langPrefix  Translation file prefix (e.g. 'booking', 'rates')
     * @param  array<string, TextColumn|IconColumn>  $overrides  Column overrides keyed by field name
     * @return array<TextColumn|IconColumn>
     */
    public static function columns(
        string $modelClass,
        string $langPrefix,
        array $overrides = [],
    ): array {
        $config = $modelClass::getConfig();
        $listColumns = $config['list_columns'];
        $casts = $config['casts'];
        $searchable = $config['searchable'];
        $sortable = $config['sortable'];
        $appends = $config['appends'];

        $columns = [];

        foreach ($listColumns as $col) {
            // Actions are handled separately via recordActions
            if ($col === 'actions') {
                continue;
            }

            // Allow per-column overrides
            if (isset($overrides[$col])) {
                $columns[] = $overrides[$col];

                continue;
            }

            $label = __("{$langPrefix}.field.{$col}");
            $cast = $casts[$col] ?? null;
            $isSearchable = in_array($col, $searchable);
            $isSortable = in_array($col, $sortable);

            // Relationship columns (_id suffix → relation.name)
            if (str_ends_with($col, '_id')) {
                $relation = str_replace('_id', '', $col);
                $columns[] = TextColumn::make("{$relation}.name")
                    ->label($label)
                    ->sortable($isSortable);

                continue;
            }

            // Boolean columns
            if ($cast === 'boolean') {
                $columns[] = IconColumn::make($col)
                    ->label($label)
                    ->boolean();

                continue;
            }

            // Date columns
            if (is_string($cast) && str_starts_with($cast, 'date')) {
                $columns[] = TextColumn::make($col)
                    ->label($label)
                    ->date('d/m/Y')
                    ->sortable($isSortable);

                continue;
            }

            // Decimal/money columns
            if (is_string($cast) && str_starts_with($cast, 'decimal')) {
                $columns[] = TextColumn::make($col)
                    ->label($label)
                    ->money('EUR', locale: fn (): string => app()->getLocale())
                    ->alignEnd()
                    ->sortable($isSortable);

                continue;
            }

            // Integer columns
            if ($cast === 'integer') {
                $columns[] = TextColumn::make($col)
                    ->label($label)
                    ->numeric()
                    ->sortable($isSortable);

                continue;
            }

            // Array columns (e.g. roles)
            if ($cast === 'array') {
                $columns[] = TextColumn::make($col)
                    ->label($label)
                    ->badge();

                continue;
            }

            // Appended attributes need getStateUsing
            $column = TextColumn::make($col)
                ->label($label)
                ->searchable($isSearchable)
                ->sortable($isSortable);

            if (in_array($col, $appends)) {
                $colName = $col;
                $column->getStateUsing(fn (Model $record) => $record->$colName);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Build icon-only record actions from model's $actions config.
     *
     * @param  class-string<Model>  $modelClass  The Eloquent model class
     * @param  string  $langPrefix  Translation file prefix
     * @return array<Action|ViewAction|EditAction>
     */
    public static function recordActions(
        string $modelClass,
        string $langPrefix,
    ): array {
        $config = $modelClass::getConfig();
        $actions = $config['actions'];
        $fillable = $config['fillable'];
        $result = [];

        foreach ($actions as $action) {
            switch ($action) {
                case 'status':
                    if (in_array('status', $fillable)) {
                        $result[] = Action::make('status')
                            ->icon(fn (Model $record): string => self::statusHeroicon($record->status))
                            ->color(fn (Model $record): string => self::statusColor($record->status))
                            ->iconButton()
                            ->tooltip(fn (Model $record): string => __("{$langPrefix}.field.status").': '.($record->status ?? '?'))
                            ->disabled();
                    }
                    break;

                case 'view':
                    $result[] = ViewAction::make()->iconButton();
                    break;

                case 'edit':
                    $result[] = EditAction::make()->iconButton();
                    break;

                case 'ota':
                    $result[] = Action::make('ota')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->iconButton()
                        ->url(fn (Model $record): ?string => $record->ota_url ?? null)
                        ->openUrlInNewTab()
                        ->visible(fn (Model $record): bool => ! empty($record->ota_url))
                        ->tooltip(__("{$langPrefix}.field.ota_link"));
                    break;
            }
        }

        return $result;
    }

    /**
     * Map booking status to a Heroicon name.
     */
    public static function statusHeroicon(?string $status): string
    {
        return match ($status) {
            'enabled', 'confirmed' => 'heroicon-o-check-circle',
            'disabled' => 'heroicon-o-x-circle',
            'new' => 'heroicon-o-sparkles',
            'cancelled', 'deleted', 'vanished' => 'heroicon-o-eye-slash',
            'option' => 'heroicon-o-clock',
            'quote' => 'heroicon-o-calculator',
            'blocked', 'unavailable' => 'heroicon-o-lock-closed',
            'undefined' => 'heroicon-o-question-mark-circle',
            'rejected' => 'heroicon-o-x-mark',
            default => 'heroicon-o-minus-circle',
        };
    }

    /**
     * Map booking status to a Filament color.
     */
    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'enabled', 'confirmed' => 'success',
            'new' => 'info',
            'option' => 'warning',
            'quote' => 'info',
            'blocked', 'unavailable' => 'warning',
            'cancelled', 'deleted', 'vanished', 'rejected' => 'danger',
            'disabled' => 'gray',
            'undefined' => 'gray',
            default => 'gray',
        };
    }
}
