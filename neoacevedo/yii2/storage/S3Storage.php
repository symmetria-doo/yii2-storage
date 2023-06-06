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
// Amazon AWS
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * S3Storage es la clase que gestiona el almacenamiento en AWS S3.
 * @author Néstor Acevedo
 */
class S3Storage extends \yii\base\Component implements StorageInterface
{
    /**
     * @var array Almacena las configuraciones para el servicio.
     *
     * Parámetros configuración:
     * - Llave de acceso (key)
     * - Llave secreta de acceso (secret)
     * - Bucket (bucket)
     * - Región (region)
     *
     */
    public $config = [];

    /**
     * @var string Prefijo/directorio de almacenamiento - Opcional -.
     */
    public $prefix = '';

    /**
     * @var \Aws\S3\S3Client Instancia de almacenamiento.
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
        $this->clientService = new S3Client([
                    "credentials" => [
                        "key" => $this->config['key'],
                        "secret" => $this->config['secret']
                    ],
                    "region" => $this->config['region'],
                    "version" => "2006-03-01"]);
        $this->bucket = $this->config['bucket'];
    }

    /**
     * @inheritdoc
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
            $this->errors = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(string $file): bool
    {
        try {
            $this->clientService->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->prefix . $file,
            ]);
            return true;
        } catch (S3Exception $ex) {
            $this->errors = $ex->getMessage();
            throw new HttpException($ex->getCode(), $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $file): string
    {
        return $this->clientService->getObjectUrl($this->bucket, $file);
    }

    /**
     * @inheritDoc
     */
    public function getFileManager()
    {
        return new FileManager();
    }
}
