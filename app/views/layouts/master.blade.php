<!DOCTYPE html>
<html lang="nb">
<head>
  <title>BIBREX</title>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
  <!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
  <!--[if lt IE 9]>
  <script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.6/html5shiv.min.js"></script>
  <![endif]-->
 
  <!-- Complete CSS (Responsive, With Icons) -->
  <link rel="stylesheet" type="text/css" href="/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="/site.css">
  <link href='http://fonts.googleapis.com/css?family=Open+Sans&subset=latin,latin-ext' rel='stylesheet' type='text/css'>
  <link rel="stylesheet" type="text/css" href="/glyphicons/html_css/css/halflings.css">
</head>
<body>

  <div class="container">

    @section('sidebar')

    <nav class="navbar navbar-default navbar-static-top" role="navigation">
      <ul class="nav navbar-nav">
        <li><a href="{{ URL::action('LoansController@getIndex') }}">Utlån</a></li>
        <li><a href="{{ URL::action('UsersController@getIndex') }}">Brukere</a></li>
        <li><a href="{{ URL::action('DocumentsController@getIndex') }}">Dokumenter</a></li>
        <li><a href="{{ URL::action('ThingsController@getIndex') }}">Ting</a></li>
        <li><a href="{{ URL::action('LogsController@getIndex') }}">Logg</a></li>
      </ul>
      <p class="navbar-text pull-right"><a href="/about">Hjelp</a></p>
     </nav>

      <p style="background: #FF0000; color:white; font-weight: bold; padding: 10px 30px; margin: 20px 0; border-radius:5px;">
        NB! Dette er en BIBREX-demo som ikke gjør noen NCIP-oppslag som medfører endringer i BIBSYS;
        demoen gjør oppslag på lånekort (LookupUser), men registrerer ikke utlån (CheckOutItem) og innleveringer (CheckInItem).
        Utlån blir kun registrert i BIBREX sin demodatabase, og lånetiden settes til 28 dager fremfor å hentes fra CheckOutResponse.
      </p>

    @show

    @if (!empty($status))
      <div class="alert alert-info" style="display:none;">
        <button type="button" class="close" data-dismiss="alert">&times;</button>  
        {{$status}}
      </div>
    @endif


    @yield('content')
  </div>

  <script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script> 
  <script type="text/javascript" src="/bootstrap/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="/hogan-2.0.0.js"></script>
  <script type="text/javascript" src="/typeahead.js/typeahead.min.js"></script>
  <!--
  <script src="//cdnjs.cloudflare.com/ajax/libs/css3finalize/3.4.0/jquery.css3finalize.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/underscore.js/1.4.4/underscore-min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/backbone.js/1.0.0/backbone-min.js"></script>
  -->
  @yield('scripts')


  <script type="text/javascript">
    if (window.location.href.match(/loans/)) {
      $('.navbar li:nth-child(1)').addClass('active');
    } else if (window.location.href.match(/users/)) {
      $('.navbar li:nth-child(2)').addClass('active');
    } else if (window.location.href.match(/documents/)) {
      $('.navbar li:nth-child(3)').addClass('active');
    } else if (window.location.href.match(/things/)) {
      $('.navbar li:nth-child(4)').addClass('active');
    } else if (window.location.href.match(/logs/)) {
      $('.navbar li:nth-child(5)').addClass('active');
    }
  </script>

  <script type="text/javascript">

    $(document).ready(function() {

      if ($('.alert').length != 0) {
        $('.alert').hide().slideDown();
      }

      //parent.postMessage("Hello","*");

    });
  </script>

</body>
</html>
