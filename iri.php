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
	 * Whether the object represents a valid IRI
	 *
	 * @var bool
	 */
	private $valid = true;
	
	public function __construct($iri)
	{
		$parsed = $this->parse_iri((string) $iri);
		$this->set_scheme($parsed['scheme']);
		$this->set_authority($parsed['authority']);
		$this->set_path($parsed['path']);
		$this->set_query($parsed['query']);
		$this->set_fragment($parsed['fragment']);
	}
	
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
	
	private function set_authority($authority)
	{
		if (($at_position = strpos($authority, '@')) !== false)
		{
			$this->set_userinfo(substr($authority, 0, $at_position));
		}
		else
		{
			$at_position = 0;
		}
		
		if (($colon_position = strpos($authority, ':', $at_position)) !== false)
		{
			if (isset($authority[$colon_position + 1]))
			{
				$this->set_port(substr($authority, $colon_position + 1));
			}
			else
			{
				$authority = substr($authority, 0, -1);
			}
		}
		else
		{
			$colon_position = strlen($authority);
		}
		
		$this->set_host(substr($authority, $at_position + 1, $colon_position - $at_position + 1));
	}