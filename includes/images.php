<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2011
*/

namespace app;

use app\models\page;

/**
* Загрузка изображений
*/
class images extends page
{
	public function _setup()
	{
		$stats = $this->cache->obtain_image_stats();

		$this->template->assign(array(
			'IMAGE_VIEWS'   => num_format($stats['total_views']),
			'TODAY_IMAGES'  => num_format($stats['today_images']),
			'TOTAL_IMAGES'  => num_format($stats['total_images']),
			'TOTAL_SIZE'    => humn_size($stats['total_size']),
			'TOTAL_TRAFFIC' => humn_size($stats['total_traffic']),
		));
	}
	
	/**
	* Форма загрузки
	*/
	public function index()
	{
		$this->user->is_auth('redirect');

		$this->template->assign(array(
			'U_ACTION' => ilink($this->url),
		));
	}
	
	/**
	* Пользователь загружает изображения
	*/
	public function index_post()
	{
		$this->user->is_auth('redirect');

		$preview_size  = $this->request->variable('preview_size', 0);
		$preview_size  = ( $preview_size != 180 && $preview_size != 360 ) ? 0 : $preview_size;
		$resize_to     = $this->request->variable('resize_to', 0);
		$resize_to     = ( $resize_to > 1600 || $resize_to < 640 ) ? 0 : $resize_to;
		$quality       = 85;
		$watermark     = $this->request->variable('watermark', '');
		$watermark_pos = $this->request->variable('watermark_pos', '');
		
		$json_ary       = array();
		$files_uploaded = $files_not_uploaded = 0;
		$date = date('y/m/d');
		
		// $this->config['images_upload_dir'] = '/srv/www/vhosts/static.ivacuum.ru/tmp/';

		if( !is_dir($this->config['images_upload_dir'] . $date . '/') )
		{
			/**
			* Изображения храним по дням
			* Если папки с текущей датой нету, то создаём её
			*/
			mkdir($this->config['images_upload_dir'] . $date . '/', 0777, true);
			mkdir($this->config['images_upload_dir'] . $date . '/t/', 0777);
			mkdir($this->config['images_upload_dir'] . $date . '/s/', 0777);
		}

		if( isset($_FILES['userfile']['name']) && sizeof($_FILES['userfile']['name']) > 10 )
		{
			trigger_error('Разрешено загружать не более 10 файлов за раз. Вы же выбрали ' . sizeof($_FILES['userfile']['name']) . '.');
		}

		/**
		* Обработка мультизагрузки
		*/
		if( isset($_FILES['userfile']['name']) && is_array($_FILES['userfile']['name']) )
		{
			for( $i = 0, $len = sizeof($_FILES['userfile']['name']); $i < $len; $i++ )
			{
				$_FILES['userfile' . $i]['name']     = $_FILES['userfile']['name'][$i];
				$_FILES['userfile' . $i]['type']     = $_FILES['userfile']['type'][$i];
				$_FILES['userfile' . $i]['tmp_name'] = $_FILES['userfile']['tmp_name'][$i];
				$_FILES['userfile' . $i]['error']    = $_FILES['userfile']['error'][$i];
				$_FILES['userfile' . $i]['size']     = $_FILES['userfile']['size'][$i];
			}

			unset($_FILES['userfile']);
		}

		/**
		* Обработка мультизагрузки в опере
		*/
		if( isset($_POST['userfile'], $_POST['userfile'][0]) )
		{
			if( $index = strpos($_POST['userfile'][0], "\n") )
			{
				$bound = substr($_POST['userfile'][0], 2, $index - 2);
				$body  = "MIME-Version: 1.0\nContent-type: multipart/form-data; boundary={$bound}\n\n" . $_POST['userfile'][0];
				unset($_POST['userfile'][0]);

				$msg = mailparse_msg_create();

				if( mailparse_msg_parse($msg, $body) )
				{
					$i = 0;

					foreach( mailparse_msg_get_structure($msg) as $st )
					{
						$section = mailparse_msg_get_part($msg, $st);
						$data    = mailparse_msg_get_part_data($section);

						if( $data['content-type'] == 'multipart/form-data' )
						{
							continue;
						}

						ob_start();

						if( mailparse_msg_extract_part($section, $body) )
						{
							$tmp = tempnam(sys_get_temp_dir(), 'php');
							file_put_contents($tmp, ob_get_clean());

							$_FILES['userfile' . $i]['name']     = substr($data['headers']['content-disposition'], strpos($data['headers']['content-disposition'], 'filename="', 0) + 10, -1);
							$_FILES['userfile' . $i]['type']     = $data['headers']['content-type'];
							$_FILES['userfile' . $i]['tmp_name'] = $tmp;
							$_FILES['userfile' . $i]['error']    = 0;
							$_FILES['userfile' . $i]['size']     = filesize($tmp);

							$i++;
						}
						else
						{
							ob_end_clean();
						}
					}
				}

				unset($f);

				mailparse_msg_free($msg);
			}
		}

		/**
		* Обходим массив загруженных файлов
		*/
		foreach( $_FILES as $files => $files_ary )
		{
			/* Файл должен быть загружен без ошибок */
			if( $files_ary['error'] != UPLOAD_ERR_OK )
			{
				continue;
			}
			
			/**
			* Обрабатываем только загруженные без ошибок файлы
			*/
			$upload = new \engine\upload\fileupload(array('gif', 'jpg', 'jpeg', 'png'), 4194304, 0, 0, 3000, 3000);
			$file = $upload->form_upload($files);
			$file->clean_filename('unique_ext', $this->user['user_id'] . '_');
			$file->move_file($this->config['images_upload_dir'] . $date, false, false);

			if( sizeof($file->error) )
			{
				$file->trigger_error();
				$files_not_uploaded++;
				
				if( $this->request->is_ajax )
				{
					$json_ary[] = array(
						'errors'       => $file->error,
						'errors_count' => sizeof($file->error),
						'uploadname'   => $file->get('uploadname'),
					);
				}
				
				continue;
			}

			/* Преобразование изображения */
			$transform = new \engine\upload\imagetransform($file->get('destination_file'));
			
			/**
			* Наложение водяного знака
			*/
			if( $watermark && $watermark_pos )
			{
				if( !$transform->set_watermark($watermark, $watermark_pos) )
				{
					$file->trigger_error($transform->error);
					$files_not_uploaded++;

					if( $this->request->is_ajax )
					{
						$json_ary[] = array(
							'errors'       => $file->error,
							'errors_count' => sizeof($file->error),
							'uploadname'   => $file->get('uploadname'),
						);
					}

					continue;
				}
			}

			/**
			* Масштабирование изображения
			*
			* Разрешаем загружать изображения вплоть до 6000х6000px,
			* но затем уменьшаем до выбранного размера
			*/
			if( $resize_to > 0 )
			{
				if( !$transform->make_thumbnail($resize_to, $file->get('destination_file')) )
				{
					$file->trigger_error($transform->error);
					$files_not_uploaded++;

					if( $this->request->is_ajax )
					{
						$json_ary[] = array(
							'errors'       => $file->error,
							'errors_count' => sizeof($file->error),
							'uploadname'   => $file->get('uploadname'),
						);
					}

					continue;
				}
			}

			/**
			* Создание маленькой копии изображения для сайта (150px в ширину)
			*/
			if( !$transform->make_thumbnail(150, sprintf('%s/t/%s', $file->get('destination_path'), $file->get('realname'))) )
			{
				$file->trigger_error($transform->error);
				$files_not_uploaded++;

				if( $this->request->is_ajax )
				{
					$json_ary[] = array(
						'errors'       => $file->error,
						'errors_count' => sizeof($file->error),
						'uploadname'   => $file->get('uploadname'),
					);
				}

				continue;
			}

			/**
			* Создание превью с размерами пользователя (180px или 360px в ширину)
			*/
			if( $preview_size > 0 )
			{
				if( !$transform->make_thumbnail($preview_size, sprintf('%s/s/%s', $file->get('destination_path'), $file->get('realname'))) )
				{
					$file->trigger_error($transform->error);
					$files_not_uploaded++;

					if( $this->request->is_ajax )
					{
						$json_ary[] = array(
							'errors'       => $file->error,
							'errors_count' => sizeof($file->error),
							'uploadname'   => $file->get('uploadname'),
						);
					}

					continue;
				}
			}

			$sql_ary = array(
				'user_id'    => $this->user['user_id'],
				'image_url'  => $file->get('realname'),
				'image_date' => date('ymd'),
				'image_time' => $this->user->ctime,
				'image_size' => $file->get('filesize')
			);

			/**
			* Заносим информацию о загруженном изображении в БД
			*/
			$sql = 'INSERT INTO ' . IMAGES_TABLE . ' ' . $this->db->build_array('INSERT', $sql_ary);
			$this->db->query($sql);

			$image_id = $this->db->insert_id();

			$this->template->append('files', array(
				'DATE'     => date('ymd'),
				'ID'       => $image_id,
				'REALNAME' => $file->get('realname'),
			));
			
			if( $this->request->is_ajax )
			{
				$json_ary[] = array(
					'date'         => date('ymd'),
					'errors'       => $file->error,
					'errors_count' => sizeof($file->error),
					'id'           => $image_id,
					'realname'     => $file->get('realname'),
					'uploadname'   => $file->get('uploadname'),
				);
			}

			$files_uploaded++;
		}

		if( $files_uploaded == 0 && $files_not_uploaded == 0 )
		{
			trigger_error('Необходимо выбрать хотя бы один файл для загрузки.');
		}
		
		if( $this->request->is_ajax )
		{
			$this->template->assign('THUMB', $preview_size > 0);
			
			ob_start();
			$this->template->display('ajax/images_links.html');
			$html = ob_get_clean();
			
			json_output(array(
				'files'              => $json_ary,
				'files_uploaded'     => $files_uploaded,
				'files_not_uploaded' => $files_not_uploaded,
				'html'               => $html,
				'success'            => true,
				'thumb'              => $preview_size > 0
			));
		}

		$this->template->assign(array(
			'FILES_UPLOADED'     => $files_uploaded,
			'FILES_NOT_UPLOADED' => $files_not_uploaded,
			'THUMB'              => $preview_size > 0
		));

		$this->template->file = 'images_links.html';
	}

