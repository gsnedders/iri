<?php

/**
 * IRI parser/serialiser/normaliser
 *
 * Copyright (c) 2007-2008, Geoffrey Sneddon and Steve Minutillo.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 	* Redistributions of source code must retain the above copyright notice,
 *	  this list of conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above copyright notice,
 *	  this list of conditions and the following disclaimer in the documentation
 *	  and/or other materials provided with the distribution.
 *
 * 	* Neither the name of the SimplePie Team nor the names of its contributors
 *	  may be used to endorse or promote products derived from this software
 *	  without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package IRI
 * @author Geoffrey Sneddon
 * @author Steve Minutillo
 * @copyright 2007-2008 Geoffrey Sneddon and Steve Minutillo
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @link http://hg.gsnedders.com/iri/
 *
 * @todo Per-scheme validation
 */
class IRI
{
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
	 * Scheme
	 *
	 * @var string
	 */
	private $scheme;

	/**
	 * User Information
	 *
	 * @var string
	 */
	private $userinfo;

	/**
	 * Host
	 *
	 * @var string
	 */
	private $host;

	/**
	 * Port
	 *
	 * @var string
	 */
	private $port;

	/**
	 * Path
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Query
	 *
	 * @var string
	 */
	private $query;

	/**
	 * Fragment
	 *
	 * @var string
	 */
	private $fragment;

	/**
	 * Whether the object represents a valid IRI
	 *
	 * @var array
	 */
	private $valid = array();

	/**
	 * Return the entire IRI when you try and read the object as a string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->iri;
	}

	/**
	 * Overload __set() to provide access via properties
	 *
	 * @param string $name Property name
	 * @param mixed $value Property value
	 * @return void
	 */
	public function __set($name, $value)
	{
		if (method_exists($this, 'set_' . $name))
		{
			return call_user_func(array($this, 'set_' . $name), $value);
		}
		else
		{
			return parent::__set($name);
		}
	}

	/**
	 * Overload __get() to provide access via properties
	 *
	 * @param string $name Property name
	 * @return mixed
	 */
	public function __get($name)
	{
		if (method_exists($this, 'get_' . $name))
		{
			return call_user_func(array($this, 'get_' . $name));
		}
		elseif ($name !== 'valid' && isset($this->$name))
		{
			return $this->$name;
		}
		else
		{
			return parent::__get($name);
		}
	}

	/**
	 * Overload __isset() to provide access via properties
	 *
	 * @param string $name Property name
	 * @return bool
	 */
	public function __isset($name)
	{
		if (method_exists($this, 'get_' . $name) || ($name !== 'valid' && isset($this->$name)))
		{
			return true;
		}
		else
		{
			return parent::__isset($name);
		}
	}

	/**
	 * Overload __unset() to provide access via properties
	 *
	 * @param string $name Property name
	 * @param mixed $value Property value
	 * @return void
	 */
	public function __unset($name)
	{
		if (method_exists($this, 'set_' . $name))
		{
			call_user_func(array($this, 'set_' . $name), '');
		}
		else
		{
			parent::__unset($name);
		}
	}

	/**
	 * Create a new IRI object, from a specified string
	 *
	 * @param string $iri
	 * @return IRI
	 */
	public function __construct($iri = '')
	{
		$this->set_iri($iri);
	}

	/**
	 * Create a new IRI object by resolving a relative IRI
	 *
	 * @param IRI $base Base IRI
	 * @param string $relative Relative IRI
	 * @return IRI
	 */
	public static function absolutize(IRI $base, $relative)
	{
		$relative = (string) $relative;
		if ($relative !== '')
		{
			$relative = new IRI($relative);
			if ($relative->scheme !== null)
			{
				$target = $relative;
			}
			elseif ($base->iri !== null)
			{
				if ($relative->authority !== null)
				{
					$target = $relative;
					$target->set_scheme($base->scheme);
				}
				else
				{
					$target = new IRI('');
					$target->set_scheme($base->scheme);
					$target->set_userinfo($base->userinfo);
					$target->set_host($base->host);
					$target->set_port($base->port);
					if ($relative->path !== null)
					{
						if (strpos($relative->path, '/') === 0)
						{
							$target->set_path($relative->path);
						}
						elseif (($base->userinfo !== null || $base->host !== null || $base->port !== null) && $base->path === null)
						{
							$target->set_path('/' . $relative->path);
						}
						elseif (($last_segment = strrpos($base->path, '/')) !== false)
						{
							$target->set_path(substr($base->path, 0, $last_segment + 1) . $relative->path);
						}
						else
						{
							$target->set_path($relative->path);
						}
						$target->set_query($relative->query);
					}
					else
					{
						$target->set_path($base->path);
						if ($relative->query !== null)
						{
							$target->set_query($relative->query);
						}
						elseif ($base->query !== null)
						{
							$target->set_query($base->query);
						}
					}
				}
				$target->set_fragment($relative->fragment);
			}
			else
			{
				// No base URL, just return the relative URL
				$target = $relative;
			}
		}
		else
		{
			$target = $base;
		}
		return $target;
	}

