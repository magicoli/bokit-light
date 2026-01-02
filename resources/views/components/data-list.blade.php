{{-- Controls form (search, filters, pagination) --}}
@if($controlsForm)
    <div class="list-controls">
        {!! $controlsForm->render() !!}
    </div>
@endif

{{-- Data table --}}
@if($items->isEmpty())
    <p class="empty-state">{{ __('forms.no_items') }}</p>
@else
    <table class="card data-list">
        <thead class="card-header">
            <tr>
                @foreach($columns as $columnName => $column)
                    <th class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                        @if($column['sortable'] ?? false)
                            @php
                                $isCurrent = $sortColumn === $columnName;
                                $nextDir = ($isCurrent && $sortDirection === 'asc') ? 'desc' : 'asc';
                                $params = array_merge(request()->all(), ['sort' => $columnName, 'dir' => $nextDir]);
                            @endphp
                            <a href="?{{ http_build_query($params) }}" class="sortable {{ $isCurrent ? 'active' : '' }}">
                                {!! $column['label'] ?? ucfirst(str_replace('_', ' ', $columnName)) !!}
                                @if($isCurrent)
                                    <span class="sort-icon">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        @else
                            {!! $column['label'] ?? ucfirst(str_replace('_', ' ', $columnName)) !!}
                        @endif
                    </th>
                @endforeach

                {{-- Actions column --}}
                @if($routePrefix)
                    <th class="col-actions">{{ __('forms.actions') }}</th>
                @endif
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
                    $colCount = count($columns) + ($routePrefix ? 1 : 0);
                @endphp

                @foreach($grouped as $groupValue => $groupItems)
                    <tr class="property-group-header">
                        <td colspan="{{ $colCount }}"><strong>{{ $groupValue }}</strong></td>
                    </tr>
                    @foreach($groupItems as $item)
                        <tr>
                            @foreach($columns as $columnName => $column)
                                <td class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                                    {!! $formatValue($item, $columnName, $column) !!}
                                </td>
                            @endforeach

                            @if($routePrefix)
                                <td class="col-actions">
                                    @if(Route::has("{$routePrefix}.edit"))
                                        <a href="{{ route("{$routePrefix}.edit", $item->id) }}" title="{{ __('forms.edit') }}">{!! icon('pencil') !!}</a>
                                    @endif
                                    @if(Route::has("{$routePrefix}.show"))
                                        <a href="{{ route("{$routePrefix}.show", $item->id) }}" title="{{ __('forms.view') }}">{!! icon('eye') !!}</a>
                                    @endif
                                </td>
                            @endif
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

                        @if($routePrefix)
                            <td class="col-actions">
                                @if(Route::has("{$routePrefix}.edit"))
                                    <a href="{{ route("{$routePrefix}.edit", $item->id) }}" title="{{ __('forms.edit') }}">{!! icon('pencil') !!}</a>
                                @endif
                                @if(Route::has("{$routePrefix}.show"))
                                    <a href="{{ route("{$routePrefix}.show", $item->id) }}" title="{{ __('forms.view') }}">{!! icon('eye') !!}</a>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endif
