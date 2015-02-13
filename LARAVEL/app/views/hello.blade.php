@extends('layout_hello')

@section('log_text')
	@if(Auth::check())
		<a href="logout">Logout</a>
	@else 
		<a href="login">Login</a>
	@endif
	
@section('content')
	<h1>Body</h1>
	
@stop