<?php

class YoutubeController {
	protected $ci;
	protected $db;
	
	private $client;
	private $youtube;
	
	private $redirectUri;
	private $channels;
	private $templates;
	private $error;

	public function __construct(Slim\Container $ci) {
		$this->ci = $ci;
		$this->db = $this->ci->db;

		$this->channels['rocks'] = 'UC3d1vYjKBiMNINJqfIJgMwA';
		$this->channels['vibes'] = 'UCKMj-Dqih1tuSZBuUvTJC9Q';
		$this->channels['world'] = 'UCsl0_3R4NRFHoiQ0LwjYv6Q';
		$this->channels['free'] = 'UCgv_wnXTVzE8fJJ-pTTdhgg';

		$this->client = new Google_Client();
		$this->client->setClientId($ci['settings']['youtube']['OAUTH2_CLIENT_ID']);
		$this->client->setClientSecret($ci['settings']['youtube']['OAUTH2_CLIENT_SECRET']);
		$this->client->setScopes('https://www.googleapis.com/auth/youtube');
		$this->client->setAccessType("offline");

		$this->redirectUri = strtok(filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL), '?');
//		var_dump($this->redirectUri);

		$this->prepareTemplates();
	}

	public function updateMeta($request, $response, $args) {
		if (!$this->prepare($this->channels[$args['channel']])) {
			$state = mt_rand();
			$this->client->setState($state);
			$_SESSION['state'] = $state;

			// Google URL for authentication
			$this->client->setRedirectUri($this->redirectUri);
			$authUrl = $this->client->createAuthUrl();

			if ($this->error) {
				return $this->ci->view->render($response, 'youtube/error.html', [
					'error' => $this->error
					]);
			}

			return $this->ci->view->render($response, 'youtube/authorize.html', [
				'channel' => $args['channel'],
				'authUrl' => $authUrl
				]);
		}

		if ($this->channels[$args['channel']] != ($channelId = $this->getMyChannelId())) {
			$tokenSessionKey = 'token-' . $this->channels[$args['channel']] . '-' . $this->client->prepareScopes();
			unset($_SESSION[$tokenSessionKey]);
			return $this->ci->view->render($response, 'youtube/errorWrongChannel.html', [
				'channel' => $args['channel'],
				'myChannelTitle' => YoutubeHelper::capitalize('A38 ' . array_flip($this->channels)[$channelId])
				]);
		}

		$videos = $this->getVideoListFromCompass($this->channels[$args['channel']], $args['submit']);
		return $this->ci->view->render($response, 'youtube/updateMeta.html', [
			'channel' => $args['channel'],
			'submit' => $args['submit'],
			'videos' => $videos
			]);
	}

	private function getVideoListFromCompass($youtube_channel_id, $update = false) {
		try {
			$videos = array();
			$sql = 'SELECT fsz.youtube_id, f.youtube_playlist_id, f.youtube_channel, IF(f.youtube_title_hu<>"", f.youtube_title_hu, f.cim) filmcim_hu, IF(f.youtube_title_en<>"", f.youtube_title_en, f.cim) filmcim_en, f.gyartas_eve, 
			(SELECT YEAR(datum) FROM programok p WHERE p.id=pf.program) ev,
			pfsz.cim dal, pfsz.zeneszerzo, pfsz.szovegiro, pfsz.fellepok
			FROM programok_fellepok_szamok pfsz
			INNER JOIN programok_fellepok pf ON (pfsz.programok_fellepo=pf.id) 
			INNER JOIN filmek_szamok fsz ON (fsz.program=pf.program AND fsz.fellepo=pf.fellepo AND fsz.programok_fellepok_szamok_sorszam=pfsz.sorszam) 
			INNER JOIN filmek f ON (fsz.film=f.id) 
			WHERE youtube_id<>"" AND youtube_channel="'.$youtube_channel_id.'" ORDER BY f.cim, fsz.sorszam';

			foreach ($this->db->query($sql) as $row) {
      			// Data from query result
				$videos[$row['youtube_id']] = $row;

      			// Hungarian year suffixes
				$videos[$row['youtube_id']]['evben'] = YoutubeHelper::evben($row['ev']);

      			// Links
				$videos[$row['youtube_id']]['channelLink'] = '';
				if ($videos[$row['youtube_id']]['youtube_channel'] != '') {
					$videos[$row['youtube_id']]['channelLink'] = sprintf('https://www.youtube.com/channel/%s?sub_confirmation=1', $videos[$row['youtube_id']]['youtube_channel']);
				}
				$videos[$row['youtube_id']]['playlistLink'] = '';
				if ($videos[$row['youtube_id']]['youtube_playlist_id'] != '') {
					$videos[$row['youtube_id']]['playlistLink'] = sprintf('https://www.youtube.com/playlist?list=%s', $videos[$row['youtube_id']]['youtube_playlist_id']);
				}

      			// Capitalize
				if ($videos[$row['youtube_id']]['filmcim'] == strtoupper($videos[$row['youtube_id']]['filmcim'])) {
					$videos[$row['youtube_id']]['filmcim'] = ucwords($videos[$row['youtube_id']]['filmcim']);
				}
				if ($videos[$row['youtube_id']]['dal'] == strtoupper($videos[$row['youtube_id']]['dal'])) {
					$videos[$row['youtube_id']]['dal'] = YoutubeHelper::capitalize($videos[$row['youtube_id']]['dal']);
				}
			}

			$counter = 0;
			$errorcounter = 0;
			foreach ($videos as $videoId => $videoData) {

    			// Call the API's videos.list method to retrieve the video resource.
				try {
					if ($update) {
						$listResponse = $this->youtube->videos->listVideos("snippet,localizations", array('id' => $videoId));

				    // If $listResponse is empty, the specified video was not found.
				    // Since the request specified a video ID, the response only
				    // contains one video resource.
						$video = $listResponse[0];
						$videoSnippet = $video['snippet'];
						$videoLocalizations = $video['localizations'];
					} else {
						$videoSnippet = array('channelTitle' => YoutubeHelper::capitalize('A38 ' . array_flip($this->channels)[$youtube_channel_id]));
						$videoLocalizations = array();
					}
					$videoSnippet['defaultLanguage'] = 'en';

					$tags = $this->templates['tags'][$youtube_channel_id];
					foreach ($tags as &$tag) {
						$tag = YoutubeHelper::removeInvalidChars(YoutubeHelper::replace($tag, array($videoData, $videoSnippet)));
					}
					$videoSnippet['tags'] = $tags;
					$videoSnippet['title'] = YoutubeHelper::shorten(YoutubeHelper::removeInvalidChars(YoutubeHelper::replace($this->templates['title']['en'], array($videoData, $videoSnippet))));
					$videoSnippet['description'] = YoutubeHelper::removeInvalidChars(YoutubeHelper::replace($this->templates['description']['en'], array($videoData, $videoSnippet, $this->templates['channelText_en'][$youtube_channel_id])));
					$videoLocalizations['hu']['title'] = YoutubeHelper::shorten(YoutubeHelper::removeInvalidChars(YoutubeHelper::replace($this->templates['title']['hu'], array($videoData, $videoSnippet))));
					$videoLocalizations['hu']['description'] = YoutubeHelper::removeInvalidChars(YoutubeHelper::replace($this->templates['description']['hu'], array($videoData, $videoSnippet, $this->templates['channelText_hu'][$youtube_channel_id])));
// lat 47.476489,  long 19.062929, alt 130 m (427 ft).

					$video['snippet'] = $videoSnippet;
					$video['localizations'] = $videoLocalizations;
					$videos[$videoId] = $video;

				    // Update the video resource by calling the videos.update() method.
					if ($update) {
						$updateResponse = $this->youtube->videos->update("snippet,localizations", $video);
						$videos[$videoId]['error'] = false;
						$videos[$videoId]['result'] = "meta frissítve";
					} else {
						$videos[$videoId]['error'] = false;
						$videos[$videoId]['result'] = '';
					}
//        $htmlBody .= dump($videoId);
//        $htmlBody .= dump($videoSnippet);
//        $htmlBody .= dump($videoLocalizations);
					$counter++;
					$htmlBody .= "#${counter} Video updated: ${videoSnippet["title"]}<br />";

				} catch (Exception $e) {
					$counter++;
					$errorcounter++;
					$error = json_decode($e->getMessage(), true)['error'];
					$videos[$videoId]['error'] = true;
					$videos[$videoId]['result'] = sprintf("Hiba: %s %s", $error['code'], $error['message']);
					$htmlBody .= sprintf("<strong>#${counter} Error %s %s: ${videoSnippet["title"]} (id: ${videoId})</strong><br />", $error['code'], $error['message']);
					continue;
				}
			}    
		} catch (Google_Service_Exception $e) {
			$htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p><p><pre>%s</pre></p>',
				htmlspecialchars($e->getMessage()), dump($videoSnippet) . dump($videoLocalizations));
		} catch (Google_Exception $e) {
			$htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
				htmlspecialchars($e->getMessage()));
		} catch (PDOException $e) {
			$htmlBody .= sprintf('<p>A database error occurred: <code>%s</code></p>',
				htmlspecialchars($e->getMessage()));
		}

		if ($errorcounter > 0) {
			$htmlBody .= sprintf('<p style="margin-top: 20px;"><strong>%s error(s) occured during the update process.</strong></p>', $errorcounter);
		}

		return $videos;
	}

	private function prepareTemplates() {
		$this->templates = array();
		$this->templates['title']['hu'] = '[filmcim_hu] - [dal] // Live [ev] // [channelTitle]'; 
		$this->templates['description']['hu'] = "Az [channelTitle] [evben] rögzített, a [filmcim_hu] koncertjén előadott [dal] című dal felvétele. Nézd meg a koncert további dalait is: [playlistLink]

[channelText] Iratkozz fel a csatornára: [channelLink]

További linkek:
http://www.a38.hu/
https://www.facebook.com/a38.hajo
https://www.youtube.com/user/A38Captain
https://www.instagram.com/a38ship/
"; 
		$this->templates['title']['en'] = '[filmcim_en] - [dal] // Live [ev] // [channelTitle]'; 
		$this->templates['description']['en'] = 'In [ev] [channelTitle] recorded the live concert footage of this song called [dal] performed by [filmcim_en]. Watch the full concert here: [playlistLink]

[channelText] Subscribe to this channel: [channelLink]

You can follow us:
http://www.a38.hu/en/
https://www.facebook.com/a38.hajo
https://www.youtube.com/user/A38Captain
https://www.instagram.com/a38ship/
';

		$this->templates['tags'][$this->channels['rocks']] = array('[filmcim_hu]', '[dal]', '[ev]', '[channelTitle]', 'hardcore', 'metal', 'punk', 'rock', 'A38 hajó', 'kultúra', 'zene', 'koncert', 'rendezvény', 'élő koncert', 'élőzene', 'koncertterem', 'művészet', 'előadóművészet', 'szórakoztatás', 'Budapest', 'Duna', 'A38 Ship', 'live concert', 'live music', 'music', 'event', 'concert hall', 'music venue', 'Budapest', 'Hungary', 'art', 'performing art', 'performance', 'culture', 'art centre', 'entertainment', 'Danube');
		$this->templates['tags'][$this->channels['vibes']] = array('[filmcim_hu]', '[dal]', '[ev]', '[channelTitle]', 'pop', 'indie', 'elektornika', 'rock', 'mainstream', 'dance', 'A38 hajó', 'kultúra', 'zene', 'koncert', 'rendezvény', 'élő koncert', 'élőzene', 'koncertterem', 'művészet', 'előadóművészet', 'szórakoztatás', 'Budapest', 'Duna', 'A38 Ship', 'live concert', 'live music', 'music', 'event', 'concert hall', 'music venue', 'Budapest', 'Hungary', 'art', 'performing art', 'performance', 'culture', 'art centre', 'entertainment', 'Danube');
		$this->templates['tags'][$this->channels['world']] = array('[filmcim_hu]', '[dal]', '[ev]', '[channelTitle]', 'világzene', 'folk', 'népzene', 'reggae', 'dub', 'ska', 'afro', 'latin', 'world music', 'folk music', 'A38 hajó', 'kultúra', 'zene', 'koncert', 'rendezvény', 'élő koncert', 'élőzene', 'koncertterem', 'művészet', 'előadóművészet', 'szórakoztatás', 'Budapest', 'Duna', 'A38 Ship', 'live concert', 'live music', 'music', 'event', 'concert hall', 'music venue', 'Budapest', 'Hungary', 'art', 'performing art', 'performance', 'culture', 'art centre', 'entertainment', 'Danube');
		$this->templates['tags'][$this->channels['free']] = array('[filmcim_hu]', '[dal]', '[ev]', '[channelTitle]', 'free', 'jazz', 'kísérleti zene', 'avantgard', 'experimental', 'A38 hajó', 'kultúra', 'zene', 'koncert', 'rendezvény', 'élő koncert', 'élőzene', 'koncertterem', 'művészet', 'előadóművészet', 'szórakoztatás', 'Budapest', 'Duna', 'A38 Ship', 'live concert', 'live music', 'music', 'event', 'concert hall', 'music venue', 'Budapest', 'Hungary', 'art', 'performing art', 'performance', 'culture', 'art centre', 'entertainment', 'Danube');

		$this->templates['channelText_hu'][$this->channels['rocks']]['channelText'] = 'Az elmúlt évtizedben az A38 Hajó a kurrens rockzenei, metal és hardcore-punk hangzások otthonaként is kivívta a rajongók megbecsülését. Büszkén mutatjuk be nektek saját koncertarchívumunk legemlékezetesebb pillanatait a legsúlyosabbtól a legfogósabb dalokig. A38 Rocks – ahol a hangerő megmozgatja a Hajót.';
		$this->templates['channelText_en'][$this->channels['rocks']]['channelText'] = 'For over a decade, A38 Ship has been the home of great contemporary rock, metal and hardcore acts. We are proud to present the most memorable moments from the biggest shows on – and for sure, in front of – the stage to the catchiest hits of acts.  A38 Rocks – where amps can sound as loud, it shakes the ship.';
		$this->templates['channelText_hu'][$this->channels['vibes']]['channelText'] = 'Fennállása óta az A38 Hajó nem csak az underground, hanem a mainstream – tehát a pop-rock, az indie és az elektronika – legnagyobb alakjait is elhozta Budapestre, sokszor elsőként az egész országban. Olyan nevek váltották egymást a Dunán, mint az M83, a Warpaint, a Crystal Castles, Zola Jesus, miközben este tíztől olyan DJ-k szolgáltatták a talpalávalót, mint Ben Klock, DJ Marky vagy épp Blawan. Az A38 Vibes a hajó saját koncertarchívumának legforróbb és legjobb hangulatú pillanatait gyűjti össze, a !!! frontemberének higanymozgásától a The Boxer Rebellion himnikus indie rockjáig. A38 Vibes – zene a legemlékezetesebb estéidhez.';
		$this->templates['channelText_en'][$this->channels['vibes']]['channelText'] = 'Since it\'s foundation, A38 Ship hosted performances of underground legends and also mainstream – aka pop-rock, indie and electronic – superstars; in many cases, for the first time in Hungary. Acts like M83, Crystal Castles, Warpaint and Zola Jesus hit the famous stage on the Danube, and after 10pm, DJ-s like Ben Klock, DJ Marky and Blawan stood behind the decks to take the night to the next level. A38 Vibes collects the most memorable moments of the ship\'s own concert-footage archive, from The Boxer Rebellion\'s hymnic indie rock anthems to the funky moves of !!!\'s Nic Offer. A38 Vibes – the soundtrack for your best night outs.';
		$this->templates['channelText_hu'][$this->channels['world']]['channelText'] = 'Akárcsak a Duna, az A38 Hajó is számos országhatáron átível, nem csak a hírnév, hanem a fellépők származása tekintetében is. Játszott itt már a jamaicai reggae-dancehall legenda Lee „Scratch” Perry, a japán pszichidelikus rock színtér sarokkövének számító Acid Mothers Temple és a máltai Tribali is. A különböző országok zenészei pedig hozták a saját népük tradicionális hangszereit, zenéjét is. Nem csoda, hogy a világ egyik leghíresebb zenei expojának, a WOMEX-nek 2015-ben - többek közt - az A38 Hajó adott helyett. A38 World – ahol a zene nem ismer határokat.';
		$this->templates['channelText_en'][$this->channels['world']]['channelText'] = "Just like the Danube on which it floats, the A38 Ship doesn't care about borders or (language) barriers – this has been proven by the well-earned international frame of the venue, and also by the colorful origins of the artists it hosts: Jamaican reggae-dancehall legend Lee “Scratch” Perry, Japanese psychedelic rock heroes Acid Mothers Temple and Tamikrest from Tuareg are just a few examples. The musicians of different nations also bring the traditional instruments and sounds of their home. It’s not a surprise that A38 Ship was one of the selected venues to host the world's biggest music expo, WOMEX 2015 in Budapest. A38 World – where music knows no boundaries.";
		$this->templates['channelText_hu'][$this->channels['free']]['channelText'] = 'Az A38 Hajó a jazz, a kísérleti, az avantgárd és más stílusok otthonaként a művészet szabadságának, öntörvényűségének, gondolatiságának, izgalmának, forradalmiságának, szellemiségének, spiritualitásának, újító jellegének, besorolhatóságának és megvásárolhatatlanságának a jegyében fogant zenéi már számos előadót mutattak be a legnagyobbak közül Peter Brötzmanntól a Vienna Art Orchestráig, Matts Gustafssontól a Huutajatig, Charles Gayltől James Chance-ig és Merzbowtól Grencsó Istvánig. A38 Free – ahol a szabadság szárnyakat ad.';
		$this->templates['channelText_en'][$this->channels['free']]['channelText'] = 'A38 Ship as the home of jazz, experimental avantgarde and other stlyes, has always presented the most exciting music, born from freedom, autonomy and  intellectuality. We’ve presented lots of great musicians, from Peter Brötzmann to Vienna Art Orchestra, Matts Gustasson to Huutajat, Charles Gayle to James Chance and Merzbow to István Grencsó. A38 Free – where free expression flies high.';
	}

	private function prepare($youtube_channel_id) {
		// Define an object that will be used to make all API requests.
		$this->youtube = new Google_Service_YouTube($this->client);

		// Check if an auth token exists for the required scopes
		$tokenSessionKey = 'token-' . $youtube_channel_id . '-' . $this->client->prepareScopes();
		if (isset($_GET['code'])) {
			if (strval($_SESSION['state']) !== strval($_GET['state'])) {
				$this->error = 'The session state did not match.';
				return false;
			}

			$this->client->setRedirectUri($this->redirectUri);
			$creds = $this->client->authenticate($_GET['code']);
			if ((is_array($creds)) && (isset($creds['error']))) {
//				var_dump($creds);
				$this->error = $creds['error_description'];
				return false;
			}

			$_SESSION[$tokenSessionKey] = $this->client->getAccessToken();
			header('Location: ' . $this->redirectUri);
		}

		if (isset($_SESSION[$tokenSessionKey])) {
			$this->client->setAccessToken($_SESSION[$tokenSessionKey]);
		}

		if ($this->client->getAccessToken()) {
			$_SESSION[$tokenSessionKey] = $this->client->getAccessToken();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns localized metadata for a channel in a selected language.
	 * If the localized text is not available in the requested language,
	 * this method will return text in the default language.
	 *
	 * @param Google_Service_YouTube $youtube YouTube service object.
	 * @param string $channelId The channelId parameter instructs the API to return the
	 * localized metadata for the channel specified by the channel id.
	 * @param string language The language of the localized metadata.
	 * @param $htmlBody - html body.
	 */
	private function getMyChannelId() {
		// Call the YouTube Data API's channels.list method to retrieve channels.
		$channels = $this->youtube->channels->listChannels("snippet", array(
			'mine' => true
			));
		if (is_array($channels['modelData']['items'])) {
			return $channels['modelData']['items'][0]['id'];
		}
		return null;
	}

	function redirect($request, $response, $to = null) {
		if (null !== $to) {
			return $response->withRedirect($to);
		}

		$originaluri = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		return $response->withRedirect($this->ci->router->pathFor('youtube-authorize', [], ['original-uri' => $originaluri]));
	}

}
