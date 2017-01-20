Bridge to convert ReactPHP response/request to zend-diactoros psr7 response/request

Here is an emxemple to use it :
 
 ```php
<?php

$container = require_once __DIR__ . '/container.php';

$bridge = new \PHPPM\Bridges\Psr7Bridge();

$app = function ($request, $response) use ($container, $bridge) {
    $psr7Request =$bridge->mapRequest($request);
    $psr7Response = new \Zend\Diactoros\Response();
    $psr7Response =  $container->get(\Zend\Stratigility\MiddlewarePipe::class)($psr7Request, $psr7Response);

    $response = $bridge->mapResponse($response, $psr7Response);
    return $response;

};

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);

$http->on('request', $app);

$socket->listen(5501);
$loop->run();
```

We will improve this document in comming days.
