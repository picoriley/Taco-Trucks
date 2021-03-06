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
$app->post('/cart', 'updateCart');
$app->get('/cart', 'getCart');
$app->get('/users','getUserInfo');
$app->post('/orders','createOrder');
$app->get('/', function() use ($app)
{
    $app->halt(404);
});

$app->run();

function createOrder()
{
	getSession();
	$app = \Slim\Slim::getInstance();
	

	$sql_insertOrder = "INSERT INTO orders (userId, orderDate, total) VALUES (:userId, :datetime, :total)";
	$sql_insertOrderItem = "INSERT INTO orderItem (orderId, quantity) VALUES (:orderId, :quantity)";
	$sql_insertOrderItemDetails = "INSERT INTO orderItemDetails (orderItemId, tacoFixinId) 
					VALUES (:orderItemId, :tacoFixinId)";

	if(!validateSession('email'))
	{
		echo "no";
	}
	else
	{
		$userId = $_SESSION['userId'];

		try {
			$body = $app->request->getBody();
       			$request = json_decode($body);
			
			$order = $request->tacos;

			$tacoQuantityArray;
			$tacoFixinArray;
			$i = 0;
			while(isset($order[$i]))
			{
				$tacoFixinArray[] = $order[$i]->fixins;
				$tacoQuantityArray[] = $order[$i]->quantity;
				$i++;
			}
			$total = $request->total;
			
			/** ENTER ORDER need userId, date, and total */
            if( ! ini_get('date.timezone') )
            {
               date_default_timezone_set('UTC');
            } 
			$datetime = new DateTime(date("Y-m-d H:i:s"));
			$date = $datetime->format("Y-m-d H:i:s");

			$db = getConnection();
			$stmt = $db->prepare($sql_insertOrder);
			$stmt->bindParam("userId", $userId);
			$stmt->bindParam("datetime", $date);
			$stmt->bindParam("total", $total);
			$stmt->execute();
			$orderId = $db->lastInsertId();
			//print_r($orderId);
			
			/** ENTER ORDER ITEMS (TACOS) need quantity */
			foreach($tacoQuantityArray as $taco => $quantity)
			{
				$stmt = $db->prepare($sql_insertOrderItem);
				$stmt->bindParam("orderId", $orderId);
				$stmt->bindParam("quantity", $quantity);
				$stmt->execute();
				$orderItemId = $db->lastInsertId();

				/**ENTER FIXINS need tacoFixinId */
				
				foreach($tacoFixinArray as $taco => $tacoFixinIds)
				{
					foreach($tacoFixinIds as $key => $tacoFixinId)
					{
						$stmt = $db->prepare($sql_insertOrderItemDetails);
						$stmt->bindParam("orderItemId", $orderItemId);
						$stmt->bindParam("tacoFixinId", $tacoFixinId);
						$stmt->execute();
					}
				}
			}
			
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}'; 
		}catch(Exception $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
		
		
	}
}	

function getCart()
{
    getSession();
    $app = \Slim\Slim::getInstance();

    try
    {
        if(isset($_SESSION['cart']))
        {
            echo $_SESSION['cart'];
        }
        else
            echo json_encode("{'errors': true}");

    }
    catch(Exception $e)
    {
        echo json_encode("{'errors': true}");
    }
}

function updateCart()
{
    getSession();
    $app = \Slim\Slim::getInstance();
    try
    {
        $body = $app->request->getBody();
        $_SESSION['cart'] = $body;
        echo json_encode("{'success': true}");
    }
    catch(Exception $e)
    {
        $app->halt(404);
    }
}

function getUserInfo()
{
    getSession();
    $app = \Slim\Slim::getInstance();
    if(!validateSession('email'))
        $app->halt(404);
    else
    {
        $email = $_SESSION['email'];

        $sql = "SELECT f_name, l_name, cc_provider, cc_number FROM users  
        WHERE users.email =:email";

    	try {
    		$db = getConnection();
    		$stmt = $db->prepare($sql);  
    		$stmt->bindParam("email", $email);
    		$stmt->execute();
    		$userInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    		$db = null;
    		echo '{"info": ' . json_encode($userInfo) . '}'; 
    	} catch(PDOException $e) {
    		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
    	}
    }
}

function getPreviousOrder()
{
    getSession();
    $app = \Slim\Slim::getInstance();

    if(!validateSession('email'))
        $app->halt(404);
    else
    {
        $email = $_SESSION['email'];

        $sql_orderItems = "SELECT `orderItemId`, `quantity`
            FROM orderItem
            WHERE orderItem.orderId= 
            (
            SELECT `orderId`
            FROM orders
            INNER JOIN (users)
            ON (users.userId=orders.userId)
            WHERE email=:email
            ORDER BY orderDate DESC LIMIT 1
            )";

        $sql_orderFixins = "SELECT name, price, heatRating, class AS type, fixinId FROM orders 
        INNER JOIN orderItem ON orders.orderId = orderItem.orderId 
        INNER JOIN orderItemDetails 
        ON orderItem.orderItemId = orderItemDetails.orderItemId 
        INNER JOIN fixins ON orderItemDetails.tacoFixinId = fixins.fixinId
        INNER JOIN fixinClass ON fixins.fixin_classId = fixinClass.fixin_classId
        LEFT JOIN sauces ON fixins.fixinId = sauces.sauceId
        WHERE orderItemDetails.orderItemId=:orderItemId";
	
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
    			$stmt->bindParam("orderItemId", $value[$s]);
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
    $sql = "SELECT `userId`, `pass` FROM `users` WHERE email=:email";
    $errors = false;
    $response;

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
            if(!$row)
            {
                $errors = true;
                $response['success'] = false;
                $response['message'] = "User does not exist";
            }
        }
        else
        {
            $errors = true;
            $response['success'] = false;
        }
    }
    catch (PDOException $e)
    {
        $app->log->error($e->getMessage());
        $errors = true;
        $response['success'] = false;
    }
    catch (Exception $e)
    {
        $app->log->error($e->getMessage());
        $errors = true;
        $response['success'] = false;
    }

    // if no errors have occured, start new session.
    if(!$errors)
    {
        if(password_verify($user->password, $row['pass']))
        {
            newSession(sha1(SALT.$user->email));
            $_SESSION['email'] = $user->email;
            $_SESSION['userId'] = $row['userId'];
            $response['success'] = true;
            $response['message'] = "User logged in successfully";
        }
        else //user has failed login
            $response['success'] = false;
    }
    echo json_encode($response);
}

function getMenu()
{
    getSession();

    $app = \Slim\Slim::getInstance();
    $sql = "SELECT `name`, `price`, `fixinId` FROM `fixins` INNER JOIN (`fixinClass`) ON 
    (`fixins`.`fixin_classId`=`fixinClass`.`fixin_classId`) 
    WHERE `class`=:item";
    $sql_ex = "SELECT `name`, `heatRating`, `price`, `fixinId`  FROM `fixins` INNER JOIN 
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
        
        // check if user email already exists
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
            $id = $db->lastInsertId();
            $response['success'] = true;
            $response['message'] = "User created";
            newSession(sha1(SALT.$user->email));
            $_SESSION['email'] = $user->email;
            $_SESSION['userId'] = $id;
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
