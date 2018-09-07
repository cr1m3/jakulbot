<?php

    require __DIR__ . '/vendor/autoload.php';
     
    use \LINE\LINEBot\SignatureValidator as SignatureValidator;
    
    // load config
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();

    $configs =  ['settings' => ['displayErrorDetails' => true],];
    
    $app = new Slim\App($configs);
     
    // buat route untuk url homepage
    $app->get('/', function($req, $res)
    {
    //   echo "JADKULBOT";

        $host = $_ENV['DBHOST'];
        $dbname = $_ENV['DBNAME'];
        $dbuser = $_ENV['DBUSER'];
        $dbpass = $_ENV['DBPASS'];
        $dbconn = pg_connect("host=$host port=5432 dbname=$dbname user=$dbuser password=$dbpass")
        or die ("Could not connect to server\n");

      $queryMatkul = pg_query($dbconn, "SELECT * FROM tblmatkul WHERE hari = 'SEIN'");
      $matku = pg_fetch_object($queryMatkul);
      $matkuCount = pg_num_rows($queryMatkul);

      if($matkuCount > 0){
         echo "benar";
      }else{
          echo "smalah";
      }
    });
     
    // buat route untuk webhook
    $app->post('/webhook', function ($request, $response)
    {
        // // init database
        // $host = $_ENV['DBHOST'];
        // $dbname = $_ENV['DBNAME'];
        // $dbuser = $_ENV['DBUSER'];
        // $dbpass = $_ENV['DBPASS'];
        // $dbconn = pg_connect("host=$host port=5432 dbname=$dbname user=$dbuser password=$dbpass")
        // or die ("Could not connect to server\n");

        // parameter hari,jurusan,jenjang,kelompok

        // get request body and line signature header
        $body      = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_LINE_SIGNATURE'];

        // log body and signature
        file_put_contents('php://stderr', 'Body: '.$body);

        // is LINE_SIGNATURE exists in request header?
        if (empty($signature)){
            return $response->withStatus(400, 'Signature not set');
        }

        // is this request comes from LINE?
        if($_ENV['PASS_SIGNATURE'] == false && ! SignatureValidator::validateSignature($body, $_ENV['CHANNEL_SECRET'], $signature)){
            return $response->withStatus(400, 'Invalid signature');
        }

        // inisiasi objek bot
        $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
        $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
    
        $data = json_decode($body, true);
        if(is_array($data['events'])){
            foreach ($data['events'] as $event)
            {
                if ($event['type'] == 'message')
                {
                    if($event['message']['type'] == 'text')
                    {
                         // ambil data matkul
                         $queryMatkul = pg_query($dbconn, "SELECT * FROM tblmatkul WHERE hari = '".strtoupper($event['message']['text'])."'");
                         $matku = pg_fetch_object($queryMatkul);

                        if(($matku->no) > 0){
                            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($matku->matkul);
                        }else{
                            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder("Pencarian tidak ditemukan harap coba lagi");
                        }

                        $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
                     return $response->withJson($result->getJSONDecodedBody(), $result->getHTTPStatus()); 
                    }
                }


                // add friend follow
                if($event['type'] == 'follow')
                {
                    $res = $bot->getProfile($event['source']['userId']);
                    if ($res->isSucceeded())
                    {
                        $profile = $res->getJSONDecodedBody();
                        // save user data
                        $welcomeMsg1 = "Hi " . $profile['displayName'] .", Selamat datang di informasi matakuliah mahasiswa STMIK Bumigora Mataram.";
                        $welcomeMsg2 = "Masukan Jadwal hari ini..?";

                        $packageId = 2;
                        $stickerId = 22;
                        $stickerMsgBuilder = new  \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId);
                        $textMessageBuilder1 = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($welcomeMsg1);
                        $textMessageBuilder2 = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($welcomeMsg2);
                        $result = $bot->pushMessage($event['source']['userId'], $stickerMsgBuilder);
                        $result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder1);
                        $result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder2);

                        // User yang baru jadi teman ke database
                        // $sqlSaveUser = "INSERT INTO pengguna (user_id, display_name) VALUES ('".$profile['userId']."', '".$profile['displayName']."') ";
                        // $sqlSaveUser .= "ON CONFLICT (user_id) DO UPDATE SET ";
                        // $sqlSaveUser .= "display_name = '".$profile['displayName']."'";
                        // pg_query($dbconn, $sqlSaveUser) or die("Cannot execute query: $sqlSaveUser\n");

                        return $result->getHTTPStatus() . ' ' . $result->getRawBody();
                    }
                }
                // end friend follow
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