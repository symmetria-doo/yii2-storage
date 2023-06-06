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

class LocalStorage extends \yii\base\Component implements StorageInterface
{
    /**
     * @var array Almacena las configuraciones para el servicio.
     *
     * Parámetros configuración:
     * - Directorio (directory)
     *
     */
    public $config = [];

    /**
     * @var string Prefijo/directorio de almacenamiento - Opcional -.
     */
    public $prefix = '';

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

    /** @var string|array */
    public $errors;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        $this->bucket = $this->config['directory'];
        $this->baseUrl = $this->config['baseUrl'];
    }

    /**
     * @inheritDoc
     */
    public function save(FileManager $file): bool
    {
        try {
            if ($file->upload($this->bucket . $this->prefix)) {
                return true;
            } else {
                $this->errors = $file->getErrors();
                return false;
            }
        } catch (\Exception $ex) {
            $this->errors = $ex->getMessage();
            throw new HttpException(500, $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $file): bool
    {
        try {
            return FileHelper::unlink(\Yii::getAlias($this->bucket . $file));
        } catch (\Exception $ex) {
            $this->errors = $ex->getMessage();
            return FileHelper::unlink(\Yii::getAlias($this->bucket . $this->prefix . $file));
        } catch (\Exception $ex) {
            $this->errors = $ex->getMessage();
            throw new HttpException(500, $ex->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $file): string
    {
        $url = \Yii::getAlias($this->baseUrl . $this->prefix . $file);
        return $url;
    }

    /**
     * @inheritDoc
     */
    public function getFileManager()
    {
        return new FileManager();
    }
}
