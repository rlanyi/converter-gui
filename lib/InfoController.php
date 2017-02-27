<?php

class InfoController {
	protected $ci;
	protected $db;

	public function __construct(Slim\Container $ci) {
		$this->ci = $ci;
		$this->db = $this->ci->db;
	}

	public function listVideos($request, $response, $args) {
		$videos = array();
		$sql = 'SELECT fsz.youtube_id, f.youtube_playlist_id, f.youtube_channel, f.cim filmcim, f.gyartas_eve ev, pfsz.cim dal, pfsz.zeneszerzo, pfsz.szovegiro, pfsz.fellepok, fsz.sorszam
		FROM programok_fellepok_szamok pfsz
		INNER JOIN programok_fellepok pf ON (pfsz.programok_fellepo=pf.id) 
		INNER JOIN filmek_szamok fsz ON (fsz.program=pf.program AND fsz.fellepo=pf.fellepo AND fsz.programok_fellepok_szamok_sorszam=pfsz.sorszam) 
		INNER JOIN filmek f ON (fsz.film=f.id) 
		WHERE youtube_id<>"" ORDER BY filmcim, fsz.sorszam';

		foreach ($this->db->query($sql) as $row) {
			$videos[$row['youtube_id']] = $row;
		}

		return $this->ci->view->render($response, 'videos/list.html', [
			'videos' => $videos
			]);
	}

	public function showConvertStatus($request, $response, $args) {
		$url = 'http://converter.ship.a38.hu/?norefresh&showprogress&noredirect';
		$status = file_get_contents($url);

		return $this->ci->view->render($response, 'videos/convertStatus.html', [
			'url' => $url,
			'status' => $status
			]);
	}

}
