@extends('layout_login')

@section('content')
	<div class="container" style="background-color: #CCCCCC; width: 700px">
		<div class="row">
		Input username and Password<br/>
		
		{{ Form::open(array('url' => 'login', 'method' => 'post', 'class' => 'form-horizontal')) }}			
		<p>
			{{ $errors->first('username') }}
			{{ $errors->first('password') }}
			{{ $errors->first() }}
		</p>	
		<div class="form-group">
			{{ Form::label('username', 'E-mail address:', array('class' => 'col-xs-4 control-label')) }} 
			<div class="col-xs-4">
				{{ Form::text('username', Input::old('email'), array('class' => 'form-control', 'placeholder' => 'Email')) }}
			</div>
		</div>
		<div class="form-group">
			{{ Form::label('password', 'Password:', array('class' => 'col-xs-4 control-label')) }} 
			<div class="col-xs-4">
				{{ Form::password('password', array('class' => 'form-control', 'placeholder' => 'Password')) }}
			</div>
		</div>
		<div class="form-group">
			<div class="col-xs-4">
				{{ Form::submit('Login', array('class' => 'btn btn-default')) }}
			</div>
		</div>
		{{ Form::close() }}
		</div>
	</div>
@stop