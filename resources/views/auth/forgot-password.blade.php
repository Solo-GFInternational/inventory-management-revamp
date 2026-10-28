@extends('layouts.auth')
@section('title', 'Forgot password')
@section('form-action', route('password.email'))
{{-- A block section: the inline @section('x', '...') form would HTML-escape the quotes. --}}
@section('form-attributes')data-processing="Sending your reset link…" data-processing-text="Please wait, this can take a moment."@endsection
@section('heading', 'Forgot your password?')
@section('subheading', "Enter your email and we'll send you a reset link.")

@section('content')
    <div>
        <label for="email" class="gf-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="gf-input">
    </div>

    <x-button type="submit" size="lg" class="w-full">Send reset link</x-button>

    <p class="text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:underline">Back to sign in</a>
    </p>
@endsection
