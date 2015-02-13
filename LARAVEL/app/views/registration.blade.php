@extends('layout_registration')

@section('content')
	{{-- content registration --}}
	<div class="container">
		<div class="row" style="width: 700px">
		 <div class="col-md-offset-7 col-md-3 text-center">				
			{{ Form::open(array('url' => 'register', 'method' => 'post', 'class' => 'form-group')) }}			
			
			Register
			<p>
				{{ $errors->first('username') }}
				{{ $errors->first('password') }}
				{{ $errors->first() }}
			</p>	
			<div class="form-inline">
			{{ Form::label('username', 'UserName:') }}
				{{ Form::text('username', Input::old('email'), array('class' => 'form-control', 'placeholder' => 'UserName')) }}
			</div>
			<div class="form-inline">
			{{ Form::label('password', 'Password:') }}
				{{ Form::password('password', array('class' => 'form-control', 'placeholder' => 'Password')) }}
			</div>
			<div class="form-inline">
			{{ Form::label('re_password', 'Re-type Password:') }}
				{{ Form::password('re_password', array('class' => 'form-control', 'placeholder' => 'Re-type Password')) }}
			</div>
			<div class="form-inline">
			{{ Form::label('fname', 'First Name:') }}
				{{ Form::text('fname', Input::old('email'), array('class' => 'form-control')) }}
			</div>
			<div class="form-inline">
			{{ Form::label('mname', 'Middle Name:') }}
				{{ Form::text('mname', Input::old('email'), array('class' => 'form-control')) }}
			</div>
			<div class="form-inline">
			{{ Form::label('lname', 'Last Name:') }}
				{{ Form::text('lname', Input::old('email'), array('class' => 'form-control')) }}
			</div>
			<div class="form-inline">
			{{ Form::checkbox('chk_remember', '1') }} {{ Form::label('chk_remember', 'Remember Me?') }}
			<br/>
			{{ Form::submit('Register', array('class' => 'btn btn-primary')) }}

			{{ Form::close() }}
		</div>
		</div>
	</div>
@stop