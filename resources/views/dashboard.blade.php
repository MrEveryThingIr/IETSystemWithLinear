@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">Dashboard</h1>
    <p>Welcome, {{ auth()->user()->username }}.</p>
    <p>Your email is verified.</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="underline">Log out</button>
    </form>
@endsection
