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

namespace neoacevedo\yii2\storage\models;

/**
 * FileManager implementa la carga de archivos.
 *
 * Valida el tipo de archivo y el tamaño máximo permitido configurado.
 * [[uploadeFile]] y [[audioFile]] son instancias de [[\yii\web\UploadedFile]] que obtienen los datos del
 * archivo que se está subiendo.
 *
 * Llamando [[upload()]] sube el archivo en el frontend en el directorio 'web' y crea
 * (si no existe) un directorio con el parámetro [[dir]].
 *
 */
class FileManager extends \yii\base\Model
{
    /**
     * Permite la carga de archivos.
     *
     * Valida el tipo de archivo.
     * @var \yii\web\UploadedFile|null [[UploadedFile]] si el tamaño no excede el máximo permitido
     * en el servidor; null en caso contrario.
     */
    public $uploadedFile;

    /** @var string */
    public $fileName;

    /** @var string */
    public $url;

    /** @var string|array */
    public $extensions;

    /** @var string */
    public $errorMessage;

    /**
     * {@inheritdoc}
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->uploadedFile = \yii\web\UploadedFile::getInstance($this, "uploadedFile");
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['uploadedFile'], 'file',
                'extensions' => $this->extensions]
        ];
    }

    /**
     * Sube el archivo en el directorio especificado.
     * @param string $path Directorio donde se subirá el archivo.
     * @return boolean
     */
    public function upload($path): bool
    {
        if ($this->validate()) {
            // Se sube en frontend
            try {
                $directory = \Yii::getAlias($path);
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                if ($this->uploadedFile !== null) {
                    $this->fileName = str_ireplace(" ", "_", $this->uploadedFile->name);
                    $this->uploadedFile->saveAs("$directory/" . $this->fileName);
                    $this->url = "$directory/" . $this->fileName;
                }
                return true;
            } catch (\Exception $ex) {
                \Yii::$app->getSession()->setFlash('error', $ex->getMessage());
                $this->errorMessage = $ex->getMessage();
            }
        } else {
            \Yii::$app->getSession()->setFlash('error', \json_encode($this->getErrors()));
            $this->errorMessage = \json_encode($this->getErrors());
        }

        return false;
    }
}
