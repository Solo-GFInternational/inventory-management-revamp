@extends('layouts.auth')
@section('title', 'Sign in')
@section('form-action', route('login'))
@section('heading', 'Inventory Management System')
@section('subheading', 'Goodman Fielder')

@section('content')
    <div>
        <label for="email" class="gf-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="gf-input">
    </div>

    <div>
        <div class="flex items-baseline justify-between">
            <label for="password" class="gf-label">Password</label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-600 hover:underline">Forgot password?</a>
        </div>
        @include('auth.partials.password-input', ['id' => 'password', 'autocomplete' => 'current-password'])
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="remember" value="1" @checked(old('remember'))
               class="size-4 rounded border-gray-300 accent-primary-600">
        Remember me
    </label>

    <x-button type="submit" size="lg" class="w-full">Sign in</x-button>
@endsection
