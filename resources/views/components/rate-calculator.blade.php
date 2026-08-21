{{-- Rate Calculator Widget - Reusable component --}}
<div class="rate-widget">
    {!! \App\Models\Rate::form('formBookWidget', route('rates.calculate'))
        ->submitButton(__('forms.search'))
        ->render() !!}

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
                'rate_name' => ['label' => __('rate.rate_used'), 'class' => 'mobile-hidden'],
                'price_per_night' => [
                    'label' => __('rate.price_per_night'),
                    'format' => 'custom',
                    'formatter' => fn($item) => is_array($item)
                        ? number_format($item['price_per_night'], 2)
                        : number_format($item->price_per_night, 2)
                ],
                'total' => [
                    'label' => __('rate.total'),
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
