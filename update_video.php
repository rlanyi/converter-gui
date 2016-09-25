<?php

$templates = array();
$templates['tags'] = array('[filmcim]', '[dal]', '[ev]', '[channelTitle]', 'hardcore', 'metal', 'punk', 'rock', 'A38 hajó', 'kultúra', 'zene', 'koncert', 'rendezvény', 'élő koncert', 'élőzene', 'koncertterem', 'művészet', 'előadóművészet', 'szórakoztatás', 'Budapest', 'Duna', 'A38 Ship', 'live concert', 'live music', 'music', 'event', 'concert hall', 'music venue', 'Budapest', 'Hungary', 'art', 'performing art', 'performance', 'culture', 'art centre', 'entertainment', 'Danube');
$templates['title']['hu'] = '[filmcim] - [dal] // Live [ev] // [channelTitle]'; 
$templates['description']['hu'] = "Az [channelTitle] [evben] rögzített, a [filmcim] koncertjén előadott [dal] című dal felvétele. Nézd meg a koncert további dalait is: [playlistLink]

Az elmúlt évtizedben az A38 Hajó a kurrens rockzenei, metal és hardcore-punk hangzások otthonaként is kivívta a rajongók megbecsülését. Büszkén mutatjuk be nektek saját koncertarchívumunk legemlékezetesebb pillanatait a legsúlyosabbtól a legfogósabb dalokig. A38 Rocks – ahol a hangerő megmozgatja a Hajót. Iratkozz fel a csatornára: [channelLink]

További linkek:
http://www.a38.hu/
https://www.facebook.com/a38.hajo
https://www.youtube.com/user/A38Captain
https://www.instagram.com/a38ship/
"; 
$templates['title']['en'] = '[filmcim] - [dal] // Live [ev] // [channelTitle]'; 
$templates['description']['en'] = 'In [ev] [channelTitle] recorded the live concert footage of this song called [dal] performed by [filmcim]. Watch the full concert here: [playlistLink]

For over a decade, A38 Ship has been the home of great contemporary rock, metal and hardcore acts. We are proud to present the most memorable moments from the biggest shows on – and for sure, in front of – the stage to the catchiest hits of acts.  A38 Rocks – where amps can sound as loud, it shakes the ship. Subscribe to this channel: [channelLink]

You can follow us:
http://www.a38.hu/en/
https://www.facebook.com/a38.hajo
https://www.youtube.com/user/A38Captain
https://www.instagram.com/a38ship/
';

  $htmlBody = '';
  try{

    $videos = array();
    $sql = 'SELECT fsz.youtube_id, f.youtube_playlist_id, f.youtube_channel, f.cim filmcim, f.gyartas_eve ev, pfsz.cim dal, pfsz.zeneszerzo, pfsz.szovegiro, pfsz.fellepok
    FROM programok_fellepok_szamok pfsz
    INNER JOIN programok_fellepok pf ON (pfsz.programok_fellepo=pf.id) 
    INNER JOIN filmek_szamok fsz ON (fsz.program=pf.program AND fsz.fellepo=pf.fellepo AND fsz.programok_fellepok_szamok_sorszam=pfsz.sorszam) 
    INNER JOIN filmek f ON (fsz.film=f.id) 
    WHERE youtube_id<>"" ORDER BY filmcim, fsz.sorszam';
 
    foreach ($conn->query($sql) as $row) {
      // Data from query result
      $videos[$row['youtube_id']] = $row;

      // Hungarian year suffixes
      $videos[$row['youtube_id']]['evben'] = evben($row['ev']);

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
        $videos[$row['youtube_id']]['dal'] = capitalize($videos[$row['youtube_id']]['dal']);
      }
    }

    foreach ($videos as $videoId => $videoData) {

    // Call the API's videos.list method to retrieve the video resource.
      $listResponse = $youtube->videos->listVideos("snippet,localizations", array('id' => $videoId));

    // If $listResponse is empty, the specified video was not found.
      if (empty($listResponse)) {
        $htmlBody .= sprintf('<h3>Can\'t find a video with video id: %s</h3>', $videoId);
      } else {
      // Since the request specified a video ID, the response only
      // contains one video resource.
        $video = $listResponse[0];
        $videoSnippet = $video['snippet'];
        $videoLocalizations = $video['localizations'];
//        var_dump($videoSnippet, $videoLocalizations);

        $videoSnippet['defaultLanguage'] = 'en';
        $tags = $templates['tags'];
        foreach ($tags as &$tag) {
          $tag = replace($tag, array($videoData, $videoSnippet));
        }
        $videoSnippet['tags'] = $tags;
        $videoSnippet['title'] = replace($templates['title']['en'], array($videoData, $videoSnippet));
        $videoSnippet['description'] = replace($templates['description']['en'], array($videoData, $videoSnippet));
        $videoLocalizations['hu']['title'] = replace($templates['title']['hu'], array($videoData, $videoSnippet));
        $videoLocalizations['hu']['description'] = replace($templates['description']['hu'], array($videoData, $videoSnippet));

//        exit();
      // Update the video resource by calling the videos.update() method.
        $video['snippet'] = $videoSnippet;
        $video['localizations'] = $videoLocalizations;
        $updateResponse = $youtube->videos->update("snippet,localizations", $video);

        $counter++;
        $htmlBody .= "#${counter} Video Updated: ${videoSnippet["title"]}<br />";
      }
    }    
  } catch (Google_Service_Exception $e) {
    $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (PDOException $e) {
    $htmlBody .= sprintf('<p>A database error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  }

