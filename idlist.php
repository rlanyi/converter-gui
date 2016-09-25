<?php

    $videos = array();
    $sql = 'SELECT fsz.youtube_id, f.youtube_playlist_id, f.youtube_channel, f.cim filmcim, f.gyartas_eve ev, pfsz.cim dal, pfsz.zeneszerzo, pfsz.szovegiro, pfsz.fellepok, fsz.sorszam
    FROM programok_fellepok_szamok pfsz
    INNER JOIN programok_fellepok pf ON (pfsz.programok_fellepo=pf.id) 
    INNER JOIN filmek_szamok fsz ON (fsz.program=pf.program AND fsz.fellepo=pf.fellepo AND fsz.programok_fellepok_szamok_sorszam=pfsz.sorszam) 
    INNER JOIN filmek f ON (fsz.film=f.id) 
    WHERE youtube_id<>"" ORDER BY filmcim, fsz.sorszam';
 
    foreach ($conn->query($sql) as $row) {
      // Data from query result
      $videos[$row['youtube_id']] = $row;
    }

    if (count($videos)) {
      $htmlBody = <<<END
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Filmcím</th>
                  <th>Cím</th>
                  <th>Video ID</th>
                  <th>Playlist ID</th>
                  <th>Csatorna</th>
                </tr>
              </thead>
              <tbody>
END;
    }

    foreach ($videos as $videoId => $videoData) {
      $htmlBody .= sprintf('
                <tr>
                  <td>%s (%s)</td>
                  <td>%s</td>
                  <td>%s</td>
                  <td>%s</td>
                  <td>%s</td>
                </tr>', $videoData['filmcim'], $videoData['sorszam'], $videoData['dal'], $videoId, $videoData['youtube_playlist_id'], $videoData['youtube_channel']);
    }

    if (count($videos)) {
      $htmlBody .= <<<END
              </tbody>
            </table>
          </div>
END;
    }
