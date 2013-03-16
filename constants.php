<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2013
*/

namespace app;

if (false === $app->load_constants($app['acm.prefix']))
{
	$app->set_constants($app['acm.prefix'], [
		/* Таблицы сайта */
		'DOWNLOADS_TABLE'    => 'site_downloads',
		'FILES_TABLE'        => 'site_files',
		'IMAGES_TABLE'       => 'site_images',
		'IMAGE_ALBUMS_TABLE' => 'site_image_albums',
		'IMAGE_REFS_TABLE'   => 'site_image_refs',
		'IMAGE_VIEWS_TABLE'  => 'site_image_views',
	]);
}
