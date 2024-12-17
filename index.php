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

    // Criar uma pasta no Google Drive
    $folderName = 'Backup_Servidor';
    $folderId = criarPastaNoDrive($driveService, $folderName);
    if (!$folderId) {
        echo "Erro ao criar a pasta no Google Drive.\n";
        return;
    }

    $files = glob('arquivos/*');

    foreach ($files as $filePath) {
        $fileName = basename($filePath);
        
        // Compactar o arquivo
        $zipPath = compactarArquivo($filePath);
        if ($zipPath) {
            // Upload do arquivo para a pasta
            $fileId = buscarArquivoExistente($driveService, basename($zipPath));
            uploadDoArquivo($zipPath, basename($zipPath), $folderId, $fileId);

            // Remover o arquivo ZIP temporário após o upload
            unlink($zipPath);
        } else {
            echo "Erro ao compactar o arquivo: $fileName.\n";
        }
    }
}

function compactarArquivo($filePath)
{
    if (!class_exists('ZipArchive')) {
        echo "Erro: A extensão ZipArchive não está habilitada.\n";
        return false;
    }

    $zipPath = $filePath . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filePath, basename($filePath));
        $zip->close();
        return $zipPath;
    } else {
        return false; // Retorna falso se a compactação falhar
    }
}

function criarPastaNoDrive($driveService, $folderName)
{
    // Verifica se a pasta já existe
    $query = "mimeType = 'application/vnd.google-apps.folder' and name = '$folderName' and trashed = false";
    
    try {
        $folders = $driveService->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name)'
        ]);
    } catch (Exception $e) {
        echo "Erro ao listar pastas: " . $e->getMessage() . "\n";
        return null;
    }

    // Se encontrar a pasta, exclui
    foreach ($folders->files as $folder) {
        try {
            $driveService->files->delete($folder->id);
            echo "Pasta '$folderName' foi atualizada.<br><br>";
        } catch (Exception $e) {
            echo "Erro ao excluir pasta: " . $e->getMessage() . "\n";
        }
    }

    // Cria a nova pasta
    $folderMetadata = new Google_Service_Drive_DriveFile([
        'name' => $folderName,
        'mimeType' => 'application/vnd.google-apps.folder'
    ]);

    try {
        $folder = $driveService->files->create($folderMetadata, ['fields' => 'id']);
        return $folder->id;
    } catch (Exception $e) {
        echo "Erro ao criar pasta: " . $e->getMessage() . "\n";
        return null;
    }
}

function buscarArquivoExistente($driveService, $fileName)
{
    $response = $driveService->files->listFiles([
        'q' => "name='" . addslashes($fileName) . "' and trashed=false",
        'fields' => 'files(id, parents)',
    ]);
    if (count($response->files) > 0) {
        return $response->files[0]; // Retorna o arquivo existente com ID e pais
    }
    return null; // Retorna null se o arquivo não existir
}

function uploadDoArquivo($filePath, $fileName, $folderId, $existingFile = null)
{
    $driveService = authenticateGoogleClient();
    $file = new Drive\DriveFile([
        'name' => $fileName
    ]);

    $chunkSizeBytes = 10 * 1024 * 1024;
    $client = $driveService->getClient();
    $client->setDefer(true);

    if ($existingFile) {
        // Atualizar o arquivo existente e mover para a nova pasta, se necessário
        $fileId = $existingFile->id;
        $currentParents = $existingFile->parents;

        $request = $driveService->files->update($fileId, $file, [
            'uploadType' => 'resumable',
            'addParents' => $folderId,
            'removeParents' => implode(',', $currentParents)
        ]);
    } else {
        // Criar novo arquivo na pasta
        $file->setParents([$folderId]);
        $request = $driveService->files->create($file, ['uploadType' => 'resumable']);
    }

    $media = new Google\Http\MediaFileUpload(
        $client,
        $request,
        mime_content_type($filePath),
        null,        
        true,
        $chunkSizeBytes
    );
    $media->setFileSize(filesize($filePath));

    $handle = fopen($filePath, 'rb');
    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSizeBytes);
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

function logError($erros)
{
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
