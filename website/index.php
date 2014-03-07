<!doctype HTML>
<html>
  <head>
    <title>The Taco Truck</title>

    <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css" />
    <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>

    <link rel="stylesheet" type="text/css" href="css/mainStyle.css">
    <link rel="stylesheet" type="text/css" href="css/homeStyle.css">

    <link href='http://fonts.googleapis.com/css?family=Alegreya+Sans|Hammersmith+One' rel='stylesheet' type='text/css'>
    
    <script type="text/javascript" src = "js/main.js" ></script>
  </head>
  <body>
    <h1 id="title">The Taco Truck</h1>
    <img src="resources/img/taco_truck_logo.png" alt="Company Logo" id="logo">
    
    <div id = "createAccountDiv">
      <h2 class ="createAnAccount">Create Account</h2>
      <form id="createAccount" action="index.html">
        <ul>
        <li>
            <input type="text" placeholder="First Name" id="firstName" name = "firstName"required/>
        </li>

        <li>
            <input type="text" placeholder="Last Name" id="lastName" name = "lastName"required/>
        </li>

        <li>
            <input type="email" placeholder="Email" id="createEmail" name="create_email"oninvalid="setCustomValidity('Please eneter a valid email address')" onchange="try{setCustomValidity('')}catch(e){}" title = "name@email.com" required/>
        </li>

        <li>
            <input type="password" id="createPassword" name="create_pass" placeholder="Password" required/>
        </li>

        <li>
            <select form="createAccount" id="CardProvider">
              <option value="Visa">Visa</option>
              <option value="MasterCard">MasterCard</option>
              <option value="American Express">American Express</option>
              <option value="Discover">Discover</option>
            </select>
        </li>

        <li>
            <input type="text" placeholder="Credit Card Number" id="creditCardNumber" name="creditCardNumber" oninvalid="setCustomValidity('This is an invalid credit card!')" onchange="try{setCustomValidity('')}catch(e){}" pattern="(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})" required />
        </li>
          </ul>
           <input type="submit" class="button" value="Sign Up" id="signUp" class="submit" />
      </form>
    </div>

    <div id="loginDiv">
      <?php include("loginModal.html"); ?>
    </div>

    <div id="createATaco">
      <h2 class="buttonText">Create a Taco</h2>
    </div>
    
  </body>
</html>