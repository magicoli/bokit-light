{{-- Search and Filters Bar --}}
@if(!empty($filters) || $search !== '')
<div class="card list-controls">
    <form method="GET" class="controls-form">
        @foreach(request()->except(['search', 'page']) as $key => $value)
            @if(!str_starts_with($key, 'filter_'))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach

        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('forms.search') }}...">

        @foreach($filters as $columnName => $options)
            <select name="filter_{{ $columnName }}">
                <option value="">{{ __("forms.all_{$columnName}") }}</option>
                @if(is_array($options))
                @foreach($options as $value => $label)
                    <option value="{{ $value }}" @selected(request("filter_{$columnName}") == $value)>{{ $label }}</option>
                @endforeach
                @endif
            </select>
        @endforeach

        <button type="submit">{{ __('forms.filter') }}</button>
        @if($search || !empty($currentFilters))
            <a href="{{ request()->url() }}">{{ __('forms.clear') }}</a>
        @endif
    </form>

    {{-- Pagination --}}
    @if($paginator)
        <div class="pagination-info">
            {{ __('forms.showing') }} {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} {{ __('forms.of') }} {{ $paginator->total() }}
        </div>

        <div class="pagination-controls">
            <form method="GET" style="display:inline">
                @foreach(request()->except(['per_page', 'page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <select name="per_page" onchange="this.form.submit()">
                    @foreach([10, 25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected(request('per_page', 25) == $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </form>

            <div class="pagination-links">
                @if($paginator->onFirstPage())
                    <span class="disabled">⇤</span>
                    <span class="disabled">←</span>
                @else
                    <a href="{{ $paginator->url(1) }}">⇤</a>
                    <a href="{{ $paginator->previousPageUrl() }}">←</a>
                @endif

                <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}">→</a>
                    <a href="{{ $paginator->url($paginator->lastPage()) }}">⇥</a>
                @else
                    <span class="disabled">→</span>
                    <span class="disabled">⇥</span>
                @endif
            </div>
        </div>
    @endif
</div>
@endif

<div class="card list">
@if($items->isEmpty())
    <p class="empty-state">{{ __('forms.no_items') }}</p>
@else
    <table class="data-list">
        <thead class="card-header">
            <tr>
                {{-- Status column if model has status field --}}
                @if($model && in_array('status', $model->getFillable()))
                    <th class="col-status">{{ __('forms.status') }}</th>
                @endif

                {{-- Regular columns with sorting links --}}
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

                {{-- Actions column if routePrefix exists --}}
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
                    $colCount = count($columns) + ($model && in_array('status', $model->getFillable()) ? 1 : 0) + ($routePrefix ? 1 : 0);
                @endphp

                @foreach($grouped as $groupValue => $groupItems)
                    <tr class="property-group-header">
                        <td colspan="{{ $colCount }}"><strong>{{ $groupValue }}</strong></td>
                    </tr>
                    @foreach($groupItems as $item)
                        <tr>
                            @if($model && in_array('status', $model->getFillable()))
                                <td class="col-status"><span class="status-badge status-{{ $item->status ?? 'unknown' }}">{{ $item->status ?? '-' }}</span></td>
                            @endif
                            @foreach($columns as $columnName => $column)
                                <td class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                                    {!! $formatValue($item, $columnName, $column) !!}
                                </td>
                            @endforeach
                            @if($routePrefix)
                                <td class="col-actions">
                                    @if(Route::has("{$routePrefix}.show"))
                                        <a href="{{ route("{$routePrefix}.show", $item->id) }}" title="{{ __('forms.view') }}">{!! icon('eye') !!}</a>
                                    @endif
                                    @if(Route::has("{$routePrefix}.edit"))
                                        <a href="{{ route("{$routePrefix}.edit", $item->id) }}" title="{{ __('forms.edit') }}">{!! icon('pencil') !!}</a>
                                    @endif
                                    @if(Route::has("{$routePrefix}.destroy"))
                                        <form method="POST" action="{{ route("{$routePrefix}.destroy", $item->id) }}" style="display:inline" onsubmit="return confirm('{{ __('forms.confirm_delete') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="{{ __('forms.delete') }}">{!! icon('trash') !!}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            @else
                @foreach($items as $item)
                    <tr>
                        @if($model && in_array('status', $model->getFillable()))
                            <td class="col-status"><span class="status-badge status-{{ $item->status ?? 'unknown' }}">{{ $item->status ?? '-' }}</span></td>
                        @endif
                        @foreach($columns as $columnName => $column)
                            <td class="col-{{ $columnName }} {{ $column['class'] ?? '' }}">
                                {!! $formatValue($item, $columnName, $column) !!}
                            </td>
                        @endforeach
                        @if($routePrefix)
                            <td class="col-actions">
                                @if(Route::has("{$routePrefix}.show"))
                                    <a href="{{ route("{$routePrefix}.show", $item->id) }}" title="{{ __('forms.view') }}">{!! icon('eye') !!}</a>
                                @endif
                                @if(Route::has("{$routePrefix}.edit"))
                                    <a href="{{ route("{$routePrefix}.edit", $item->id) }}" title="{{ __('forms.edit') }}">{!! icon('pencil') !!}</a>
                                @endif
                                @if(Route::has("{$routePrefix}.destroy"))
                                    <form method="POST" action="{{ route("{$routePrefix}.destroy", $item->id) }}" style="display:inline" onsubmit="return confirm('{{ __('forms.confirm_delete') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="{{ __('forms.delete') }}">{!! icon('trash') !!}</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endif
</div>
