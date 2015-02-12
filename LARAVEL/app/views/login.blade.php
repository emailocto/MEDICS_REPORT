@extends('layout_login')

@section('content')
	Input username and Password<br/>
	
	{{ Form::open(array('url' => 'login', 'method' => 'post')) }}
	<p>
		{{ $errors->first('username') }}
		{{ $errors->first('password') }}
		{{ $errors->first() }}
	</p>	
	<div>
		{{ Form::label('username', 'E-mail address:', array('class' => 'awesome')) }} {{ Form::text('username', Input::old('email')) }}
	</div>
	<div>
		{{ Form::label('password', 'Password:', array('class' => 'awesome')) }} {{ Form::password('password') }}
	</div>
		{{ Form::submit('Login') }}
	{{ Form::close() }}
@stop