@extends('layout_login')

@section('content')
	<div class="container">
		<div class="row" style="width: 700px">
		 <div class="col-md-offset-7 col-md-3 text-center">				
			{{ Form::open(array('url' => 'login', 'method' => 'post', 'class' => 'form-signin')) }}			
			
			Input username and Password
			<p>
				{{ $errors->first('username') }}
				{{ $errors->first('password') }}
				{{ $errors->first() }}
			</p>	
			{{ Form::text('username', Input::old('email'), array('class' => 'form-control', 'placeholder' => 'UserName')) }}
			{{ Form::password('password', array('class' => 'form-control', 'placeholder' => 'Password')) }}
			{{ Form::checkbox('chk_remember', '1') }} {{ Form::label('chk_remember', 'Remember Me?') }}
			<br/>
			{{ Form::submit('Login', array('class' => 'btn btn-primary')) }}

			{{ Form::close() }}
		</div>
		</div>
	</div>
@stop