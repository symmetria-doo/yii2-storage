<?php

/*
 * Copyright (C) 2018 Néstor Acevedo <clientes at neoacevedo.co>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace neoacevedo\yii2;

use DateTime;
use neoacevedo\yii2\storage\models\FileManager;
use yii\web\HttpException;
use yii\helpers\FileHelper;
// Amazon AWS
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
// Azure
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException as AzureStorageException;
// Google Cloud
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Core\Exception\ServiceException as GoogleCloudStorageException;
use Google\Cloud\Core\Timestamp;

/**
 * La clase Storage gestiona el medio de almacenamiento de archivos que se suban.
 *
 * Maneja 4 tipos de alacenamiento:
 * - Amazon S3
 * - Azure Storage Blob
 * - Google Storage Cloud
 * - Almacenamiento local.
 * @author Néstor Acevedo
 * @deprecated since 22.05.11 Use en su lugar `S3Storage` | `AzureStorage` | `GoogleCloudStorage` | `LocalStorage`
 */
class Storage extends \yii\base\Component
{
    /**
     * Constante de almacenamiento: Amazon S3
     */
    public const AWS_S3 = 's3';

    /**
     * Constante de almacenamiento: Azure Storage Blob
     */
    public const AZURE_BLOB_STORAGE = 'azure';

    /**
     * Constante de almacenamiento: Google Storage Cloud
     */
    public const GOOGLE_CLOUD_STORAGE = 'gsc';

    /**
     * Constante de almacenamiento: Local
     */
    public const LOCAL = 'local';

    /**
     * @var string Define el almacenamiento que se usará.
     */
    public $service;

    /**
     * @var array Almacena las configuraciones para el servicio.
     *
     * Para Amazon S3:
     * <ul>
     *     <li>Llave de acceso (key)</li>
     *     <li>Llave secreta de acceso (secret)</li>
     *     <li>Bucket (bucket)</li>
     *     <li>Región (region)</li>
     * </ul>
     * Para Azure Storage Blob:
     * <ul>
     *      <li>Nombre de la cuenta (accountName)</li>
     *      <li>Clave secreta de la cuenta (accountKey)</li>
     *      <li>Contenedor (container)</li>
     * </ul>
     * Para Google Storage Cloud:
     * <ul>
     *      <li>ID del proyecto (projectId)</li>
     *      <li>Bucket (bucket)</li>
     *      <li>Archivo de clave (keyFile). El contenido de dicho archivo. Configurable de manera directa o mediante `file_get_contents`</li>
     * </ul>
     * Para almacenamiento local:
     * <ul>
     *      <li>Directorio (directory)</li>
     * </ul>
     *
     *
     *
     */
    public $config = [];

    /**
     * @var string Prefijo/directorio de almacenamiento - Opcional -.
     */
    public $prefix = '';

    /**
     * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy|\Aws\S3\S3Client|\Google\Cloud\Storage\StorageClient Instancia de almacenamiento (S3Client, BlobRestProxy, StorageClient).
     * @access private
     */
    private $clientService;

    /**
     * @var string Nombre del contenedor/bucket/directorio de almacenamiento.
     * @access private
     */
    private $bucket = '@frontend/web/';

    /**
     *
     * @var string URL base para los archivos.
     * @access private
     */
    private $baseUrl = '';

