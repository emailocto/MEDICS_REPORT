<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Medics.com</title>
	<link rel="stylesheet" href="css/home.css" />
	<link rel="stylesheet" href="css/bootstrap/bootstrap.css" />
</head>
<body>
	<div class="container" id="container">		
		<span align="right">@yield('log_text')</span>
		<div class="top" id="top">					
			<h1 class="home_header">Medics.com</h1>			
		</div>
		
		<div class="welcome" id="body">					
			@yield('content')
		</div>
	</div>
</body>
</html>
