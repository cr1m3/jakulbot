<?php

    require __DIR__ . '/vendor/autoload.php';
     
    use \LINE\LINEBot;
    use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
    use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
    use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
    use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
    use \LINE\LINEBot\SignatureValidator as SignatureValidator;
    
    // load config
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();

    // set false for production
    $pass_signature = true;
     
    // set LINE channel_access_token and channel_secret
    // $channel_access_token = "N8VNZFIdSSkMiX3okdv9dmtjpj8OAdw4yXX0QGo7mRYzMZxgxRYgU0jXiKRL33PAwKJt3IgxpdSTVsLF3Lh6LNnbtIvxtd5+f6FDiKDKsI/FpQFePni6gYvieFnTGA/ZLV5Ae+vc/m9gfvgEAiQBzQdB04t89/1O/w1cDnyilFU=";
    // $channel_secret = "15bd07d77917a3f8454746daef9d4eb7";

    $channel_access_token = $_ENV['CHANNEL_ACCESS_TOKEN'];
    $channel_secret = $_ENV['CHANNEL_SECRET'];


     
    // inisiasi objek bot
    $httpClient = new CurlHTTPClient($channel_access_token);
    $bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);
     

    $configs =  ['settings' => ['displayErrorDetails' => true],];
    
    $app = new Slim\App($configs);
     
    // buat route untuk url homepage
    $app->get('/', function($req, $res)
    {
      echo "JADKULBOT";
    });
     
    // buat route untuk webhook
    $app->post('/webhook', function ($request, $response) use ($bot, $pass_signature)
    {
        // get request body and line signature header
        $body      = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : '';
     
        // log body and signature
        file_put_contents('php://stderr', 'Body: '.$body);
     
        if($pass_signature === false)
        {
            // is LINE_SIGNATURE exists in request header?
            if(empty($signature)){
                return $response->withStatus(400, 'Signature not set');
            }
     
            // is this request comes from LINE?
            if(! SignatureValidator::validateSignature($body, $channel_secret, $signature)){
                return $response->withStatus(400, 'Invalid signature');
            }
        }
    
        // kode aplikasi nanti disini
        $data = json_decode($body, true);
        if(is_array($data['events'])){
            foreach ($data['events'] as $event)
            {
                if ($event['type'] == 'message')
                {
                    if($event['message']['type'] == 'text')
                    {
                        $textMessageBuilder = new TextMessageBuilder('ini pesan balasan');
                        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
                         
                     return $response->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus()); 
                    }
                }
            }
        }
     
    });
    
    
    // $app->get('/pushmessage', function($req, $res) use ($bot)
    // {
    //     // send push message to user
    //     $userId = 'U3bf29c14b2605b75c39e0728375756b9';
    //     $textMessageBuilder = new TextMessageBuilder('Halo, ini pesan push');
    //     $result = $bot->pushMessage($userId, $textMessageBuilder);
       
    //     return $res->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus());
    // });
    
    // $app->get('/content/{messageId}', function($req, $res) use ($bot)
    // {
    //     // get message content
    //     $route      = $req->getAttribute('route');
    //     $messageId = $route->getArgument('messageId');
    //     $result = $bot->getMessageContent($messageId);
     
    //     // set response
    //     $res->write($result->getRawBody());
     
    //     return $res->withHeader('Content-Type', $result->getHeader('Content-Type'));
    // });
     
$app->run();