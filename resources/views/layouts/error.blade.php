@extends('layouts.app')

@php
    use Symfony\Component\HttpFoundation\Response;

    // Auto-detect error code from view name or exception
    $errorCode = 500; // default

    // Try to get status code from exception
    if (isset($exception)) {
        if (method_exists($exception, 'getStatusCode')) {
            $errorCode = $exception->getStatusCode();
        }
    }

    // Fallback: extract from view name (e.g., "errors.403" -> 403)
    if (!isset($exception) || !method_exists($exception, 'getStatusCode')) {
        $viewName = View::getFacadeRoot()->getName();
        if (preg_match('/\.(\d{3})$/', $viewName, $matches)) {
            $errorCode = (int)$matches[1];
        }
    }

    // Get HTTP status text from Symfony - same as Laravel default
    $statusText = Response::$statusTexts[$errorCode] ?? 'Error';

    // Allow override via sections
    if (View::hasSection('error-code')) {
        $errorCode = View::getSection('error-code');
    }
    if (View::hasSection('error-title')) {
        $statusText = View::getSection('error-title');
    }

    // Get message from exception or section
    $message = '';
    if (View::hasSection('error-message')) {
        $message = View::getSection('error-message');
    }
@endphp

@section('title', $errorCode . ' - ' . $statusText)
@section('body-class', 'error error-page error-' . $errorCode)

@section('content')
<div class="error-content">
    <h1 class="error-code">{{ $errorCode }}</h1>
    <h2 class="error-title">{{ $statusText }}</h2>
    @if($message)
        <p class="error-message">{{ $message }}</p>
    @endif

    @hasSection('error-content')
        @yield('error-content')
    @endif

</div>
@endsection
