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
      echo "JADKULBOT";
    });
     
    // buat route untuk webhook
    $app->post('/webhook', function ($request, $response)
    {
        // // init database
        $host = $_ENV['DBHOST'];
        $dbname = $_ENV['DBNAME'];
        $dbuser = $_ENV['DBUSER'];
        $dbpass = $_ENV['DBPASS'];
        $dbconn = pg_connect("host=$host port=5432 dbname=$dbname user=$dbuser password=$dbpass")
        or die ("Could not connect to server\n");

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
                        // parameter hari/jurusan/jenjang
                        $inputMatkul = strtoupper($event['message']['text']);
                        $data = explode("/",$inputMatkul);
 
                         $queryMatkul = pg_query($dbconn, "SELECT * FROM tblmatkul WHERE hari = '".$data[0]."' AND jurusan = '".$data[1]."' AND jenjang = '".$data[2]."'");
                         $matkuCount = pg_num_rows($queryMatkul);

                         if($matkuCount > 0){
                            $matku = pg_fetch_object($queryMatkul);
                            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(
                                "HARI : ".$matku->hari.",
                                \n JURUSAN : ".$matku->jurusan."
                                \n JENJANG : ".$matku->jenjang."
                                \n RUANG : ".$matku->ruang."
                                \n WAKTU : ".$matku->waktu."
                                \n KELOMPOK : ".$matku->kelompok."
                                \n DOSEN : ".$matku->dosen
                            );
                            $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
                         }else{
                            $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder("Pencarian tidak ditemukan harap coba lagi");
                            $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);
                        }

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
                        $welcomeMsg2 = "Masukan pencarian \n HARI/JURUSAN/JENJANG \n ex:(SENIN/RPL/S1TI) : ";

                        $packageId = 2;
                        $stickerId = 22;
                        $stickerMsgBuilder = new  \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId);
                        $textMessageBuilder1 = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($welcomeMsg1);
                        $textMessageBuilder2 = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($welcomeMsg2);
                        $result = $bot->pushMessage($event['source']['userId'], $stickerMsgBuilder);
                        $result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder1);
                        $result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder2);

                        return $result->getHTTPStatus() . ' ' . $result->getRawBody();
                    }
                }
                // end friend follow
            }
        }
    });
         
$app->run();