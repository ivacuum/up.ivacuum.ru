<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2011
*/

namespace app\models;

use engine\models\page as base_page;

class page extends base_page
{
	public function page_header()
	{
		parent::page_header();
		
		$this->template->assign(array(
			'S_BASE_JS_MTIME' => filemtime($this->config['images_dir'] . 'bootstrap/' . $this->config['bootstrap_version'] . '/plugins.js'),
			'S_MAIN_JS_MTIME' => filemtime($this->config['js_dir'] . 'base.js'),
			'S_STYLE_MTIME'   => filemtime($this->config['images_dir'] . 'bootstrap/' . $this->config['bootstrap_version'] . '/expansion.css'),
		));
	}
}
