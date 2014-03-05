<?php
require '../../vendor/autoload.php';
require_once('../../init.php');
require_once('../../lib/password.php');
require_once('../../lib/session.php');

session_cache_limiter(false);


$app = new \Slim\Slim();
$app->log->setEnabled(true);

$app->get('/menu', 'getMenu');
$app->post('/login', 'authenticateUser');
$app->get('/logout', 'destroySession');
$app->get('/locations', 'getLocations');
$app->post('/users', 'createUser');
$app->get('/orders', 'getPreviousOrder');
$app->get('/', function() use ($app)
{
    $app->halt(404);
});

$app->run();



function getPreviousOrder()
{
    getSession();
    $app = \Slim\Slim::getInstance();

    if(!validateSession('email'))
        $app->halt(404);
    else
    {
        $email = $_SESSION['email'];

        $sql_orderItems = "SELECT orderItemId, quantity From orders 
        INNER JOIN orderItem ON orders.orderId = orderItem.orderId 
        INNER JOIN users ON orders.userID = users.userId 
        WHERE users.email =:email 
        AND orders.orderId = 
        (SELECT orderId FROM orders ORDER BY orderDate DESC LIMIT 1)";

        $sql_orderFixins = "SELECT name, price, heatRating FROM orders 
        INNER JOIN orderItem ON orders.orderId = orderItem.orderId 
        INNER JOIN orderItemDetails 
        ON orderItem.orderItemId = orderItemDetails.orderItemId 
        INNER JOIN fixins ON orderItemDetails.tacoFixinId = fixins.fixinId 
        LEFT JOIN sauces ON fixins.fixinId = sauces.sauceId INNER JOIN users 
        ON orders.userID = users.userId 
        WHERE email=:email AND orderItem.orderItemId =:orderItem 
        AND orders.orderId = (SELECT orderId FROM orders ORDER BY orderDate 
        DESC LIMIT 1)";
	
    	$order = array();

    	try {
    		$db = getConnection();
    		$stmt = $db->prepare($sql_orderItems);  
    		$stmt->bindParam("email", $email);
    		$stmt->execute();
    		$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    		$s = "orderItemId";
    		$q = "quantity";
    		$i = 0;

    		foreach($orderItems as $key => $value) {
    			
    			$stmt = $db->prepare($sql_orderFixins);
    			$stmt->bindParam("email", $email);
    			$stmt->bindParam("orderItem", $value[$s]);
    			$stmt->execute();
    			$fixins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    			array_push($fixins, array($q => $value[$q]));
    			$order[$i++] = $fixins;
    			
    		}  

    		$db = null;
    		echo '{"order": ' . json_encode($order) . '}'; 
    	} catch(PDOException $e) {
    		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
    	}
    }
}

function getLocations()
{
    getSession();
    $app = \Slim\Slim::getInstance();
    $sql = "SELECT * FROM locations";
    $response;
    try
    {
        $db = getConnection();
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response['locations'] = $rows;
        $response['success'] = true;
    }
    catch(PDOException $e)
    {
        $app->log->error($e->getMessage());
        $response['success'] = false;
        $response['message'] = "Errors occured";
    }
    echo json_encode($response);
}