    /**
     * Instancia de [[\yii\web\UploadedFile]] que obtiene los datos del archivo que se está subiendo.
     * @var \yii\web\UploadedFile
     */
    public $uploadedFile;
    public $errors = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        switch ($this->service) {
            case self::AWS_S3:
                $this->clientService = new S3Client([
                    "credentials" => [
                        "key" => $this->config['key'],
                        "secret" => $this->config['secret']
                    ],
                    "region" => $this->config['region'],
                    "version" => "2006-03-01"]);
                $this->bucket = $this->config['bucket'];
                break;
            case self::AZURE_BLOB_STORAGE:
                $connectionString = "DefaultEndpointsProtocol=http;AccountName=" . $this->config['accountName'] . ";AccountKey=" . $this->config['accountKey'];
                $this->clientService = BlobRestProxy::createBlobService($connectionString);
                $this->bucket = $this->config['container'];
                break;
            case self::GOOGLE_CLOUD_STORAGE:
                $this->clientService = new StorageClient([
                    'keyFile' => json_decode($this->config['keyFile'], true),
                    'projectId' => $this->config['projectId']]);
                $this->bucket = $this->config['bucket'];
                break;
            case self::LOCAL:
            default:
                $this->bucket = $this->config['directory'];
                $this->baseUrl = $this->config['baseUrl'];
        }
    }

    /**
     * Sube el archivo al servicio de almacenamiento.
     *
     * @return boolean
     * @throws yii\base\InvalidArgumentException
     */
    public function save()
    {
        $bool = false;
        $fileManager = FileManager::instance();
        if (isset($this->uploadedFile)) {
            $fileManager->uploadedFile = $this->uploadedFile;
        }
        $fileManager->extensions = $this->config['extensions'];
        switch ($this->service) {
            case self::AWS_S3:
                $bool = $this->uploadToS3($fileManager);
                break;
            case self::AZURE_BLOB_STORAGE:
                $bool = $this->uploadToAzure($fileManager);
                break;
            case self::GOOGLE_CLOUD_STORAGE:
                $bool = $this->uploadToGoogle($fileManager);
                break;
            case self::LOCAL:
            default:
                $bool = $this->uploadToLocal($fileManager);
        }

        return $bool;
    }

    /**
     * Borra un archivo del servicio de almacenamiento.
     * @param string $file
     * @return boolean
     */
    public function delete($file)
    {
        $bool = false;
        switch ($this->service) {
            case self::AWS_S3:
                $bool = $this->deleteFromS3($file);
                break;
            case self::AZURE_BLOB_STORAGE:
                $bool = $this->deleteFromAzure($file);
                break;
            case self::GOOGLE_CLOUD_STORAGE:
                $bool = $this->deleteFromGoogle($file);
                break;
            case self::LOCAL:
            default:
                $bool = $this->deleteFromLocal($file);
        }

        return $bool;
    }

    /**
     * Obtiene la URL del archivo devuelta por el servicio de almacenamiento
     * @param string $file Es la ruta relativa del archivo, ejemplo: <pre>"images/file1.txt"</pre>
     * @return string
     */
    public function getUrl($file)
    {
        switch ($this->service) {
            case self::AWS_S3:
                return $this->clientService->getObjectUrl($this->bucket, $file);
            case self::AZURE_BLOB_STORAGE:
                $listBlobsOptions = new ListBlobsOptions();
                $listBlobsOptions->setPrefix($file);
                $blob_list = $this->clientService->listBlobs($this->bucket, $listBlobsOptions);
                $blobs = $blob_list->getBlobs();

                foreach ($blobs as $blob) {
                    return $blob->getUrl();
                }
                // no break
            case self::GOOGLE_CLOUD_STORAGE:
                return $this->clientService->bucket($this->bucket)->object($file)->signedUrl(new Timestamp(new DateTime('tomorrow')));
            case self::LOCAL:
                // Predefinido.
            default:
                $arrayPath = \explode("/", $this->bucket);
                if (in_array("web", $arrayPath)) {
                    $posToSlice = array_search("web", $arrayPath) + 1;
                    $arrayPath = array_slice($arrayPath, $posToSlice);
                    $path = \implode("/", $arrayPath);
                }
                if ($this->prefix === '') {
                    $filePath = "/$path$file";
                } else {
                    $filePath = "/$file";
                }
                // remover slashes adicionales
                $filePath = preg_replace('#[/\\\\]+#', "/", $filePath);
                $url = $this->baseUrl . $filePath;
                return $url;
        }
    }

    /**
     * Devuelve una instancia del modelo [[FileManager]].
     *
     * Esta instancia puede ser usada en el formulario donde se deba subir un archivo.
     * @return FileManager
     */
    public function getModel()
    {
        return new FileManager();
    }

    /**
     * Sube el archivo a Azure Storage Blob
     * @param \neoacevedo\yii2\storage\models\FileManager $file
     * @return boolean
     * @throws HttpException
     */
    private function uploadToAzure($file)
    {
        try {
            if ($file->validate()) {
                $blob_content = file_get_contents($file->uploadedFile->tempName);
                $blockBlobOptions = new CreateBlockBlobOptions();
                $blockBlobOptions->setContentType($file->uploadedFile->type);
                $this->clientService->createBlockBlob(
                    $this->bucket,
                    $this->prefix . $file->uploadedFile->name,
                    $blob_content,
                    $blockBlobOptions
                );
                return true;
            } else {
                return false;
            }
        } catch (AzureStorageException $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * Borra un archivo alojado en Azure Storage Blob
     * @param string $file
     * @return boolean
     * @throws HttpException
     */
    private function deleteFromAzure($file)
    {
        try {
            $this->clientService->deleteBlob($this->bucket, $this->prefix . $file);
            return true;
        } catch (AzureStorageException $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * Sube el archivo a Amazon S3
     * @param \neoacevedo\yii2\storage\models\FileManager $file
     * @return boolean
     * @throws HttpException
     */
    private function uploadToS3($file)
    {
        try {
            if ($file->validate()) {
                $content = file_get_contents($file->uploadedFile->tempName);
                $this->clientService->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $this->prefix . $file->uploadedFile->name,
                    'Body' => $content,
                    'ContentType' => $file->uploadedFile->type,
                    'ACL' => 'public-read']);
                return true;
            } else {
                return false;
            }
        } catch (S3Exception $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * Borra un archivo alojado en Amazon S3
     * @param string $file
     * @return boolean
     * @throws HttpException
     */
    private function deleteFromS3($file)
    {
        try {
            $this->clientService->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->prefix . $file,
            ]);
            return true;
        } catch (S3Exception $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * Sube el archivo a Google Cloud Storage
     * @param \neoacevedo\yii2\storage\models\FileManager $file
     * @return boolean
     * @throws HttpException
     */
    private function uploadToGoogle($file)
    {
        try {
            if ($file->validate()) {
                $content = file_get_contents($file->uploadedFile->tempName);
                $this->clientService->bucket($this->bucket, true)->upload($content, [
                    'name' => $this->prefix . $file->uploadedFile->name,
                    'predefinedAcl' => 'publicRead',
                    'contentType' => $file->uploadedFile->type
                ]);
                return true;
            } else {
                return false;
            }
        } catch (GoogleCloudStorageException $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * Borra un archivo alojado en Google Cloud Storage
     * @param string $file
     * @return boolean
     * @throws HttpException
     */
    private function deleteFromGoogle($file)
    {
        try {
            $this->clientService->bucket($this->bucket, true)->object($this->prefix . $file)->delete();
            return true;
        } catch (GoogleCloudStorageException $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * Sube el archivo al servidor web.
     * @param \neoacevedo\yii2\storage\models\FileManager $file
     * @return boolean
     * @throws \yii\web\HttpException
     */
    private function uploadToLocal($file)
    {
        try {
            if ($file->upload($this->bucket . $this->prefix)) {
                return true;
            } else {
                $this->errors[] = $file->getErrors();
                return false;
            }
        } catch (\Exception $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException(500, $ex->getMessage());
        }
    }

    /**
     * Borra un archivo del almacenamiento local
     * @param string $file
     * @return boolean
     * @throws HttpException
     */
    private function deleteFromLocal($file)
    {
        try {
            return FileHelper::unlink(\Yii::getAlias($this->bucket . $file));
        } catch (\Exception $ex) {
            $this->errors[] = $ex->getMessage();
            return FileHelper::unlink(\Yii::getAlias($this->bucket . $this->prefix . $file));
        } catch (\Exception $ex) {
            $this->errors[] = $ex->getMessage();
            throw new HttpException(500, $ex->getMessage());
        }
    }
}
