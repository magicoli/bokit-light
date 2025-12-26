@if($items->isEmpty())
    <p class="empty-state">{{ __('forms.no_items') }}</p>
@else
    <table class="card data-list">
        <thead class="card-header">
            <tr>
                @foreach($columns as $columnName => $column)
                    <th class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                        {!! $column['label'] ?? ucfirst(str_replace('_', ' ', $columnName)) !!}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="card-body">
            @if($groupBy ?? null)
                @php
                    $grouped = [];
                    foreach ($items as $item) {
                        $groupValue = is_array($item) ? $item[$groupBy] : $item->$groupBy;
                        if (!isset($grouped[$groupValue])) {
                            $grouped[$groupValue] = [];
                        }
                        $grouped[$groupValue][] = $item;
                    }
                @endphp
                
                @foreach($grouped as $groupValue => $groupItems)
                    <tr class="property-group-header">
                        <td colspan="{{ count($columns) }}"><strong>{{ $groupValue }}</strong></td>
                    </tr>
                    @foreach($groupItems as $item)
                        <tr>
                            @foreach($columns as $columnName => $column)
                                <td class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                                    {!! $formatValue($item, $columnName, $column) !!}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            @else
                @foreach($items as $item)
                    <tr>
                        @foreach($columns as $columnName => $column)
                            <td class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                                {!! $formatValue($item, $columnName, $column) !!}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endif
