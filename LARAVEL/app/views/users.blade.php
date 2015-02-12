@extends('layout_users')

@section('content')
	Users!
	
	@foreach($users as $user)
		<p>{{$user->sEmail}} {{$user->sFirstName}}</p>
	@endforeach
@stop