<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2013
*/

namespace app\cache;

use fw\cache\service as base_service;

class service extends base_service
{
	/**
	* Статистика по изображениям в галерее
	*/
	public function obtain_image_stats()
	{
		if (false === $stats = $this->driver->get('image_stats'))
		{
			$stats = [];

			/* Количество изображений, загруженных за последние сутки */
			$sql = '
				SELECT
					COUNT(*) as today_images
				FROM
					' . IMAGES_TABLE . '
				WHERE
					image_time >= UNIX_TIMESTAMP(CURRENT_DATE())';
			$this->db->query($sql);
			$row = $this->db->fetchrow();
			$this->db->freeresult();
			$stats += $row;

			/* Общая статистика */
			$sql = '
				SELECT
					COUNT(*) AS total_images,
					SUM(image_size) AS total_size,
					SUM(image_size * image_views) AS total_traffic,
					SUM(image_views) AS total_views
				FROM
					' . IMAGES_TABLE;
			$this->db->query($sql);
			$row = $this->db->fetchrow();
			$this->db->freeresult();
			$stats += $row;

			$this->driver->set('image_stats', $stats, 120);
		}

		return $stats;
	}
}