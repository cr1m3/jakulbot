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

// initiate app
$configs =  [
	'settings' => ['displayErrorDetails' => true],
];
$app = new Slim\App($configs);

// ------------- TWIG ---------------
// Get container
$container = $app->getContainer();

// Register component on container
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('templates', [
        'cache' => false,
        'debug' => true
    ]);


    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));

    return $view;
};


// ----------------------------------------

/* ROUTES */
$app->get('/', function ($request, $response) {
	return $this->view->render($response, 'landing.html');
});

$app->get('/tambah[/]', function ($request, $response) {
	return $this->view->render($response, 'tambah-bengkel.html');
});


$app->post('/', function ($request, $response)
{

	// init database
	$host = $_ENV['DBHOST'];
	$dbname = $_ENV['DBNAME'];
	$dbuser = $_ENV['DBUSER'];
	$dbpass = $_ENV['DBPASS'];
	$dbconn = pg_connect("host=$host port=5432 dbname=$dbname user=$dbuser password=$dbpass")
	or die ("Could not connect to server\n");

	// get request body and line signature header
	$body 	   = file_get_contents('php://input');
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

	// init bot
	$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
	$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);


	$data = json_decode($body, true);

	foreach ($data['events'] as $event)
	{

		// Simpan Log Event ke database
		$sqlSaveLog = "INSERT INTO eventlogs (singature, events) VALUES ('$signature', '".$event['type']."')";
		pg_query($dbconn, $sqlSaveLog) or die("Cannot execute query: $sqlSaveLog\n");

		$sqlUser = "SELECT * FROM pengguna where user_id = '".$event['source']['userId']."' LIMIT 1";
		$queryUser = pg_query($dbconn, $sqlUser) or die("Cannot execute query: $sqlUser\n");
		$user = pg_fetch_object($queryUser);
		
		if ($event['type'] == 'message')
		{
			if($event['message']['type'] == 'text')
			{

				if ($user->addstep == 0){

					if(strtoupper($event['message']['text']) == 'ADD'){
						$balas = "Baiklah, silahkan kirimkan data yang akurat ya. Kamu bisa membatalkan proses ini dengan mengetik 'Batal'.";
						// or we can use pushMessage() instead to send reply message
						$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
						$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

						$balas = "Kirimkan nama bengkel atau pemiliknya. Contoh: 'Bengkel Pak Budi'.";
						// or we can use pushMessage() instead to send reply message
						$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
						$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

						// update addstep user menjadi 1
						$sqlUpdateStep = "UPDATE pengguna SET addstep = 1 WHERE user_id = '" .$event['source']['userId']."'";
						pg_query($dbconn, $sqlUpdateStep) or die("Cannot execute query: $sqlUpdateStep\n");

					} else {
						// $balas = "Kirimkan lokasimu saat ini untuk mencari bengkel.";
						// or we can use pushMessage() instead to send reply message

						// $textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
						// $result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

						if($event['message']['text'] == '?'){
							// actions
							$options[] = new MessageTemplateActionBuilder("Paham", '?');
							
							// prepare button template
							$question['image'] = "https://3.bp.blogspot.com/-R96nBD6QuJc/WL7c9_TXucI/AAAAAAAAES4/C4G7g6IZ1MMO5nJOK6RNcV8aaYhjBJCjQCK4B/s1600/berbagi-lokasi-line.jpg";
							$question['text'] = "Klik '+', lalu klik 'Berbagi Lokasi'";
						   	$buttonTemplate = new ButtonTemplateBuilder("Bantuan", $question['text'], $question['image'], $options);

						   	// build message
						   	$messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);

						   	// send message
						   	$result = $bot->pushMessage($event['source']['userId'], $messageBuilder);

						} else {
							$options[] = new MessageTemplateActionBuilder("Bantuan?", '?');
							// prepare button template

							$question['image'] = "erbagi-lokasi-line.jpg";
							$question['text'] = "Kirimkan lokasimu saat ini untuk mencari bengkel";
							$buttonTemplate = new ButtonTemplateBuilder("Hello!", $question['text'], $question['image'], $options);

							// build message
							$messageBuilder = new TemplateMessageBuilder("Ada pesan untukmu, pastikan membukanya dengan app mobile Line ya!", $buttonTemplate);

							// send message
							$result = $bot->pushMessage($event['source']['userId'], $messageBuilder);
						}




					}
				} else {


					// batal menambahkan bengkel
					if(strtoupper($event['message']['text']) == 'BATAL'){
						// Hapus bengkel
						pg_query($dbconn, "DELETE FROM bengkel WHERE id_bengkel=".$user->id_bengkel);
						// Reset step user
						pg_query($dbconn, "UPDATE pengguna SET addstep=0, id_bengkel=0 WHERE user_id='".$event['source']['userId']."'");

						// update yang digunakan saat ini agar berhenti melakukan input
						$sqlUser = "SELECT * FROM pengguna where user_id = '".$event['source']['userId']."' LIMIT 1";
						$queryUser = pg_query($dbconn, $sqlUser) or die("Cannot execute query: $sqlUser\n");
						$user = pg_fetch_object($queryUser);
					}



					switch ($user->addstep) {
						case 0:
							// Step dibatalkan
							$balas = "Yah! dibatalkan...";
							$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
							$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

							$stickerMsgBuilder = new  \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(2, 18);
							$result = $bot->pushMessage($event['source']['userId'], $stickerMsgBuilder);
							break;
						case 1:
							// Setp untuk mengirimkan nama bengkel
							$sqlSimpanBengkel = "INSERT INTO bengkel (nama_bengkel, user_id, step) VALUES ";
							$sqlSimpanBengkel .= "('".$event['message']['text']."','".$event['source']['userId']."',1)";
							$simpanBengkel = pg_query($dbconn, $sqlSimpanBengkel) or die("Cannot execute query: $sqlSimpanBengkel\n");

							if($simpanBengkel){
								//ambil data bengkel yang baru ditambahkan si user untuk proses step selanjutnya
								$queryBengkel = pg_query($dbconn, "SELECT * FROM bengkel WHERE user_id = '".$event['source']['userId']."' AND step=1 LIMIT 1");
								$bengkel = pg_fetch_object($queryBengkel);

								// simpan id bengkel ke tabel user dan tingkatkan step
								pg_query($dbconn, "UPDATE pengguna SET id_bengkel = '".$bengkel->id_bengkel."', addstep=2 WHERE user_id='".$event['source']['userId']."'");

								$balas = "Bagus! Sekarang kirimkan nomer telepon bengkelnya. ketik '0' bila tidak tahu.";
								$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
								$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);
							}


							break;
						case 2:
							// Setp untuk mengirimkan nomer telepon
							$sqlSimpanBengkel = "UPDATE bengkel SET telp= '".$event['message']['text']."', step=3 WHERE id_bengkel={$user->id_bengkel}";
							$simpanBengkel = pg_query($dbconn, $sqlSimpanBengkel) or die("Cannot execute query: $sqlSimpanBengkel\n");

							if($simpanBengkel){
								// update step
								pg_query($dbconn, "UPDATE pengguna SET addstep=3 WHERE user_id='".$event['source']['userId']."'");

								$balas = "Sekarang kirimkan koordinat lokasi bengkelnya.";
								$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
								$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);
							}

							break;
						case 3:
							// Setp untuk mengirimkan lokasi bengkel
							$balas = "Mohon kirimkan koordinat lokasi bengkel yang akurat ya. Tekan simbol '+' di pojok kanan bawah, kemudian pilih 'Berbagi Lokasi'.";
							$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
							$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);
							break;

						default:
							# code...
							$balas = "Sepertinya data yang kamu kirimkan belum benar.";
							$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
							$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);
							break;
					}
				}


				return $result->getHTTPStatus() . ' ' . $result->getRawBody();
			}

			if($event['message']['type'] == 'location'){

				// Ambil latitude dan longitude yang dikirim
				$lat = $event['message']['latitude'];
				$lng = $event['message']['longitude'];
				$address = $event['message']['address'];


				if ($user->addstep == 0){

					$balas = "Tunggu sebantar ya, sedang mencari...";
					$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
					$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

					// Cari lokasi terdekat di google maps
					// $sqlCariBengkel = "SELECT *, ( 6371 * acos( cos( radians(" . $lat . ") ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(" . $lng . ") ) + sin( radians(" . $lat . ") ) * sin( radians( lat ) ) ) ) AS distance FROM bengkel HAVING distance < 15";


					$miles = 1; // 1 mil dari posisi user
					$totalmiles = $miles * 0.0110238;
	            	$lowlat = $lat - $totalmiles;
	            	$hilat = $lat + $totalmiles;
	            	$lowlng = $lng - $totalmiles;
	            	$hilng = $lng + $totalmiles;
	            	$sqlCariBengkel = "select * from bengkel where lat between ";
	            	$sqlCariBengkel .= "$lowlat and $hilat";
	            	$sqlCariBengkel .= " and lng between $lowlng and $hilng";

					$hasilCari = pg_query($dbconn, $sqlCariBengkel) or die("Cannot execute query: $sqlCariBengkel\n");


					if ( pg_num_rows($hasilCari) > 0) {
						while( $data = pg_fetch_assoc($hasilCari) ){
							$title = !empty($data['nama_bengkel']) ? $data['nama_bengkel'] : "Bengkel Tambal Ban";
							$address = !empty( $data['alamat'] ) ? $data['alamat'] : "Alamat tidak tersedia";
							$latitude = $data['lat'];
							$longitude = $data['lng'];
							$locationMessageBuilder = new \LINE\LINEBot\MessageBuilder\LocationMessageBuilder($title,$address,$latitude,$longitude);
							$result = $bot->pushMessage($event['source']['userId'], $locationMessageBuilder);
						}
					} else {
						$balas = "Mohon maaf, tidak ada bengkel yang ditemukan di sekitarmu. ";
						$balas .= "Maukah Kamu membantuku untuk menambahkan bengkel. ";
						$balas .= "Ketik 'Add' untuk menambahkan.";
						$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
						$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);
					}
				} else {
					if($user->addstep == 3){

						// Setp untuk mengirimkan nomer telepon
						$sqlSimpanBengkel = "UPDATE bengkel ";
						$sqlSimpanBengkel .= "SET alamat= '$address', lat=$lat, lng=$lng, step=0 ";
						$sqlSimpanBengkel .= "WHERE id_bengkel =" .$user->id_bengkel;
						$simpanBengkel = pg_query($dbconn, $sqlSimpanBengkel) or die("Cannot execute query: $sqlSimpanBengkel\n");

						if($simpanBengkel){
							// input koordinat bengkel yang ditambahkan
							pg_query($dbconn, "UPDATE pengguna SET addstep=0, id_bengkel=0 WHERE user_id='".$event['source']['userId']."'");

							$balas = "Terima kasih atas kontirbusinya. Bengkel sudah ditambahkan.";
							$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($balas);
							$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

							$stickerMsgBuilder = new  \LINE\LINEBot\MessageBuilder\StickerMessageBuilder(1, 4);
							$result = $bot->pushMessage($event['source']['userId'], $stickerMsgBuilder);
						}
					}
				}


				return $result->getHTTPStatus() . ' ' . $result->getRawBody();
			}


		}

		if($event['type'] == 'follow')
		{

			$res = $bot->getProfile($event['source']['userId']);
			if ($res->isSucceeded())
		    {
		        $profile = $res->getJSONDecodedBody();
		        // save user data

				$welcomeMsg = "Hi " . $profile['displayName'] .", Aku akan membantumu menemukan bengkel tambal ban terdekat. Silahkan kirimkan koordinat lokasimu sekarang.";

				$packageId = 2;
				$stickerId = 22;
				$stickerMsgBuilder = new  \LINE\LINEBot\MessageBuilder\StickerMessageBuilder($packageId, $stickerId);
				$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($welcomeMsg);
				$result = $bot->pushMessage($event['source']['userId'], $stickerMsgBuilder);
				$result = $bot->pushMessage($event['source']['userId'], $textMessageBuilder);

				// User yang baru jadi teman ke database
				$sqlSaveUser = "INSERT INTO pengguna (user_id, display_name) VALUES ('".$profile['userId']."', '".$profile['displayName']."') ";
				$sqlSaveUser .= "ON CONFLICT (user_id) DO UPDATE SET ";
				$sqlSaveUser .= "display_name = '".$profile['displayName']."'";
				pg_query($dbconn, $sqlSaveUser) or die("Cannot execute query: $sqlSaveUser\n");

				return $result->getHTTPStatus() . ' ' . $result->getRawBody();
			}
		}


	}


	pg_close($dbconn);

});

// $app->get('/push/{to}/{message}', function ($request, $response, $args)
// {
// 	$httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
// 	$bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
//
// 	$textMessageBuilder = new \LINE\LINEBot\MessageBuilder\TextMessageBuilder($args['message']);
// 	$result = $bot->pushMessage($args['to'], $textMessageBuilder);
//
// 	return $result->getHTTPStatus() . ' ' . $result->getRawBody();
// });

/* JUST RUN IT */
$app->run();