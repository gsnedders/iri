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
		$this->set_authority($parsed['authority']);
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
		elseif (preg_match('/^(([^:\/?#]+):)?(\/\/([^\/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?$/', $iri, $match))
		{
			for ($i = count($match); $i <= 9; $i++)
			{
				$match[$i] = '';
			}
			return $cache[$iri] = array('scheme' => $match[2], 'authority' => $match[4], 'path' => $match[5], 'query' => $match[7], 'fragment' => $match[9]);
		}
		else
		{
			return $cache[$iri] = array('scheme' => '', 'authority' => '', 'path' => '', 'query' => '', 'fragment' => '');
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
	 * Set the authority. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $authority
	 * @return bool
	 */
	private function set_authority($authority)
	{
		$return = 1;
		$old_userinfo = $this->userinfo;
		$old_port = $this->port;
		
		if (($at_position = strpos($authority, '@')) !== false)
		{
			$return &= $this->set_userinfo(substr($authority, 0, $at_position));
		}
		else
		{
			$this->set_userinfo(null);
			$at_position = 0;
		}
		
		if (($colon_position = strpos($authority, ':', $at_position)) !== false)
		{
			if (isset($authority[$colon_position + 1]))
			{
				$return &= $this->set_port(substr($authority, $colon_position + 1));
			}
			else
			{
				$authority = substr($authority, 0, -1);
			}
		}
		else
		{
			$this->set_port(null);
			$colon_position = strlen($authority);
		}
		
		if ($return && $this->set_host(substr($authority, $at_position + 1, $colon_position - $at_position + 1)))
		{
			return true;
		}
		else
		{
			$this->userinfo = $old_userinfo;
			$this->port = $old_port;
			return false;
		}
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
				if ($position + 2 >= $strlen || !strspn($userinfo, self::hexdigit, $position + 1, 2))
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