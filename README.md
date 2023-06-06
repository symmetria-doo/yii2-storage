Yii2 Storage
============
[![PayPal - The safer, easier way to pay online!](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate/?hosted_button_id=BUT4UEAG3FQV8)
[![ePayco](https://multimedia.epayco.co/dashboard/btns/btn5.png)](https://secure.payco.co/checkoutopen/48732)

[![Latest Stable Version](https://poser.pugx.org/neoacevedo/yii2-storage/v/stable)](https://packagist.org/packages/neoacevedo/yii2-storage)
[![Total Downloads](https://poser.pugx.org/neoacevedo/yii2-storage/downloads)](https://packagist.org/packages/neoacevedo/yii2-storage)
[![Latest Unstable Version](https://poser.pugx.org/neoacevedo/yii2-storage/v/unstable)](https://packagist.org/packages/neoacevedo/yii2-storage)
[![License](https://poser.pugx.org/neoacevedo/yii2-storage/license)](https://packagist.org/packages/neoacevedo/yii2-storage)
[![Monthly Downloads](https://poser.pugx.org/neoacevedo/yii2-storage/d/monthly)](https://packagist.org/packages/neoacevedo/yii2-storage)
[![Daily Downloads](https://poser.pugx.org/neoacevedo/yii2-storage/d/daily)](https://packagist.org/packages/neoacevedo/yii2-storage)


Gestión de almacenamiento para Yii2.

\#storage \#module \#upload \#file \#extension \#aws \#azure \#google

Instalación
------------

La forma preferida de instalar esta extensión es a través de [composer](http://getcomposer.org/download/).

Luego ejecute

```
php composer.phar require --prefer-dist neoacevedo/yii2-storage
```

o agregue

```js
"neoacevedo/yii2-storage": "*"
```

a la sección require de su archivo `composer.json`.

Uso
-----

Una vez que la extensión está instalada, configure las credenciales para el servicio de almacenamiento en el archivo de configuración de su aplicación Yii  : 

```php
<?php

'components' => [
	/**
	 * Amazon S3
	 */ 
	'storageAWS' => [
		'class' => 'neoacevedo\yii2\storage\S3Storage',
	    'config' => [
	        'key' => 'YOUR_IAM_ACCESS_KEY',
	        'secret' => 'YOUR_IAM__SECRET_ACCESS_KEY',
	        'bucket' => 'your-bucket',
	        'region' => 'your-region',
	        'extensions' => 'pdf, jpg, jpeg, gif, png, bmp'
	    ],
	    'prefix' => '', // ruta al directorio de imágenes. Ej: images/ (Opcional)
	]


	/**
	 * Azure Storage Blob
	 */
	'storageAzure' => [
		'class' => 'neoacevedo\yii2\storage\AzureStorage',
	    'config' => [
	        'accountName' => 'ACCOUNT_NAME',
	        'accountKey' => 'ACCOUNT_KEY',
	        'container' => 'your-container',
	        'extensions' => 'pdf, jpg, jpeg, gif, png, bmp'
	    ],
	    'prefix' => '' // ruta al directorio de imágenes. Ej: images/ (Opcional)
	]

	/**
	 * Google Storage Cloud
	 */
	'storageGoogle' => [
		'class' => 'neoacevedo\yii2\storage\GoogleCloudStorage',
	    'config' => [
	        'projectId' => 'YOUR_PROJECT_ID',
	        'bucket' => 'your-bucket'
	        'keyFile' => '', // Contenido del archivo JSON generado en la consola de Google
	        'extensions' => 'pdf, jpg, jpeg, gif, png, bmp'
	    ],
	    'prefix' => '', // ruta al directorio de imágenes. Ej: images/ (Opcional)
	]

	/**
	 * Almacenamiento local
	 */ 
	'storageLocal' => [
		'class' => 'neoacevedo\yii2\storage\LocalStorage',
	    'config' => [
	        'baseUrl' => '/web', // reemplace "/web" por "/", "@web", "/frontend/web" o "/backend/web" según sea el caso.
	        'directory' => '@webroot/web/uploads/', // reemplace @webroot por @frontend o @backend según sea el caso. La ruta debe terminar con una barra diagonal
	        'extensions' => 'pdf, jpg, jpeg, gif, png, bmp'
	    ],
	    'prefix' => '', // ruta al directorio de imágenes. La ruta debe terninar con una barra diagonal si se establece. Ej: images/ (Opcional)
]
```

Ahora puede llamarlo desde su aplicación :

```php
...
$fileManager = Yii::$app->storage->getFileManager();
...
/**
 * Sube el archivo de imagen.
 * @param \neoacevedo\yii2\storage\models\FileManager $fileManager
 * @return boolean
 */
public function upload($fileManager)
{
    if (null !== $fileManager->uploadedFile) {
        return Yii::$app->storage->save($fileManager);
    } else {
        return false;
    }
}

...
// obtener la URL generada
echo Yii::$app->storage->getUrl(Yii::$app->storage->prefix . $fileManager->uploadedFile->name); 
```


O simplemente en su código  :

```php
<?php 
use neoacevedo\yii2\storage\S3Storage;

public function upload()
{
	$storage = new S3Storage([
		'config' => [
			'key' => 'YOUR_IAM_ACCESS_KEY',
			'secret' => 'YOUR_IAM_SECRET_ACCESS_KEY',
			'bucket' => 'your-bucket',
			'region' => 'your-region',
			'extensions' => 'pdf, jpg, jpeg, gif, png, bmp'
		],
		'prefix' => '' // opcional
	]);
	
	return $storage->save($storage->getFileManager());
}
```

Puede usar el modelo del componente en su formulario de las siguientes maneras en su controlador:

```php
...
// Constructor de clase
	$storage = new S3Storage([
		'config' => [
			'key' => 'YOUR_IAM_ACCESS_KEY',
			'secret' => 'YOUR_IAM_SECRET_ACCESS_KEY',
			'bucket' => 'your-bucket',
			'region' => 'your-region',
			'extensions' => 'pdf, jpg, jpeg, gif, png, bmp'
		],
		'prefix' => '' // opcional
	]);
	return $this->render('create', [
                'model' => $model,
                'fileManager' => $storage->getFileManager()
    ]);	
...
// Como componente
	return $this->render('create', [
                'model' => $model,
                'fileManager' => Yii::$app->storage->getFileManager()
    ]);
...
// Usando el modelo de manera directa
	return $this->render('create', [
                'model' => $model,
                'fileManager' => new neoacevedo\yii2\storage\models\FileManager()
    ]);
```

Dentro de la vista:

```php
<?= $form->field($fileManager, 'uploadedFile')->fileInput() ?>
```
