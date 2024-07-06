<?php

use Spatie\Dropbox\Client;

require 'vendor/autoload.php';

$authorizationToken = getenv('DROPBOX_ACCESS_TOKEN');
if (empty($authorizationToken)) {
    echo 'Environment variable DROPBOX_ACCESS_TOKEN not set';
    exit(1);
}

$serverId = getenv('SERVER_ID');
if (empty($serverId)) {
    echo 'Environment variable SERVER_ID not set';
    exit(1);
}

$dropboxBasePath = getenv('DROPBOX_BASE_PATH');
if (empty($dropboxBasePath)) {
    echo 'Environment variable DROPBOX_BASE_PATH not set';
    exit(1);
}

$slackUrl = getenv('SLACK_WEBHOOK_URL');

$client = new Client($authorizationToken);
// $client = new Spatie\Dropbox\Client([$appKey, $appSecret]);

// get arguments
$searchFolder = $argv[1];
$dateTime = date_create_from_format('Ymd-His', $argv[2]);

$destinationDropboxFolder = $dropboxBasePath
    . '/' . $dateTime->format('Y-m-d')
    . '/' . $serverId . '-'. $dateTime->format('YmdHis');

$folderList = findFiles($searchFolder);
if(empty($folderList)) {
    echo 'No files found' . PHP_EOL;
    exit(0);
}

echo 'Uploading backup files to Dropbox...' . PHP_EOL;

$results = [];
$startTime = time();
$allOk = true;

foreach ($folderList as $dirPath => $dirFiles) {
    foreach ($dirFiles as $fileName) {
        $fullFile = $dirPath . '/' . $fileName;
        $dropboxFile = $destinationDropboxFolder . '/' . $fileName;
        $file = fopen($fullFile, 'rb');

        echo '- Uploading: ' . $fileName . PHP_EOL;

        try {
            $result = (bool) $client->upload($dropboxFile, $file, 'add', true);
        } catch (Throwable $e) {
            $result = false;
        }

        $allOk = $allOk && $result;
        $results[$fileName] = $result;
    }
}

echo 'Result: ' . ($allOk ? 'OK' : 'ERROR') . PHP_EOL;

if (!empty($slackUrl)) {
    $endTime = time();
    $fileList = [];
    foreach ($results as $file => $result) {
        $fileList[] = ($result ? '[OK]' : '[ER]') . ' ' . $file;
    }

    echo 'Sending Slack message...' . PHP_EOL;
    sendSlackMessage(
        $serverId,
        $slackUrl,
        $allOk,
        $fileList,
        $startTime,
        $endTime,
        $destinationDropboxFolder,
    );
}

echo 'Done' . PHP_EOL;
exit(0);






function findFiles($dir) {
    $dir_array = array();
    // Create array of current directory
    $files = @scandir($dir);

    if(is_array($files)) {
        foreach($files as $val) {
            // Skip home and previous listings
            if($val == '.' || $val == '..')
                continue;

            // If directory then dive deeper, else add file to directory key
            if(is_dir($dir.'/'.$val)) {
                // Add value to current array, dir or file
                // $dir_array[$dir][] = $val;

                $dir_array += findFiles($dir.'/'.$val, $dir_array);
            } else {
                $dir_array[$dir][] = $val;
            }
        }
    }
    ksort($dir_array);

    return $dir_array;
}

// <Slack>

function freedisk() {
    $bytes = disk_free_space(".");
    $si_prefix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $base = 1024;
    $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
    return sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class];
}

function slackCurl($url, $params)
{
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_RETURNTRANSFER => true
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    // $error = curl_error($ch);
    curl_close($ch);
    return $result === 'ok';
}

function sendSlackMessage($serverId, $slackUrl, $allOk, $fileList, $startTime, $endTime, $destinationDropboxFolder)
{
    $data = [
        'attachments' => [
            [
                'title' => 'Backup: ' . $serverId,
                // 'text' => 'Clientes Server backup',
                'color' => $allOk ? 'good' : 'danger',
                'fields' => [
                    [
                        'title' => 'Start time',
                        'value' => date('c', $startTime),
                        'short' => false,
                    ],
                    // [
                    //     'title' => 'End',
                    //     'value' => date('c', $endTime),
                    //     'short' => true,
                    // ],
                    [
                        'title' => 'Elapsed time',
                        'value' => ($endTime - $startTime) . ' secs.',
                        'short' => true,
                    ],
                    [
                        'title' => 'Free space',
                        'value' => freedisk(),
                        'short' => true,
                    ],
                    [
                        'title' => 'Dropbox folder',
                        'value' => $destinationDropboxFolder,
                        'short' => false,
                    ],
                    [
                        'title' => 'Files',
                        'value' => implode("\n", $fileList),
                        'short' => false
                    ],
                ]
            ]
        ]
    ];

    // enviar notificacion Slack
    slackCurl($slackUrl, $data);
}

// </Slack>