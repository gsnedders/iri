<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file contains the implementation of the Net_IPv6 class
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to the New BSD license, that is
 * available through the world-wide-web at http://www.opensource.org/licenses/bsd-license.php
 * If you did not receive a copy of the new BSDlicense and are unable
 * to obtain it through the world-wide-web, please send a note to
 * license@php.net so we can mail you a copy immediately
 *
 * @category Net
 * @package Net_IPv6
 * @author Alexander Merz <alexander.merz@web.de>
 * @copyright 2003-2005 The PHP Group
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @version CVS: $Id: IPv6.php,v 1.14 2006/02/09 15:13:06 alexmerz Exp $
 * @link http://pear.php.net/package/Net_IPv6
 */

// {{{ Net_IPv6

/**
 * Class to validate and to work with IPv6 addresses.
 *
 * @category Net
 * @package Net_IPv6
 * @author Alexander Merz <alexander.merz@web.de>
 * @copyright 2003-2005 The PHP Group
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @version CVS: $Id: IPv6.php,v 1.14 2006/02/09 15:13:06 alexmerz Exp $
 * @link http://pear.php.net/package/Net_IPv6
 * @author elfrink at introweb dot nl
 * @author Josh Peck <jmp at joshpeck dot org>
 */
class Net_IPv6 {

    // {{{ removeNetmaskBits()

    /**
     * Removes a possible existing netmask specification at an IP addresse.
     *
     * @param String $ip the (compressed) IP as Hex representation
     * @return String the IP without netmask length
     * @since 1.1.0
     * @access public
     * @static
     */
    function removeNetmaskSpec($ip) {
        if(false !== strpos($ip, '/')) {
            list($addr, $nm) = explode('/', $ip);
        } else {
            $addr = $ip;                
        }
        return $addr;
    }

    // }}}
    // {{{ Uncompress()

    /**
     * Uncompresses an IPv6 adress
     *
     * RFC 2373 allows you to compress zeros in an adress to '::'. This
     * function expects an valid IPv6 adress and expands the '::' to
     * the required zeros.
     *
     * Example:  FF01::101	->  FF01:0:0:0:0:0:0:101
     *           ::1        ->  0:0:0:0:0:0:0:1
     *
     * @access public
     * @see Compress()
     * @static
     * @param string $ip	a valid IPv6-adress (hex format)
     * @return string	the uncompressed IPv6-adress (hex format)
	 */
    function Uncompress($ip) {
        $uip = Net_IPv6::removeNetmaskSpec($ip);
        $c1 = -1;
        $c2 = -1;
        if (false !== strpos($ip, '::') ) {
            list($ip1, $ip2) = explode('::', $ip);
            if(""==$ip1) {
                $c1 = -1;
            } else {
               	$pos = 0;
                if(0 < ($pos = substr_count($ip1, ':'))) {
                    $c1 = $pos;
                } else {
                    $c1 = 0;
                }
            }
            if(""==$ip2) {
                $c2 = -1;
            } else {
                $pos = 0;
                if(0 < ($pos = substr_count($ip2, ':'))) {
                    $c2 = $pos;
                } else {
                    $c2 = 0;
                }
            }
            if(strstr($ip2, '.')) {
                $c2++;
            }
            if(-1 == $c1 && -1 == $c2) { // ::
                $uip = "0:0:0:0:0:0:0:0";
            } else if(-1==$c1) {              // ::xxx
                $fill = str_repeat('0:', 7-$c2);
                $uip =  str_replace('::', $fill, $uip);
            } else if(-1==$c2) {              // xxx::
                $fill = str_repeat(':0', 7-$c1);
                $uip =  str_replace('::', $fill, $uip);
            } else {                          // xxx::xxx
                $fill = str_repeat(':0:', 6-$c2-$c1);
                $uip =  str_replace('::', $fill, $uip);
                $uip =  str_replace('::', ':', $uip);
            }
        }
        return $uip;
    }

    // }}}
    // {{{ SplitV64()

    /**
     * Splits an IPv6 adress into the IPv6 and a possible IPv4 part
     *
     * RFC 2373 allows you to note the last two parts of an IPv6 adress as
     * an IPv4 compatible adress
     *
     * Example:  0:0:0:0:0:0:13.1.68.3
     *           0:0:0:0:0:FFFF:129.144.52.38
     *
     * @access public
     * @static
     * @param string $ip	a valid IPv6-adress (hex format)
     * @return array		[0] contains the IPv6 part, [1] the IPv4 part (hex format)
     */
    function SplitV64($ip) {
        $ip = Net_IPv6::removeNetmaskSpec($ip);
        $ip = Net_IPv6::Uncompress($ip);
        if (strstr($ip, '.')) {
            $pos = strrpos($ip, ':');
            $ip{$pos} = '_';
            $ipPart = explode('_', $ip);
            return $ipPart;
        } else {
            return array($ip, "");
        }
    }

    // }}}
    // {{{ checkIPv6()

    /**
     * Checks an IPv6 adress
     *
     * Checks if the given IP is IPv6-compatible
     *
     * @access public
     * @static
     * @param string $ip	a valid IPv6-adress
     * @return boolean	true if $ip is an IPv6 adress
     */
    function checkIPv6($ip) {
        $ip = Net_IPv6::removeNetmaskSpec($ip);
        $ipPart = Net_IPv6::SplitV64($ip);
        $count = 0;
        if (!empty($ipPart[0])) {
            $ipv6 =explode(':', $ipPart[0]);
            for ($i = 0; $i < count($ipv6); $i++) {
                $dec = hexdec($ipv6[$i]);
                $hex = strtoupper(preg_replace("/^[0]{1,3}(.*[0-9a-fA-F])$/", "\\1", $ipv6[$i]));
                if ($ipv6[$i] >= 0 && $dec <= 65535 && $hex == strtoupper(dechex($dec))) {
                    $count++;
                }
            }
            if (8 == $count) {
                return true;
            } elseif (6 == $count and !empty($ipPart[1])) {
                $ipv4 = explode('.',$ipPart[1]);
                $count = 0;
                for ($i = 0; $i < count($ipv4); $i++) {
                    if ($ipv4[$i] >= 0 && (integer)$ipv4[$i] <= 255 && preg_match("/^\d{1,3}$/", $ipv4[$i])) {
                        $count++;
                    }
                }
                if (4 == $count) {
                    return true;
                }
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    // }}}
}
// }}}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
