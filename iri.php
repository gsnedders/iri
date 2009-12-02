<?php

/**
 * IRI parser/serialiser/normaliser
 *
 * Copyright (c) 2007-2009, Geoffrey Sneddon and Steve Minutillo.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *  * Neither the name of the SimplePie Team nor the names of its contributors
 *       may be used to endorse or promote products derived from this software
 *       without specific prior written permission.
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
 * @copyright 2007-2009 Geoffrey Sneddon and Steve Minutillo
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
     * Normalization database
     *
     * Each key is the scheme, each value is an array with each key as the IRI
     * part and value as the default value for that part.
     */
    private $normalization = array(
        'acap' => array(
            'port' => 674
        ),
        'dict' => array(
            'port' => 2628
        ),
        'file' => array(
            'host' => 'localhost'
        ),
        'http' => array(
            'port' => 80,
            'path' => '/'
        ),
        'https' => array(
            'port' => 443,
            'path' => '/'
        ),
    );

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
            call_user_func(array($this, 'set_' . $name), $value);
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
        if (!$this->is_valid())
        {
            return false;
        }
        elseif (method_exists($this, 'get_' . $name))
        {
            $return = call_user_func(array($this, 'get_' . $name));
        }
        elseif (isset($this->$name))
        {
            $return = $this->$name;
        }
        else
        {
            $return = null;
        }
        
        if ($return === null && isset($this->normalization[$this->scheme][$name]))
        {
            return $this->normalization[$this->scheme][$name];
        }
        else
        {
            return $return;
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
        if (method_exists($this, 'get_' . $name) || isset($this->$name))
        {
            return true;
        }
        else
        {
            return false;
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
     * @param IRI|string $relative Relative IRI
     * @return IRI
     */
    public static function absolutize(IRI $base, $relative)
    {
        if (!($relative instanceof IRI))
        {
            $relative = new IRI((string) $relative);
        }
        if ($relative->iri !== '')
        {
            if ($relative->scheme !== null)
            {
                $target = clone $relative;
            }
            elseif ($base->iri !== null)
            {
                if ($relative->iauthority !== null)
                {
                    $target = clone $relative;
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
     * Create a new IRI object by creating a relative IRI from two IRIs
     *
     * @param IRI $base Base IRI
     * @param IRI $destination Destination IRI
     * @return IRI
     */
    public static function build_relative(IRI $base, IRI $destination)
    {
    }

    /**
     * Parse an IRI into scheme/authority/path/query/fragment segments
     *
     * @param string $iri
     * @return array
     */
    private function parse_iri($iri)
    {
        $iri = trim($iri, "\x20\x09\x0A\x0C\x0D");
        static $cache = array();
        if (isset($cache[$iri]))
        {
            return $cache[$iri];
        }
        elseif (preg_match('/^((?P<scheme>[^:\/?#]+):)?(\/\/(?P<authority>[^\/?#]*))?(?P<path>[^?#]*)(\?(?P<query>[^#]*))?(#(?P<fragment>.*))?$/', $iri, $match))
        {
            if (!isset($match[1]) || $match[1] === '')
            {
                $match['scheme'] = null;
            }
            if (!isset($match[3]) || $match[3] === '')
            {
                $match['authority'] = null;
            }
            if (!isset($match[5]) || $match[5] === '')
            {
                $match['path'] = null;
            }
            if (!isset($match[6]) || $match[6] === '')
            {
                $match['query'] = null;
            }
            if (!isset($match[8]) || $match[8] === '')
            {
                $match['fragment'] = null;
            }
            return $cache[$iri] = $match;
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
     * @param string $extra_chars Valid characters not in iunreserved or
     *                            iprivate (this is ASCII-only)
     * @param bool $iprivate Allow iprivate
     * @return string
     */
    private function replace_invalid_with_pct_encoding($string, $extra_chars, $iprivate = false)
    {
        // Replace invalid percent characters
        $string = preg_replace('/%($|[^A-Fa-f0-9]|[A-Fa-f0-9][^A-Fa-f0-9])/', '%25\1', $string);
        
        // Normalize as many pct-encoded sections as possible
        $string = preg_replace_callback('/(?:%[A-Fa-f0-9]{2})+/', array(&$this, 'remove_iunreserved_percent_encoded'), $string);
        
        // Add unreserved and % to $extra_chars (the latter is safe because all
        // pct-encoded sections are now valid).
        $extra_chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~%';
        
        // Now replace any bytes that aren't allowed with their pct-encoded versions
        $position = 0;
        $strlen = strlen($string);
        while (($position += strspn($string, $extra_chars, $position)) < $strlen)
        {
            $value = ord($string[$position]);
            
            // Start position
            $start = $position;
        
            // By default we are valid
            $valid = true;
            
            // No one byte sequences are valid due to the while.
            // Two byte sequence:
            if (($value & 0xE0) === 0xC0)
            {
                $character = ($value & 0x1F) << 6;
                $length = 2;
                $remaining = 1;
            }
            // Three byte sequence:
            elseif (($value & 0xF0) === 0xE0)
            {
                $character = ($value & 0x0F) << 12;
                $length = 3;
                $remaining = 2;
            }
            // Four byte sequence:
            elseif (($value & 0xF8) === 0xF0)
            {
                $character = ($value & 0x07) << 18;
                $length = 4;
                $remaining = 3;
            }
            // Invalid byte:
            else
            {
                $valid = false;
                $length = 1;
                $remaining = 0;
            }
            
            if ($remaining)
            {
                if ($position + $length <= $strlen)
                {
                    for ($position++; $remaining; $position++)
                    {
                        $value = ord($string[$position]);
                        
                        // Check that the byte is valid, then add it to the character:
                        if (($value & 0xC0) === 0x80)
                        {
                            $character |= ($value & 0x3F) << (--$remaining * 6);
                        }
                        // If it is invalid, count the sequence as invalid and reprocess the current byte:
                        else
                        {
                            $valid = false;
                            $position--;
                            break;
                        }
                    }
                }
                else
                {
                    $position = $strlen - 1;
                    $valid = false;
                }
            }
                
            // Percent encode anything invalid or not in ucschar
            if (
                // Invalid sequences
                !$valid
                // Non-shortest form sequences are invalid
                || $length > 1 && $character <= 0x7F
                || $length > 2 && $character <= 0x7FF
                || $length > 3 && $character <= 0xFFFF
                // Outside of range of ucschar codepoints
                // Noncharacters
                || ($character & 0xFFFE) === 0xFFFE
                || $character >= 0xFDD0 && $character <= 0xFDEF
                || (
                    // Everything else not in ucschar
                       $character > 0xD7FF && $character < 0xF900
                    || $character < 0xA0
                    || $character > 0xEFFFD
                )
                && (
                    // Everything not in iprivate, if it applies
                       !$iprivate
                    || $character < 0xE000
                    || $character > 0x10FFFD
                )
            )
            {
                // If we were a character, pretend we weren't, but rather an error.
                if ($valid)
                    $position--;
                    
                for ($j = $start; $j <= $position; $j++)
                {
                    $string = substr_replace($string, sprintf('%%%02X', ord($string[$j])), $j, 1);
                    $j += 2;
                    $position += 2;
                    $strlen += 2;
                }
            }
        }
        
        return $string;
    }

    /**
     * Callback function for preg_replace_callback.
     *
     * Removes sequences of percent encoded bytes that represent UTF-8
     * encoded characters in iunreserved
     *
     * @param array $match PCRE match
     * @return string Replacement
     */
    private function remove_iunreserved_percent_encoded($match)
    {
        // As we just have valid percent encoded sequences we can just explode
        // and ignore the first member of the returned array (an empty string).
        $bytes = explode('%', $match[0]);
        
        // Initialize the new string (this is what will be returned) and that
        // there are no bytes remaining in the current sequence (unsurprising
        // at the first byte!).
        $string = '';
        $remaining = 0;
        
        // Loop over each and every byte, and set $value to its value
        for ($i = 1, $len = count($bytes); $i < $len; $i++)
        {
            $value = hexdec($bytes[$i]);
            
            // If we're the first byte of sequence:
            if (!$remaining)
            {
                // Start position
                $start = $i;
                
                // By default we are valid
                $valid = true;
                
                // One byte sequence:
                if ($value <= 0x7F)
                {
                    $character = $value;
                    $length = 1;
                }
                // Two byte sequence:
                elseif (($value & 0xE0) === 0xC0)
                {
                    $character = ($value & 0x1F) << 6;
                    $length = 2;
                    $remaining = 1;
                }
                // Three byte sequence:
                elseif (($value & 0xF0) === 0xE0)
                {
                    $character = ($value & 0x0F) << 12;
                    $length = 3;
                    $remaining = 2;
                }
                // Four byte sequence:
                elseif (($value & 0xF8) === 0xF0)
                {
                    $character = ($value & 0x07) << 18;
                    $length = 4;
                    $remaining = 3;
                }
                // Invalid byte:
                else
                {
                    $valid = false;
                    $remaining = 0;
                }
            }
            // Continuation byte:
            else
            {
                // Check that the byte is valid, then add it to the character:
                if (($value & 0xC0) === 0x80)
                {
                    $remaining--;
                    $character |= ($value & 0x3F) << ($remaining * 6);
                }
                // If it is invalid, count the sequence as invalid and reprocess the current byte as the start of a sequence:
                else
                {
                    $valid = false;
                    $remaining = 0;
                    $i--;
                }
            }
            
            // If we've reached the end of the current byte sequence, append it to Unicode::$data
            if (!$remaining)
            {
                // Percent encode anything invalid or not in iunreserved
                if (
                    // Invalid sequences
                    !$valid
                    // Non-shortest form sequences are invalid
                    || $length > 1 && $character <= 0x7F
                    || $length > 2 && $character <= 0x7FF
                    || $length > 3 && $character <= 0xFFFF
                    // Outside of range of iunreserved codepoints
                    || $character < 0x2D
                    || $character > 0xEFFFD
                    // Noncharacters
                    || ($character & 0xFFFE) === 0xFFFE
                    || $character >= 0xFDD0 && $character <= 0xFDEF
                    // Everything else not in iunreserved (this is all BMP)
                    || $character === 0x2F
                    || $character > 0x39 && $character < 0x41
                    || $character > 0x5A && $character < 0x61
                    || $character > 0x7A && $character < 0x7E
                    || $character > 0x7E && $character < 0xA0
                    || $character > 0xD7FF && $character < 0xF900
                )
                {
                    for ($j = $start; $j <= $i; $j++)
                    {
                        $string .= '%' . strtoupper($bytes[$j]);
                    }
                }
                else
                {
                    for ($j = $start; $j <= $i; $j++)
                    {
                        $string .= chr(hexdec($bytes[$j]));
                    }
                }
            }
        }
        
        // If we have any bytes left over they are invalid (i.e., we are
        // mid-way through a multi-byte sequence)
        if ($remaining)
        {
            for ($j = $start; $j < $len; $j++)
            {
                $string .= '%' . strtoupper($bytes[$j]);
            }
        }
        
        return $string;
    }

    /**
     * Check if the object represents a valid IRI. This needs to be done on each
     * call as some things change depending on another part of the IRI.
     *
     * @return bool
     */
    public function is_valid()
    {
        if ($this->scheme !== null)
        {
            $len = strlen($this->scheme);
            switch (true)
            {
                case $len > 1:
                    if (!strspn($this->scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-.', 1))
                    {
                        return false;
                    }

                case $len > 0:
                    if (!strspn($this->scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 0, 1))
                    {
                        return false;
                    }
                    break;
                
                case $len === 0:
                    return false;
            }
        }
        
        if ($this->host !== null && substr($this->host, 0, 1) === '[' && substr($this->host, -1) === ']' && !filter_var(substr($this->host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        {
            return false;
        }
        
        if ($this->port !== null && !ctype_digit($this->port))
        {
            return false;
        }
        
        if ($this->path !== null && (
            substr($this->path, 0, 2) === '//' && $this->get_iauthority() === null
            || substr($this->path, 0, 1) !== '/' && $this->path !== '' && $this->get_iauthority() !== null
            ))
        {
            return false;
        }
        
        return true;
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
        if ($scheme === null)
        {
            $this->scheme = null;
        }
        elseif (
               !($scheme = (string) $scheme)
            || !isset($scheme[0])
            || $scheme[0] < 'A'
            || $scheme[0] > 'Z' && $scheme[0] < 'a'
            || $scheme[0] > 'z'
            || strspn($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-.') !== strlen($scheme)
        )
        {
            $this->scheme = null;
            return false;
        }
        else
        {
            $this->scheme = strtolower($scheme);
        }
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
        if (($port_start = strpos($authority, ':', strpos($authority, ']'))) !== false)
        {
            if (($port = substr($authority, $port_start + 1)) === false)
            {
                $port = null;
            }
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
        if ($userinfo === null)
        {
            $this->userinfo = null;
        }
        else
        {
            $this->userinfo = $this->replace_invalid_with_pct_encoding($userinfo, '!$&\'()*+,;=:');
            
            if (isset($this->normalization[$this->scheme]['userinfo']) && $this->userinfo === $this->normalization[$this->scheme]['userinfo'])
            {
                $this->userinfo = null;
            }
        }
        
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
        if ($host === null)
        {
            $this->host = null;
            return true;
        }
        elseif (substr($host, 0, 1) === '[' && substr($host, -1) === ']')
        {
            if (filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
            {
                $this->host = '[' . Net_IPv6::compress(substr($host, 1, -1)) . ']';
            }
            else
            {
                $this->host = null;
                return false;
            }
        }
        else
        {
            $host = $this->replace_invalid_with_pct_encoding($host, '!$&\'()*+,;=');
            
            // Lowercase, but ignore pct-encoded sections (as they should
            // remain uppercase). This must be done after the previous step
            // as that can add unescaped characters.
            static $important_chars;
            if (!$important_chars)
                $important_chars = implode('', range('A', 'Z')) . '%';
            
            $position = 0;
            $strlen = strlen($host);
            while (($position += strcspn($host, $important_chars, $position)) < $strlen)
            {
                if ($host[$position] === '%')
                {
                    $position += 3;
                }
                else
                {
                    $host[$position] = strtolower($host[$position]);
                    $position++;
                }
            }
            
            $this->host = $host;
        }
        
        if (isset($this->normalization[$this->scheme]['host']) && $this->host === $this->normalization[$this->scheme]['host'])
        {
            $this->host = null;
        }
        
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
        if ($port === null)
        {
            $this->port = null;
            return true;
        }
        elseif (ctype_digit($port))
        {
            $this->port = (int) $port;
            if (isset($this->normalization[$this->scheme]['port']) && $this->port === $this->normalization[$this->scheme]['port'])
            {
                $this->port = null;
            }
            return true;
        }
        else
        {
            $this->port = null;
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
        if ($path === null)
        {
            $this->path = null;
            return true;
        }
        else
        {
            $path = explode('/', $path);
            $this->path = '';
            foreach ($path as $segment)
            {
                $this->path .= $this->replace_invalid_with_pct_encoding($segment, '!$&\'()*+,;=@');
                $this->path .= '/';
            }
            $this->path = substr($this->path, 0, -1);
            if ($this->scheme !== null)
            {
                $this->path = $this->remove_dot_segments($this->path);
            }
            if (isset($this->normalization[$this->scheme]['path']) && $this->path === $this->normalization[$this->scheme]['path'])
            {
                $this->path = null;
            }
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
        if ($query === null)
        {
            $this->query = null;
        }
        else
        {
            $this->query = $this->replace_invalid_with_pct_encoding($query, '!$&\'()*+,;=:@/?', true);
            if (isset($this->normalization[$this->scheme]['query']) && $this->query === $this->normalization[$this->scheme]['query'])
            {
                $this->query = null;
            }
        }
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
        if ($fragment === null)
        {
            $this->fragment = null;
        }
        else
        {
            $this->fragment = $this->replace_invalid_with_pct_encoding($fragment, '!$&\'()*+,;=:@/?');
            if (isset($this->normalization[$this->scheme]['fragment']) && $this->fragment === $this->normalization[$this->scheme]['fragment'])
            {
                $this->fragment = null;
            }
        }
        return true;
    }

    /**
     * Convert an IRI to a URI (or parts thereof)
     *
     * @return string
     */
    private function to_uri($string)
    {
        static $non_ascii;
        if (!$non_ascii)
            $non_ascii = implode('', range("\x80", "\xFF"));
        
        $position = 0;
        $strlen = strlen($string);
        while (($position += strcspn($string, $non_ascii, $position)) < $strlen)
        {
            $string = substr_replace($string, sprintf('%%%02X', ord($string[$position])), $position, 1);
            $position += 3;
            $strlen += 2;
        }
        
        return $string;
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
        if (($iauthority = $this->iauthority) !== null)
        {
            $iri .= '//' . $iauthority;
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
     * Get the complete URI
     *
     * @return string
     */
    private function get_uri()
    {
        $iri = $this->iri;
        if (is_string($iri))
            return $this->to_uri($iri);
        else
            return $iri;
    }

    /**
     * Get the complete iauthority
     *
     * @return string
     */
    private function get_iauthority()
    {
        $iauthority = '';
        if ($this->userinfo !== null)
        {
            $iauthority .= $this->userinfo . '@';
        }
        if ($this->host !== null)
        {
            $iauthority .= $this->host;
        }
        if ($this->port !== null)
        {
            $iauthority .= ':' . $this->port;
        }

        if ($this->userinfo !== null || $this->host !== null || $this->port !== null)
        {
            return $iauthority;
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
        $iauthority = $this->iauthority;
        if (is_string($iauthority))
            return $this->to_uri($iauthority);
        else
            return $iauthority;
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
     * Example:  FF01::101   ->  FF01:0:0:0:0:0:0:101
     *           ::1         ->  0:0:0:0:0:0:0:1
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
     * Example:  FF01:0:0:0:0:0:0:101   ->  FF01::101
     *           0:0:0:0:0:0:0:1        ->  ::1
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
     * Example:  0:0:0:0:0:0:13.1.68.3
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
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }
}

?>