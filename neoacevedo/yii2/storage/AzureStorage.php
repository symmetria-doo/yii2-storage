<?php

/**
 * Copyright (C) 2022 Néstor Acevedo <clientes at neoacevedo.co>
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

namespace neoacevedo\yii2\storage;

use DateTime;
use neoacevedo\yii2\storage\models\FileManager;
use yii\web\HttpException;
use yii\helpers\FileHelper;
// Azure
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException as AzureStorageException;

/**
 * AzureStorage es la clase que gestiona el almacenamiento en Microsoft Azure.
 * @author Néstor Acevedo
 */
class AzureStorage extends \yii\base\Component implements StorageInterface
{
    /**
     * @var array Almacena las configuraciones para el servicio.
     *
     * Parámetros configuración:
     * - Nombre de la cuenta (accountName)
     * - Clave secreta de la cuenta (accountKey)
     * - Contenedor (container)
     *
     */
    public $config = [];

    /**
     * @var string Prefijo/directorio de almacenamiento - Opcional -.
     */
    public $prefix = '';

    /**
     * @var \MicrosoftAzure\Storage\Blob\BlobRestProxy Instancia de almacenamiento.
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

    /** @var string */
    public $errors;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $connectionString = "DefaultEndpointsProtocol=https;AccountName=" . $this->config['accountName'] . ";AccountKey=" . $this->config['accountKey'];
        $this->clientService = BlobRestProxy::createBlobService($connectionString);
        $this->bucket = $this->config['container'];
    }

    /**
     * @inheritDoc
     */
    public function save(FileManager $file): bool
    {
        try {
            if ($file->validate()) {
                $content = false;
                $url = $file->uploadedFile->tempName;
                if ($url) {
                    $content = file_get_contents($url);
                }
                $blockBlobOptions = new CreateBlockBlobOptions();
                $blockBlobOptions->setContentType($file->uploadedFile->type);
                $this->clientService->createBlockBlob(
                    $this->bucket,
                    $this->prefix . $file->uploadedFile->name,
                    $content,
                    $blockBlobOptions
                );
                return true;
            } else {
                return false;
            }
        } catch (AzureStorageException $ex) {
            $this->errors = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $file): bool
    {
        try {
            $this->clientService->deleteBlob($this->bucket, $this->prefix . $file);
            return true;
        } catch (AzureStorageException $ex) {
            $this->errors = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $file): string
    {
        $listBlobsOptions = new ListBlobsOptions();
        $listBlobsOptions->setPrefix($file);
        $blob_list = $this->clientService->listBlobs($this->bucket, $listBlobsOptions);
        $blobs = $blob_list->getBlobs();

        foreach ($blobs as $blob) {
            return $blob->getUrl();
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileManager()
    {
        return new FileManager();
    }
}
