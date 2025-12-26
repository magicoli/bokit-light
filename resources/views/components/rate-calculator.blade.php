{{-- @pushOnce does not work here for @vite css --}}
{{-- @section does not work here for @vite css --}}
@vite('resources/css/rates-widget.css')

<div class="rate-widget">
    <form method="POST" action="{{ route('rates.calculate') }}" class="calculator-form">
        @csrf
        <div class="fields-row search-form">
            <fieldset class="form-field field-date field-check_in">
                <label for="calc_check_in">{{ __('app.check_in') }}</label>
                <input type="date" name="check_in" id="calc_check_in" value="{{ old('check_in', request('check_in', now()->addDay()->format('Y-m-d'))) }}" required>
            </fieldset>

            <fieldset class="form-field field-date field-check_out">
                <label for="calc_check_out">{{ __('app.check_out') }}</label>
                <input type="date" name="check_out" id="calc_check_out" value="{{ old('check_out', request('check_out', now()->addDays(8)->format('Y-m-d'))) }}" required>
            </fieldset>

            <fieldset class="form-field field-number field-adults">
                <label for="calc_adults">{{ __('app.adults') }}</label>
                <input type="number" name="adults" id="calc_adults" value="{{ old('adults', request('adults', 2)) }}" min="1" required>
            </fieldset>

            <fieldset class="form-field field-number field-children">
                <label for="calc_children">{{ __('app.children') }}</label>
                <input type="number" name="children" id="calc_children" value="{{ old('children', request('children', 0)) }}" min="0">
            </fieldset>

        </div>
        <div class="button-group">
            <button type="button" class="btn btn-secondary" onclick="this.form.reset(); window.location.href = window.location.pathname;">{{ __('app.clear') }}</button>
            <button type="submit" class="btn btn-primary">{{ __('rates.search') }}</button>
        </div>
    </form>

    @if($errors->has('calculation'))
        <div class="notices">
            @foreach($errors->get('calculation') as $error)
                <div class="notice notice-warning">
                    <span class="tag">&nbsp;&nbsp;</span>
                    <span class="message">{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if(session('calculation_results'))
        @php
            $results = session('calculation_results');

            $listColumns = [
                'unit_name' => ['label' => __('app.unit')],
                'rate_name' => ['label' => __('rates.rate_used'), 'class' => 'mobile-hidden'],
                'price_per_night' => [
                    'label' => __('rates.price_per_night'),
                    'format' => 'custom',
                    'formatter' => fn($item) => is_array($item)
                        ? number_format($item['price_per_night'], 2)
                        : number_format($item->price_per_night, 2)
                ],
                'total' => [
                    'label' => __('rates.total'),
                    'format' => 'custom',
                    'formatter' => fn($item) => is_array($item)
                        ? number_format($item['total'], 2)
                        : number_format($item->total, 2)
                ],
            ];
        @endphp

        {!! (new \App\Support\DataList($results))
            ->columns($listColumns)
            ->groupBy('property_name')
            ->render() !!}
    @endif
</div>
