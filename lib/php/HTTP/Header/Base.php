<?php

class HTTP_Header_Base {

	/**
	 * Convert an "interval" parameter in the style of mod_expires into a number
	 * of seconds, so it can be combined with a current timestamp.  Makes use of
	 * the same syntax as mod_expires uses for the "ExpiresDefault" directive:
	 *
	 *   "<base> [plus] {<num>  <type>}*"
	 *
	 * where <base> is one of:
	 *   - access
	 *   - now (equivalent to 'access')
	 *   - modification
	 *
	 * The plus keyword is optional. <num> should be an integer value [acceptable
	 * to atoi()], and <type> is one of:
	 *   - years
	 *   - months
	 *   - weeks
	 *   - days
	 *   - hours
	 *   - minutes
	 *   - seconds
	 *
	 * @param string Interval
	 * @param string Path to file used for intervals based on modification time
	 * @return integer Timestamp
	 */
	static public function intervalToTimestamp($interval, $path=__FILE__) {
		$seconds_convert = array('years'   => 60*60*24*365.24,
								 'months'  => 60*60*24*30.44,
								 'weeks'   => 60*60*24*7,
								 'days'    => 60*60*24,
								 'hours'   => 60*60,
								 'minutes' => 60,
								 'seconds' => 1);
		$base = '';
		$plus = '';
		$times = array();
		$offset = 0;

		$parts = preg_split('/\s+/', trim(strtolower($interval)));

		if (count($parts) > 0) {
			$base = array_shift($parts);
		}
		if (count($parts) > 0) {
			$plus = array_shift($parts);
			if ($plus != 'plus') {
				throw new Exception("The value for 'plus' in interval string can only be 'plus'");
			}
		}
		while (count($parts) >= 2) {
			$num = array_shift($parts);
			$type = array_shift($parts);
			$times[] = array('num' => $num, 'type' => $type);
		}

		// Calculate the timestamp offset.
		foreach ($times as $time) {
			if (!isset($seconds_convert[$time['type']])) {
				throw new Exception("Illegal value '{$time['type']}' found in expires interval, '$interval'");
			}
			$offset += (int)$time['num'] * $seconds_convert[$time['type']];
		}

		// Get base timestamp.
		if ($base == 'modification') {
			if (file_exists($path)) {
				$ts = filemtime($path);
			} else {
				throw new Exception("Invalid file path: '$path'");
				$ts = time();
			}
		} else {
			$ts = time();
		}

		return $ts + $offset;
	}


	/**
	 * Convert an "interval" parameter in the style of mod_expires into a number
	 * of seconds into the future, i.e. a time-to live.
	 *
	 * See intervalToTimestamp() for a description of the "interval" parameter.
	 *
	 * @param string Interval
	 * @param string Path to file used for intervals based on modification time
	 * @return integer TTL (in seconds)
	 */
	static public function intervalToTTL($interval, $path=__FILE__) {
		$timestamp = self::intervalToTimestamp($interval, $path);
		$ttl = $timestamp - time();
		return $ttl;
	}


	/**
	 * Return an HTTP-compliant date string.
	 *
	 * @param integer Timestamp
	 */
	static public function getHTTPDate ($timestamp) {
		return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
	}

}

?>