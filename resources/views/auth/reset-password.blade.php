@extends('layouts.auth')
@section('title', 'Reset password')
@section('form-action', route('password.store'))
@section('heading', 'Choose a new password')
@section('subheading', 'At least 8 characters.')

@section('content')
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <div>
        <label for="email" class="gf-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
               class="gf-input">
    </div>

    <div>
        <label for="password" class="gf-label">New password</label>
        @include('auth.partials.password-input', ['id' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8])
    </div>

    <div>
        <label for="password_confirmation" class="gf-label">Confirm new password</label>
        @include('auth.partials.password-input', ['id' => 'password_confirmation', 'autocomplete' => 'new-password', 'minlength' => 8])
    </div>

    <x-button type="submit" size="lg" class="w-full">Reset password</x-button>

    <p class="text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:underline">Back to sign in</a>
    </p>
@endsection
