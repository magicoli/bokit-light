@extends('layouts.error')

{{-- The layout reads the code off an exception; rendered straight from the rate limiter there is
     none, so it is stated here through the layout's own override. --}}
@section('error-code', 429)
