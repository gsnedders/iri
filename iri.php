<?php
/*
 * Copyright (C) 2004 Apple Computer, Inc.  All rights reserved.
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
 * THIS SOFTWARE IS PROVIDED BY APPLE COMPUTER, INC. ``AS IS'' AND ANY
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
	const scheme_first_char = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	const scheme = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-.';
	private $is_valid = true;
	
	private function __construct()
	{
	}
	
	public static function create()
	{
		$args = func_get_args();
		switch (count($args))
		{
			case 1:
				if (is_string($args[0]))
				{
					$return = new IRI;
					return $return->parse($args[0]);
				}
				break;
			
			case 2:
				if ($args[0] instanceof IRI && is_string($args[1]))
				{
					$return = new IRI;
					return $return->init($args[0], $args[1]);
				}
		}
		throw new Exception('Invalid number of arguments or invalid argument types for IRI::create()');
	}
	
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
			self::parse($relative);
		}
		// If the base is empty or opaque (e.g. data: or javascript:), then the
		// IRI is invalid.
		elseif (!$base->is_hierarchical())
		{
			$this->is_valid = false;
		}
		// the reference must be empty - the RFC says this is a reference to the
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
					self::parse(substr($base->iri_string(), 0, $base->query_end_pos()) . $relative);
					break;
				
				// Query-only reference
				case '?':
					self::parse(substr($base->iri_string(), 0, $base->path_end_pos()) . $relative);
					break;
				
				case '/':
					// Authority
					if (isset($relative[1]) && $relative[1] === '/')
					{
						self::parse(substr($base->iri_string(), 0, $base->scheme_end_pos() + 1) . $relative);
					}
					// Absolute path
					else
					{
						self::parse(substr($base->iri_string(), 0, $base->port_end_pos()) . $relative);
					}
					break;
			}
		}
	}
}

?>