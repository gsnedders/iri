<?php
/*
 * Copyright (C) 2004, 2007 Apple Inc.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY APPLE INC. ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL APPLE COMPUTER, INC. OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 * OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 
 */

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
	private $is_valid = true;
	
	/**
	 * Position of end of scheme
	 *
	 * @var int
	 */
	private $scheme_end_pos;
	
	/**
	 * Position of start of username
	 *
	 * @var int
	 */
	private $user_start_pos;
	
	/**
	 * Position of end of username
	 *
	 * @var int
	 */
	private $user_end_pos;
	
	/**
	 * Position of end of password
	 *
	 * @var int
	 */
	private $password_end_pos;
	
	/**
	 * Position of end of host
	 *
	 * @var int
	 */
	private $host_end_pos;
	
	/**
	 * Position of end of port
	 *
	 * @var int
	 */
	private $port_end_pos;
	
	/**
	 * Position of end of path
	 *
	 * @var int
	 */
	private $path_end_pos;
	
	/**
	 * Position of end of query
	 *
	 * @var int
	 */
	private $query_end_pos;
	
	/**
	 * Position of end of fragment
	 *
	 * @var int
	 */
	private $fragment_end_pos;
	
	/**
	 * __construct() is private as the class should be initated with new_iri()
	 * or new_relative_iri()
	 *
	 * @see IRI::new_iri()
	 * @see IRI::new_relative_iri()
	 */
	private function __construct()
	{
	}
	
	/**
	 * Create a new IRI from a string
	 *
	 * @param string $iri
	 * @return IRI
	 */
	public static function new_iri($iri)
	{
		$return = new IRI;
		return $return->parse((string) $iri);
	}
	
	/**
	 * Create a new IRI from a base IRI and a relative string
	 *
	 * @param IRI $base
	 * @param IRI $relative
	 * @return IRI
	 */
	public static function new_relative_iri(IRI $base, $relative)
	{
		$return = new IRI;
		return $return->init($base, (string) $relative);
	}
	
	/**
	 * Initalise the object if we have a base IRI and a relative one (merge the
	 * two IRIs together, etc.)
	 *
	 * @param IRI $base
	 * @param IRI $relative
	 * @return IRI
	 */
	private function init(IRI $base, $relative)
	{
		// No traditional type-hinting in PHP, so type cast $relative
		// to string.
		$relative = (string) $relative;
		
		// Allow at least absolute IRIs to resolve against an empty IRI.
		if (!$base->is_valid() && !$base->is_empty())
		{
			$this->is_valid = false;
			return;
		}
		
		// For compatibility with Win IE, we must treat backslashes as if
		// they were slashes, as long as we're not dealing with the
		// javascript: schema.
		if (substr($relative, 0, 11) !== 'javascript:')
		{
			$relative = str_replace('\\', '/', $relative);
		}
		
		// Workaround for leading/trailing whitespace
		$relative = trim($relative, ' ');
		
		// According to the RFC, the reference should be interpreted as an
		// absolute URI if possible, using the "leftmost, longest"
		// algorithm. If the URI reference is absolute it will have a
		// scheme, meaning that it will have a colon before the first
		// non-scheme element.
		$absolute = false;
		$relative_len = strlen($relative);
		if ($relative !== '' && ($position = strspn($relative, self::scheme_first_char)))
		{
			if ($relative_len < $position)
			{
				$position += strspn($relative, self::scheme, $position);
				if ($relative_len < $position && $relative[$position] === ':')
				{
					$position++;
					if ($relative_len < $position
						&& $relative[$position] !== '/'
						&& $base->protocol() == substr($relative, 0, $position - 1)
						&& $base->is_hierarchical())
					{
						$relative = substr($relative, $position);
					}
					else
					{
						$absolute = true;
					}
				}
			}
		}
		
		if ($absolute)
		{
			$this->parse($relative);
		}
		// If the base is empty or opaque (e.g. data: or javascript:), then the
		// IRI is invalid unless the relative IRI is a single fragment.
		elseif (!$base->is_hierarchical())
		{
			if (isset($relative[0]) && $relative[0] === '#')
			{
				$this->parse(substr($base->iri_string(), 0, $base->query_end_pos()) . $relative);
			}
			else
			{
				$this->is_valid = false;
				return;
			}
		}
		// The reference must be empty - the RFC says this is a reference to the
		// same document.
		elseif ($relative === '')
		{
			return $base;
		}
		else
		{
			switch ($relative[0])
			{
				// Must be fragment-only reference
				case '#':
					$this->parse(substr($base->iri_string(), 0, $base->query_end_pos()) . $relative);
					break;
				
				// Query-only reference
				case '?':
					$this->parse(substr($base->iri_string(), 0, $base->path_end_pos()) . $relative);
					break;
				
				case '/':
					// Authority
					if (isset($relative[1]) && $relative[1] === '/')
					{
						$this->parse(substr($base->iri_string(), 0, $base->scheme_end_pos() + 1) . $relative);
					}
					// Absolute path
					else
					{
						$this->parse(substr($base->iri_string(), 0, $base->port_end_pos()) . $relative);
					}
					break;
				
				// Relative Path
				default:
					if ($base->scheme_end_pos() + 1 !== $base->port_end_pos() && $base->port_end_pos() === $base->path_end_pos())
					{
						$this->parse(substr($base->iri_string(), 0, $base->port_end_pos()) . '/' . $relative);
					}
					else
					{
						$this->parse(substr($base->iri_string(), 0, $base->port_end_pos()) . dirname('/' . substr($base->iri_string(), $base->port_end_pos(), $base->path_end_pos()) . '.') . $relative);
					}
					break;
			}
		}
		return $this;
	}
	
	private function parse($iri)
	{
		// Valid IRI must be non-empty, and must start with an
		// alphabetic character.
		if ($iri === '' || !strspn($iri, self::scheme_first_char, 0, 1))
		{
			$this->is_valid = false;
			return;
		}
		
		$scheme_end = 1 + strspn($iri, self::scheme, 1);
		
		if (!isset($iri[$scheme_end]) || $iri[$scheme_end] !== ':')
		{
			$this->is_valid = false;
			return;
		}
		
		$user_start = $scheme_end + 1;
		$user_end = null;
		$password_start = null;
		$password_end = null;
		$host_start = null;
		$host_end = null;
		$port_start = null;
		$port_end = null;
		
		$hierarchical = (bool) (isset($iri[$scheme_end + 1]) && $iri[$scheme_end + 1] === '/');
	}
}

?>