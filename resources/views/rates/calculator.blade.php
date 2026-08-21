@extends('layouts.app')

@section('title', __('rate.test_calculator'))

@section('content')
    <div class="card">
        @include('components.rate-calculator')
    </div>
@endsection
