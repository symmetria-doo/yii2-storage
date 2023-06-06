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

/**
 * StorageInterface es la interfaz que implementan las diferentes clases que proveen gestión de almacenamiento.
 * @author Néstor Acevedo
 */
interface StorageInterface
{
    /**
     * Obtiene la URL del archivo devuelta por el servicio de almacenamiento
     * @param string $file Es la ruta relativa del archivo, ejemplo: `"images/file1.txt"`
     * @return string
     */
    public function getUrl(string $file): string;

    /**
     * Sube el archivo al servicio de almacenamiento.
     * @param \neoacevedo\yii2\storage\models\FileManager $file
     * @return boolean
     * @throws yii\base\InvalidArgumentException
     */
    public function save(\neoacevedo\yii2\storage\models\FileManager $file): bool;

    /**
     * Borra un archivo del servicio de almacenamiento.
     * @param string $file Ruta del archivo sin el dominio.
     * @return boolean
     */
    public function delete(string $file): bool;

    /**
     * Devuelve una instancia del modelo [[\neoacevedo\yii2\storage\models\FileManager]].
     *
     * Esta instancia puede ser usada en el formulario donde se deba subir un archivo.
     * @return \neoacevedo\yii2\storage\models\FileManager
     */
    public function getFileManager();
}
