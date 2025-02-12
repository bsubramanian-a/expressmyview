<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Askbootstrap">
    <meta name="author" content="Askbootstrap">
    <title>{{ env('APP_NAME') }} - Register</title>
    <!-- Favicon Icon -->
    <link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}">
    <!-- Bootstrap core CSS-->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"
          integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    <!-- Custom fonts for this template-->
    <script src="https://kit.fontawesome.com/59c9493eb5.js" crossorigin="anonymous"></script>
    <!-- Custom styles for this template-->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <!-- Owl Carousel -->
    <link rel="stylesheet" href="{{ asset('css/owl.carousel.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/owl.theme.default.min.css') }}">
</head>
<body class="login-main-body">
<section class="login-main-wrapper">
    <div class="container-fluid pl-0 pr-0">
        <div class="row no-gutters">
            <div class="col-md-5 p-5 bg-white full-height">
                <div class="login-main-left">
                    <div class="text-center mb-5 login-main-left-header pt-4">
                        <img src="{{ asset('img/favicon.png') }}" class="img-fluid" alt="LOGO">
                        <h5 class="mt-3 mb-3">Welcome to {{ env('APP_NAME') }}</h5>
                        <p>It is a long established fact that a reader <br> will be distracted by the readable.</p>
                    </div>
                    <form action="{{ route('register') }}" method="post" id="registration_form">
                        @csrf
                        <div class="form-group">
                            <label for="name">Your Full Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="name" placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="email">Email address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="email" placeholder="Enter email address">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone number</label>
                            <input type="text" class="form-control" name="phone" id="phone" placeholder="Enter phone number">
                        </div>
                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Password">
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" placeholder="Retype password">
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-outline-primary btn-block btn-lg">Sign Up</button>
                        </div>
                    </form>
                    <div class="text-center mt-5">
                        <p class="light-gray">Already have an Account? <a href="{{ route('login') }}">Sign In</a></p>
                    </div>
					 <div class="text-center mt-5">
                        <p class="light-gray"> <a href="{{ route('home') }}"><i class="fas fa-long-arrow-alt-left"></i> Back to Home</a></p>
                     </div>
					
                </div>
            </div>
        <div class="col-md-7">
                  <div class="login-main-right bg-white p-5 mt-5 mb-5">
                     <div class="owl-carousel owl-carousel-login">
                        <div class="item">
                           <div class="carousel-login-card text-center">
                              <img src="img/login_image1.svg" class="img-fluid" alt="LOGO">
                              <h5 class="mt-5 mb-3">Watch Live Streams</h5>
                              <p class="mb-4">when an unknown printer took a galley of type and scrambled<br> it to make a type specimen book. It has survived not <br>only five centuries</p>
                           </div>
                        </div>
                        <div class="item">
                           <div class="carousel-login-card text-center">
                              <img src="img/login_image2.svg" class="img-fluid" alt="LOGO">
                              <h5 class="mt-5 mb-3">View Millions of Podcasts</h5>
                              <p class="mb-4">when an unknown printer took a galley of type and scrambled<br> it to make a type specimen book. It has survived not <br>only five centuries</p>
                           </div>
                        </div>
                        <div class="item">
                           <div class="carousel-login-card text-center">
                              <img src="img/login_image3.svg" class="img-fluid" alt="LOGO">
                              <h5 class="mt-5 mb-3">Get Notified from your Subscription</h5>
                              <p class="mb-4">when an unknown printer took a galley of type and scrambled<br> it to make a type specimen book. It has survived not <br>only five centuries</p>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
        </div>
    </div>
</section>
<!-- Bootstrap core JavaScript-->
<script src="{{ asset('js/app.js') }}" defer></script>
<!-- Core plugin JavaScript-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"
        integrity="sha256-H3cjtrm/ztDeuhCN9I4yh4iN2Ybx/y1RM7rMmAesA0k=" crossorigin="anonymous" defer></script>
<!-- Owl Carousel -->
<script src="{{ asset('js/owl.carousel.min.js') }}" defer></script>
<!-- jQuery Validation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js" defer></script>
<!-- Custom scripts for all pages-->
<script src="{{ asset('js/custom.js') }}" defer></script>
<script src="{{ asset('js/login.js') }}" defer></script>
</body>
</html>