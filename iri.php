<?php

/**
 * IPv6 tools, inc. validator
 */
require_once 'Net_IPv6/IPv6.php';

/**
 * IRI parser/serialiser
 *
 * @package IRI
 */
class IRI
{
	/**
	 * Valid characters for the first character of the scheme
	 */
	const scheme_first_char = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	
	/**
	 * Valid characters for the scheme
	 */
	const scheme = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-.';
	
	/**
	 * Valid characters for the userinfo (minus pct-encoded)
	 */
	const userinfo = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=:';
	
	/**
	 * Valid characters for the reg-name (minus pct-encoded)
	 */
	const reg_name = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=';
	
	/**
	 * Valid characters for the path (minus pct-encoded and colon)
	 */
	const path = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=@/';
	
	/**
	 * Valid characters for DIGIT
	 */
	const digit = '0123456789';
	
	/**
	 * Valid characters for HEXDIGIT
	 */
	const hexdigit = '0123456789ABCDEFabcdef';
	
	/**
	 * Don't change case
	 */
	const same_case = 1;
	
	/**
	 * Change to lowercase
	 */
	const lowercase = 2;
	
	/**
	 * Change to uppercase
	 */
	const uppercase = 4;
	
	/**
	 * Whether the object represents a valid IRI
	 *
	 * @var bool
	 */
	private $valid = true;
	
	/**
	 * Create a new IRI object, from a specified string
	 *
	 * @param string $iri
	 */
	public function __construct($iri)
	{
		$parsed = $this->parse_iri((string) $iri);
		$this->set_scheme($parsed['scheme']);
		$this->set_userinfo($parsed['userinfo']);
		$this->set_host($parsed['host']);
		$this->set_port($parsed['port']);
		$this->set_path($parsed['path']);
		$this->set_query($parsed['query']);
		$this->set_fragment($parsed['fragment']);
	}
	
	/**
	 * Parse an IRI into scheme/authority.path/query/fragment segments
	 *
	 * @param string $iri
	 * @return array
	 */
	private function parse_iri($iri)
	{
		static $cache = array();
		if (isset($cache[$iri]))
		{
			return $cache[$iri];
		}
		elseif (preg_match('/^(([^:\/?#]+):)?(\/\/(([^\/?#@]*)@)?([^\/?#]*)(:([0-9]*))?)?([^?#]*)(\?([^#]*))?(#(.*))?$/', $iri, $match))
		{
			for ($i = count($match); $i <= 13; $i++)
			{
				$match[$i] = '';
			}
			return $cache[$iri] = array('scheme' => $match[2], 'userinfo' => $match[5], 'host' => $match[6], 'port' => $match[8], 'path' => $match[9], 'query' => $match[11], 'fragment' => $match[13]);
		}
		else
		{
			return $cache[$iri] = array('scheme' => '', 'userinfo' => '', 'host' => '', 'port' => '', 'path' => '', 'query' => '', 'fragment' => '');
		}
	}
	
	/**
	 * Replace invalid character with percent encoding
	 *
	 * @param string $string Input string
	 * @param string $valid_chars Valid characters
	 * @param int $case Normalise case
	 * @return string
	 */
	private function replace_invalid_with_pct_encoding($string, $valid_chars, $case = self::same_case)
	{
		// Normalise case
		if ($case & self::lowercase)
		{
			$string = strtolower($string);
		}
		elseif ($case & self::upppercase)
		{
			$string = strtoupper($string);
		}
		
		// Store position and string length (to avoid constantly recalculating this)
		$position = 0;
		$strlen = strlen($string);
		
		// Loop as long as we have invalid characters, advancing the position to the next invalid character
		while (($position += strspn($string, $valid_chars, $position)) < $strlen)
		{
			// If we have a % character
			if ($string[$position] === '%')
			{
				// If we have a pct-encoded section
				if ($position + 2 < $strlen && strspn($string, self::hexdigit, $position + 1, 2))
				{
					// Get the the represented character
					$chr = chr(hexdec(substr($string, $position + 1, 2)));
					
					// If the character is valid, replace the pct-encoded with the actual character while normalising case
					if (strpos($valid_chars, $chr) !== false)
					{
						if ($case & self::lowercase)
						{
							$chr = strtolower($chr);
						}
						elseif ($case & self::upppercase)
						{
							$chr = strtoupper($chr);
						}
						$string = substr_replace($string, $chr, $position + 1, 2);
						$strlen -= 2;
						$position++;
					}
					
					// Otherwise just normalise the pct-encoded to uppercase
					else
					{
						$string = substr_replace($string, strtoupper(substr($string, $position + 1, 2)), $position + 1, 2);
						$position += 3;
					}
				}
				// If we don't have a pct-encoded section, just replace the % with its own esccaped form
				else
				{
					$string = substr_replace($string, '%25', $position, 1);
					$strlen += 2;
					$position += 3;
				}
			}
			// If we have an invalid character, change into its pct-encoded form
			else
			{
				$string = str_replace($string[$position], strtoupper(dechex(ord($string[$position]))), $string, $count);
				$strlen += 2 * $count;
			}
		}
		return $string;
	}
	
	/**
	 * Set the scheme. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $scheme
	 * @return bool
	 */
	public function set_scheme($scheme)
	{
		$len = strlen($scheme);
		switch (true)
		{
			case $len > 1:
				if (!strspn($scheme, self::scheme, 1))
				{
					$this->scheme = null;
					$this->valid = false;
					return false;
				}
			
			case $len > 0:
				if (!strspn($scheme, self::scheme_first_char, 0, 1))
				{
					$this->scheme = null;
					$this->valid = false;
					return false;
				}
		}
		$this->scheme = strtolower($scheme);
		return true;
	}
	
	/**
	 * Set the userinfo.
	 *
	 * @param string $userinfo
	 * @return bool
	 */
	public function set_userinfo($userinfo)
	{
		$this->userinfo = $this->replace_invalid_with_pct_encoding($userinfo, self::userinfo);
		return true;
	}
	
	/**
	 * Set the port. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $port
	 * @return bool
	 */
	public function set_port($port)
	{
		if ($port === null || $port === '')
		{
			$this->port = null;
			return true;
		}
		elseif (strspn($port, self::digit) === strlen($port))
		{
			$this->port = (int) $port;
			return true;
		}
		else
		{
			$this->port = null;
			$this->valid = false;
			return false;
		}
	}
	
	/**
	 * Set the host. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $host
	 * @return bool
	 */
	public function set_host($host)
	{
		if ($host === null || $host === '')
		{
			$this->host = null;
			return true;
		}
		elseif ($host[0] === '[' && substr($host, -1) === ']')
		{
			if (Net_IPv6::checkIPv6(substr($host, 1, -1)))
			{
				$this->host = $host;
				return true;
			}
			else
			{
				$this->host = null;
				$this->valid = false;
				return false;
			}
		}
		else
		{
			$this->host = $this->replace_invalid_with_pct_encoding($host, self::reg_name, self::lowercase);
			return true;
		}
	}
	
	/**
	 * Set the path.
	 *
	 * @param string $path
	 * @return bool
	 */
	public function set_path($path)
	{
		if ($path === null || $path === '')
		{
			$this->path = null;
			return true;
		}
		elseif (substr($path, 0, 2) === '//' && $this->userinfo === null && $this->host === null && $this->port === null)
		{
			$this->path = null;
			$this->valid = false;
			return false;
		}
		$this->path = $this->replace_invalid_with_pct_encoding($path, self::path);
		return true;
	}
}