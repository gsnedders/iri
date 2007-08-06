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
	
	public function __construct(IRI $base, $relative)
	{
		// No traditional type-hinting in PHP, so type cast $relative
		// to string.
		$relative = (string) $relative;
		
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
					if ($relative_len < $position + 1
						&& $relative[$position + 1] !== '/'
						&& $base->protocol() == substr($relative, 0, $position)
						&& $base->is_hierarchical())
					{
						$relative = substr($relative, $position + 1);
					}
					else
					{
						$absolute = true;
					}
				}
			}
		}
	}
}

?>