	/**
	 * Parse an IRI into scheme/authority/path/query/fragment segments
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
	 * Remove dot segments from a path
	 *
	 * @param string $input
	 * @return string
	 */
	private function remove_dot_segments($input)
	{
		$output = '';
		while (strpos($input, './') !== false || strpos($input, '/.') !== false || $input === '.' || $input === '..')
		{
			// A: If the input buffer begins with a prefix of "../" or "./", then remove that prefix from the input buffer; otherwise,
			if (strpos($input, '../') === 0)
			{
				$input = substr($input, 3);
			}
			elseif (strpos($input, './') === 0)
			{
				$input = substr($input, 2);
			}
			// B: if the input buffer begins with a prefix of "/./" or "/.", where "." is a complete path segment, then replace that prefix with "/" in the input buffer; otherwise,
			elseif (strpos($input, '/./') === 0)
			{
				$input = substr_replace($input, '/', 0, 3);
			}
			elseif ($input === '/.')
			{
				$input = '/';
			}
			// C: if the input buffer begins with a prefix of "/../" or "/..", where ".." is a complete path segment, then replace that prefix with "/" in the input buffer and remove the last segment and its preceding "/" (if any) from the output buffer; otherwise,
			elseif (strpos($input, '/../') === 0)
			{
				$input = substr_replace($input, '/', 0, 4);
				$output = substr_replace($output, '', strrpos($output, '/'));
			}
			elseif ($input === '/..')
			{
				$input = '/';
				$output = substr_replace($output, '', strrpos($output, '/'));
			}
			// D: if the input buffer consists only of "." or "..", then remove that from the input buffer; otherwise,
			elseif ($input === '.' || $input === '..')
			{
				$input = '';
			}
			// E: move the first path segment in the input buffer to the end of the output buffer, including the initial "/" character (if any) and any subsequent characters up to, but not including, the next "/" character or the end of the input buffer
			elseif (($pos = strpos($input, '/', 1)) !== false)
			{
				$output .= substr($input, 0, $pos);
				$input = substr_replace($input, '', 0, $pos);
			}
			else
			{
				$output .= $input;
				$input = '';
			}
		}
		return $output . $input;
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
		elseif ($case & self::uppercase)
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
				if ($position + 2 < $strlen && strspn($string, '0123456789ABCDEFabcdef', $position + 1, 2) === 2)
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
						elseif ($case & self::uppercase)
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
				$string = str_replace($string[$position], '%' . sprintf('%%%02X', ord($string[$position])), $string, $count);
				$strlen += 2 * $count;
			}
		}
		return $string;
	}
	
	/**
	 * Do normalisation of the current IRI
	 *
	 * @todo Normalisation of dict scheme (cannot do this fully per spec without AI)
	 */
	private function normalise()
	{
		switch ($this->scheme)
		{
			case 'http':
			case 'https':
				if ($this->port === null)
				{
					$this->port = 80;
				}
				if ($this->path === null)
				{
					$this->path = '/';
				}
				break;
			
			case 'acap':
				if ($this->port === null)
				{
					$this->port = 674;
				}
				break;
				
			case 'dict':
				if ($this->port === null)
				{
					$this->port = 2628;
				}
				break;
			
			case 'file':
				if ($this->host === null)
				{
					$this->host = 'localhost';
				}
				break;
		}
	}

	/**
	 * Check if the object represents a valid IRI
	 *
	 * @return bool
	 */
	public function is_valid()
	{
		return array_sum($this->valid) === count($this->valid);
	}

	/**
	 * Set the entire IRI. Returns true on success, false on failure (if there
	 * are any invalid characters).
	 *
	 * @param string $iri
	 * @return bool
	 */
	private function set_iri($iri)
	{
		$parsed = $this->parse_iri((string) $iri);
		
		return $this->set_scheme($parsed['scheme'])
			&& $this->set_authority($parsed['authority'])
			&& $this->set_path($parsed['path'])
			&& $this->set_query($parsed['query'])
			&& $this->set_fragment($parsed['fragment']);
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
		if ($scheme === null || $scheme === '')
		{
			$this->scheme = null;
		}
		else
		{
			$len = strlen($scheme);
			switch (true)
			{
				case $len > 1:
					if (!strspn($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-.', 1))
					{
						$this->scheme = null;
						$this->valid[__FUNCTION__] = false;
						return false;
					}

				case $len > 0:
					if (!strspn($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 0, 1))
					{
						$this->scheme = null;
						$this->valid[__FUNCTION__] = false;
						return false;
					}
			}
			$this->scheme = strtolower($scheme);
		}
		$this->valid[__FUNCTION__] = true;
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
		if (($userinfo_end = strrpos($authority, '@')) !== false)
		{
			$userinfo = substr($authority, 0, $userinfo_end);
			$authority = substr($authority, $userinfo_end + 1);
		}
		else
		{
			$userinfo = null;
		}

		if (($port_start = strpos($authority, ':')) !== false)
		{
			$port = substr($authority, $port_start + 1);
			$authority = substr($authority, 0, $port_start);
		}
		else
		{
			$port = null;
		}

		return $this->set_userinfo($userinfo) && $this->set_host($authority) && $this->set_port($port);
	}

	/**
	 * Set the userinfo.
	 *
	 * @param string $userinfo
	 * @return bool
	 */
	private function set_userinfo($userinfo)
	{
		if ($userinfo === null || $userinfo === '')
		{
			$this->userinfo = null;
		}
		else
		{
			$this->userinfo = $this->replace_invalid_with_pct_encoding($userinfo, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=:');
		}
		$this->valid[__FUNCTION__] = true;
		return true;
	}

	/**
	 * Set the host. Returns true on success, false on failure (if there are
	 * any invalid characters).
	 *
	 * @param string $host
	 * @return bool
	 */
	private function set_host($host)
	{
		if ($host === null || $host === '')
		{
			$this->host = null;
			$this->valid[__FUNCTION__] = true;
			return true;
		}
		elseif ($host[0] === '[' && substr($host, -1) === ']')
		{
			if (Net_IPv6::check_ipv6(substr($host, 1, -1)))
			{
				$this->host = $host;
				$this->valid[__FUNCTION__] = true;
				return true;
			}
			else
			{
				$this->host = null;
				$this->valid[__FUNCTION__] = false;
				return false;
			}
		}
		else
		{
			$this->host = $this->replace_invalid_with_pct_encoding($host, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=', self::lowercase);
			$this->valid[__FUNCTION__] = true;
			return true;
		}
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
			$this->valid[__FUNCTION__] = true;
			return true;
		}
		elseif (strspn($port, '0123456789') === strlen($port))
		{
			$this->port = (int) $port;
			$this->valid[__FUNCTION__] = true;
			return true;
		}
		else
		{
			$this->port = null;
			$this->valid[__FUNCTION__] = false;
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
			$this->valid[__FUNCTION__] = true;
			return true;
		}
		elseif (substr($path, 0, 2) === '//' && $this->userinfo === null && $this->host === null && $this->port === null)
		{
			$this->path = null;
			$this->valid[__FUNCTION__] = false;
			return false;
		}
		else
		{
			$this->path = $this->replace_invalid_with_pct_encoding($path, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=@/');
			if ($this->scheme !== null)
			{
				$this->path = $this->remove_dot_segments($this->path);
			}
			$this->valid[__FUNCTION__] = true;
			return true;
		}
	}

	/**
	 * Set the query.
	 *
	 * @param string $query
	 * @return bool
	 */
	private function set_query($query)
	{
		if ($query === null || $query === '')
		{
			$this->query = null;
		}
		else
		{
			$this->query = $this->replace_invalid_with_pct_encoding($query, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=:@/?');
		}
		$this->valid[__FUNCTION__] = true;
		return true;
	}

	/**
	 * Set the fragment.
	 *
	 * @param string $fragment
	 * @return bool
	 */
	private function set_fragment($fragment)
	{
		if ($fragment === null || $fragment === '')
		{
			$this->fragment = null;
		}
		else
		{
			$this->fragment = $this->replace_invalid_with_pct_encoding($fragment, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~!$&\'()*+,;=:@/?');
		}
		$this->valid[__FUNCTION__] = true;
		return true;
	}

	/**
	 * Get the complete IRI
	 *
	 * @return string
	 */
	private function get_iri()
	{
		$iri = '';
		if ($this->scheme !== null)
		{
			$iri .= $this->scheme . ':';
		}
		if (($authority = $this->authority) !== null)
		{
			$iri .= '//' . $authority;
		}
		if ($this->path !== null)
		{
			$iri .= $this->path;
		}
		if ($this->query !== null)
		{
			$iri .= '?' . $this->query;
		}
		if ($this->fragment !== null)
		{
			$iri .= '#' . $this->fragment;
		}

		if ($iri !== '')
		{
			return $iri;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Get the complete authority
	 *
	 * @return string
	 */
	private function get_authority()
	{
		$authority = '';
		if ($this->userinfo !== null)
		{
			$authority .= $this->userinfo . '@';
		}
		if ($this->host !== null)
		{
			$authority .= $this->host;
		}
		if ($this->port !== null)
		{
			$authority .= ':' . $this->port;
		}

		if ($authority !== '')
		{
			return $authority;
		}
		else
		{
			return null;
		}
	}
}

/**
 * Class to validate and to work with IPv6 addresses.
 *
 * This was originally based on the PEAR class of the same name, but has been
 * almost entirely rewritten.
 */
class Net_IPv6
{
	/**
	 * Removes any existing address prefix from an IPv6 address.
	 *
	 * @param string $ip The IPv6 address
	 * @return string The IPv6 address the without address prefix
	 */
	private static function remove_address_prefix($ip)
	{
		if (strpos($ip, '/') !== false)
		{
			list($addr, $nm) = explode('/', $ip, 2);
		}
		else
		{
			$addr = $ip;				
		}
		return $addr;
	}
	
	/**
	 * Returns any address prefix from an IPv5 address.
	 *
	 * @param string $ip The IPv6 address
	 * @return string The address prefix
	 */
	private static function get_address_prefix($ip)
	{
		if (strpos($ip, '/') !== false)
		{
			list($addr, $nm) = explode('/', $ip, 2);
		}
		else
		{
			$nm = '';				
		}
		return $nm;
	}

	/**
	 * Uncompresses an IPv6 address
	 *
	 * RFC 4291 allows you to compress concecutive zero pieces in an address to
	 * '::'. This method expects a valid IPv6 address and expands the '::' to
	 * the required number of zero pieces.
	 *
	 * Example:	 FF01::101  ->  FF01:0:0:0:0:0:0:101
	 *           ::1        ->  0:0:0:0:0:0:0:1
	 *
	 * @author Alexander Merz <alexander.merz@web.de>
	 * @author elfrink at introweb dot nl
	 * @author Josh Peck <jmp at joshpeck dot org>
	 * @copyright 2003-2005 The PHP Group
	 * @license http://www.opensource.org/licenses/bsd-license.php
	 * @param string $ip An IPv6 address
	 * @return string The uncompressed IPv6 address
	 */
	public static function uncompress($ip)
	{
		$netmask = self::get_address_prefix($ip);
		$uip = self::remove_address_prefix($ip);
		$c1 = -1;
		$c2 = -1;
		if (substr_count($uip, '::') === 1)
		{
			list($ip1, $ip2) = explode('::', $uip);
			if ($ip1 === '')
			{
				$c1 = -1;
			}
			else
			{
				$c1 = substr_count($ip1, ':');
			}
			if ($ip2 === '')
			{
				$c2 = -1;
			}
			else
			{
				$c2 = substr_count($ip2, ':');
			}
			if (strpos($ip2, '.') !== false)
			{
				$c2++;
			}
			// ::
			if ($c1 === -1 && $c2 === -1)
			{
				$uip = '0:0:0:0:0:0:0:0';
			}
			// ::xxx
			else if ($c1 === -1)
			{
				$fill = str_repeat('0:', 7 - $c2);
				$uip = str_replace('::', $fill, $uip);
			}
			// xxx::
			else if ($c2 === -1)
			{
				$fill = str_repeat(':0', 7 - $c1);
				$uip = str_replace('::', $fill, $uip);
			}
			// xxx::xxx
			else
			{
				$fill = ':' . str_repeat('0:', 6 - $c2 - $c1);
				$uip = str_replace('::', $fill, $uip);
			}
		}
		if ($netmask !== '')
		{
			$uip .= "/$netmask";
		}
		return $uip;
	}

	/**
	 * Compresses an IPv6 address
	 *
	 * RFC 4291 allows you to compress concecutive zero pieces in an address to
	 * '::'. This method expects a valid IPv6 address and compresses consecutive
	 * zero pieces to '::'.
	 *
	 * Example:	 FF01:0:0:0:0:0:0:101  ->  FF01::101
	 *           0:0:0:0:0:0:0:1       ->  ::1
	 *
	 * @see uncompress()
	 * @param string $ip An IPv6 address
	 * @return string The compressed IPv6 address
	 */
	public static function compress($ip)
	{
		// Prepare the IP to be compressed
		$ip = self::uncompress($ip);
		$netmask = self::get_address_prefix($ip);
		$ip = self::remove_address_prefix($ip);
		$ip_parts = self::split_v6_v4($ip);
		
		// Break up the IP into each seperate part
		$ipp = explode(':', $ip_parts[0]);
		
		// Initialise vars to count consecutive zero pieces
		$consecutive_zeros = 0;
		$max_consecutive_zeros = 0;
		for ($i = 0; $i < count($ipp); $i++)
		{
			// Normalise the number (this changes things like 01 to 0)
			$ipp[$i] = dechex(hexdec($ipp[$i]));
			
			// Count the zeros
			if ($ipp[$i] === '0')
			{
				$consecutive_zeros++;
			}
			elseif ($consecutive_zeros > $max_consecutive_zeros)
			{
				$consecutive_zeros_pos = $i - $consecutive_zeros;
				$max_consecutive_zeros = $consecutive_zeros;
				$consecutive_zeros = 0;
			}
		}
		if ($consecutive_zeros > $max_consecutive_zeros)
		{
			$consecutive_zeros_pos = $i - $consecutive_zeros;
			$max_consecutive_zeros = $consecutive_zeros;
			$consecutive_zeros = 0;
		}
		
		// Rebuild the IP
		if ($max_consecutive_zeros > 0)
		{
			$cip = '';
			for ($i = 0; $i < count($ipp); $i++)
			{
				// Add a : for the longest consecutive sequence, or :: if it's at the end
				if ($i === $consecutive_zeros_pos)
				{
					if ($i === count($ipp) - $max_consecutive_zeros)
					{
						$cip .= '::';
					}
					else
					{
						$cip .= ':';
					}
				}
				// Otherwise, just add the piece to the new output
				elseif ($i < $consecutive_zeros_pos || $i >= $consecutive_zeros_pos + $max_consecutive_zeros)
				{
					if ($i !== 0)
					{
						$cip .= ':';
					}
					$cip .= $ipp[$i];
				}
			}
		}
		// Cheat if we don't have any zero pieces
		else
		{
			$cip = implode(':', $ipp);
		}
		
		// Re-add any IPv4 part of the address
		if ($ip_parts[1] !== '')
		{
			$cip .= ":{$ip_parts[1]}";
		}
		// Re-add any netmask
		if ($netmask)
		{
			$cip .= "/$netmask";
		}
		return $cip;
	}

	/**
	 * Splits an IPv6 address into the IPv6 and IPv4 representation parts
	 *
	 * RFC 4291 allows you to represent the last two parts of an IPv6 address
	 * using the standard IPv4 representation
	 *
	 * Example:	 0:0:0:0:0:0:13.1.68.3
	 *           0:0:0:0:0:FFFF:129.144.52.38
	 *
	 * @param string $ip An IPv6 address
	 * @return array [0] contains the IPv6 represented part, and [1] the IPv4 represented part
	 */
	private static function split_v6_v4($ip)
	{
		$ip = self::remove_address_prefix($ip);
		if (strpos($ip, '.') !== false)
		{
			$pos = strrpos($ip, ':');
			$ipv6_part = substr($ip, 0, $pos);
			$ipv4_part = substr($ip, $pos + 1);
			return array($ipv6_part, $ipv4_part);
		}
		else
		{
			return array($ip, '');
		}
	}

	/**
	 * Checks an IPv6 address
	 *
	 * Checks if the given IP is a valid IPv6 address
	 *
	 * @param string $ip An IPv6 address
	 * @return bool true if $ip is a valid IPv6 address
	 */
	public static function check_ipv6($ip)
	{
		$ip = self::uncompress($ip);
		$ipPart = self::split_v6_v4($ip);
		$netmask = self::get_address_prefix($ip);
		$count = 0;
		if (!empty($ipPart[0]) && ($netmask === '' || (ctype_digit($netmask) && $netmask >= 0 && $netmask <= 128)))
		{
			$ipv6 = explode(':', $ipPart[0]);
			foreach ($ipv6 as $ipv6_part)
			{
				$dec = hexdec($ipv6_part);
				if ($dec >= 0 && $dec <= 0xFFFF && ctype_xdigit($ipv6_part))
				{
					$count++;
				}
			}
			if ($count === 8 && empty($ipPart[1]))
			{
				return true;
			}
			elseif ($count === 6 && !empty($ipPart[1]))
			{
				$ipv4 = explode('.', $ipPart[1]);
				$count = 0;
				foreach ($ipv4 as $ipv4_part)
				{
					if ($ipv4_part >= 0 && $ipv4_part <= 255 && ctype_digit($ipv4_part))
					{
						$count++;
					}
				}
				if ($count === 4)
				{
					return true;
				}
			}
		}
		return false;
	}
}

?>