function authenticateUser()
{
    getSession();

    $app = \Slim\Slim::getInstance();
    $user;
    $sql = "SELECT `pass` FROM `users` WHERE email=:email";
    $errors = false;

    try
    {
        $body = $app->request->getBody();
        $user = json_decode($body);
        if(isset($user))
        {
            $db = getConnection();
            $stmt = $db->prepare($sql);
            $stmt->execute(array(':email' => $user->email));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        else
            $errors = true;
    }
    catch (PDOException $e)
    {
        $app->log->error($e->getMessage());
        $errors = true;
    }
    catch (Exception $e)
    {
        $app->log->error($e->getMessage());
        $errors = true;
    }

    // if no errors have occured, start new session.
    if(!$errors)
    {
        if(password_verify($user->password, $row['pass']))
        {
            newSession(sha1(SALT.$user->email));
            $_SESSION['email'] = $user->email;
            echo json_encode("[ { 'success': true, 'email': ".$_SESSION['email']." } ]");
        }
        else //user has failed login
        echo json_encode("[ { 'success': false, 'message': 'pass bad' } ]");
    }
    else // if errors have occured
        echo json_encode("[ { 'success': false } ]");
}

function getMenu()
{
    getSession();

    $app = \Slim\Slim::getInstance();
    $sql = "SELECT `name`, `price` FROM `fixins` INNER JOIN (`fixinClass`) ON 
    (`fixins`.`fixin_classId`=`fixinClass`.`fixin_classId`) 
    WHERE `class`=:item";
    $sql_ex = "SELECT `name`, `price`, `heatRating` FROM `fixins` INNER JOIN 
    (`fixinClass`) ON (`fixinClass`.`fixin_classId`=`fixins`.`fixin_classId`)
    INNER JOIN (`sauces`) ON (`sauces`.`sauceId`=`fixins`.`fixinId`) 
    WHERE `fixinClass`.`class`=:item";
    $items = array(
        'type',
        'tortillas',
        'rice',
        'cheese',
        'beans',
        'sauces',
        'vegetables',
        'extras' 
    );

    $menu = array();

    try
    {
        $db = getConnection();
        foreach ($items as $item) {
            if($item != 'sauces')
                $stmt = $db->prepare($sql);
            else
                $stmt = $db->prepare($sql_ex);
            $stmt->execute(array(':item' => $item));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $menu[$item] = $rows;
        }
    }

    catch (PDOException $e)
    {
        $app->log->error($e->getMessage());
        $menu = array();

    }
    echo '{"menu": '. json_encode($menu) . '}';
}

function getConnection()
{
    $dbhost = DB_HOST;
    $dbname = DB_NAME;
    $dbuser = DB_USER;
    $dbpass = DB_PASS;

    $dbset = "mysql:host=$dbhost;dbname=$dbname;";

    $db = new PDO($dbset, $dbuser, $dbpass);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $db;
}

function createUser()
{
    getSession();
    $app = \Slim\Slim::getInstance();
    $sql = "INSERT INTO `users`(`f_name`, `l_name`, `email`, `pass`,
        `telephoneNumber`, `cc_provider`, `cc_number`)
    VALUES (:f_name, :l_name, :email, :pass, :tele, :cc_p, :cc_n)";
    $sql_check = "SELECT COUNT(*) AS is_user FROM `users` WHERE `email`=:email";

    $response = array();

    try
    {
        $body = $app->request->getBody();
        $user = json_decode($body);
        $db = getConnection();
        
        // check if user email already exisits
        $stmt = $db->prepare($sql_check);
        $stmt->execute(array(':email' => $user->email));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If an account is NOT found with that email
        if($row['is_user'] == 0)
        {
            $stmt = $db->prepare($sql);
            $params = array(
                ':f_name' => $user->f_name,
                ':l_name' => $user->l_name,
                ':email' => $user->email,
                ':pass' => password_hash($user->password, PASSWORD_DEFAULT),
                ':tele' => $user->tele_num,
                ':cc_p' => $user->cc_p,
                ':cc_n' => $user->cc_n
            );
            $stmt->execute($params);
            $response['success'] = true;
            $response['message'] = "User created";
            newSession(sha1(SALT.$user->email));
            $_SESSION['email'] = $user->email;
        }
        else
        {
            $response['success'] = false;
            $response['message'] = "Duplicate email";
        }
    }
    catch(PDOException $e)
    {
        $app->log->error($e->getMessage());
        $response['success'] = false;
        $response['message'] = "Errors occured.";
    }
    catch(Exception $e)
    {
        $app->log->error($e->getMessage());
        $response['success'] = false;
        $response['message'] = "Errors occured.";
    }

    echo json_encode($response);
}
