<?php

class YoutubeHelper {
	
	public static function shorten($s, $limit = 100, $separator = '//') {
		$s = str_replace('<', '', $s);
		$s = str_replace('>', '', $s);
		if (strlen($s) > $limit) {
			if (false !== ($pos = strrpos($s, $separator))) {
				$s = self::shorten(trim(substr($s, 0, $pos)) . '...', $limit, $separator);
			} elseif (false !== ($pos = strrpos($s, ' '))) {
				$s = self::shorten(trim(substr($s, 0, $pos)) . '...', $limit, ' ', $separator);
			} else {
				$s = substr($s . '...', 0, $limit-3);
			}
		}
		return trim($s);
	}

	public static function replace($subject, $values) {
		if (preg_match_all('/\[(.*?)\]/', $subject, $matches) !== false) {
			foreach ($matches[1] as $match) {
				if (!is_array($values[0])) {
					$values = array($values);
				}
				foreach ($values as $v) {
					if (isset($v[$match])) {
						$subject = str_replace(sprintf('[%s]', $match), $v[$match], $subject);
					}
				}
			}
		}
		return $subject;
	}

	public static function evben($year) {
		$suffixes = array(1 => 'ben', 2 => 'ben', 3 => 'ban', 4 => 'ben', 5 => 'ben', 6 => 'ban', 7 => 'ben', 8 => 'ban', 9 => 'ben');

		if (substr($year, -2) == '20') {
			return sprintf('%s-%s', $year, 'ban');
		}

		if (substr($year, -3) == '000') {
			return sprintf('%s-%s', $year, 'ben');
		}

		if (substr($year, -2) == '00') {
			return sprintf('%s-%s', $year, 'ban');
		}

		if (substr($year, -1) == '0') {
			return sprintf('%s-%s', $year, $suffixes[substr($year, -2, 1)]);
		}

		return sprintf('%s-%s', $year, $suffixes[substr($year, -1)]);
	}

	public static function capitalize($subject) {
		$words = array('A', 'An', 'The', 
			'And', 'Of', 'Or', 'For', 'Nor', 
			'As', 'For', 'Per', 'In', 'Into', 'With', 'On', 'At', 'To', 'Via', 'From', 'By');
		$subject = preg_replace_callback('/\b(' . implode( '|', $words) . ')\b/i', function($matches) {
			return strtolower($matches[1]);
		}, ucwords(strtolower($subject)));

		$subject = ucfirst($subject);

		// Find words with special chars
		$subject = preg_replace_callback('~(?=(?:\S*[^\w\s])+)\S+~', function($matches) {
			return strtoupper($matches[0]);
		}, $subject);

		return $subject;
	}

	public static function removeInvalidChars($s) {
		$s = str_replace('<', '', $s);
		$s = str_replace('>', '', $s);
		return $s;
	}
}