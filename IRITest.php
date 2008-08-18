<?php

/**
 * IRI test cases
 *
 * Copyright (c) 2008 Geoffrey Sneddon.
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
 * @copyright 2008 Geoffrey Sneddon
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @link http://hg.gsnedders.com/iri/
 *
 */

require_once 'PHPUnit/Framework.php';
require_once 'iri.php';
 
class IRITest extends PHPUnit_Framework_TestCase
{
	public static function rfc3986_tests()
	{
		return array(
			// Normal
			array('g:h', 'g:h'),
			array('g', 'http://a/b/c/g'),
			array('./g', 'http://a/b/c/g'),
			array('g/', 'http://a/b/c/g/'),
			array('/g', 'http://a/g'),
			array('//g', 'http://g'),
			array('?y', 'http://a/b/c/d;p?y'),
			array('g?y', 'http://a/b/c/g?y'),
			array('#s', 'http://a/b/c/d;p?q#s'),
			array('g#s', 'http://a/b/c/g#s'),
			array('g?y#s', 'http://a/b/c/g?y#s'),
			array(';x', 'http://a/b/c/;x'),
			array('g;x', 'http://a/b/c/g;x'),
			array('g;x?y#s', 'http://a/b/c/g;x?y#s'),
			array('', 'http://a/b/c/d;p?q'),
			array('.', 'http://a/b/c/'),
			array('./', 'http://a/b/c/'),
			array('..', 'http://a/b/'),
			array('../', 'http://a/b/'),
			array('../g', 'http://a/b/g'),
			array('../..', 'http://a'),
			array('../../', 'http://a'),
			array('../../g', 'http://a/g'),
			// Abnormal
			array('../../../g', 'http://a/g'),
			array('../../../../g', 'http://a/g'),
			array('/./g', 'http://a/g'),
			array('/../g', 'http://a/g'),
			array('g.', 'http://a/b/c/g.'),
			array('.g', 'http://a/b/c/.g'),
			array('g..', 'http://a/b/c/g..'),
			array('..g', 'http://a/b/c/..g'),
			array('./../g', 'http://a/b/g'),
			array('./g/.', 'http://a/b/c/g/'),
			array('g/./h', 'http://a/b/c/g/h'),
			array('g/../h', 'http://a/b/c/h'),
			array('g;x=1/./y', 'http://a/b/c/g;x=1/y'),
			array('g;x=1/../y', 'http://a/b/c/y'),
			array('g?y/./x', 'http://a/b/c/g?y/./x'),
			array('g?y/../x', 'http://a/b/c/g?y/../x'),
			array('g#s/./x', 'http://a/b/c/g#s/./x'),
			array('g#s/../x', 'http://a/b/c/g#s/../x'),
			array('http:g', 'http:g'),
		);
	}
 
	/**
	 * @dataProvider rfc3986_tests
	 */
	public function testRFC3986($relative, $expected)
	{
		$base = new IRI('http://a/b/c/d;p?q');
		$this->assertEquals($expected, IRI::absolutize($base, $relative)->iri);
	}
	
	public static function sp_tests()
	{
		return array(
			array('http://a/b/c/d', 'f%0o', 'http://a/b/c/f%250o'),
			array('http://a/b/', 'c', 'http://a/b/c'),
			array('http://a/', 'b', 'http://a/b'),
			array('http://a/', '/b', 'http://a/b'),
			array('http://a/b', 'c', 'http://a/c'),
			array('http://a/b/', "c\x0Ad", 'http://a/b/c%0Ad'),
			array('http://a/b/', "c\x0A\x0B", 'http://a/b/c%0A%0B'),
			array('http://a/b/c', '//0', 'http://0'),
			array('http://a/b/c', '0', 'http://a/b/0'),
			array('http://a/b/c', '?0', 'http://a/b/c?0'),
			array('http://a/b/c', '#0', 'http://a/b/c#0'),
			array('http://0/b/c', 'd', 'http://0/b/d'),
			array('http://a/b/c?0', 'd', 'http://a/b/d'),
			array('http://a/b/c#0', 'd', 'http://a/b/d'),
			array('http://example.com', '//example.net', 'http://example.net'),
			array('http:g', 'a', 'http:a'),
		);
	}
 
	/**
	 * @dataProvider sp_tests
	 */
	public function testSP($base, $relative, $expected)
	{
		$base = new IRI($base);
		$this->assertEquals($expected, IRI::absolutize($base, $relative)->iri);
	}
	
	public static function normalization_tests()
	{
		return array(
			array('example://a/b/c/%7Bfoo%7D', 'example://a/b/c/%7Bfoo%7D'),
			array('eXAMPLE://a/./b/../b/%63/%7bfoo%7d', 'example://a/b/c/%7Bfoo%7D'),
			array('example://%61/b/c/%7Bfoo%7D', 'example://a/b/c/%7Bfoo%7D'),
			array('example://%41/b/c/%7Bfoo%7D', 'example://a/b/c/%7Bfoo%7D'),
			array('HTTP://EXAMPLE.com/', 'http://example.com'),
			array('http://example.com/', 'http://example.com'),
			array('http://example.com:', 'http://example.com'),
			array('http://example.com:80', 'http://example.com'),
			array('http://@example.com', 'http://@example.com'),
			array('http://example.com?', 'http://example.com?'),
			array('http://example.com#', 'http://example.com#'),
			array('https://example.com/', 'https://example.com'),
			array('https://example.com:', 'https://example.com'),
			array('https://example.com:80', 'https://example.com'),
			array('https://@example.com', 'https://@example.com'),
			array('https://example.com?', 'https://example.com?'),
			array('https://example.com#', 'https://example.com#'),
			array('file://localhost/foobar', 'file:/foobar'),
		);
	}
 
	/**
	 * @dataProvider normalization_tests
	 */
	public function testStringNormalization($input, $output)
	{
		$input = new IRI($input);
		$this->assertEquals($output, $input->iri);
	}
 
	/**
	 * @dataProvider normalization_tests
	 */
	public function testObjectNormalization($input, $output)
	{
		$input = new IRI($input);
		$output = new IRI($output);
		$this->assertEquals($output, $input);
	}
}

?>