<?php

class LoginController extends BaseController {

	public function showLogin()
	{					
		//check if has logged in already, go to home	
		if(Auth::check()) 
			return View::make('hello');
		else		
			return View::make('login');
	}
	
	public function doLogin()
	{ 
		$rules = array(
			'username' => 'required|email', //make sure the email is an actual email
			'password' => 'required|alphaNum|min:3' //password can only be alphanumeric and has to be greater than 3chars
		);
		
		$userdata = array(
			'username' => Input::get('username'),
			'password' => Input::get('password')
		);
		
		//run the validation rules on the inputs from the form
		$validator = Validator::make($userdata, $rules);
		
		//if the validator fails, redirect back to the form
		if($validator->fails()){ 			
			return Redirect::to('login')
				->withErrors($validator) //send back all errors to the login form
				->withInput(Input::except('password')); //send back the input, exclude the password			
		} else {		
			//attempt to do the login
			//echo "<script>console.log('df');</script>";   
			
			if(Auth::attempt($userdata, true)) { 
				return Redirect::to('/'); 
			} else { 	
				return Redirect::to('login')
					->withErrors(['Username or password not recognized.'])
					->withInput(Input::except('password')); //send back the input, exclude the password	
			}			
		}
	}
}
