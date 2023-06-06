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
// Google Cloud
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Core\Exception\ServiceException as GoogleCloudStorageException;
use Google\Cloud\Core\Timestamp;

class GoogleCloudStorage extends \yii\base\Component implements StorageInterface
{
    /**
     * @var array Almacena las configuraciones para el servicio.
     *
     * Parámetros configuración:
     * - ID del proyecto (projectId)
     * - Bucket (bucket)
     * - Archivo de clave (keyFile). El contenido de dicho archivo. Configurable de manera directa o mediante `file_get_contents`
     *
     */
    public $config = [];

    /**
     * @var string Prefijo/directorio de almacenamiento - Opcional -.
     */
    public $prefix = '';

    /**
     * @var \Google\Cloud\Storage\StorageClient Instancia de almacenamiento.
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
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        $this->clientService = new StorageClient([
            'keyFile' => json_decode($this->config['keyFile'], true),
            'projectId' => $this->config['projectId']]);
        $this->bucket = $this->config['bucket'];
    }

    /**
     * @inheritDoc
     */
    public function save(FileManager $file): bool
    {
        try {
            if ($file->validate()) {
                $content = false;
                $url = $file->uploadedFile;
                if ($url) {
                    $content = file_get_contents($url);
                }
                $this->clientService->bucket($this->bucket, true)->upload($content, [
                    'name' => $this->prefix . $file->uploadedFile->name,
                    'predefinedAcl' => 'publicRead',
                    'contentType' => $file->extensions
                ]);
                return true;
            } else {
                return false;
            }
        } catch (GoogleCloudStorageException $ex) {
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
            $this->clientService->bucket($this->bucket, true)->object($this->prefix . $file)->delete();
            return true;
        } catch (GoogleCloudStorageException $ex) {
            $this->errors = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $file): string
    {
        return $this->clientService->bucket($this->bucket)->object($file)->signedUrl(new Timestamp(new DateTime('tomorrow')));
    }

    /**
     * @inheritDoc
     */
    public function getFileManager()
    {
        return new FileManager();
    }
}