	/**
	* Где смотрят изображения
	*/
	public function referers()
	{
		$sql = '
			SELECT
				*
			FROM
				' . IMAGE_VIEWS_TABLE . '
			ORDER BY
				views_count DESC';
		$this->db->query($sql);

		while( $row = $this->db->fetchrow() )
		{
			$this->template->assign(mb_strtoupper($row['views_from']) . '_VIEWS', num_format($row['views_count']));
		}

		$this->db->freeresult();

		$sql = '
			SELECT
				*
			FROM
				' . IMAGE_REFS_TABLE . '
			ORDER BY
				ref_views DESC';
		$this->db->query_limit($sql, 100);
		$i = 1;

		while( $row = $this->db->fetchrow() )
		{
			$this->template->append('ref', array(
				'DOMAIN' => $row['ref_domain'],
				'VIEWS'  => num_format($row['ref_views'])
			));

			$i++;
		}

		$this->db->freeresult();
	}
	
	/**
	* Подробная статистика просмотров изображений
	*/
	public function stats()
	{
		$stats = $this->cache->obtain_image_stats();

		$sql = '
			SELECT
				COUNT(i.image_views) as total_images,
				SUM(i.image_size) as total_size,
				SUM(i.image_views) as total_views,
				u.user_id,
				u.username,
				u.user_url,
				u.user_colour
			FROM
				' . IMAGES_TABLE . ' i
			LEFT JOIN
				' . USERS_TABLE . ' u ON (u.user_id = i.user_id)
			GROUP BY
				i.user_id
			ORDER BY
				total_views DESC';
		$this->db->query_limit($sql, 100, 0, 60);

		while( $row = $this->db->fetchrow() )
		{
			$this->template->append('users', array(
				'IMAGES'  => num_format($row['total_images']),
				'PROFILE' => $row['username'],
				'SIZE'    => humn_size($row['total_size']),
				'VIEWS'   => num_format($row['total_views'])
			));
		}

		$this->db->freeresult();

		$this->template->vars(array(
			'TODAY_IMAGES'  => num_format($stats['today_images']),
			'TOTAL_IMAGES'  => num_format($stats['total_images']),
			'TOTAL_SIZE'    => humn_size($stats['total_size']),
			'TOTAL_TRAFFIC' => humn_size($stats['total_traffic']),
			'TOTAL_VIEWS'   => num_format($stats['total_views'])
		));
	}
}
