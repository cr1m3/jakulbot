<?php

    require __DIR__ . '/vendor/autoload.php';
  
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
                            $JURUSAN = $event['message']['text'];
                            $queryEvent = "UPDATE tblevent SET jurusan='$JURUSAN' WHERE id='1'";
                            pg_query($dbconn, $queryEvent) or die("Cannot execute query: $queryEvent\n");

                            $options[] = new MessageTemplateActionBuilder("S1TI", 'S1TI');
                            $options[] = new MessageTemplateActionBuilder("D3TI", 'D3TI');
                            $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                            $question['text'] = "Pilih Jenjang Anda";
                            $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                            $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                            $result = $bot->pushMessage($event['source']['userId'], $messageBuilder); 
                        }

                        if($event['message']['text'] == "S1TI" || $event['message']['text'] == "D3TI"){
                            $JENJANG = $event['message']['text'];

                            $queryEvent = "UPDATE tblevent SET jenjang='$JENJANG' WHERE id='1'";
                            pg_query($dbconn, $queryEvent) or die("Cannot execute query: $queryEvent\n");

                            $MSG = "Masukan HARI : 
                                    \n * SENIN
                                    \n * SELASA
                                    \n * RABU
                                    \n * KAMIS
                                    \n * JUMAT
                                    \n * SABTU";

                            $textMSGBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($MSG);
                            $result = $bot->pushMessage($event['source']['userId'], $textMSGBuilder);
                        }

                        if(strtoupper($event['message']['text']) == "SENIN" || strtoupper($event['message']['text']) == "SELASA" || 
                            strtoupper($event['message']['text']) == "RABU" || strtoupper($event['message']['text']) == "KAMIS" || 
                            strtoupper($event['message']['text']) == "JUMAT" || strtoupper($event['message']['text']) == "SABTU"){
                            $HARI = strtoupper($event['message']['text']);
 
                            $queryEvent = "UPDATE tblevent SET hari='$HARI' WHERE id='1'";
                            pg_query($dbconn, $queryEvent) or die("Cannot execute query: $queryEvent\n");
                         
                            $options[] = new MessageTemplateActionBuilder("SELESAI", 'SELESAI');
                            $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                            $question['text'] = "Klik 'SELESAI' untuk melihat hasil";
                            $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                            $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                            $result = $bot->pushMessage($event['source']['userId'], $messageBuilder);
                        }
 
                        if($event['message']['text'] == "SELESAI"){

                            $queryMatkul = pg_query($dbconn, "SELECT * FROM tblmatkul WHERE hari = (SELECT hari FROM tblevent) AND jurusan = (SELECT jurusan FROM tblevent) AND jenjang = (SELECT jenjang FROM tblevent)");
                            $matkuCount = pg_num_rows($queryMatkul);

                            if($matkuCount > 0){
                                $matku = pg_fetch_object($queryMatkul);
                                $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder(
                                    "JADWAL KULIAH 
                                    \n * HARI : ".$matku->hari."
                                    \n * JURUSAN : ".$matku->jurusan."
                                    \n * JENJANG : ".$matku->jenjang."
                                    \n * RUANG : ".$matku->ruang."
                                    \n * WAKTU : ".$matku->waktu."
                                    \n * KELOMPOK : ".$matku->kelompok."
                                    \n * DOSEN : ".$matku->dosen
                                );
                                $result = $bot->replyMessage($event['source']['userId'], $textMessageBuilder);
                            }
                            // else{
                            //     $options[] = new MessageTemplateActionBuilder("CARI YANG LAIN", 'MULAI');
                            //     $question['image'] = "https://scontent-atl3-1.cdninstagram.com/vp/d028c1f665944cf64f24d03edd8818b6/5C18755A/t51.2885-15/e35/37629924_825187871202623_3854795657114025984_n.jpg";
                            //     $question['text'] = "Jadwal untuk matakuliah belum ada";
                            //     $buttonTemplate = new ButtonTemplateBuilder("JADWAL KULIAH", $question['text'], $question['image'], $options);
                                
                            //     $messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);
                            //     $result = $bot->pushMessage($event['source']['userId'], $messageBuilder);  
                            // }
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