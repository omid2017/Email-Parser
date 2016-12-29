<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.inc';

session_start();

/**
 * Class WorkerThreads
 */
class WorkerThreads extends Thread
{
    private $workerId;
    private $FOUND_BODY;
    public $result;

    public function __construct($id, $found_bdy)
    {
        $this->workerId = $id;
        $this->FOUND_BODY = $found_bdy;
    }

    public function run()
    {
        sleep(1);
        //Cutting off the Signature
        $signature = substr($this->FOUND_BODY, strrpos($this->FOUND_BODY, "\n--") + strlen('\n--') + 1, strlen($this->FOUND_BODY));
        //echo $FOUND_BODY;
        $token = strtok($signature, PHP_EOL);
        $row_counter = 0;
        while ($token !== false) {
            if ($row_counter == 0) { //first line of signature
                $first_name = substr($token, 0, strpos($token, ' '));
                $last_name = substr($token, strpos($token, ' ') + 1);
            }
            if (strpbrk($token, '1234567890') !== FALSE) {
                $matches = array();
                preg_match_all('/[0-9]{2}[\s][0-9]{4}[\s][0-9]{3}[\s][0-9]{3}/', $token, $matches);
                $phone_numbers = $matches;
            }
            $token = strtok(PHP_EOL);
            $row_counter++;
        }

        $first_name = $first_name ?: 'Not Found';
        $last_name = $last_name ?: 'Not Found';
        $phone_numbers[0] = array_shift($phone_numbers[0]) ?: 'Not Found';

        $this->result = array($first_name, $last_name, $phone_numbers[0]);
    }
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . APP_PATH . 'oauth2callback.php');
    $client->addScope(Google_Service_Gmail::GMAIL_READONLY);

    if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
        $client->setAccessToken($_SESSION['access_token']);
    } else {
        $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . APP_PATH . 'oauth2callback.php';
        header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    }

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $redirect_uri = 'http://' . $_SERVER['HTTP_HOST'] . APP_PATH . 'oauth2callback.php';
        header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    }
    return $client;
}

function download_send_headers($filename)
{
    // disable caching
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");

    // force download
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");

    // disposition / encoding on response body
    header("Content-Disposition: attachment;filename={$filename}");
    header("Content-Transfer-Encoding: binary");
}

/**
 * @param array $array
 * @return null|string
 */
function array2csv(array &$array)
{
    if (count($array) == 0) {
        return null;
    }
    $csv_fields = array();

    $csv_fields[] = 'Name';
    $csv_fields[] = 'last Name';
    $csv_fields[] = 'Telephone No';

    ob_start();
    $df = fopen("php://output", 'w');
    fputcsv($df, $csv_fields);

    foreach ($array as $row) {
        fputcsv($df, $row);
    }
    fclose($df);
    return ob_get_clean();
}

/**
 * Decode the body.
 * @param : encoded body  - or null
 * @return : the body if found, else FALSE;
 */
function decodeBody($body)
{
    $rawData = $body;
    $sanitizedData = strtr($rawData, '-_', '+/');
    $decodedMessage = base64_decode($sanitizedData);
    if (!$decodedMessage) {
        $decodedMessage = FALSE;
    }
    return $decodedMessage;
}

/**
 * Get Message with given ID.
 *
 * @param  Google_Service_Gmail $service Authorized Gmail API instance.
 * @param  string $userId User's email address. The special value 'me'
 * can be used to indicate the authenticated user.
 * @param  string $messageId ID of Message to get.
 * @return Google_Service_Gmail_Message Message retrieved.
 */
function getMessage($service, $userId, $messageId)
{
    try {
        $message = $service->users_messages->get($userId, $messageId);
        print 'Message with ID: ' . $message->getId() . ' retrieved.';
        return $message;
    } catch (Exception $e) {
        print 'An error occurred: ' . $e->getMessage();
    }
}

/**
 * Get list of Messages in user's mailbox.
 *
 * @param  Google_Service_Gmail $service Authorized Gmail API instance.
 * @param  string $userId User's email address. The special value 'me'
 * can be used to indicate the authenticated user.
 * @return array Array of Messages.
 */
function listMessages($service, $userId)
{
    $pageToken = NULL;
    $messages = array();
    $opt_param = array();
    do {
        try {
            if ($pageToken) {
                $opt_param['pageToken'] = $pageToken;
            }
            $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
            if ($messagesResponse->getMessages()) {
                $messages = array_merge($messages, $messagesResponse->getMessages());
                $pageToken = $messagesResponse->getNextPageToken();
            }
        } catch (Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    } while ($pageToken);

    foreach ($messages as $message) {
        print 'Message with ID: ' . $message->getId() . '<br/>';
    }

    return $messages;
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Gmail($client);

$list = $service->users_messages->listUsersMessages(USER, $MAX_RESULT);
$i = 1;
$FOUND_BODY = array();
try {
    while ($list->getMessages() != null) {

        foreach ($list->getMessages() as $mlist) {
            if ($i <= 3) { //limited to first message in the list (inbox)

                $message_id = $mlist->id;
                $optParamsGet2['format'] = 'full';
                $single_message = $service->users_messages->get('me', $message_id, $optParamsGet2);
                $payload = $single_message->getPayload();

                // With no attachment, the payload might be directly in the body, encoded.
                $body = $payload->getBody();
                $FOUND_BODY[$i] = decodeBody($body['data']);

                // If we didn't find a body, let's look for the parts
                if (!$FOUND_BODY[$i]) {
                    $parts = $payload->getParts();
                    foreach ($parts as $part) {
                        if ($part['body']) {
                            $FOUND_BODY[$i] = decodeBody($part['body']->data);
                            break;
                        }
                        // Last try: if we didn't find the body in the first parts,
                        // let's loop into the parts of the parts .
                        if ($part['parts'] && !$FOUND_BODY[$i]) {
                            foreach ($part['parts'] as $p) {
                                // replace 'text/html' by 'text/plain' if you prefer
                                if ($p['mimeType'] === 'text/html' && $p['body']) {
                                    $FOUND_BODY[$i] = decodeBody($p['body']->data);
                                    break;
                                }
                            }
                        }
                        if ($FOUND_BODY[$i]) {
                            break;
                        }
                    }
                }
                $i++;
            } else {
                break;
            }
        }
        if ($list->getNextPageToken() != null) {
            $pageToken = $list->getNextPageToken();
            $list = $service->users_messages->listUsersMessages(USER, ['pageToken' => $pageToken, $MAX_RESULT]);
        } else {
            break;
        }
    }

    // Worker pool
    $workers = [];
    $rt = array();
    $i = count($FOUND_BODY);

    // Initialize and start the threads
    foreach (range(1, $i) as $ij) {
        $workers[$ij] = new WorkerThreads($ij, $FOUND_BODY[$ij]);
        $workers[$ij]->start();
    }

    // Let the threads come back
    foreach (range(1, $i) as $ij) {
        $workers[$ij]->join();
        $rt[] = $workers[$ij]->result;
    }

} catch (Exception $e) {
    echo $e->getMessage();
}
download_send_headers("data_export_" . date("Y-m-d") . ".csv");
echo array2csv($rt);
