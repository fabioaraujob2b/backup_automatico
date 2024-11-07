<?php

require 'vendor/autoload.php';

use Google\Service\Drive;

function authenticateGoogleClient()
{
    $credenciais = json_decode(file_get_contents('access_data.json'), true);
    
    $client = new Google\Client();
    $client->setClientId($credenciais['web']['client_id']);
    $client->setClientSecret($credenciais['web']['client_secret']);
    $client->setRedirectUri($credenciais['web']['redirect_uris'][0]);
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    $client->setAccessType('offline');

    $tokenData = json_decode(file_get_contents('access_token.json'), true);    
    $refreshToken = $tokenData['refresh_token'];
    $accessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

    if (isset($accessToken['access_token'])) {
        $client->setAccessToken($accessToken);
    } else {
        throw new Exception("Erro ao obter o access token: " . json_encode($accessToken));
    }

    if ($client->isAccessTokenExpired()) {
        $accessToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        if (isset($accessToken['access_token'])) {
            $client->setAccessToken($accessToken);
        } else {
            throw new Exception("Erro ao renovar o access token: " . json_encode($accessToken));
        }
    }

    //Cria o arquivo access_token.json com os dados renovados.
    $accessToken = $client->getAccessToken();
    if ($accessToken) {
        file_put_contents('access_token.json', json_encode($accessToken));
    }

    return new Google\Service\Drive($client);
}

function uploadTodosOsArquivos()
{
    $driveService = authenticateGoogleClient();
    $files = glob('arquivos/*');

    foreach ($files as $filePath) {
        $fileName = basename($filePath); 
        uploadDoArquivo($filePath, $fileName); 
    }
}

function uploadDoArquivo($filePath, $fileName)
{
    $driveService = authenticateGoogleClient();
    $file = new Drive\DriveFile();
    $file->setName($fileName);

    $chunckSizeBytes = 10 * 1024 * 1024;
    $client = $driveService->getClient();
    $client->setDefer(true);

    $request = $driveService->files->create($file, ['uploadType' => 'resumable']);
    $media = new Google\Http\MediaFileUpload(
        $client,
        $request,
        mime_content_type($filePath),
        null,
        true,
        $chunckSizeBytes
    );
    $media->setFileSize(filesize($filePath));

    $handle = fopen($filePath, 'rb');
    while (!feof($handle)) {
        $chunk = fread($handle, $chunckSizeBytes);
        $status = $media->nextChunk($chunk);
    }
    fclose($handle);

    $client->setDefer(false);

    if ($status != false) {
        echo "Upload concluído! File ID: " . $status->id . "\n";
    } else {
        echo "Erro no upload do arquivo: $fileName.\n";
    }
}

function logError($erros){
    $logFile = 'error_log';
    $logMessage = "[" . date("d-m-Y") . "] " . $erros->getMessage() . "\n";
    if (!file_exists($logFile)) {
        file_put_contents($logFile, "Arquivo de log criado em " . date("d-m-Y") . "\n", FILE_APPEND);
    }
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo "Ocorreu um erro! Verifique o arquivo error_log.txt.\n";
}

//Manipula os erros globais.
set_exception_handler('logError');
//Aumenta o tempo de execução.
ini_set('max_execution_time', 3000);
//Aumenta o limite de memoria.
ini_set('memory_limit', '5G');

uploadTodosOsArquivos();
