@if($items->isEmpty())
    <p class="empty-state">{{ __('forms.no_items') }}</p>
@else
    <table class="card data-list">
        <thead class="card-header">
            <tr>
                @foreach($columns as $columnName => $column)
                    <th>{!! $column['label'] ?? ucfirst(str_replace('_', ' ', $columnName)) !!}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="card-body">
            @foreach($items as $item)
                <tr>
                    @foreach($columns as $columnName => $column)
                        <td>
                            {!! $formatValue($item, $columnName, $column) !!}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
