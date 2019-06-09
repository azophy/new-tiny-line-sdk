<?php 

class LINEBotTiny
{
    public function __construct($channelAccessToken, $channelSecret)
    {
        $this->channelAccessToken = $channelAccessToken;
        $this->channelSecret = $channelSecret;

        if (function_exists('hash_equals'))  {
            $this->hash_equals = 'hash_equals' ;
        } else {
            $this->hash_equals = function($knownString, $userString) {
                $strlen = function ($string) {
                    if (function_exists('mb_strlen')) 
                        return mb_strlen($string, '8bit');
                    else
                        return strlen($string);
                };

                if (($length = $strlen($knownString)) !== $strlen($userString)) {
                    return false;
                }

                $diff = 0;

                for ($i = 0; $i < $length; $i++) {
                    $diff |= ord($knownString[$i]) ^ ord($userString[$i]);
                }
                return $diff === 0;
            };
        }
    }

    public function parseEvents()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            error_log("Method not allowed");
            exit();
        }

        $entityBody = file_get_contents('php://input');

        if (strlen($entityBody) === 0) {
            http_response_code(400);
            error_log("Missing request body");
            exit();
        }

        if (!$this->hash_equals($this->sign($entityBody), $_SERVER['HTTP_X_LINE_SIGNATURE'])) {
            http_response_code(400);
            error_log("Invalid signature value");
            exit();
        }

        $data = json_decode($entityBody, true);
        if (!isset($data['events'])) {
            http_response_code(400);
            error_log("Invalid request body: missing events property");
            exit();
        }
        return $data['events'];
    }

    public function replyMessage($message)
    {
        $header = array(
            "Content-Type: application/json",
            'Authorization: Bearer ' . $this->channelAccessToken,
        );

        $context = stream_context_create(array(
            "http" => array(
                "method" => "POST",
                "header" => implode("\r\n", $header),
                "content" => json_encode($message),
            ),
        ));
		$response = $this::exec_url('https://api.line.me/v2/bot/message/reply',$this->channelAccessToken,json_encode($message));
    }
	
    public function pushMessage($message) 
    {
        
		$response = $this::exec_url('https://api.line.me/v2/bot/message/push',$this->channelAccessToken,json_encode($message));
    }
	
    public function profil($userId)
    {
		return json_decode($this::exec_url('https://api.line.me/v2/bot/profile/'.$userId,$this->channelAccessToken));
    }

    private function sign($body)
    {
        $hash = hash_hmac('sha256', $body, $this->channelSecret, true);
        $signature = base64_encode($hash);
        return $signature;
    }

    static function exec_url($fullurl,$channelAccessToken,$message=Null)
    {
            $header = array(
                "Content-Type: application/json",
                'Authorization: Bearer '.$channelAccessToken,
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            if ($message != Null) {
                curl_setopt($ch, CURLOPT_POST,           1 );
                curl_setopt($ch, CURLOPT_POSTFIELDS,     $message); 
            }
            curl_setopt($ch, CURLOPT_FAILONERROR, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_URL, $fullurl);
            
            $returned =  curl_exec($ch);
        
            return($returned);
    }
}


// ======== MAIN PROGRAM SECTION ===========

$channelAccessToken = getenv('LINE_API_ACCESS_TOKEN'); //sesuaikan 
$channelSecret = getenv('LINE_API_SECRET');//sesuaikan
//$channelAccessToken = $Hook['env']['LINE_API_ACCESS_TOKEN']; //sesuaikan 
//$channelSecret = $Hook['env']['LINE_API_SECRET'];//sesuaikan

$client = new LINEBotTiny($channelAccessToken, $channelSecret);
foreach ($client->parseEvents() as $event) {
    switch ($event['type']) {
        case 'message':
            $message = $event['message'];
            switch ($message['type']) {
                case 'text':
                    $client->replyMessage([
                        'replyToken' => $event['replyToken'],
                        'messages' => [
                            [
                                'type' => 'text',
                                'text' => $message['text']
                            ]
                        ]
                    ]);
                    break;
                default:
                    error_log('Unsupported message type: ' . $message['type']);
                    break;
            }
            break;
        default:
            error_log('Unsupported event type: ' . $event['type']);
            break;
    }
};
