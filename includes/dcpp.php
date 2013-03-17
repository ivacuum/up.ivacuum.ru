<?php
/**
* @package up.ivacuum.ru
* @copyright (c) 2011
*/

namespace app;

use app\models\page;

/**
* Загрузка клиентов DC++
*/
class dcpp extends page
{
	public function index()
	{
		$today_dlc     = 0;
		$total_dlc     = 0;
		$total_size    = 0;
		$total_traffic = 0;

		/**
		* Количество закачек за последние сутки
		*/
		$sql = '
			SELECT
				COUNT(*) as total_dlc
			FROM
				' . DOWNLOADS_TABLE . '
			WHERE
				dl_time >= UNIX_TIMESTAMP(CURRENT_DATE())';
		$result = $db->query($sql);

		while( $row = $db->fetchrow($result) )
		{
			$today_dlc += $row['total_dlc'];
		}

		$db->freeresult($result);

		/**
		* Общее количество закачек и трафик
		*/
		$sql = '
			SELECT
				SUM(download_count) AS total_dlc,
				SUM(file_size) AS total_size,
				SUM(file_size * download_count) AS total_traffic
			FROM
				' . FILES_TABLE;
		$result = $db->query($sql);
		$row = $db->fetchrow($result);
		$db->freeresult($result);
		$total_dlc     = $row['total_dlc'];
		$total_size    = $row['total_size'];
		$total_traffic = $row['total_traffic'];

		$template->vars([
			'TODAY_DLC'     => $today_dlc,
			'TOTAL_DLC'     => $total_dlc,
			'TOTAL_SIZE'    => $total_size,
			'TOTAL_TRAFFIC' => $total_traffic,
		]);

		page_header('Загрузка клиентов DC++');

		$template->file = 'dcpp.html';

		page_footer();
	}
	
	public function index_post()
	{
		/**
		* Клиенты DC++
		*/
		if( !$auth->acl_get('a_') )
		{
			$template->setvar('LOGO', 'alert');
			trigger_error('Клиенты DC++ могут загружать только администраторы проекта.');
		}

		/**
		* Выпадающий список с предыдущими вариантами названий и URL клиентов
		*/
		if( $term )
		{
			$search_for = getvar('search_for', '');

			if( $search_for != 'file_name' && $search_for != 'file_url' )
			{
				/* Поиск ограничен двумя параметрами */
				trigger_error('ERROR');
			}

			$sql = '
				SELECT
					' . $search_for . '
				FROM
					' . FILES_TABLE . '
				WHERE
					file_project = ' . $db->check_value('dc') . '
				AND
					' . $search_for . ' ' . $db->like_expression(htmlspecialchars_decode($term)) . '
				ORDER BY
					file_time DESC';
			$result = $db->query_limit($sql, 10);
			$output = '';

			while( $row = $db->fetchrow($result) )
			{
				if( $output )
				{
					$output .= ',';
				}

				$output .= '"' . $row[$search_for] . '"';
			}

			$db->freeresult($result);

			printf('[%s]', $output);
			garbage_collection(false);
			exit;
		}

		if( $submit && !empty($_FILES) )
		{
			$file_name = getvar('file_name', '');
			$file_url  = getvar('file_url', '');

			if( !$file_name || !$file_url )
			{
				$template->setvar('LOGO', 'alert');
				trigger_error('Обязательно нужно указать название и URL клиента.');
			}

			/* Файл загружен без ошибок */
			if( $_FILES['userfile']['error'] == UPLOAD_ERR_OK )
			{
				/**
				* Обрабатываем только загруженные без ошибок файлы
				*/
				$upload = new \fileupload('', ['dmg', 'exe', 'msi', 'zip'], 41943040);
				$file = $upload->form_upload('userfile');
				$file->clean_filename('defined', $file_url);
				$file->move_file('static.ivacuum.ru/d/dc/clients', true, true);

				if( sizeof($file->error) )
				{
					$file->remove();
					trigger_error($file->error[0]);
				}
				else
				{
					$sql_ary = [
						'file_project'   => 'dc',
						'file_folder'    => 'clients',
						'file_time'      => $user->ctime,
						'file_name'      => $file_name,
						'file_url'       => $file_url,
						'file_size'      => $file->filesize,
						'file_extension' => $file->extension
					];

					/**
					* Заносим информацию о загруженном изображении в БД
					*/
					$sql = 'INSERT INTO ' . FILES_TABLE . ' ' . $db->build_array('INSERT', $sql_ary);
					$db->query($sql);

					$template->setvar('LOGO', 'success');
					meta_refresh(2, ilink('/dcpp.html'));
					trigger_error('Файл загружен');
				}
			}
		}
	}
}
