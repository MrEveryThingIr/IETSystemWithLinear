@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-app.page-header title="Dashboard" :description="'Welcome, '.auth()->user()->username.'.'" />
    <x-app.empty-state title="You’re all set" description="Your email is verified." icon="check-circle" />
@endsection
