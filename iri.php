<?php

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
	 * Valid characters for DIGIT
	 */
	const digit = '0123456789';
	
	/**
	 * Valid characters for HEXDIGIT
	 */
	const hexdigit = '0123456789ABCDEFabcdef';
	
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
	 * Set the scheme. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $scheme
	 * @return bool
	 */
	private function set_scheme($scheme)
	{
		$len = strlen($scheme);
		switch (true)
		{
			case $len > 1:
				if (!strspn($scheme, self::scheme, 1))
				{
					$this->valid = false;
					return false;
				}
			
			case $len > 0:
				if (!strspn($scheme, self::scheme_first_char, 0, 1))
				{
					$this->valid = false;
					return false;
				}
		}
		$this->scheme = $scheme;
		return true;
	}
	
	/**
	 * Set the userinfo.
	 *
	 * @param string $userinfo
	 * @return bool
	 */
	private function set_userinfo($userinfo)
	{
		$position = 0;
		$strlen = strlen($userinfo);
		while (($position += strspn($userinfo, self::userinfo, $position)) < $strlen)
		{
			if ($userinfo[$position] === '%')
			{
				if ($position + 2 < $strlen && strspn($userinfo, self::hexdigit, $position + 1, 2))
				{
					$userinfo = substr_replace(substr($userinfo, $position + 1, 2), strtoupper(substr($userinfo, $position + 1, 2)), $position + 1, 2);
				}
				else
				{
					$userinfo = substr_replace($userinfo, '%25', $position, 1);
					$strlen += 2;
				}
				$position += 3;
			}
			else
			{
				$userinfo = str_replace($userinfo[$position], strtoupper(dechex(ord($userinfo[$position]))), $userinfo, $count);
				$strlen += 2 * $count;
			}
		}
		$this->userinfo = $userinfo;
		return true;
	}
	
	/**
	 * Set the port. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $port
	 * @return bool
	 */
	private function set_port($port)
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
			return false;
		}
	}
	
	/**
	 * Set the path.
	 *
	 * @param string $path
	 * @return bool
	 */
	private function set_path($path)
	{
		if ($path === null || $path === '')
		{
			$this->path = null;
			return true;
		}
		elseif (substr($path, 0, 2) === '//' && $this->host === null)
		{
			return false;
		}
		elseif ($this->scheme === null)
		{
			if (($end_segment = strpos($path, '/')) === false)
			{
				$end_segment = strlen($path);
			}
			if (($colon = strpos($path, ':')) !== false && $colon < $end_segment)
			{
				return false;
			}
		}
		$this->path = $path;
		return true;
	}