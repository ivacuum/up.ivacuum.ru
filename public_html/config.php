<?php
/**
*
* @package up.ivacuum.ru
* @copyright (c) 2011
*
*/

namespace app;

$acm_prefix = 'up.ivacuum.ru';

/**
* Константы
* apc_delete($acm_prefix . '_constants');
*/
if( false === load_constants() )
{
	set_constants(array(
		/* Таблицы сайта */
		'DOWNLOADS_TABLE'    => 'site_downloads',
		'FILES_TABLE'        => 'site_files',
		'IMAGES_TABLE'       => 'site_images',
		'IMAGE_ALBUMS_TABLE' => 'site_image_albums',
		'IMAGE_REFS_TABLE'   => 'site_image_refs',
		'IMAGE_VIEWS_TABLE'  => 'site_image_views',
	));
}
