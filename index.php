<?php

    require __DIR__ . '/vendor/autoload.php';

    session_start();
     
    use \LINE\LINEBot\SignatureValidator as SignatureValidator;
    use LINE\LINEBot\TemplateActionBuilder;
    use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
    use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
    use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
    use LINE\LINEBot\MessageBuilder;
    
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

        // init database
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
                        if(strtoupper($event['message']['text']) == 'MULAI'){
                            
                            $options[] = new MessageTemplateActionBuilder("RPL", 'RPL');
                            $options[] = new MessageTemplateActionBuilder("MULTIMEDIA", 'MULTIMEDIA');
                            $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                            $question['text'] = "Pilih Jurusan Anda";
                            $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                            $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                            $result = $bot->pushMessage($event['source']['userId'], $messageBuilder);
                        
                        }
  
                        if($event['message']['text'] == "RPL" || $event['message']['text'] == "MULTIMEDIA"){
                            $_SESSION["jurusan"] = $event['message']['text'];
                            
                            $options[] = new MessageTemplateActionBuilder("S1TI", 'S1TI');
                            $options[] = new MessageTemplateActionBuilder("D3TI", 'D3TI');
                            $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                            $question['text'] = "Pilih Jenjang Anda";
                            $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                            $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                            $result = $bot->pushMessage($event['source']['userId'], $messageBuilder); 
                        }

                        if($event['message']['text'] == "S1TI" || $event['message']['text'] == "D3TI"){
                            $_SESSION["jenjang"] = $event['message']['text'];

                            // $MSG = "Masukan HARI ex:(SENIN)";
                            // $textMSGBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($MSG);
                            // $result = $bot->pushMessage($event['source']['userId'], $textMSGBuilder);

                            $options[] = new MessageTemplateActionBuilder("SENIN", 'SENIN');
                            $options[] = new MessageTemplateActionBuilder("SELASA", 'SELASA');
                            // $options[] = new MessageTemplateActionBuilder("RABU", 'RABU');
                            // $options[] = new MessageTemplateActionBuilder("KAMIS", 'KAMIS');
                            // $options[] = new MessageTemplateActionBuilder("JUMAT", 'JUMAT');
                            // $options[] = new MessageTemplateActionBuilder("SABTU", 'SABTU');
                            $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                            $question['text'] = "Pilih hari anda";
                            $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                            $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                            $result = $bot->pushMessage($event['source']['userId'], $messageBuilder);
                        }

                        if($event['message']['text'] == "SENIN" || $event['message']['text'] == "SELASA" || 
                            $event['message']['text'] == "RABU" || $event['message']['text'] == "KAMIS" || 
                            $event['message']['text'] == "JUMAT" || $event['message']['text'] == "SABTU"){
                            $_SESSION["hari"] = $event['message']['text'];

                            echo $_SESSION["hari"].'-'.$_SESSION["jurusan"].'-'.$_SESSION["jenjang"];

                            $queryMatkul = pg_query($dbconn, "SELECT * FROM tblmatkul WHERE hari = '".$_SESSION["hari"]."' AND jurusan = '".$_SESSION["jurusan"]."' AND jenjang = '".$_SESSION["jenjang"]."'");
                            // $queryMatkul = pg_query($dbconn, "SELECT * FROM tblmatkul WHERE hari = 'SENIN' AND jurusan = 'RPL' AND jenjang = 'S1TI'");
                            
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

                                $options[] = new MessageTemplateActionBuilder("MULAI", 'MULAI');
                                $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                                $question['text'] = "Hi ".$profile['displayName'].", Selamat datang di informasi Jadwal Kuliah STMIK";
                                $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                                
                                $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                                $result = $bot->pushMessage($event['source']['userId'], $messageBuilder);

                            }

                            session_unset();
                            session_destroy();
                        }
                        
                        return $result->getHTTPStatus() . ' ' . $result->getRawBody();
                    }
                }


                // add friend follow
                if($event['type'] == 'follow')
                {
                    $res = $bot->getProfile($event['source']['userId']);
                    if ($res->isSucceeded())
                    {
                        $profile = $res->getJSONDecodedBody();
                        
                        $options[] = new MessageTemplateActionBuilder("MULAI", 'MULAI');
                        $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                        $question['text'] = "Hi ".$profile['displayName'].", Selamat datang di informasi Jadwal Kuliah STMIK";
                        $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                        
                        $packageId = 2;
                        $stickerId = 22;
                        $stickerMsgBuilder = new  \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId);
                        $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                        // send message
                        $result = $bot->pushMessage($event['source']['userId'], $stickerMsgBuilder);
                        $result = $bot->pushMessage($event['source']['userId'], $messageBuilder);
    
                        return $result->getHTTPStatus() . ' ' . $result->getRawBody();
                    }
                }
                // end friend follow
            }
        }

        pg_close($dbconn);
    });
         
$app->run();