<?php

/**
 * Pure-PHP arbitrary precision integer arithmetic library.
 *
 * Supports base-2, base-10, base-16, and base-256 numbers.  Uses the GMP or BCMath extensions, if available,
 * and an internal implementation, otherwise.
 *
 * PHP versions 4 and 5
 *
 * {@internal (all DocBlock comments regarding implementation - such as the one that follows - refer to the
 * {@link MATH_BIGINTEGER_MODE_INTERNAL MATH_BIGINTEGER_MODE_INTERNAL} mode)
 *
 * Math_BigInteger uses base-2**26 to perform operations such as multiplication and division and
 * base-2**52 (ie. two base 2**26 digits) to perform addition and subtraction.  Because the largest possible
 * value when multiplying two base-2**26 numbers together is a base-2**52 number, double precision floating
 * point numbers - numbers that should be supported on most hardware and whose significand is 53 bits - are
 * used.  As a consequence, bitwise operators such as >> and << cannot be used, nor can the modulo operator %,
 * which only supports integers.  Although this fact will slow this library down, the fact that such a high
 * base is being used should more than compensate.
 *
 * Numbers are stored in {@link http://en.wikipedia.org/wiki/Endianness little endian} format.  ie.
 * (new Math_BigInteger(pow(2, 26)))->value = array(0, 1)
 *
 * Useful resources are as follows:
 *
 *  - {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf Handbook of Applied Cryptography (HAC)}
 *  - {@link http://math.libtomcrypt.com/files/tommath.pdf Multi-Precision Math (MPM)}
 *  - Java's BigInteger classes.  See /j2se/src/share/classes/java/math in jdk-1_5_0-src-jrl.zip
 *
 * Here's an example of how to use this library:
 * <code>
 * <?php
 *    include 'Math/BigInteger.php';
 *
 *    $a = new Math_BigInteger(2);
 *    $b = new Math_BigInteger(3);
 *
 *    $c = $a->add($b);
 *
 *    echo $c->toString(); // outputs 5
 * ?>
 * </code>
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  Math
 * @package   Math_BigInteger
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright MMVI Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://pear.php.net/package/Math_BigInteger
 */

/**#@+
 * Reduction constants
 *
 * @access private
 * @see Math_BigInteger::_reduce()
 */
/**
 * @see Math_BigInteger::_montgomery()
 * @see Math_BigInteger::_prepMontgomery()
 */
define('MATH_BIGINTEGER_MONTGOMERY', 0);
/**
 * @see Math_BigInteger::_barrett()
 */
define('MATH_BIGINTEGER_BARRETT', 1);
/**
 * @see Math_BigInteger::_mod2()
 */
define('MATH_BIGINTEGER_POWEROF2', 2);
/**
 * @see Math_BigInteger::_remainder()
 */
define('MATH_BIGINTEGER_CLASSIC', 3);
/**
 * @see Math_BigInteger::__clone()
 */
define('MATH_BIGINTEGER_NONE', 4);
/**#@-*/

/**#@+
 * Array constants
 *
 * Rather than create a thousands and thousands of new Math_BigInteger objects in repeated function calls to add() and
 * multiply() or whatever, we'll just work directly on arrays, taking them in as parameters and returning them.
 *
 * @access private
 */
/**
 * $result[MATH_BIGINTEGER_VALUE] contains the value.
 */
define('MATH_BIGINTEGER_VALUE', 0);
/**
 * $result[MATH_BIGINTEGER_SIGN] contains the sign.
 */
define('MATH_BIGINTEGER_SIGN', 1);
/**#@-*/

/**#@+
 * @access private
 * @see Math_BigInteger::_montgomery()
 * @see Math_BigInteger::_barrett()
 */
/**
 * Cache constants
 *
 * $cache[MATH_BIGINTEGER_VARIABLE] tells us whether or not the cached data is still valid.
 */
define('MATH_BIGINTEGER_VARIABLE', 0);
/**
 * $cache[MATH_BIGINTEGER_DATA] contains the cached data.
 */
define('MATH_BIGINTEGER_DATA', 1);
/**#@-*/

/**#@+
 * Mode constants.
 *
 * @access private
 * @see Math_BigInteger::Math_BigInteger()
 */
/**
 * To use the pure-PHP implementation
 */
define('MATH_BIGINTEGER_MODE_INTERNAL', 1);
/**
 * To use the BCMath library
 *
 * (if enabled; otherwise, the internal implementation will be used)
 */
define('MATH_BIGINTEGER_MODE_BCMATH', 2);
/**
 * To use the GMP library
 *
 * (if present; otherwise, either the BCMath or the internal implementation will be used)
 */
define('MATH_BIGINTEGER_MODE_GMP', 3);
/**#@-*/

/**
 * Karatsuba Cutoff
 *
 * At what point do we switch between Karatsuba multiplication and schoolbook long multiplication?
 *
 * @access private
 */
define('MATH_BIGINTEGER_KARATSUBA_CUTOFF', 25);

/**
 * Pure-PHP arbitrary precision integer arithmetic library. Supports base-2, base-10, base-16, and base-256
 * numbers.
 *
 * @package Math_BigInteger
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class Math_BigInteger
{
    /**
     * Holds the BigInteger's value.
     *
     * @var Array
     * @access private
     */
    var $value;

    /**
     * Holds the BigInteger's magnitude.
     *
     * @var Boolean
     * @access private
     */
    var $is_negative = false;

    /**
     * Random number generator function
     *
     * @see setRandomGenerator()
     * @access private
     */
    var $generator = 'mt_rand';

    /**
     * Precision
     *
     * @see setPrecision()
     * @access private
     */
    var $precision = -1;

    /**
     * Precision Bitmask
     *
     * @see setPrecision()
     * @access private
     */
    var $bitmask = false;

    /**
     * Mode independent value used for serialization.
     *
     * If the bcmath or gmp extensions are installed $this->value will be a non-serializable resource, hence the need for
     * a variable that'll be serializable regardless of whether or not extensions are being used.  Unlike $this->value,
     * however, $this->hex is only calculated when $this->__sleep() is called.
     *
     * @see __sleep()
     * @see __wakeup()
     * @var String
     * @access private
     */
    var $hex;

    /**
     * Converts base-2, base-10, base-16, and binary strings (base-256) to BigIntegers.
     *
     * If the second parameter - $base - is negative, then it will be assumed that the number's are encoded using
     * two's compliment.  The sole exception to this is -10, which is treated the same as 10 is.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('0x32', 16); // 50 in base-16
     *
     *    echo $a->toString(); // outputs 50
     * ?>
     * </code>
     *
     * @param optional $x base-10 number or base-$base number if $base set.
     * @param optional integer $base
     * @return Math_BigInteger
     * @access public
     */
    function Math_BigInteger($x = 0, $base = 10)
    {
        if ( !defined('MATH_BIGINTEGER_MODE') ) {
            switch (true) {
                case extension_loaded('gmp'):
                    define('MATH_BIGINTEGER_MODE', MATH_BIGINTEGER_MODE_GMP);
                    break;
                case extension_loaded('bcmath'):
                    define('MATH_BIGINTEGER_MODE', MATH_BIGINTEGER_MODE_BCMATH);
                    break;
                default:
                    define('MATH_BIGINTEGER_MODE', MATH_BIGINTEGER_MODE_INTERNAL);
            }
        }

        if (function_exists('openssl_public_encrypt') && !defined('MATH_BIGINTEGER_OPENSSL_DISABLE') && !defined('MATH_BIGINTEGER_OPENSSL_ENABLED')) {
            // some versions of XAMPP have mismatched versions of OpenSSL which causes it not to work
            ob_start();
            @phpinfo();
            $content = ob_get_contents();
            ob_end_clean();

            preg_match_all('#OpenSSL (Header|Library) Version(.*)#im', $content, $matches);

            $versions = array();
            if (!empty($matches[1])) {
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $versions[$matches[1][$i]] = trim(str_replace('=>', '', strip_tags($matches[2][$i])));
                }
            }

            // it doesn't appear that OpenSSL versions were reported upon until PHP 5.3+
            switch (true) {
                case !isset($versions['Header']):
                case !isset($versions['Library']):
                case $versions['Header'] == $versions['Library']:
                    define('MATH_BIGINTEGER_OPENSSL_ENABLED', true);
                    break;
                default:
                    define('MATH_BIGINTEGER_OPENSSL_DISABLE', true);
            }
        }

        if (!defined('PHP_INT_SIZE')) {
            define('PHP_INT_SIZE', 4);
        }

        if (!defined('MATH_BIGINTEGER_BASE') && MATH_BIGINTEGER_MODE == MATH_BIGINTEGER_MODE_INTERNAL) {
            switch (PHP_INT_SIZE) {
                case 8: // use 64-bit integers if int size is 8 bytes
                    define('MATH_BIGINTEGER_BASE',       31);
                    define('MATH_BIGINTEGER_BASE_FULL',  0x80000000);
                    define('MATH_BIGINTEGER_MAX_DIGIT',  0x7FFFFFFF);
                    define('MATH_BIGINTEGER_MSB',        0x40000000);
                    // 10**9 is the closest we can get to 2**31 without passing it
                    define('MATH_BIGINTEGER_MAX10',      1000000000);
                    define('MATH_BIGINTEGER_MAX10_LEN',  9);
                    // the largest digit that may be used in addition / subtraction
                    define('MATH_BIGINTEGER_MAX_DIGIT2', pow(2, 62));
                    break;
                //case 4: // use 64-bit floats if int size is 4 bytes
                default:
                    define('MATH_BIGINTEGER_BASE',       26);
                    define('MATH_BIGINTEGER_BASE_FULL',  0x4000000);
                    define('MATH_BIGINTEGER_MAX_DIGIT',  0x3FFFFFF);
                    define('MATH_BIGINTEGER_MSB',        0x2000000);
                    // 10**7 is the closest to 2**26 without passing it
                    define('MATH_BIGINTEGER_MAX10',      10000000);
                    define('MATH_BIGINTEGER_MAX10_LEN',  7);
                    // the largest digit that may be used in addition / subtraction
                    // we do pow(2, 52) instead of using 4503599627370496 directly because some
                    // PHP installations will truncate 4503599627370496.
                    define('MATH_BIGINTEGER_MAX_DIGIT2', pow(2, 52));
            }
        }

        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                switch (true) {
                    case is_resource($x) && get_resource_type($x) == 'GMP integer':
                    // PHP 5.6 switched GMP from using resources to objects
                    case is_object($x) && get_class($x) == 'GMP':
                        $this->value = $x;
                        return;
                }
                $this->value = gmp_init(0);
                break;
            case MATH_BIGINTEGER_MODE_BCMATH:
                $this->value = '0';
                break;
            default:
                $this->value = array();
        }

        // '0' counts as empty() but when the base is 256 '0' is equal to ord('0') or 48
        // '0' is the only value like this per http://php.net/empty
        if (empty($x) && (abs($base) != 256 || $x !== '0')) {
            return;
        }

        switch ($base) {
            case -256:
                if (ord($x[0]) & 0x80) {
                    $x = ~$x;
                    $this->is_negative = true;
                }
            case  256:
                switch ( MATH_BIGINTEGER_MODE ) {
                    case MATH_BIGINTEGER_MODE_GMP:
                        $sign = $this->is_negative ? '-' : '';
                        $this->value = gmp_init($sign . '0x' . bin2hex($x));
                        break;
                    case MATH_BIGINTEGER_MODE_BCMATH:
                        // round $len to the nearest 4 (thanks, DavidMJ!)
                        $len = (strlen($x) + 3) & 0xFFFFFFFC;

                        $x = str_pad($x, $len, chr(0), STR_PAD_LEFT);

                        for ($i = 0; $i < $len; $i+= 4) {
                            $this->value = bcmul($this->value, '4294967296', 0); // 4294967296 == 2**32
                            $this->value = bcadd($this->value, 0x1000000 * ord($x[$i]) + ((ord($x[$i + 1]) << 16) | (ord($x[$i + 2]) << 8) | ord($x[$i + 3])), 0);
                        }

                        if ($this->is_negative) {
                            $this->value = '-' . $this->value;
                        }

                        break;
                    // converts a base-2**8 (big endian / msb) number to base-2**26 (little endian / lsb)
                    default:
                        while (strlen($x)) {
                            $this->value[] = $this->_bytes2int($this->_base256_rshift($x, MATH_BIGINTEGER_BASE));
                        }
                }

                if ($this->is_negative) {
                    if (MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_INTERNAL) {
                        $this->is_negative = false;
                    }
                    $temp = $this->add(new Math_BigInteger('-1'));
                    $this->value = $temp->value;
                }
                break;
            case  16:
            case -16:
                if ($base > 0 && $x[0] == '-') {
                    $this->is_negative = true;
                    $x = substr($x, 1);
                }

                $x = preg_replace('#^(?:0x)?([A-Fa-f0-9]*).*#', '$1', $x);

                $is_negative = false;
                if ($base < 0 && hexdec($x[0]) >= 8) {
                    $this->is_negative = $is_negative = true;
                    $x = bin2hex(~pack('H*', $x));
                }

                switch ( MATH_BIGINTEGER_MODE ) {
                    case MATH_BIGINTEGER_MODE_GMP:
                        $temp = $this->is_negative ? '-0x' . $x : '0x' . $x;
                        $this->value = gmp_init($temp);
                        $this->is_negative = false;
                        break;
                    case MATH_BIGINTEGER_MODE_BCMATH:
                        $x = ( strlen($x) & 1 ) ? '0' . $x : $x;
                        $temp = new Math_BigInteger(pack('H*', $x), 256);
                        $this->value = $this->is_negative ? '-' . $temp->value : $temp->value;
                        $this->is_negative = false;
                        break;
                    default:
                        $x = ( strlen($x) & 1 ) ? '0' . $x : $x;
                        $temp = new Math_BigInteger(pack('H*', $x), 256);
                        $this->value = $temp->value;
                }

                if ($is_negative) {
                    $temp = $this->add(new Math_BigInteger('-1'));
                    $this->value = $temp->value;
                }
                break;
            case  10:
            case -10:
                // (?<!^)(?:-).*: find any -'s that aren't at the beginning and then any characters that follow that
                // (?<=^|-)0*: find any 0's that are preceded by the start of the string or by a - (ie. octals)
                // [^-0-9].*: find any non-numeric characters and then any characters that follow that
                $x = preg_replace('#(?<!^)(?:-).*|(?<=^|-)0*|[^-0-9].*#', '', $x);

                switch ( MATH_BIGINTEGER_MODE ) {
                    case MATH_BIGINTEGER_MODE_GMP:
                        $this->value = gmp_init($x);
                        break;
                    case MATH_BIGINTEGER_MODE_BCMATH:
                        // explicitly casting $x to a string is necessary, here, since doing $x[0] on -1 yields different
                        // results then doing it on '-1' does (modInverse does $x[0])
                        $this->value = $x === '-' ? '0' : (string) $x;
                        break;
                    default:
                        $temp = new Math_BigInteger();

                        $multiplier = new Math_BigInteger();
                        $multiplier->value = array(MATH_BIGINTEGER_MAX10);

                        if ($x[0] == '-') {
                            $this->is_negative = true;
                            $x = substr($x, 1);
                        }

                        $x = str_pad($x, strlen($x) + ((MATH_BIGINTEGER_MAX10_LEN - 1) * strlen($x)) % MATH_BIGINTEGER_MAX10_LEN, 0, STR_PAD_LEFT);
                        while (strlen($x)) {
                            $temp = $temp->multiply($multiplier);
                            $temp = $temp->add(new Math_BigInteger($this->_int2bytes(substr($x, 0, MATH_BIGINTEGER_MAX10_LEN)), 256));
                            $x = substr($x, MATH_BIGINTEGER_MAX10_LEN);
                        }

                        $this->value = $temp->value;
                }
                break;
            case  2: // base-2 support originally implemented by Lluis Pamies - thanks!
            case -2:
                if ($base > 0 && $x[0] == '-') {
                    $this->is_negative = true;
                    $x = substr($x, 1);
                }

                $x = preg_replace('#^([01]*).*#', '$1', $x);
                $x = str_pad($x, strlen($x) + (3 * strlen($x)) % 4, 0, STR_PAD_LEFT);

                $str = '0x';
                while (strlen($x)) {
                    $part = substr($x, 0, 4);
                    $str.= dechex(bindec($part));
                    $x = substr($x, 4);
                }

                if ($this->is_negative) {
                    $str = '-' . $str;
                }

                $temp = new Math_BigInteger($str, 8 * $base); // ie. either -16 or +16
                $this->value = $temp->value;
                $this->is_negative = $temp->is_negative;

                break;
            default:
                // base not supported, so we'll let $this == 0
        }
    }

    /**
     * Converts a BigInteger to a byte string (eg. base-256).
     *
     * Negative numbers are saved as positive numbers, unless $twos_compliment is set to true, at which point, they're
     * saved as two's compliment.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('65');
     *
     *    echo $a->toBytes(); // outputs chr(65)
     * ?>
     * </code>
     *
     * @param Boolean $twos_compliment
     * @return String
     * @access public
     * @internal Converts a base-2**26 number to base-2**8
     */
    function toBytes($twos_compliment = false)
    {
        if ($twos_compliment) {
            $comparison = $this->compare(new Math_BigInteger());
            if ($comparison == 0) {
                return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
            }

            $temp = $comparison < 0 ? $this->add(new Math_BigInteger(1)) : $this->copy();
            $bytes = $temp->toBytes();

            if (empty($bytes)) { // eg. if the number we're trying to convert is -1
                $bytes = chr(0);
            }

            if (ord($bytes[0]) & 0x80) {
                $bytes = chr(0) . $bytes;
            }

            return $comparison < 0 ? ~$bytes : $bytes;
        }

        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                if (gmp_cmp($this->value, gmp_init(0)) == 0) {
                    return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
                }

                $temp = gmp_strval(gmp_abs($this->value), 16);
                $temp = ( strlen($temp) & 1 ) ? '0' . $temp : $temp;
                $temp = pack('H*', $temp);

                return $this->precision > 0 ?
                    substr(str_pad($temp, $this->precision >> 3, chr(0), STR_PAD_LEFT), -($this->precision >> 3)) :
                    ltrim($temp, chr(0));
            case MATH_BIGINTEGER_MODE_BCMATH:
                if ($this->value === '0') {
                    return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
                }

                $value = '';
                $current = $this->value;

                if ($current[0] == '-') {
                    $current = substr($current, 1);
                }

                while (bccomp($current, '0', 0) > 0) {
                    $temp = bcmod($current, '16777216');
                    $value = chr($temp >> 16) . chr($temp >> 8) . chr($temp) . $value;
                    $current = bcdiv($current, '16777216', 0);
                }

                return $this->precision > 0 ?
                    substr(str_pad($value, $this->precision >> 3, chr(0), STR_PAD_LEFT), -($this->precision >> 3)) :
                    ltrim($value, chr(0));
        }

        if (!count($this->value)) {
            return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
        }
        $result = $this->_int2bytes($this->value[count($this->value) - 1]);

        $temp = $this->copy();

        for ($i = count($temp->value) - 2; $i >= 0; --$i) {
            $temp->_base256_lshift($result, MATH_BIGINTEGER_BASE);
            $result = $result | str_pad($temp->_int2bytes($temp->value[$i]), strlen($result), chr(0), STR_PAD_LEFT);
        }

        return $this->precision > 0 ?
            str_pad(substr($result, -(($this->precision + 7) >> 3)), ($this->precision + 7) >> 3, chr(0), STR_PAD_LEFT) :
            $result;
    }

    /**
     * Converts a BigInteger to a hex string (eg. base-16)).
     *
     * Negative numbers are saved as positive numbers, unless $twos_compliment is set to true, at which point, they're
     * saved as two's compliment.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('65');
     *
     *    echo $a->toHex(); // outputs '41'
     * ?>
     * </code>
     *
     * @param Boolean $twos_compliment
     * @return String
     * @access public
     * @internal Converts a base-2**26 number to base-2**8
     */
    function toHex($twos_compliment = false)
    {
        return bin2hex($this->toBytes($twos_compliment));
    }

    /**
     * Converts a BigInteger to a bit string (eg. base-2).
     *
     * Negative numbers are saved as positive numbers, unless $twos_compliment is set to true, at which point, they're
     * saved as two's compliment.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('65');
     *
     *    echo $a->toBits(); // outputs '1000001'
     * ?>
     * </code>
     *
     * @param Boolean $twos_compliment
     * @return String
     * @access public
     * @internal Converts a base-2**26 number to base-2**2
     */
    function toBits($twos_compliment = false)
    {
        $hex = $this->toHex($twos_compliment);
        $bits = '';
        for ($i = strlen($hex) - 8, $start = strlen($hex) & 7; $i >= $start; $i-=8) {
            $bits = str_pad(decbin(hexdec(substr($hex, $i, 8))), 32, '0', STR_PAD_LEFT) . $bits;
        }
        if ($start) { // hexdec('') == 0
            $bits = str_pad(decbin(hexdec(substr($hex, 0, $start))), 8, '0', STR_PAD_LEFT) . $bits;
        }
        $result = $this->precision > 0 ? substr($bits, -$this->precision) : ltrim($bits, '0');

        if ($twos_compliment && $this->compare(new Math_BigInteger()) > 0 && $this->precision <= 0) {
            return '0' . $result;
        }

        return $result;
    }

    /**
     * Converts a BigInteger to a base-10 number.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('50');
     *
     *    echo $a->toString(); // outputs 50
     * ?>
     * </code>
     *
     * @return String
     * @access public
     * @internal Converts a base-2**26 number to base-10**7 (which is pretty much base-10)
     */
    function toString()
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                return gmp_strval($this->value);
            case MATH_BIGINTEGER_MODE_BCMATH:
                if ($this->value === '0') {
                    return '0';
                }

                return ltrim($this->value, '0');
        }

        if (!count($this->value)) {
            return '0';
        }

        $temp = $this->copy();
        $temp->is_negative = false;

        $divisor = new Math_BigInteger();
        $divisor->value = array(MATH_BIGINTEGER_MAX10);
        $result = '';
        while (count($temp->value)) {
            list($temp, $mod) = $temp->divide($divisor);
            $result = str_pad(isset($mod->value[0]) ? $mod->value[0] : '', MATH_BIGINTEGER_MAX10_LEN, '0', STR_PAD_LEFT) . $result;
        }
        $result = ltrim($result, '0');
        if (empty($result)) {
            $result = '0';
        }

        if ($this->is_negative) {
            $result = '-' . $result;
        }

        return $result;
    }

    /**
     * Copy an object
     *
     * PHP5 passes objects by reference while PHP4 passes by value.  As such, we need a function to guarantee
     * that all objects are passed by value, when appropriate.  More information can be found here:
     *
     * {@link http://php.net/language.oop5.basic#51624}
     *
     * @access public
     * @see __clone()
     * @return Math_BigInteger
     */
    function copy()
    {
        $temp = new Math_BigInteger();
        $temp->value = $this->value;
        $temp->is_negative = $this->is_negative;
        $temp->generator = $this->generator;
        $temp->precision = $this->precision;
        $temp->bitmask = $this->bitmask;
        return $temp;
    }

    /**
     *  __toString() magic method
     *
     * Will be called, automatically, if you're supporting just PHP5.  If you're supporting PHP4, you'll need to call
     * toString().
     *
     * @access public
     * @internal Implemented per a suggestion by Techie-Michael - thanks!
     */
    function __toString()
    {
        return $this->toString();
    }

    /**
     * __clone() magic method
     *
     * Although you can call Math_BigInteger::__toString() directly in PHP5, you cannot call Math_BigInteger::__clone()
     * directly in PHP5.  You can in PHP4 since it's not a magic method, but in PHP5, you have to call it by using the PHP5
     * only syntax of $y = clone $x.  As such, if you're trying to write an application that works on both PHP4 and PHP5,
     * call Math_BigInteger::copy(), instead.
     *
     * @access public
     * @see copy()
     * @return Math_BigInteger
     */
    function __clone()
    {
        return $this->copy();
    }

    /**
     *  __sleep() magic method
     *
     * Will be called, automatically, when serialize() is called on a Math_BigInteger object.
     *
     * @see __wakeup()
     * @access public
     */
    function __sleep()
    {
        $this->hex = $this->toHex(true);
        $vars = array('hex');
        if ($this->generator != 'mt_rand') {
            $vars[] = 'generator';
        }
        if ($this->precision > 0) {
            $vars[] = 'precision';
        }
        return $vars;

    }

    /**
     *  __wakeup() magic method
     *
     * Will be called, automatically, when unserialize() is called on a Math_BigInteger object.
     *
     * @see __sleep()
     * @access public
     */
    function __wakeup()
    {
        $temp = new Math_BigInteger($this->hex, -16);
        $this->value = $temp->value;
        $this->is_negative = $temp->is_negative;
        $this->setRandomGenerator($this->generator);
        if ($this->precision > 0) {
            // recalculate $this->bitmask
            $this->setPrecision($this->precision);
        }
    }

    /**
     * Adds two BigIntegers.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('10');
     *    $b = new Math_BigInteger('20');
     *
     *    $c = $a->add($b);
     *
     *    echo $c->toString(); // outputs 30
     * ?>
     * </code>
     *
     * @param Math_BigInteger $y
     * @return Math_BigInteger
     * @access public
     * @internal Performs base-2**52 addition
     */
    function add($y)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_add($this->value, $y->value);

                return $this->_normalize($temp);
            case MATH_BIGINTEGER_MODE_BCMATH:
                $temp = new Math_BigInteger();
                $temp->value = bcadd($this->value, $y->value, 0);

                return $this->_normalize($temp);
        }

        $temp = $this->_add($this->value, $this->is_negative, $y->value, $y->is_negative);

        $result = new Math_BigInteger();
        $result->value = $temp[MATH_BIGINTEGER_VALUE];
        $result->is_negative = $temp[MATH_BIGINTEGER_SIGN];

        return $this->_normalize($result);
    }

    /**
     * Performs addition.
     *
     * @param Array $x_value
     * @param Boolean $x_negative
     * @param Array $y_value
     * @param Boolean $y_negative
     * @return Array
     * @access private
     */
    function _add($x_value, $x_negative, $y_value, $y_negative)
    {
        $x_size = count($x_value);
        $y_size = count($y_value);

        if ($x_size == 0) {
            return array(
                MATH_BIGINTEGER_VALUE => $y_value,
                MATH_BIGINTEGER_SIGN => $y_negative
            );
        } else if ($y_size == 0) {
            return array(
                MATH_BIGINTEGER_VALUE => $x_value,
                MATH_BIGINTEGER_SIGN => $x_negative
            );
        }

        // subtract, if appropriate
        if ( $x_negative != $y_negative ) {
            if ( $x_value == $y_value ) {
                return array(
                    MATH_BIGINTEGER_VALUE => array(),
                    MATH_BIGINTEGER_SIGN => false
                );
            }

            $temp = $this->_subtract($x_value, false, $y_value, false);
            $temp[MATH_BIGINTEGER_SIGN] = $this->_compare($x_value, false, $y_value, false) > 0 ?
                                          $x_negative : $y_negative;

            return $temp;
        }

        if ($x_size < $y_size) {
            $size = $x_size;
            $value = $y_value;
        } else {
            $size = $y_size;
            $value = $x_value;
        }

        $value[count($value)] = 0; // just in case the carry adds an extra digit

        $carry = 0;
        for ($i = 0, $j = 1; $j < $size; $i+=2, $j+=2) {
            $sum = $x_value[$j] * MATH_BIGINTEGER_BASE_FULL + $x_value[$i] + $y_value[$j] * MATH_BIGINTEGER_BASE_FULL + $y_value[$i] + $carry;
            $carry = $sum >= MATH_BIGINTEGER_MAX_DIGIT2; // eg. floor($sum / 2**52); only possible values (in any base) are 0 and 1
            $sum = $carry ? $sum - MATH_BIGINTEGER_MAX_DIGIT2 : $sum;

            $temp = MATH_BIGINTEGER_BASE === 26 ? intval($sum / 0x4000000) : ($sum >> 31);

            $value[$i] = (int) ($sum - MATH_BIGINTEGER_BASE_FULL * $temp); // eg. a faster alternative to fmod($sum, 0x4000000)
            $value[$j] = $temp;
        }

        if ($j == $size) { // ie. if $y_size is odd
            $sum = $x_value[$i] + $y_value[$i] + $carry;
            $carry = $sum >= MATH_BIGINTEGER_BASE_FULL;
            $value[$i] = $carry ? $sum - MATH_BIGINTEGER_BASE_FULL : $sum;
            ++$i; // ie. let $i = $j since we've just done $value[$i]
        }

        if ($carry) {
            for (; $value[$i] == MATH_BIGINTEGER_MAX_DIGIT; ++$i) {
                $value[$i] = 0;
            }
            ++$value[$i];
        }

        return array(
            MATH_BIGINTEGER_VALUE => $this->_trim($value),
            MATH_BIGINTEGER_SIGN => $x_negative
        );
    }

    /**
     * Subtracts two BigIntegers.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('10');
     *    $b = new Math_BigInteger('20');
     *
     *    $c = $a->subtract($b);
     *
     *    echo $c->toString(); // outputs -10
     * ?>
     * </code>
     *
     * @param Math_BigInteger $y
     * @return Math_BigInteger
     * @access public
     * @internal Performs base-2**52 subtraction
     */
    function subtract($y)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_sub($this->value, $y->value);

                return $this->_normalize($temp);
            case MATH_BIGINTEGER_MODE_BCMATH:
                $temp = new Math_BigInteger();
                $temp->value = bcsub($this->value, $y->value, 0);

                return $this->_normalize($temp);
        }

        $temp = $this->_subtract($this->value, $this->is_negative, $y->value, $y->is_negative);

        $result = new Math_BigInteger();
        $result->value = $temp[MATH_BIGINTEGER_VALUE];
        $result->is_negative = $temp[MATH_BIGINTEGER_SIGN];

        return $this->_normalize($result);
    }

    /**
     * Performs subtraction.
     *
     * @param Array $x_value
     * @param Boolean $x_negative
     * @param Array $y_value
     * @param Boolean $y_negative
     * @return Array
     * @access private
     */
    function _subtract($x_value, $x_negative, $y_value, $y_negative)
    {
        $x_size = count($x_value);
        $y_size = count($y_value);

        if ($x_size == 0) {
            return array(
                MATH_BIGINTEGER_VALUE => $y_value,
                MATH_BIGINTEGER_SIGN => !$y_negative
            );
        } else if ($y_size == 0) {
            return array(
                MATH_BIGINTEGER_VALUE => $x_value,
                MATH_BIGINTEGER_SIGN => $x_negative
            );
        }

        // add, if appropriate (ie. -$x - +$y or +$x - -$y)
        if ( $x_negative != $y_negative ) {
            $temp = $this->_add($x_value, false, $y_value, false);
            $temp[MATH_BIGINTEGER_SIGN] = $x_negative;

            return $temp;
        }

        $diff = $this->_compare($x_value, $x_negative, $y_value, $y_negative);

        if ( !$diff ) {
            return array(
                MATH_BIGINTEGER_VALUE => array(),
                MATH_BIGINTEGER_SIGN => false
            );
        }

        // switch $x and $y around, if appropriate.
        if ( (!$x_negative && $diff < 0) || ($x_negative && $diff > 0) ) {
            $temp = $x_value;
            $x_value = $y_value;
            $y_value = $temp;

            $x_negative = !$x_negative;

            $x_size = count($x_value);
            $y_size = count($y_value);
        }

        // at this point, $x_value should be at least as big as - if not bigger than - $y_value

        $carry = 0;
        for ($i = 0, $j = 1; $j < $y_size; $i+=2, $j+=2) {
            $sum = $x_value[$j] * MATH_BIGINTEGER_BASE_FULL + $x_value[$i] - $y_value[$j] * MATH_BIGINTEGER_BASE_FULL - $y_value[$i] - $carry;
            $carry = $sum < 0; // eg. floor($sum / 2**52); only possible values (in any base) are 0 and 1
            $sum = $carry ? $sum + MATH_BIGINTEGER_MAX_DIGIT2 : $sum;

            $temp = MATH_BIGINTEGER_BASE === 26 ? intval($sum / 0x4000000) : ($sum >> 31);

            $x_value[$i] = (int) ($sum - MATH_BIGINTEGER_BASE_FULL * $temp);
            $x_value[$j] = $temp;
        }

        if ($j == $y_size) { // ie. if $y_size is odd
            $sum = $x_value[$i] - $y_value[$i] - $carry;
            $carry = $sum < 0;
            $x_value[$i] = $carry ? $sum + MATH_BIGINTEGER_BASE_FULL : $sum;
            ++$i;
        }

        if ($carry) {
            for (; !$x_value[$i]; ++$i) {
                $x_value[$i] = MATH_BIGINTEGER_MAX_DIGIT;
            }
            --$x_value[$i];
        }

        return array(
            MATH_BIGINTEGER_VALUE => $this->_trim($x_value),
            MATH_BIGINTEGER_SIGN => $x_negative
        );
    }

    /**
     * Multiplies two BigIntegers
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('10');
     *    $b = new Math_BigInteger('20');
     *
     *    $c = $a->multiply($b);
     *
     *    echo $c->toString(); // outputs 200
     * ?>
     * </code>
     *
     * @param Math_BigInteger $x
     * @return Math_BigInteger
     * @access public
     */
    function multiply($x)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_mul($this->value, $x->value);

                return $this->_normalize($temp);
            case MATH_BIGINTEGER_MODE_BCMATH:
                $temp = new Math_BigInteger();
                $temp->value = bcmul($this->value, $x->value, 0);

                return $this->_normalize($temp);
        }

        $temp = $this->_multiply($this->value, $this->is_negative, $x->value, $x->is_negative);

        $product = new Math_BigInteger();
        $product->value = $temp[MATH_BIGINTEGER_VALUE];
        $product->is_negative = $temp[MATH_BIGINTEGER_SIGN];

        return $this->_normalize($product);
    }

    /**
     * Performs multiplication.
     *
     * @param Array $x_value
     * @param Boolean $x_negative
     * @param Array $y_value
     * @param Boolean $y_negative
     * @return Array
     * @access private
     */
    function _multiply($x_value, $x_negative, $y_value, $y_negative)
    {
        //if ( $x_value == $y_value ) {
        //    return array(
        //        MATH_BIGINTEGER_VALUE => $this->_square($x_value),
        //        MATH_BIGINTEGER_SIGN => $x_sign != $y_value
        //    );
        //}

        $x_length = count($x_value);
        $y_length = count($y_value);

        if ( !$x_length || !$y_length ) { // a 0 is being multiplied
            return array(
                MATH_BIGINTEGER_VALUE => array(),
                MATH_BIGINTEGER_SIGN => false
            );
        }

        return array(
            MATH_BIGINTEGER_VALUE => min($x_length, $y_length) < 2 * MATH_BIGINTEGER_KARATSUBA_CUTOFF ?
                $this->_trim($this->_regularMultiply($x_value, $y_value)) :
                $this->_trim($this->_karatsuba($x_value, $y_value)),
            MATH_BIGINTEGER_SIGN => $x_negative != $y_negative
        );
    }

    /**
     * Performs long multiplication on two BigIntegers
     *
     * Modeled after 'multiply' in MutableBigInteger.java.
     *
     * @param Array $x_value
     * @param Array $y_value
     * @return Array
     * @access private
     */
    function _regularMultiply($x_value, $y_value)
    {
        $x_length = count($x_value);
        $y_length = count($y_value);

        if ( !$x_length || !$y_length ) { // a 0 is being multiplied
            return array();
        }

        if ( $x_length < $y_length ) {
            $temp = $x_value;
            $x_value = $y_value;
            $y_value = $temp;

            $x_length = count($x_value);
            $y_length = count($y_value);
        }

        $product_value = $this->_array_repeat(0, $x_length + $y_length);

        // the following for loop could be removed if the for loop following it
        // (the one with nested for loops) initially set $i to 0, but
        // doing so would also make the result in one set of unnecessary adds,
        // since on the outermost loops first pass, $product->value[$k] is going
        // to always be 0

        $carry = 0;

        for ($j = 0; $j < $x_length; ++$j) { // ie. $i = 0
            $temp = $x_value[$j] * $y_value[0] + $carry; // $product_value[$k] == 0
            $carry = MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31);
            $product_value[$j] = (int) ($temp - MATH_BIGINTEGER_BASE_FULL * $carry);
        }

        $product_value[$j] = $carry;

        // the above for loop is what the previous comment was talking about.  the
        // following for loop is the "one with nested for loops"
        for ($i = 1; $i < $y_length; ++$i) {
            $carry = 0;

            for ($j = 0, $k = $i; $j < $x_length; ++$j, ++$k) {
                $temp = $product_value[$k] + $x_value[$j] * $y_value[$i] + $carry;
                $carry = MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31);
                $product_value[$k] = (int) ($temp - MATH_BIGINTEGER_BASE_FULL * $carry);
            }

            $product_value[$k] = $carry;
        }

        return $product_value;
    }

    /**
     * Performs Karatsuba multiplication on two BigIntegers
     *
     * See {@link http://en.wikipedia.org/wiki/Karatsuba_algorithm Karatsuba algorithm} and
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=120 MPM 5.2.3}.
     *
     * @param Array $x_value
     * @param Array $y_value
     * @return Array
     * @access private
     */
    function _karatsuba($x_value, $y_value)
    {
        $m = min(count($x_value) >> 1, count($y_value) >> 1);

        if ($m < MATH_BIGINTEGER_KARATSUBA_CUTOFF) {
            return $this->_regularMultiply($x_value, $y_value);
        }

        $x1 = array_slice($x_value, $m);
        $x0 = array_slice($x_value, 0, $m);
        $y1 = array_slice($y_value, $m);
        $y0 = array_slice($y_value, 0, $m);

        $z2 = $this->_karatsuba($x1, $y1);
        $z0 = $this->_karatsuba($x0, $y0);

        $z1 = $this->_add($x1, false, $x0, false);
        $temp = $this->_add($y1, false, $y0, false);
        $z1 = $this->_karatsuba($z1[MATH_BIGINTEGER_VALUE], $temp[MATH_BIGINTEGER_VALUE]);
        $temp = $this->_add($z2, false, $z0, false);
        $z1 = $this->_subtract($z1, false, $temp[MATH_BIGINTEGER_VALUE], false);

        $z2 = array_merge(array_fill(0, 2 * $m, 0), $z2);
        $z1[MATH_BIGINTEGER_VALUE] = array_merge(array_fill(0, $m, 0), $z1[MATH_BIGINTEGER_VALUE]);

        $xy = $this->_add($z2, false, $z1[MATH_BIGINTEGER_VALUE], $z1[MATH_BIGINTEGER_SIGN]);
        $xy = $this->_add($xy[MATH_BIGINTEGER_VALUE], $xy[MATH_BIGINTEGER_SIGN], $z0, false);

        return $xy[MATH_BIGINTEGER_VALUE];
    }

    /**
     * Performs squaring
     *
     * @param Array $x
     * @return Array
     * @access private
     */
    function _square($x = false)
    {
        return count($x) < 2 * MATH_BIGINTEGER_KARATSUBA_CUTOFF ?
            $this->_trim($this->_baseSquare($x)) :
            $this->_trim($this->_karatsubaSquare($x));
    }

    /**
     * Performs traditional squaring on two BigIntegers
     *
     * Squaring can be done faster than multiplying a number by itself can be.  See
     * {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=7 HAC 14.2.4} /
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=141 MPM 5.3} for more information.
     *
     * @param Array $value
     * @return Array
     * @access private
     */
    function _baseSquare($value)
    {
        if ( empty($value) ) {
            return array();
        }
        $square_value = $this->_array_repeat(0, 2 * count($value));

        for ($i = 0, $max_index = count($value) - 1; $i <= $max_index; ++$i) {
            $i2 = $i << 1;

            $temp = $square_value[$i2] + $value[$i] * $value[$i];
            $carry = MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31);
            $square_value[$i2] = (int) ($temp - MATH_BIGINTEGER_BASE_FULL * $carry);

            // note how we start from $i+1 instead of 0 as we do in multiplication.
            for ($j = $i + 1, $k = $i2 + 1; $j <= $max_index; ++$j, ++$k) {
                $temp = $square_value[$k] + 2 * $value[$j] * $value[$i] + $carry;
                $carry = MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31);
                $square_value[$k] = (int) ($temp - MATH_BIGINTEGER_BASE_FULL * $carry);
            }

            // the following line can yield values larger 2**15.  at this point, PHP should switch
            // over to floats.
            $square_value[$i + $max_index + 1] = $carry;
        }

        return $square_value;
    }

    /**
     * Performs Karatsuba "squaring" on two BigIntegers
     *
     * See {@link http://en.wikipedia.org/wiki/Karatsuba_algorithm Karatsuba algorithm} and
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=151 MPM 5.3.4}.
     *
     * @param Array $value
     * @return Array
     * @access private
     */
    function _karatsubaSquare($value)
    {
        $m = count($value) >> 1;

        if ($m < MATH_BIGINTEGER_KARATSUBA_CUTOFF) {
            return $this->_baseSquare($value);
        }

        $x1 = array_slice($value, $m);
        $x0 = array_slice($value, 0, $m);

        $z2 = $this->_karatsubaSquare($x1);
        $z0 = $this->_karatsubaSquare($x0);

        $z1 = $this->_add($x1, false, $x0, false);
        $z1 = $this->_karatsubaSquare($z1[MATH_BIGINTEGER_VALUE]);
        $temp = $this->_add($z2, false, $z0, false);
        $z1 = $this->_subtract($z1, false, $temp[MATH_BIGINTEGER_VALUE], false);

        $z2 = array_merge(array_fill(0, 2 * $m, 0), $z2);
        $z1[MATH_BIGINTEGER_VALUE] = array_merge(array_fill(0, $m, 0), $z1[MATH_BIGINTEGER_VALUE]);

        $xx = $this->_add($z2, false, $z1[MATH_BIGINTEGER_VALUE], $z1[MATH_BIGINTEGER_SIGN]);
        $xx = $this->_add($xx[MATH_BIGINTEGER_VALUE], $xx[MATH_BIGINTEGER_SIGN], $z0, false);

        return $xx[MATH_BIGINTEGER_VALUE];
    }

    /**
     * Divides two BigIntegers.
     *
     * Returns an array whose first element contains the quotient and whose second element contains the
     * "common residue".  If the remainder would be positive, the "common residue" and the remainder are the
     * same.  If the remainder would be negative, the "common residue" is equal to the sum of the remainder
     * and the divisor (basically, the "common residue" is the first positive modulo).
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('10');
     *    $b = new Math_BigInteger('20');
     *
     *    list($quotient, $remainder) = $a->divide($b);
     *
     *    echo $quotient->toString(); // outputs 0
     *    echo "\r\n";
     *    echo $remainder->toString(); // outputs 10
     * ?>
     * </code>
     *
     * @param Math_BigInteger $y
     * @return Array
     * @access public
     * @internal This function is based off of {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=9 HAC 14.20}.
     */
    function divide($y)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $quotient = new Math_BigInteger();
                $remainder = new Math_BigInteger();

                list($quotient->value, $remainder->value) = gmp_div_qr($this->value, $y->value);

                if (gmp_sign($remainder->value) < 0) {
                    $remainder->value = gmp_add($remainder->value, gmp_abs($y->value));
                }

                return array($this->_normalize($quotient), $this->_normalize($remainder));
            case MATH_BIGINTEGER_MODE_BCMATH:
                $quotient = new Math_BigInteger();
                $remainder = new Math_BigInteger();

                $quotient->value = bcdiv($this->value, $y->value, 0);
                $remainder->value = bcmod($this->value, $y->value);

                if ($remainder->value[0] == '-') {
                    $remainder->value = bcadd($remainder->value, $y->value[0] == '-' ? substr($y->value, 1) : $y->value, 0);
                }

                return array($this->_normalize($quotient), $this->_normalize($remainder));
        }

        if (count($y->value) == 1) {
            list($q, $r) = $this->_divide_digit($this->value, $y->value[0]);
            $quotient = new Math_BigInteger();
            $remainder = new Math_BigInteger();
            $quotient->value = $q;
            $remainder->value = array($r);
            $quotient->is_negative = $this->is_negative != $y->is_negative;
            return array($this->_normalize($quotient), $this->_normalize($remainder));
        }

        static $zero;
        if ( !isset($zero) ) {
            $zero = new Math_BigInteger();
        }

        $x = $this->copy();
        $y = $y->copy();

        $x_sign = $x->is_negative;
        $y_sign = $y->is_negative;

        $x->is_negative = $y->is_negative = false;

        $diff = $x->compare($y);

        if ( !$diff ) {
            $temp = new Math_BigInteger();
            $temp->value = array(1);
            $temp->is_negative = $x_sign != $y_sign;
            return array($this->_normalize($temp), $this->_normalize(new Math_BigInteger()));
        }

        if ( $diff < 0 ) {
            // if $x is negative, "add" $y.
            if ( $x_sign ) {
                $x = $y->subtract($x);
            }
            return array($this->_normalize(new Math_BigInteger()), $this->_normalize($x));
        }

        // normalize $x and $y as described in HAC 14.23 / 14.24
        $msb = $y->value[count($y->value) - 1];
        for ($shift = 0; !($msb & MATH_BIGINTEGER_MSB); ++$shift) {
            $msb <<= 1;
        }
        $x->_lshift($shift);
        $y->_lshift($shift);
        $y_value = &$y->value;

        $x_max = count($x->value) - 1;
        $y_max = count($y->value) - 1;

        $quotient = new Math_BigInteger();
        $quotient_value = &$quotient->value;
        $quotient_value = $this->_array_repeat(0, $x_max - $y_max + 1);

        static $temp, $lhs, $rhs;
        if (!isset($temp)) {
            $temp = new Math_BigInteger();
            $lhs =  new Math_BigInteger();
            $rhs =  new Math_BigInteger();
        }
        $temp_value = &$temp->value;
        $rhs_value =  &$rhs->value;

        // $temp = $y << ($x_max - $y_max-1) in base 2**26
        $temp_value = array_merge($this->_array_repeat(0, $x_max - $y_max), $y_value);

        while ( $x->compare($temp) >= 0 ) {
            // calculate the "common residue"
            ++$quotient_value[$x_max - $y_max];
            $x = $x->subtract($temp);
            $x_max = count($x->value) - 1;
        }

        for ($i = $x_max; $i >= $y_max + 1; --$i) {
            $x_value = &$x->value;
            $x_window = array(
                isset($x_value[$i]) ? $x_value[$i] : 0,
                isset($x_value[$i - 1]) ? $x_value[$i - 1] : 0,
                isset($x_value[$i - 2]) ? $x_value[$i - 2] : 0
            );
            $y_window = array(
                $y_value[$y_max],
                ( $y_max > 0 ) ? $y_value[$y_max - 1] : 0
            );

            $q_index = $i - $y_max - 1;
            if ($x_window[0] == $y_window[0]) {
                $quotient_value[$q_index] = MATH_BIGINTEGER_MAX_DIGIT;
            } else {
                $quotient_value[$q_index] = $this->_safe_divide(
                    $x_window[0] * MATH_BIGINTEGER_BASE_FULL + $x_window[1],
                    $y_window[0]
                );
            }

            $temp_value = array($y_window[1], $y_window[0]);

            $lhs->value = array($quotient_value[$q_index]);
            $lhs = $lhs->multiply($temp);

            $rhs_value = array($x_window[2], $x_window[1], $x_window[0]);

            while ( $lhs->compare($rhs) > 0 ) {
                --$quotient_value[$q_index];

                $lhs->value = array($quotient_value[$q_index]);
                $lhs = $lhs->multiply($temp);
            }

            $adjust = $this->_array_repeat(0, $q_index);
            $temp_value = array($quotient_value[$q_index]);
            $temp = $temp->multiply($y);
            $temp_value = &$temp->value;
            $temp_value = array_merge($adjust, $temp_value);

            $x = $x->subtract($temp);

            if ($x->compare($zero) < 0) {
                $temp_value = array_merge($adjust, $y_value);
                $x = $x->add($temp);

                --$quotient_value[$q_index];
            }

            $x_max = count($x_value) - 1;
        }

        // unnormalize the remainder
        $x->_rshift($shift);

        $quotient->is_negative = $x_sign != $y_sign;

        // calculate the "common residue", if appropriate
        if ( $x_sign ) {
            $y->_rshift($shift);
            $x = $y->subtract($x);
        }

        return array($this->_normalize($quotient), $this->_normalize($x));
    }

    /**
     * Divides a BigInteger by a regular integer
     *
     * abc / x = a00 / x + b0 / x + c / x
     *
     * @param Array $dividend
     * @param Array $divisor
     * @return Array
     * @access private
     */
    function _divide_digit($dividend, $divisor)
    {
        $carry = 0;
        $result = array();

        for ($i = count($dividend) - 1; $i >= 0; --$i) {
            $temp = MATH_BIGINTEGER_BASE_FULL * $carry + $dividend[$i];
            $result[$i] = $this->_safe_divide($temp, $divisor);
            $carry = (int) ($temp - $divisor * $result[$i]);
        }

        return array($result, $carry);
    }

    /**
     * Performs modular exponentiation.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger('10');
     *    $b = new Math_BigInteger('20');
     *    $c = new Math_BigInteger('30');
     *
     *    $c = $a->modPow($b, $c);
     *
     *    echo $c->toString(); // outputs 10
     * ?>
     * </code>
     *
     * @param Math_BigInteger $e
     * @param Math_BigInteger $n
     * @return Math_BigInteger
     * @access public
     * @internal The most naive approach to modular exponentiation has very unreasonable requirements, and
     *    and although the approach involving repeated squaring does vastly better, it, too, is impractical
     *    for our purposes.  The reason being that division - by far the most complicated and time-consuming
     *    of the basic operations (eg. +,-,*,/) - occurs multiple times within it.
     *
     *    Modular reductions resolve this issue.  Although an individual modular reduction takes more time
     *    then an individual division, when performed in succession (with the same modulo), they're a lot faster.
     *
     *    The two most commonly used modular reductions are Barrett and Montgomery reduction.  Montgomery reduction,
     *    although faster, only works when the gcd of the modulo and of the base being used is 1.  In RSA, when the
     *    base is a power of two, the modulo - a product of two primes - is always going to have a gcd of 1 (because
     *    the product of two odd numbers is odd), but what about when RSA isn't used?
     *
     *    In contrast, Barrett reduction has no such constraint.  As such, some bigint implementations perform a
     *    Barrett reduction after every operation in the modpow function.  Others perform Barrett reductions when the
     *    modulo is even and Montgomery reductions when the modulo is odd.  BigInteger.java's modPow method, however,
     *    uses a trick involving the Chinese Remainder Theorem to factor the even modulo into two numbers - one odd and
     *    the other, a power of two - and recombine them, later.  This is the method that this modPow function uses.
     *    {@link http://islab.oregonstate.edu/papers/j34monex.pdf Montgomery Reduction with Even Modulus} elaborates.
     */
    function modPow($e, $n)
    {
        $n = $this->bitmask !== false && $this->bitmask->compare($n) < 0 ? $this->bitmask : $n->abs();

        if ($e->compare(new Math_BigInteger()) < 0) {
            $e = $e->abs();

            $temp = $this->modInverse($n);
            if ($temp === false) {
                return false;
            }

            return $this->_normalize($temp->modPow($e, $n));
        }

        if ( MATH_BIGINTEGER_MODE == MATH_BIGINTEGER_MODE_GMP ) {
            $temp = new Math_BigInteger();
            $temp->value = gmp_powm($this->value, $e->value, $n->value);

            return $this->_normalize($temp);
        }

        if ($this->compare(new Math_BigInteger()) < 0 || $this->compare($n) > 0) {
            list(, $temp) = $this->divide($n);
            return $temp->modPow($e, $n);
        }

        if (defined('MATH_BIGINTEGER_OPENSSL_ENABLED')) {
            $components = array(
                'modulus' => $n->toBytes(true),
                'publicExponent' => $e->toBytes(true)
            );

            $components = array(
                'modulus' => pack('Ca*a*', 2, $this->_encodeASN1Length(strlen($components['modulus'])), $components['modulus']),
                'publicExponent' => pack('Ca*a*', 2, $this->_encodeASN1Length(strlen($components['publicExponent'])), $components['publicExponent'])
            );

            $RSAPublicKey = pack('Ca*a*a*',
                48, $this->_encodeASN1Length(strlen($components['modulus']) + strlen($components['publicExponent'])),
                $components['modulus'], $components['publicExponent']
            );

            $rsaOID = pack('H*', '300d06092a864886f70d0101010500'); // hex version of MA0GCSqGSIb3DQEBAQUA
            $RSAPublicKey = chr(0) . $RSAPublicKey;
            $RSAPublicKey = chr(3) . $this->_encodeASN1Length(strlen($RSAPublicKey)) . $RSAPublicKey;

            $encapsulated = pack('Ca*a*',
                48, $this->_encodeASN1Length(strlen($rsaOID . $RSAPublicKey)), $rsaOID . $RSAPublicKey
            );

            $RSAPublicKey = "-----BEGIN PUBLIC KEY-----\r\n" .
                             chunk_split(base64_encode($encapsulated)) .
                             '-----END PUBLIC KEY-----';

            $plaintext = str_pad($this->toBytes(), strlen($n->toBytes(true)) - 1, "\0", STR_PAD_LEFT);

            if (openssl_public_encrypt($plaintext, $result, $RSAPublicKey, OPENSSL_NO_PADDING)) {
                return new Math_BigInteger($result, 256);
            }
        }

        if ( MATH_BIGINTEGER_MODE == MATH_BIGINTEGER_MODE_BCMATH ) {
                $temp = new Math_BigInteger();
                $temp->value = bcpowmod($this->value, $e->value, $n->value, 0);

                return $this->_normalize($temp);
        }

        if ( empty($e->value) ) {
            $temp = new Math_BigInteger();
            $temp->value = array(1);
            return $this->_normalize($temp);
        }

        if ( $e->value == array(1) ) {
            list(, $temp) = $this->divide($n);
            return $this->_normalize($temp);
        }

        if ( $e->value == array(2) ) {
            $temp = new Math_BigInteger();
            $temp->value = $this->_square($this->value);
            list(, $temp) = $temp->divide($n);
            return $this->_normalize($temp);
        }

        return $this->_normalize($this->_slidingWindow($e, $n, MATH_BIGINTEGER_BARRETT));

        // the following code, although not callable, can be run independently of the above code
        // although the above code performed better in my benchmarks the following could might
        // perform better under different circumstances. in lieu of deleting it it's just been
        // made uncallable

        // is the modulo odd?
        if ( $n->value[0] & 1 ) {
            return $this->_normalize($this->_slidingWindow($e, $n, MATH_BIGINTEGER_MONTGOMERY));
        }
        // if it's not, it's even

        // find the lowest set bit (eg. the max pow of 2 that divides $n)
        for ($i = 0; $i < count($n->value); ++$i) {
            if ( $n->value[$i] ) {
                $temp = decbin($n->value[$i]);
                $j = strlen($temp) - strrpos($temp, '1') - 1;
                $j+= 26 * $i;
                break;
            }
        }
        // at this point, 2^$j * $n/(2^$j) == $n

        $mod1 = $n->copy();
        $mod1->_rshift($j);
        $mod2 = new Math_BigInteger();
        $mod2->value = array(1);
        $mod2->_lshift($j);

        $part1 = ( $mod1->value != array(1) ) ? $this->_slidingWindow($e, $mod1, MATH_BIGINTEGER_MONTGOMERY) : new Math_BigInteger();
        $part2 = $this->_slidingWindow($e, $mod2, MATH_BIGINTEGER_POWEROF2);

        $y1 = $mod2->modInverse($mod1);
        $y2 = $mod1->modInverse($mod2);

        $result = $part1->multiply($mod2);
        $result = $result->multiply($y1);

        $temp = $part2->multiply($mod1);
        $temp = $temp->multiply($y2);

        $result = $result->add($temp);
        list(, $result) = $result->divide($n);

        return $this->_normalize($result);
    }

    /**
     * Performs modular exponentiation.
     *
     * Alias for Math_BigInteger::modPow()
     *
     * @param Math_BigInteger $e
     * @param Math_BigInteger $n
     * @return Math_BigInteger
     * @access public
     */
    function powMod($e, $n)
    {
        return $this->modPow($e, $n);
    }

    /**
     * Sliding Window k-ary Modular Exponentiation
     *
     * Based on {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=27 HAC 14.85} /
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=210 MPM 7.7}.  In a departure from those algorithims,
     * however, this function performs a modular reduction after every multiplication and squaring operation.
     * As such, this function has the same preconditions that the reductions being used do.
     *
     * @param Math_BigInteger $e
     * @param Math_BigInteger $n
     * @param Integer $mode
     * @return Math_BigInteger
     * @access private
     */
    function _slidingWindow($e, $n, $mode)
    {
        static $window_ranges = array(7, 25, 81, 241, 673, 1793); // from BigInteger.java's oddModPow function
        //static $window_ranges = array(0, 7, 36, 140, 450, 1303, 3529); // from MPM 7.3.1

        $e_value = $e->value;
        $e_length = count($e_value) - 1;
        $e_bits = decbin($e_value[$e_length]);
        for ($i = $e_length - 1; $i >= 0; --$i) {
            $e_bits.= str_pad(decbin($e_value[$i]), MATH_BIGINTEGER_BASE, '0', STR_PAD_LEFT);
        }

        $e_length = strlen($e_bits);

        // calculate the appropriate window size.
        // $window_size == 3 if $window_ranges is between 25 and 81, for example.
        for ($i = 0, $window_size = 1; $e_length > $window_ranges[$i] && $i < count($window_ranges); ++$window_size, ++$i);

        $n_value = $n->value;

        // precompute $this^0 through $this^$window_size
        $powers = array();
        $powers[1] = $this->_prepareReduce($this->value, $n_value, $mode);
        $powers[2] = $this->_squareReduce($powers[1], $n_value, $mode);

        // we do every other number since substr($e_bits, $i, $j+1) (see below) is supposed to end
        // in a 1.  ie. it's supposed to be odd.
        $temp = 1 << ($window_size - 1);
        for ($i = 1; $i < $temp; ++$i) {
            $i2 = $i << 1;
            $powers[$i2 + 1] = $this->_multiplyReduce($powers[$i2 - 1], $powers[2], $n_value, $mode);
        }

        $result = array(1);
        $result = $this->_prepareReduce($result, $n_value, $mode);

        for ($i = 0; $i < $e_length; ) {
            if ( !$e_bits[$i] ) {
                $result = $this->_squareReduce($result, $n_value, $mode);
                ++$i;
            } else {
                for ($j = $window_size - 1; $j > 0; --$j) {
                    if ( !empty($e_bits[$i + $j]) ) {
                        break;
                    }
                }

                for ($k = 0; $k <= $j; ++$k) {// eg. the length of substr($e_bits, $i, $j+1)
                    $result = $this->_squareReduce($result, $n_value, $mode);
                }

                $result = $this->_multiplyReduce($result, $powers[bindec(substr($e_bits, $i, $j + 1))], $n_value, $mode);

                $i+=$j + 1;
            }
        }

        $temp = new Math_BigInteger();
        $temp->value = $this->_reduce($result, $n_value, $mode);

        return $temp;
    }

    /**
     * Modular reduction
     *
     * For most $modes this will return the remainder.
     *
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $n
     * @param Integer $mode
     * @return Array
     */
    function _reduce($x, $n, $mode)
    {
        switch ($mode) {
            case MATH_BIGINTEGER_MONTGOMERY:
                return $this->_montgomery($x, $n);
            case MATH_BIGINTEGER_BARRETT:
                return $this->_barrett($x, $n);
            case MATH_BIGINTEGER_POWEROF2:
                $lhs = new Math_BigInteger();
                $lhs->value = $x;
                $rhs = new Math_BigInteger();
                $rhs->value = $n;
                return $x->_mod2($n);
            case MATH_BIGINTEGER_CLASSIC:
                $lhs = new Math_BigInteger();
                $lhs->value = $x;
                $rhs = new Math_BigInteger();
                $rhs->value = $n;
                list(, $temp) = $lhs->divide($rhs);
                return $temp->value;
            case MATH_BIGINTEGER_NONE:
                return $x;
            default:
                // an invalid $mode was provided
        }
    }

    /**
     * Modular reduction preperation
     *
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $n
     * @param Integer $mode
     * @return Array
     */
    function _prepareReduce($x, $n, $mode)
    {
        if ($mode == MATH_BIGINTEGER_MONTGOMERY) {
            return $this->_prepMontgomery($x, $n);
        }
        return $this->_reduce($x, $n, $mode);
    }

    /**
     * Modular multiply
     *
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $y
     * @param Array $n
     * @param Integer $mode
     * @return Array
     */
    function _multiplyReduce($x, $y, $n, $mode)
    {
        if ($mode == MATH_BIGINTEGER_MONTGOMERY) {
            return $this->_montgomeryMultiply($x, $y, $n);
        }
        $temp = $this->_multiply($x, false, $y, false);
        return $this->_reduce($temp[MATH_BIGINTEGER_VALUE], $n, $mode);
    }

    /**
     * Modular square
     *
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $n
     * @param Integer $mode
     * @return Array
     */
    function _squareReduce($x, $n, $mode)
    {
        if ($mode == MATH_BIGINTEGER_MONTGOMERY) {
            return $this->_montgomeryMultiply($x, $x, $n);
        }
        return $this->_reduce($this->_square($x), $n, $mode);
    }

    /**
     * Modulos for Powers of Two
     *
     * Calculates $x%$n, where $n = 2**$e, for some $e.  Since this is basically the same as doing $x & ($n-1),
     * we'll just use this function as a wrapper for doing that.
     *
     * @see _slidingWindow()
     * @access private
     * @param Math_BigInteger
     * @return Math_BigInteger
     */
    function _mod2($n)
    {
        $temp = new Math_BigInteger();
        $temp->value = array(1);
        return $this->bitwise_and($n->subtract($temp));
    }

    /**
     * Barrett Modular Reduction
     *
     * See {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=14 HAC 14.3.3} /
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=165 MPM 6.2.5} for more information.  Modified slightly,
     * so as not to require negative numbers (initially, this script didn't support negative numbers).
     *
     * Employs "folding", as described at
     * {@link http://www.cosic.esat.kuleuven.be/publications/thesis-149.pdf#page=66 thesis-149.pdf#page=66}.  To quote from
     * it, "the idea [behind folding] is to find a value x' such that x (mod m) = x' (mod m), with x' being smaller than x."
     *
     * Unfortunately, the "Barrett Reduction with Folding" algorithm described in thesis-149.pdf is not, as written, all that
     * usable on account of (1) its not using reasonable radix points as discussed in
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=162 MPM 6.2.2} and (2) the fact that, even with reasonable
     * radix points, it only works when there are an even number of digits in the denominator.  The reason for (2) is that
     * (x >> 1) + (x >> 1) != x / 2 + x / 2.  If x is even, they're the same, but if x is odd, they're not.  See the in-line
     * comments for details.
     *
     * @see _slidingWindow()
     * @access private
     * @param Array $n
     * @param Array $m
     * @return Array
     */
    function _barrett($n, $m)
    {
        static $cache = array(
            MATH_BIGINTEGER_VARIABLE => array(),
            MATH_BIGINTEGER_DATA => array()
        );

        $m_length = count($m);

        // if ($this->_compare($n, $this->_square($m)) >= 0) {
        if (count($n) > 2 * $m_length) {
            $lhs = new Math_BigInteger();
            $rhs = new Math_BigInteger();
            $lhs->value = $n;
            $rhs->value = $m;
            list(, $temp) = $lhs->divide($rhs);
            return $temp->value;
        }

        // if (m.length >> 1) + 2 <= m.length then m is too small and n can't be reduced
        if ($m_length < 5) {
            return $this->_regularBarrett($n, $m);
        }

        // n = 2 * m.length

        if ( ($key = array_search($m, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
            $key = count($cache[MATH_BIGINTEGER_VARIABLE]);
            $cache[MATH_BIGINTEGER_VARIABLE][] = $m;

            $lhs = new Math_BigInteger();
            $lhs_value = &$lhs->value;
            $lhs_value = $this->_array_repeat(0, $m_length + ($m_length >> 1));
            $lhs_value[] = 1;
            $rhs = new Math_BigInteger();
            $rhs->value = $m;

            list($u, $m1) = $lhs->divide($rhs);
            $u = $u->value;
            $m1 = $m1->value;

            $cache[MATH_BIGINTEGER_DATA][] = array(
                'u' => $u, // m.length >> 1 (technically (m.length >> 1) + 1)
                'm1'=> $m1 // m.length
            );
        } else {
            extract($cache[MATH_BIGINTEGER_DATA][$key]);
        }

        $cutoff = $m_length + ($m_length >> 1);
        $lsd = array_slice($n, 0, $cutoff); // m.length + (m.length >> 1)
        $msd = array_slice($n, $cutoff);    // m.length >> 1
        $lsd = $this->_trim($lsd);
        $temp = $this->_multiply($msd, false, $m1, false);
        $n = $this->_add($lsd, false, $temp[MATH_BIGINTEGER_VALUE], false); // m.length + (m.length >> 1) + 1

        if ($m_length & 1) {
            return $this->_regularBarrett($n[MATH_BIGINTEGER_VALUE], $m);
        }

        // (m.length + (m.length >> 1) + 1) - (m.length - 1) == (m.length >> 1) + 2
        $temp = array_slice($n[MATH_BIGINTEGER_VALUE], $m_length - 1);
        // if even: ((m.length >> 1) + 2) + (m.length >> 1) == m.length + 2
        // if odd:  ((m.length >> 1) + 2) + (m.length >> 1) == (m.length - 1) + 2 == m.length + 1
        $temp = $this->_multiply($temp, false, $u, false);
        // if even: (m.length + 2) - ((m.length >> 1) + 1) = m.length - (m.length >> 1) + 1
        // if odd:  (m.length + 1) - ((m.length >> 1) + 1) = m.length - (m.length >> 1)
        $temp = array_slice($temp[MATH_BIGINTEGER_VALUE], ($m_length >> 1) + 1);
        // if even: (m.length - (m.length >> 1) + 1) + m.length = 2 * m.length - (m.length >> 1) + 1
        // if odd:  (m.length - (m.length >> 1)) + m.length     = 2 * m.length - (m.length >> 1)
        $temp = $this->_multiply($temp, false, $m, false);

        // at this point, if m had an odd number of digits, we'd be subtracting a 2 * m.length - (m.length >> 1) digit
        // number from a m.length + (m.length >> 1) + 1 digit number.  ie. there'd be an extra digit and the while loop
        // following this comment would loop a lot (hence our calling _regularBarrett() in that situation).

        $result = $this->_subtract($n[MATH_BIGINTEGER_VALUE], false, $temp[MATH_BIGINTEGER_VALUE], false);

        while ($this->_compare($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $m, false) >= 0) {
            $result = $this->_subtract($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $m, false);
        }

        return $result[MATH_BIGINTEGER_VALUE];
    }

    /**
     * (Regular) Barrett Modular Reduction
     *
     * For numbers with more than four digits Math_BigInteger::_barrett() is faster.  The difference between that and this
     * is that this function does not fold the denominator into a smaller form.
     *
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $n
     * @return Array
     */
    function _regularBarrett($x, $n)
    {
        static $cache = array(
            MATH_BIGINTEGER_VARIABLE => array(),
            MATH_BIGINTEGER_DATA => array()
        );

        $n_length = count($n);

        if (count($x) > 2 * $n_length) {
            $lhs = new Math_BigInteger();
            $rhs = new Math_BigInteger();
            $lhs->value = $x;
            $rhs->value = $n;
            list(, $temp) = $lhs->divide($rhs);
            return $temp->value;
        }

        if ( ($key = array_search($n, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
            $key = count($cache[MATH_BIGINTEGER_VARIABLE]);
            $cache[MATH_BIGINTEGER_VARIABLE][] = $n;
            $lhs = new Math_BigInteger();
            $lhs_value = &$lhs->value;
            $lhs_value = $this->_array_repeat(0, 2 * $n_length);
            $lhs_value[] = 1;
            $rhs = new Math_BigInteger();
            $rhs->value = $n;
            list($temp, ) = $lhs->divide($rhs); // m.length
            $cache[MATH_BIGINTEGER_DATA][] = $temp->value;
        }

        // 2 * m.length - (m.length - 1) = m.length + 1
        $temp = array_slice($x, $n_length - 1);
        // (m.length + 1) + m.length = 2 * m.length + 1
        $temp = $this->_multiply($temp, false, $cache[MATH_BIGINTEGER_DATA][$key], false);
        // (2 * m.length + 1) - (m.length - 1) = m.length + 2
        $temp = array_slice($temp[MATH_BIGINTEGER_VALUE], $n_length + 1);

        // m.length + 1
        $result = array_slice($x, 0, $n_length + 1);
        // m.length + 1
        $temp = $this->_multiplyLower($temp, false, $n, false, $n_length + 1);
        // $temp == array_slice($temp->_multiply($temp, false, $n, false)->value, 0, $n_length + 1)

        if ($this->_compare($result, false, $temp[MATH_BIGINTEGER_VALUE], $temp[MATH_BIGINTEGER_SIGN]) < 0) {
            $corrector_value = $this->_array_repeat(0, $n_length + 1);
            $corrector_value[count($corrector_value)] = 1;
            $result = $this->_add($result, false, $corrector_value, false);
            $result = $result[MATH_BIGINTEGER_VALUE];
        }

        // at this point, we're subtracting a number with m.length + 1 digits from another number with m.length + 1 digits
        $result = $this->_subtract($result, false, $temp[MATH_BIGINTEGER_VALUE], $temp[MATH_BIGINTEGER_SIGN]);
        while ($this->_compare($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $n, false) > 0) {
            $result = $this->_subtract($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $n, false);
        }

        return $result[MATH_BIGINTEGER_VALUE];
    }

    /**
     * Performs long multiplication up to $stop digits
     *
     * If you're going to be doing array_slice($product->value, 0, $stop), some cycles can be saved.
     *
     * @see _regularBarrett()
     * @param Array $x_value
     * @param Boolean $x_negative
     * @param Array $y_value
     * @param Boolean $y_negative
     * @param Integer $stop
     * @return Array
     * @access private
     */
    function _multiplyLower($x_value, $x_negative, $y_value, $y_negative, $stop)
    {
        $x_length = count($x_value);
        $y_length = count($y_value);

        if ( !$x_length || !$y_length ) { // a 0 is being multiplied
            return array(
                MATH_BIGINTEGER_VALUE => array(),
                MATH_BIGINTEGER_SIGN => false
            );
        }

        if ( $x_length < $y_length ) {
            $temp = $x_value;
            $x_value = $y_value;
            $y_value = $temp;

            $x_length = count($x_value);
            $y_length = count($y_value);
        }

        $product_value = $this->_array_repeat(0, $x_length + $y_length);

        // the following for loop could be removed if the for loop following it
        // (the one with nested for loops) initially set $i to 0, but
        // doing so would also make the result in one set of unnecessary adds,
        // since on the outermost loops first pass, $product->value[$k] is going
        // to always be 0

        $carry = 0;

        for ($j = 0; $j < $x_length; ++$j) { // ie. $i = 0, $k = $i
            $temp = $x_value[$j] * $y_value[0] + $carry; // $product_value[$k] == 0
            $carry = MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31);
            $product_value[$j] = (int) ($temp - MATH_BIGINTEGER_BASE_FULL * $carry);
        }

        if ($j < $stop) {
            $product_value[$j] = $carry;
        }

        // the above for loop is what the previous comment was talking about.  the
        // following for loop is the "one with nested for loops"

        for ($i = 1; $i < $y_length; ++$i) {
            $carry = 0;

            for ($j = 0, $k = $i; $j < $x_length && $k < $stop; ++$j, ++$k) {
                $temp = $product_value[$k] + $x_value[$j] * $y_value[$i] + $carry;
                $carry = MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31);
                $product_value[$k] = (int) ($temp - MATH_BIGINTEGER_BASE_FULL * $carry);
            }

            if ($k < $stop) {
                $product_value[$k] = $carry;
            }
        }

        return array(
            MATH_BIGINTEGER_VALUE => $this->_trim($product_value),
            MATH_BIGINTEGER_SIGN => $x_negative != $y_negative
        );
    }

    /**
     * Montgomery Modular Reduction
     *
     * ($x->_prepMontgomery($n))->_montgomery($n) yields $x % $n.
     * {@link http://math.libtomcrypt.com/files/tommath.pdf#page=170 MPM 6.3} provides insights on how this can be
     * improved upon (basically, by using the comba method).  gcd($n, 2) must be equal to one for this function
     * to work correctly.
     *
     * @see _prepMontgomery()
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $n
     * @return Array
     */
    function _montgomery($x, $n)
    {
        static $cache = array(
            MATH_BIGINTEGER_VARIABLE => array(),
            MATH_BIGINTEGER_DATA => array()
        );

        if ( ($key = array_search($n, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
            $key = count($cache[MATH_BIGINTEGER_VARIABLE]);
            $cache[MATH_BIGINTEGER_VARIABLE][] = $x;
            $cache[MATH_BIGINTEGER_DATA][] = $this->_modInverse67108864($n);
        }

        $k = count($n);

        $result = array(MATH_BIGINTEGER_VALUE => $x);

        for ($i = 0; $i < $k; ++$i) {
            $temp = $result[MATH_BIGINTEGER_VALUE][$i] * $cache[MATH_BIGINTEGER_DATA][$key];
            $temp = $temp - MATH_BIGINTEGER_BASE_FULL * (MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31));
            $temp = $this->_regularMultiply(array($temp), $n);
            $temp = array_merge($this->_array_repeat(0, $i), $temp);
            $result = $this->_add($result[MATH_BIGINTEGER_VALUE], false, $temp, false);
        }

        $result[MATH_BIGINTEGER_VALUE] = array_slice($result[MATH_BIGINTEGER_VALUE], $k);

        if ($this->_compare($result, false, $n, false) >= 0) {
            $result = $this->_subtract($result[MATH_BIGINTEGER_VALUE], false, $n, false);
        }

        return $result[MATH_BIGINTEGER_VALUE];
    }

    /**
     * Montgomery Multiply
     *
     * Interleaves the montgomery reduction and long multiplication algorithms together as described in
     * {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=13 HAC 14.36}
     *
     * @see _prepMontgomery()
     * @see _montgomery()
     * @access private
     * @param Array $x
     * @param Array $y
     * @param Array $m
     * @return Array
     */
    function _montgomeryMultiply($x, $y, $m)
    {
        $temp = $this->_multiply($x, false, $y, false);
        return $this->_montgomery($temp[MATH_BIGINTEGER_VALUE], $m);

        // the following code, although not callable, can be run independently of the above code
        // although the above code performed better in my benchmarks the following could might
        // perform better under different circumstances. in lieu of deleting it it's just been
        // made uncallable

        static $cache = array(
            MATH_BIGINTEGER_VARIABLE => array(),
            MATH_BIGINTEGER_DATA => array()
        );

        if ( ($key = array_search($m, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
            $key = count($cache[MATH_BIGINTEGER_VARIABLE]);
            $cache[MATH_BIGINTEGER_VARIABLE][] = $m;
            $cache[MATH_BIGINTEGER_DATA][] = $this->_modInverse67108864($m);
        }

        $n = max(count($x), count($y), count($m));
        $x = array_pad($x, $n, 0);
        $y = array_pad($y, $n, 0);
        $m = array_pad($m, $n, 0);
        $a = array(MATH_BIGINTEGER_VALUE => $this->_array_repeat(0, $n + 1));
        for ($i = 0; $i < $n; ++$i) {
            $temp = $a[MATH_BIGINTEGER_VALUE][0] + $x[$i] * $y[0];
            $temp = $temp - MATH_BIGINTEGER_BASE_FULL * (MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31));
            $temp = $temp * $cache[MATH_BIGINTEGER_DATA][$key];
            $temp = $temp - MATH_BIGINTEGER_BASE_FULL * (MATH_BIGINTEGER_BASE === 26 ? intval($temp / 0x4000000) : ($temp >> 31));
            $temp = $this->_add($this->_regularMultiply(array($x[$i]), $y), false, $this->_regularMultiply(array($temp), $m), false);
            $a = $this->_add($a[MATH_BIGINTEGER_VALUE], false, $temp[MATH_BIGINTEGER_VALUE], false);
            $a[MATH_BIGINTEGER_VALUE] = array_slice($a[MATH_BIGINTEGER_VALUE], 1);
        }
        if ($this->_compare($a[MATH_BIGINTEGER_VALUE], false, $m, false) >= 0) {
            $a = $this->_subtract($a[MATH_BIGINTEGER_VALUE], false, $m, false);
        }
        return $a[MATH_BIGINTEGER_VALUE];
    }

    /**
     * Prepare a number for use in Montgomery Modular Reductions
     *
     * @see _montgomery()
     * @see _slidingWindow()
     * @access private
     * @param Array $x
     * @param Array $n
     * @return Array
     */
    function _prepMontgomery($x, $n)
    {
        $lhs = new Math_BigInteger();
        $lhs->value = array_merge($this->_array_repeat(0, count($n)), $x);
        $rhs = new Math_BigInteger();
        $rhs->value = $n;

        list(, $temp) = $lhs->divide($rhs);
        return $temp->value;
    }

    /**
     * Modular Inverse of a number mod 2**26 (eg. 67108864)
     *
     * Based off of the bnpInvDigit function implemented and justified in the following URL:
     *
     * {@link http://www-cs-students.stanford.edu/~tjw/jsbn/jsbn.js}
     *
     * The following URL provides more info:
     *
     * {@link http://groups.google.com/group/sci.crypt/msg/7a137205c1be7d85}
     *
     * As for why we do all the bitmasking...  strange things can happen when converting from floats to ints. For
     * instance, on some computers, var_dump((int) -4294967297) yields int(-1) and on others, it yields
     * int(-2147483648).  To avoid problems stemming from this, we use bitmasks to guarantee that ints aren't
     * auto-converted to floats.  The outermost bitmask is present because without it, there's no guarantee that
     * the "residue" returned would be the so-called "common residue".  We use fmod, in the last step, because the
     * maximum possible $x is 26 bits and the maximum $result is 16 bits.  Thus, we have to be able to handle up to
     * 40 bits, which only 64-bit floating points will support.
     *
     * Thanks to Pedro Gimeno Fortea for input!
     *
     * @see _montgomery()
     * @access private
     * @param Array $x
     * @return Integer
     */
    function _modInverse67108864($x) // 2**26 == 67,108,864
    {
        $x = -$x[0];
        $result = $x & 0x3; // x**-1 mod 2**2
        $result = ($result * (2 - $x * $result)) & 0xF; // x**-1 mod 2**4
        $result = ($result * (2 - ($x & 0xFF) * $result))  & 0xFF; // x**-1 mod 2**8
        $result = ($result * ((2 - ($x & 0xFFFF) * $result) & 0xFFFF)) & 0xFFFF; // x**-1 mod 2**16
        $result = fmod($result * (2 - fmod($x * $result, MATH_BIGINTEGER_BASE_FULL)), MATH_BIGINTEGER_BASE_FULL); // x**-1 mod 2**26
        return $result & MATH_BIGINTEGER_MAX_DIGIT;
    }

    /**
     * Calculates modular inverses.
     *
     * Say you have (30 mod 17 * x mod 17) mod 17 == 1.  x can be found using modular inverses.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger(30);
     *    $b = new Math_BigInteger(17);
     *
     *    $c = $a->modInverse($b);
     *    echo $c->toString(); // outputs 4
     *
     *    echo "\r\n";
     *
     *    $d = $a->multiply($c);
     *    list(, $d) = $d->divide($b);
     *    echo $d; // outputs 1 (as per the definition of modular inverse)
     * ?>
     * </code>
     *
     * @param Math_BigInteger $n
     * @return mixed false, if no modular inverse exists, Math_BigInteger, otherwise.
     * @access public
     * @internal See {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=21 HAC 14.64} for more information.
     */
    function modInverse($n)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_invert($this->value, $n->value);

                return ( $temp->value === false ) ? false : $this->_normalize($temp);
        }

        static $zero, $one;
        if (!isset($zero)) {
            $zero = new Math_BigInteger();
            $one = new Math_BigInteger(1);
        }

        // $x mod -$n == $x mod $n.
        $n = $n->abs();

        if ($this->compare($zero) < 0) {
            $temp = $this->abs();
            $temp = $temp->modInverse($n);
            return $this->_normalize($n->subtract($temp));
        }

        extract($this->extendedGCD($n));

        if (!$gcd->equals($one)) {
            return false;
        }

        $x = $x->compare($zero) < 0 ? $x->add($n) : $x;

        return $this->compare($zero) < 0 ? $this->_normalize($n->subtract($x)) : $this->_normalize($x);
    }

    /**
     * Calculates the greatest common divisor and Bezout's identity.
     *
     * Say you have 693 and 609.  The GCD is 21.  Bezout's identity states that there exist integers x and y such that
     * 693*x + 609*y == 21.  In point of fact, there are actually an infinite number of x and y combinations and which
     * combination is returned is dependant upon which mode is in use.  See
     * {@link http://en.wikipedia.org/wiki/B%C3%A9zout%27s_identity Bezout's identity - Wikipedia} for more information.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger(693);
     *    $b = new Math_BigInteger(609);
     *
     *    extract($a->extendedGCD($b));
     *
     *    echo $gcd->toString() . "\r\n"; // outputs 21
     *    echo $a->toString() * $x->toString() + $b->toString() * $y->toString(); // outputs 21
     * ?>
     * </code>
     *
     * @param Math_BigInteger $n
     * @return Math_BigInteger
     * @access public
     * @internal Calculates the GCD using the binary xGCD algorithim described in
     *    {@link http://www.cacr.math.uwaterloo.ca/hac/about/chap14.pdf#page=19 HAC 14.61}.  As the text above 14.61 notes,
     *    the more traditional algorithim requires "relatively costly multiple-precision divisions".
     */
    function extendedGCD($n)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                extract(gmp_gcdext($this->value, $n->value));

                return array(
                    'gcd' => $this->_normalize(new Math_BigInteger($g)),
                    'x'   => $this->_normalize(new Math_BigInteger($s)),
                    'y'   => $this->_normalize(new Math_BigInteger($t))
                );
            case MATH_BIGINTEGER_MODE_BCMATH:
                // it might be faster to use the binary xGCD algorithim here, as well, but (1) that algorithim works
                // best when the base is a power of 2 and (2) i don't think it'd make much difference, anyway.  as is,
                // the basic extended euclidean algorithim is what we're using.

                $u = $this->value;
                $v = $n->value;

                $a = '1';
                $b = '0';
                $c = '0';
                $d = '1';

                while (bccomp($v, '0', 0) != 0) {
                    $q = bcdiv($u, $v, 0);

                    $temp = $u;
                    $u = $v;
                    $v = bcsub($temp, bcmul($v, $q, 0), 0);

                    $temp = $a;
                    $a = $c;
                    $c = bcsub($temp, bcmul($a, $q, 0), 0);

                    $temp = $b;
                    $b = $d;
                    $d = bcsub($temp, bcmul($b, $q, 0), 0);
                }

                return array(
                    'gcd' => $this->_normalize(new Math_BigInteger($u)),
                    'x'   => $this->_normalize(new Math_BigInteger($a)),
                    'y'   => $this->_normalize(new Math_BigInteger($b))
                );
        }

        $y = $n->copy();
        $x = $this->copy();
        $g = new Math_BigInteger();
        $g->value = array(1);

        while ( !(($x->value[0] & 1)|| ($y->value[0] & 1)) ) {
            $x->_rshift(1);
            $y->_rshift(1);
            $g->_lshift(1);
        }

        $u = $x->copy();
        $v = $y->copy();

        $a = new Math_BigInteger();
        $b = new Math_BigInteger();
        $c = new Math_BigInteger();
        $d = new Math_BigInteger();

        $a->value = $d->value = $g->value = array(1);
        $b->value = $c->value = array();

        while ( !empty($u->value) ) {
            while ( !($u->value[0] & 1) ) {
                $u->_rshift(1);
                if ( (!empty($a->value) && ($a->value[0] & 1)) || (!empty($b->value) && ($b->value[0] & 1)) ) {
                    $a = $a->add($y);
                    $b = $b->subtract($x);
                }
                $a->_rshift(1);
                $b->_rshift(1);
            }

            while ( !($v->value[0] & 1) ) {
                $v->_rshift(1);
                if ( (!empty($d->value) && ($d->value[0] & 1)) || (!empty($c->value) && ($c->value[0] & 1)) ) {
                    $c = $c->add($y);
                    $d = $d->subtract($x);
                }
                $c->_rshift(1);
                $d->_rshift(1);
            }

            if ($u->compare($v) >= 0) {
                $u = $u->subtract($v);
                $a = $a->subtract($c);
                $b = $b->subtract($d);
            } else {
                $v = $v->subtract($u);
                $c = $c->subtract($a);
                $d = $d->subtract($b);
            }
        }

        return array(
            'gcd' => $this->_normalize($g->multiply($v)),
            'x'   => $this->_normalize($c),
            'y'   => $this->_normalize($d)
        );
    }

    /**
     * Calculates the greatest common divisor
     *
     * Say you have 693 and 609.  The GCD is 21.
     *
     * Here's an example:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigInteger(693);
     *    $b = new Math_BigInteger(609);
     *
     *    $gcd = a->extendedGCD($b);
     *
     *    echo $gcd->toString() . "\r\n"; // outputs 21
     * ?>
     * </code>
     *
     * @param Math_BigInteger $n
     * @return Math_BigInteger
     * @access public
     */
    function gcd($n)
    {
        extract($this->extendedGCD($n));
        return $gcd;
    }

    /**
     * Absolute value.
     *
     * @return Math_BigInteger
     * @access public
     */
    function abs()
    {
        $temp = new Math_BigInteger();

        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp->value = gmp_abs($this->value);
                break;
            case MATH_BIGINTEGER_MODE_BCMATH:
                $temp->value = (bccomp($this->value, '0', 0) < 0) ? substr($this->value, 1) : $this->value;
                break;
            default:
                $temp->value = $this->value;
        }

        return $temp;
    }

    /**
     * Compares two numbers.
     *
     * Although one might think !$x->compare($y) means $x != $y, it, in fact, means the opposite.  The reason for this is
     * demonstrated thusly:
     *
     * $x  > $y: $x->compare($y)  > 0
     * $x  < $y: $x->compare($y)  < 0
     * $x == $y: $x->compare($y) == 0
     *
     * Note how the same comparison operator is used.  If you want to test for equality, use $x->equals($y).
     *
     * @param Math_BigInteger $y
     * @return Integer < 0 if $this is less than $y; > 0 if $this is greater than $y, and 0 if they are equal.
     * @access public
     * @see equals()
     * @internal Could return $this->subtract($x), but that's not as fast as what we do do.
     */
    function compare($y)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                return gmp_cmp($this->value, $y->value);
            case MATH_BIGINTEGER_MODE_BCMATH:
                return bccomp($this->value, $y->value, 0);
        }

        return $this->_compare($this->value, $this->is_negative, $y->value, $y->is_negative);
    }

    /**
     * Compares two numbers.
     *
     * @param Array $x_value
     * @param Boolean $x_negative
     * @param Array $y_value
     * @param Boolean $y_negative
     * @return Integer
     * @see compare()
     * @access private
     */
    function _compare($x_value, $x_negative, $y_value, $y_negative)
    {
        if ( $x_negative != $y_negative ) {
            return ( !$x_negative && $y_negative ) ? 1 : -1;
        }

        $result = $x_negative ? -1 : 1;

        if ( count($x_value) != count($y_value) ) {
            return ( count($x_value) > count($y_value) ) ? $result : -$result;
        }
        $size = max(count($x_value), count($y_value));

        $x_value = array_pad($x_value, $size, 0);
        $y_value = array_pad($y_value, $size, 0);

        for ($i = count($x_value) - 1; $i >= 0; --$i) {
            if ($x_value[$i] != $y_value[$i]) {
                return ( $x_value[$i] > $y_value[$i] ) ? $result : -$result;
            }
        }

        return 0;
    }

    /**
     * Tests the equality of two numbers.
     *
     * If you need to see if one number is greater than or less than another number, use Math_BigInteger::compare()
     *
     * @param Math_BigInteger $x
     * @return Boolean
     * @access public
     * @see compare()
     */
    function equals($x)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                return gmp_cmp($this->value, $x->value) == 0;
            default:
                return $this->value === $x->value && $this->is_negative == $x->is_negative;
        }
    }

    /**
     * Set Precision
     *
     * Some bitwise operations give different results depending on the precision being used.  Examples include left
     * shift, not, and rotates.
     *
     * @param Integer $bits
     * @access public
     */
    function setPrecision($bits)
    {
        $this->precision = $bits;
        if ( MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_BCMATH ) {
            $this->bitmask = new Math_BigInteger(chr((1 << ($bits & 0x7)) - 1) . str_repeat(chr(0xFF), $bits >> 3), 256);
        } else {
            $this->bitmask = new Math_BigInteger(bcpow('2', $bits, 0));
        }

        $temp = $this->_normalize($this);
        $this->value = $temp->value;
    }

    /**
     * Logical And
     *
     * @param Math_BigInteger $x
     * @access public
     * @internal Implemented per a request by Lluis Pamies i Juarez <lluis _a_ pamies.cat>
     * @return Math_BigInteger
     */
    function bitwise_and($x)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_and($this->value, $x->value);

                return $this->_normalize($temp);
            case MATH_BIGINTEGER_MODE_BCMATH:
                $left = $this->toBytes();
                $right = $x->toBytes();

                $length = max(strlen($left), strlen($right));

                $left = str_pad($left, $length, chr(0), STR_PAD_LEFT);
                $right = str_pad($right, $length, chr(0), STR_PAD_LEFT);

                return $this->_normalize(new Math_BigInteger($left & $right, 256));
        }

        $result = $this->copy();

        $length = min(count($x->value), count($this->value));

        $result->value = array_slice($result->value, 0, $length);

        for ($i = 0; $i < $length; ++$i) {
            $result->value[$i]&= $x->value[$i];
        }

        return $this->_normalize($result);
    }

    /**
     * Logical Or
     *
     * @param Math_BigInteger $x
     * @access public
     * @internal Implemented per a request by Lluis Pamies i Juarez <lluis _a_ pamies.cat>
     * @return Math_BigInteger
     */
    function bitwise_or($x)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_or($this->value, $x->value);

                return $this->_normalize($temp);
            case MATH_BIGINTEGER_MODE_BCMATH:
                $left = $this->toBytes();
                $right = $x->toBytes();

                $length = max(strlen($left), strlen($right));

                $left = str_pad($left, $length, chr(0), STR_PAD_LEFT);
                $right = str_pad($right, $length, chr(0), STR_PAD_LEFT);

                return $this->_normalize(new Math_BigInteger($left | $right, 256));
        }

        $length = max(count($this->value), count($x->value));
        $result = $this->copy();
        $result->value = array_pad($result->value, $length, 0);
        $x->value = array_pad($x->value, $length, 0);

        for ($i = 0; $i < $length; ++$i) {
            $result->value[$i]|= $x->value[$i];
        }

        return $this->_normalize($result);
    }

    /**
     * Logical Exclusive-Or
     *
     * @param Math_BigInteger $x
     * @access public
     * @internal Implemented per a request by Lluis Pamies i Juarez <lluis _a_ pamies.cat>
     * @return Math_BigInteger
     */
    function bitwise_xor($x)
    {
        switch ( MATH_BIGINTEGER_MODE ) {
            case MATH_BIGINTEGER_MODE_GMP:
                $temp = new Math_BigInteger();
                $temp->value = gmp_xor($this->value, $x->value);

                return $this->_normalize($temp);
            case MATH_BIGINTEGER_MODE_BCMATH:
                $left = $this->toBytes();
                $right = $x->toBytes();

                $length = max(strlen($left), strlen($right));

                $left = str_pad($left, $length, chr(0), STR_PAD_LEFT);
                $right = str_pad($right, $length, chr(0), STR_PAD_LEFT);

                return $this->_normalize(new Math_BigInteger($left ^ $right, 256));
        }

        $length = max(count($this->value), count($x->value));
        $result = $this->copy();
        $result->value = array_pad($result->value, $length, 0);
        $x->value = array_pad($x->value, $length, 0);

        for ($i = 0; $i < $length; ++$i) {
            $result->value[$i]^= $x->value[$i];
        }

        return $this->_normalize($result);
    }

    /**
     * Logical Not
     *
     * @access public
     * @internal Implemented per a request by Lluis Pamies i Juarez <lluis _a_ pamies.cat>
     * @return Math_BigInteger
     */
    function bitwise_not()
    {
        // calculuate "not" without regard to $this->precision
        // (will always result in a smaller number.  ie. ~1 isn't 1111 1110 - it's 0)
        $temp = $this->toBytes();
        $pre_msb = decbin(ord($temp[0]));
        $temp = ~$temp;
        $msb = decbin(ord($temp[0]));
        if (strlen($msb) == 8) {
            $msb = substr($msb, strpos($msb, '0'));
        }
        $temp[0] = chr(bindec($msb));

        // see if we need to add extra leading 1's
        $current_bits = strlen($pre_msb) + 8 * strlen($temp) - 8;
        $new_bits = $this->precision - $current_bits;
        if ($new_bits <= 0) {
            return $this->_normalize(new Math_BigInteger($temp, 256));
        }

        // generate as many leading 1's as we need to.
        $leading_ones = chr((1 << ($new_bits & 0x7)) - 1) . str_repeat(chr(0xFF), $new_bits >> 3);
        $this->_base256_lshift($leading_ones, $current_bits);

        $temp = str_pad($temp, strlen($leading_ones), chr(0), STR_PAD_LEFT);

 ary prereturn $this->_normalize(new Math_BigInteger($leading_ones | $temp, 256));rary }trary /**rary p* Logical Right Shiftumbers.umbers. or BCs  *
 * Supp's by $sr BC bits, effectively divibaseand 2**an int.Math extensions@param  * Supp an intons 4 andision iary.
 *
 * Suppons 4 andaccess publicons 4 andinternal The only version that yields any speed increases is the one that  refer HP versi/rary functer tbitwise_r GMPor BC(an int)rary {rary prec-10,  = library.
 *
 * Suppobitrary precswitch ( MATH_BIGINTEGER_MODE )s base-2**2. twcasend division and
 * ba_GMP:**52 (ie. twoiplictatic $twoitrary prece largesif (!isset(n.  )e-2**52 (ie. twoase-2**26 wo = gmp_init('2'6, and er is a basebase-25number, doub-10, ->value-2**26 div_q(nteger sion ,**26 pow * va, an int16, cause the largestbreakogether is a b base 2**26 digits) to perBCd dim addition and sube precision flobcdivpoint numbers -bcbers'2't should, *
 *0be supported on most hardware and whosedefault: // could just replace _ln intewith tege, butDE_In allhough th() calls wts innTH_Bto be rewrittencause the largestd sho// and I don't wantsuchdoto th..HP vers As a consequence, bitwiseoint numbersogether is a base-e preci_rary doshould ogether is2**52 numbeision integer arithmetic -10, 6, and base-256 numbers.  Uses thLefP or BCMath extensions, if available,
 * and an internal implementatiomultiplyherwise.
 *
 * PHP versions 4 and 5
 *
 * {@internal (all DocBlock comments regarding implementation - such as the one that follows - refer to the
 * {@link MATH_BIGINTEGER_MODE_INTERNAL MATH_BIGINTEGER_MODE_INTERNAL} mode)
 *
lefath_BigInteger uses base-2**26 to perform operations such as multiplication and division and
 * base-2**52 (ie. two base 2**26 digits) to perform addition and subtraction.  Because the largest possible
 * value when multiplying two base-2**26 numbers together is a base-2**52 number, double precision floatinmulpoint numbers - numbers that should be supported on most hardware and whose significand is 53 bits - are
 * used.  As a consequence, bitwise oeby granted, free > and << cannot be used, nor can the modulo operator %,
 * which only supports integers.  Althotle enis fact will slow this library down, the fact that such a high
 * base is being used should more than compensate.
 *
 * Numbers are stored in {@link http://en.wikipedia.org/wiki/Endianness litrary doian} format.  ie.
 * (new Math_BigInteger(pow(2, 26)))->value = array(0, 1)
 *
 * Useful resourcRotateMath extensionsInstead ofDE_INtop xernal beherwdroppt suhey're appendt suchE_INn intedmode stringHP versions 4 and 5
 *
 * {@internal (all DocBlock comments regarding implementation - such as thava/math in jdk-1_5_0-src-jftwaregInteger uses base-2**26 IS",p://en.witoBytesh as multipli posnteger precifer t> 0e-2**52 (ie. two$AMAGES OR p://en.wiAMAGES ORogether is a b posnd division and
 * bas==nificand is 53 bits - are
 *se-2**52 (ie. twoITY, maskN AN ACTIObitSE O->subtract library.
 *
 * Suppo116, and WARE OR THE USE OR OTNGS INDERS BE LIAr is a base-2 elseHE SOFTWARE OR THE USE OR OTHER DEALINGS INeger
 * @author    Jim Wmat.  ie.
igginton <terrafrost6 to perord(R COP[0]@author    Jim for ($i = 0;g/licen>> $i; ++$i@author    Jim  WHETHER IN A8 *IED,lenit-lic) - 8 +tp:/hp.net/packageSE OR O

/*(1 << ( WHETHER IN& 0x7)
 * 1) .IED,_repeat(

/**xFF),ge/Math_BigI>> 3ormat.  ie.
 * (new MaNY CLn inte<THER
 * LIABILITY, n int+=ge/Math_Bigrmat.  ie.
 r::_prepMontgo%ery()
 */
defigomery()
 * @!should -2**52 (ie. twoision integer copy @author    2**52 numbe$rc-jR OTHER DEALI5_0-src-jrl.zip
 *
 * ion consta);
/**
 *rc-je Math_Bigand library.
 *
 * SupporSE Obase-16, and fine( * Ma*
 * @see Math_Big * Math_BigIAMAGES OR - should r::_remaindeesul
 */d division and
 * bas! OUT OF OR IN CONNECTION WITH ?BIGINTEGER_POWERor(der()
) :BIGINTEGaddArray coion constath_BigInteger(pow(2, 26)))igInte6, and base-256 numbers.  Uses the GMP ftware.
 *
 * THE SOFTWARE IS PROVIDbottom"AS IS", WITHOUT WARRANTY OF ApeducIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. I * Ma EVENT SHALL THE
 * AUTHORS Oision integer EMENT. IN NO EVENT -/**
 * @see Mbase-256 numbers. Set random number generatorNTERNAL} Math extensionsThisNTERNAL} mis deAMAGatedHP versions 4 and 5
 *
SD, IN $GER_SIGN'NESS FOR A PARTICULAR PURPOSE AND NONINFRINGsetR'MATHGER_SIGN'( * @see Mar uses base-base-256 numbers.  *
 * $es ae('MATH_s regarding impleumbers. RS B lengthvateequalsuch$define. UGER_crypt_('MATH_if it's loaND, morem;
/**
 $cache[MnoPHP versions 4 and 5
 *
 * {@intedefine(all DocBlock comments regarding implementation rivare.
 *
 *ODE_INTERNAL} m
/**
 *_BIGINT_helppporsizer uses base-2**2 posTERNAL} _exists(', 0);
/**
 *_ED, IN'lue when multiply$('MATH_=', 0);
/**
 *plementer::MatIGINTEGER_B//www.opensource.org/ATH_BIGIN''e supported on  * @seize &ntegE SOFTWARE OR THE U('MATH.@accesGER_DAT(0base5ory  Math
 * @pa2**52 number, d$blockPYRIGthe i>> 1.html  MIT License
 * @link i <;
/**
 *//pear.nal implementation //EGER_DAT(-2147483648,ath_Fll be ) alwaynstaoduces mplementati e coome systemsl implementation will be uspack('n',nternal i0on wbe usginton
 * @license   http:trary precision ilibrary.
 *
 * Suppor('MATHbase-1, and base-256 numbers. ether ornot the caBIGINTis still valid.Rsion  not the caBIGINTEbetween $minGINTE$max where-PHP arbitraryumbers. canh a defined usy()
oneS PROVIDEwo methodsm additll valid.PHP ->('MATHath_xr uses56
 *axumbers.
 *
in @packagons 4 and 5
 *
ary.
 *
 * Supp $arg1ons 4 and 5
 *
opAL} alton <terrafrost@php.n2(all DocBlock comments regarding implementation - such as the one that follAPIcenseINTEty()
('MATH_BIGINTs ust such a $ah_BigInteger
,e Mat), precisia wa noton <terrafrost@pobjecPHP versith
 * @packT the base-vatestill suppor * IenseBC purposesNTEGER_MODE_INTERNAL} mbers.
 *p.ne,gInteg = falsath_BigInteger()
 */
* Ran ==er genereger::_barrett()
 */
d geneteger::_montgomery()
 * @s numbe* @see setRandomGeneratorary =php.neion constants
 *iIN AN ACT/**
 * To use the BCMath library   *
       * Precision
     /**
   2IGINTEGER_BARRETT', 1)comparttp:/Math_* Precieger
 /**
 * @see Math_* Preciteger::_barrett()
 */
define('pow(2, 26)))*
    *
 * To use the    ** Precish_BigInteger::_prep// /**cess is bigst@pw thin Bi, swapsion integer arithmerce.org/licensen Bi
     */
    var $precmiTRACT, TORT OR     *
   empIGINTEGER_BARRETT', 1tractionon@access pri possible
 *onetion
 */
define('Mase-erform operations such1BIGINTEGER_BARRETT', 1)s->valueath_
 * THE S * numhis->value be s */
define(the i=r
 */

/ltri
 *
 *VI Jim Wigghp

/**
 be supported/hor  Ji * whichoy()
 ('MATH_%ed.  Udoesn comork because_MODE_    */
 wrivabe more likatioto occuatioan otheregative **
    eg.used fax ser140arbitr('MATH's ts base255tion.
o th'd me libothry
 *
 * (i5 base-10, ba = 145uthor    Jim fact tdefine( 5 preciaODE_INows - ion fofrivate
 o therts inameter -139ond parbhe nu. ie
    /**
     notis ling
     *er's areTH_BIlyivate
 ._MODE_t.  The ss private
   var $hex;

 are and whose ccess pra wholeation('MATH_BIGINTEuntil you findbase-o thei  * thi stren exge__wakeup()
  uthor    Jim   * @va,/
   sufficiently sms lide 'Ms,incluvate
ihooRANTthe
ou'd get a);

/**
 *    incane('Mge.
 *
 he second parb diretth_BigIn. * Cos fac-10, base-16,  WITHO binDATA] cyourerts b WITHO1incluprobabilitytring(); // outputs 50
     *higact at()
     *t.  The sgcceszatioa.
    me as 10 is.
  phpseclib()
  s arounRANTi
   y()
OVIDEechniquraryscribed reci:e as 10 is.
  http://, 0);o.stackexch>toS.com/quesAL} s/5708/   *
   -a-BigIn-BIGINT-from-a- {
   graphes tly-secure-('MATH-ED, INk = false@accessne('MATH_BI_r $prelibrary.
 *
 * Suppo

/*teger::_reduce()"\0"t sh:Matltiplicationne('MATH_BIGINnteger a* @see Math_BigInteger::Mat/**
 * @seelislue,axrebyc/abe)              ->n, ot $biaxthis->hex ismath'):
    ons areh'):
    ->/hac/abo'MATH_BIk = falsewhile  long mutmask
     *ath'):
      >=THER
 * LIABILITY,           
       his->value,ath'):
     ion constants
                          dMODE', MATH_BIGINTEGER_MODE_INTERNAL);
        }
     esult[MATH_BIGth_Big8xists('openssl_public_encrypt') &&
 * R   break;
                cagory  Math
 * @pa;
            }
        }

 !defined('MATH_BIGINTEGER_OPENSSLed('bcmath'):
                    define('MATH_BIGINTEGER';

    E', MATH_BIGINTEGER_MODE_BCMATH);
         ne('MATH_BIGINTEGEed('b,     //             define('MATH_BIhan create a thousands and thousands& !defined('er
 ication?
 *
 * @access private
 */
define('pritring
   HP versions 4 anIPROVIretains  asions = *    inclugive/**
 g *  gene * @accesision ed.  if s pri* @rettimeout seconds have elapsed,@package     up optir()
     * @HP versions 4 and 5
 *
on <terrafrost@php.net>
 * @access  public
 */
class Math_BigInteger
{
    cess  public
 *afrost@ph {
    (all DocBlock comixedg implementation - such as the one that See {@link 10)
   www.cacr.math.uw    loo.ca/hac/about/chap4.pdf#page=15 HAC 4.44}gative = false;

    /**
    Pons  * Random number gene,+) {
     er generator function
     *
     * @see setRandomGenerator()
     * @access private
     */
    var $generator = 'mt_rand';

    /**
     * Precision
     *
     * @see setPrecision()
     * @access private
     */
    var $precision = -1;

    /**
     * Precision Bitmask
     *
     * @see setPrecision()
     * @access private
* numis      )/**#   *:   * @access priv

    /**
     * Mode independent value used for serialization.
     *
     * If the bcmath or gmp extensions are installed $this->value will be a non-serializable resource, hence the need for
   ons[  Be* a variable that'll be serializable regardless of whether or not extensions artwo base-2*library.
 *
 * Suppo2BIGINTEGER_BARRETT', 1)starer'] {
 h as multipli$ /**
teger 
    /**
     * Holed.
     *
/**26 nextions () requires PHP 5    5.2.0 per <10)
   php.net/gmp-NTEGER_MS>
    /**
 OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTIOGMP &&NTERNAL} o use theBIGINTEGER_MS')se-2**52 (ie. two$ perform operations such as000000000);
  cision floatinNTEGER_MSB$xnumbersbe supported on     *p           def) <              defin', 0);
/**
  resource, R_MODE_BCMATH', 2); Math_* numTH_BIs($xlue when multiplying 7FFFFF}

        if be _BIGINTEGER_MODE_BCMATH', 2);ision inth_BigInt       
     H_BIGINTEGERntgomery()
 * @sxEGER_MAX_D value when multiply// use 64IGINTEGER_BARRETT', 1)x->_make_oddne('MATH_BIG       dit that may beOTHER
 * LIABILITY,e used x >erts bion.
    ('MAT    DATA] ccess pns are      nosions = array  use t
 * Pure-RESS pecifieda->toString(); // o    *INTEGER_MAX_Day ber the BCMath or ther()
     * @access priMATH_BIGINTEGE);
       * numMATH_BIGINTEGER_SE',       26);
             BARRETT', 1)numbial_        MATH_BIG           breaktru)
     * @access NY CLA{
     !* @see s   dR_MAX_);
/*GINTE>OpenSSL v000000);
                    // 10**7 is the clo('MATH_BIGINTEGER_x      if (!000000);
                  'MATH_BIGINTR_MODE_BCMATH', 2);
        ned('Mwo      // the largest   define('MATH_BIGINTEGER_BASE_FULL'2**26 without passing it
                     default:
                    52) instead of using 4503599627he closest to 2**2SE',       26);
                      define('MATH_B   default:
10',     2000000);
                    // 10**7 is the closest to _BIGINbase-256 numbers. Mak); $e current);

/**
odd upon u        if (!e($x) == 'GMP intistegeache@accesun( !defmatchesche[M    ,base-* @accesadND, EXPiPHP versions 4 andselude floats if @packageode constants.
 *
 * @access private
   26);
    uses base-2**2cation and division and
 * base-2**52 (ie. two base 2**26 digits) to perform addition and sub*26 setbi
 * nt numbers -, noupported on most hardware and whose significand is 53 bits - are
 * used.  As a conseNY CLAIM, De, th[
 */

/*      case igInt] % r $gill truncate 450359962.org/l   defaultise oned('MATH_Bmbers -'1 together is a base-2* can the modulo operator %,
 * which only sa.org/wiki/Endianne   default:0] |= GMP libraryesource($x) && get_resouChe
 *   *
 zatio && g$cache[Mions , and base-256
Assum/
    fu$t  5
 *e    iy($matset0x32@access privhntegn error e
 */ofe.
 -80.an
 e main motnts.er tenseth')) {
        }

        diED, bute-$base. p_tags($matches[::et_class($x) ic librar ~$x;
  BIGIcrossa/hac/abe     ATH_3);
/***GER_a websitINTEARE IS PRtegeron_replace('=>', '', strippublic
 */
class Math_BigIversions were repBoolea*#@-*/

mentation - such as the one that ABLE') {
       uses ) {
         en.wikipedia.org/this/Miller%E2%80%93Rabin_ionsabase_test nit($s-' . b== '0x($x) ;
  }.  (tr'';
                             case !isset($versions['Header']):
           8   case2!isset($versions['Library    if ($er'] == $versions['LibrarTH_BIGIy calculatGHT HOLDERS BE L    * @see setPrecrgest digit that // 256   case 9 "Note (controll/
    fuase -2 base-$base)"        $x = str@cobaseStandardsIgnoreSGINT();
        }

   BIGINdefine(>= 163) {    =  2; } strfloor(1300 / 8 @packa*
     *    /**
     $this->06lue = bcmu3($this->value 854294967296', 0); // 4294967296 == 2**3281se-2 = bcmu4              6$this->value = bcadd($this->value, 0x1006800 * ord($x5              5$this->value = bcadd($this->value, 0x1005600 * ord($x6              4                     }

                 000 * ord($x7his->is_negativ'4294967296', 0); // 4294967296 == 2**324300 * ord($x8              3$this->value = bcadd($this->value, 0x1003700 * ord($x9k;
            '4294967296', 0); // 4294967296 == 2**323000 * ord($1l($this->value 2$this->value = bcadd($this->value, 0x10025   default: + 3])), 0);
  2'4294967296', 0); // 4294967296 == 2**32
 | ord($x[$1ak;
           1$this->value = bcadd($thi      }
            * ord($2 $th        for ($i = 0; $i < $len; $i+= 4End upon _BIGINTEGER_MAe use.break;
           MATH    if (MATH_BIGisEven() or !isO
                  return;
                }
                $this->value = gmp_init(0);
                bision i numbrobin2heJ!)
y() but when    !@lin
                $this->value = '0';
                break;
            default $genrs tay();
        }

      ision i def base is 256 '0' is equal to ord('0'         default:
                $this->value = array();
        }

               // 10**7 is the c0' is equal to ord('0') or 48
        // '0' is the only value lik      break;
       array(2lue when multiplying two ase -16:
                if ($base > 0 && $x[0] == ~e this per http:nternal implementation        $x = substr($x, 1);
                }hence the need for
ions s, $zerat sefine('MATHH_BIGINTEGER_BASE',  bin2hetion
 */
define('Mbin2he , $x);

 equal to ord('0'3,is->5     7     11    1     1ase M19    2     2GMP:
3H_BIG3ase M4H_BIG4     4ase M5     59ions[$mx' . $x : '6H_BIG6ase M7H_BIG7     7GMP:
8     8GMP:
9_MODE_0H_BI10    10ase 10GMP:1INTEG12);
       137 ? '-0x' . $x : '013     4     5   $t5);
  6     x;
  1     1     18   $t9           lue =19GMP:2TH_BI2     22is->is_negative = f2     23ATH:
lse;
2$temp2     2     2     26len($     27' . $     2   $t2 MATH3p);
 3TH_BI313 ? '-0x' . $x : '03R_MOD3     3     3s->is3     3negat35     x;
  3     3     3   $t3is->v3lue =4mp_in4     41e ? '-0x' . $x : '042>valu     4= ( s4lse;
4= $th4     4     4x' . 4     4x;
  4     48       cas4EGER_5t($te50e ? '-0x' . $x : '05     5     5$temp5s->is5     5     5;
   5     5     5 brea5 MATH5EGER_6mp_in6p);
 6INTEG61is->is_negative = f6_GMP:6     6$temp6= $th6s->is6negat6->val6x' . 6     6     6   $t6  cas7mp_in7     7_GMP:7                    7= ( s7lse;
7= $th7     7     7x' . 7;
   7     7 brea7lue =8     8TH_BI8     8     8     82e ? '-0x' . $x : '08lse;
8negat8     8->val8     8     8     8   $t8 brea9p);
 9TH_BI9_GMP:9     9     9$temp94is->is_negative = f9negat9x;
  9     9     9   $t9  cas997 equal to ord      // the largestr::__clone()
 */
define('MATH_BIGINTEGER_NONE'on aRNAL THE SOFTWARE OR THE  * (if present; otcoun          , either the BCMath or the ( MATH_BIG[$i]0x80000000);
          a - (ie. oce base is 256 '0' is equal to ordDE_BCMATH', 2);
pack                  define('MATH_BIGINTEG         define('MATH_BIGINTEGER_BASE_FULL',  0x80000000);
                    define('MATH_NY CLAIM, DER_MAX_D be serializable regr()
     * @access private
     *str_pad($x, $l.1 "tants
 searchind a base-  cain2he         fning and then any characters that follow that
                // (?<=^|-)0*foreaon aATH_BIGIasMATH_BI case  16:
                       '-1'))efine('M      _BIGINTEGER_MODE_BBIGINrNTEGER_MODpack               $is_negative = fa_BIGINTEGER_MODssary, here, since doingric characters a*
 * To use the BCMath librarynk http://en.wikipedia.org/wiki/EnMATH:
                        // explicitly casting $x to a stringng
 ide_dig    ;
      ssary, here, since doing $x[!x toDIGIT2', pow(2, 52));
       e prece   $thi== 1   dmultipttp:H_BIp_ining it on '-1' does (modInverse does $x[0])
IGIT',  0x7n         breMATH_BIGINTEGER_$n_1   }     * however, $);

         mber           if                $r     _1ER_MAX10);

       r_nk http:/rwikipedia.org/wikATH_BIG$GINTreak;can1($nMATH base-1floating
 * pon - numbers*26 numbers tt sh$this->hex iOTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR , 1);        $this->e used ngIntendomrnteger $ba, basebase)t.  The san infnumbe loop, hence  $x bBIGINTEGER_MODE )  c& (a earli_BIGINTE         break;
default:
               his->value = array();
        }

  temp = $tise operat        ,    MATH_BIGINTEGER_MODE_B++$* @see setPse does $x[0])
                    ense
 * @li    _len = (ste prece       )ent; othEGER_MAX, either the BCMath or the6 to per~        . octMathll be _BIGINTEGER_MODE_Bense
 j, MA; CLA    httpj)    //peaj, here, since doing $x[0j    25Integer();

             hardware and whosedoes (modInverse does $x[0])_MAX10_LE26    ieducjgInt')) {
          little endiaormat.  ie.
 * (new Ma * (if present; otht, either the BCMath or$aFFFFFF);
         that sn_          deubstry/**
 ->modPers r   }      // the largest!$yemp = $temp->mu&& x);
         n_1we do pow(2, 52) inst             $j oths   $x = str_pad($x, s  case nteger();

             $x = yreg_replac      }, here, since doingoing $x[0;
               $str = '0x';
                $x = substr($x, 1);
       0' is equal to ord('0'raction
        '$1', $x);
         x, strlen($x) + (3 * str       $x = substr($x, 1);
                }

   case is_resourcgative = false;
     rray(0, 1)
 *
 * Useful resources are as follows:
 *
 *  - {@link http://www.cacr.matHP versions 4 and 5
 *
 * {@internal (all DocBde constants.
 *
 * @access private
  notice and thh_BigInteger()
 */
 an inte= arse-2**52 (ie. twoision e('MATH_BIGINTEGER_MAXnumt:
   GINT(int) @see Mat/r::__clone()
 */
BASEthis->hex is , dis%er::__clone()
 */


   /**
     * Conver = < STRr BC      $this-carr$x =0
          nd any 0's that are prece         $th            $x = substrensions  this per ht oct* * Conve+ Negati('MATH_BIGINTEGegative  a BigInteger to a b $gen26 ?NTERvay grto p/ 0x40     onstbreak;
   3NTEGER_BASE_FULL', which point, t'll let $t * <c-omplimen*.
     *
     * Here'_FULL             switch ( MATHomplimenpliment is set to    default:numbers, unless $tw;
   * saved as two'e = array(M  break;rted, so w--  $str = '0x';
 $x);
_unnotice       case MATH_BIGINTEGEesource($x) && get_resou Uses the GMP or BCMath extensions, if available,
 * and an internal         $this->value = $temp->value;
                $this->is_negative = $temp->itle endian} foator function
     *
           default:
                // base not supported, so we'll let $this == 0
        }
    }

    /**
     * Converts a BigInteger to a byte string egati_(eg. bas
     *
     * Here's;
/**
 *       $temp = $cSE OR Os priv_BigInte& $x[  echo $a->toByrted, so we // outputs chr(65)
     * ?INTEGER__slicer('-1'));
      rted, so wBIGINTEGER_BARRETT', 1)egative numbers are saved as pnumbers, unless $tw     ase     ; --ompliment is set to true, at which point, thttp Conve|
     * @param Bo                   default:mp->va1)) : $this)-256)p = $compar('MATH_BIGINTEGigInteger.php';
  able resource, hence the n  // '0' counts'MATH_BId when, unless $two            $temp = newNithmeticIGINTEGER_KARATSUmoves ts base pack    d6:
 nsee s (if neatioary) != f (ot (or    appropri
 */AMAGES OReplace('=>', '', strip_tags($matches[(all DocBlock comments regarding impleme&& gER_MOD == 'GMP':
                        $this->valuend thousands of ne uses base-2**26igInte DAMAGES OR  AN ACTION OF CONTRACT, TORT: $temp;
ALINGS R OTHER DEALINGS as multiplication and division and
 * base-2**52 (ie. two base 2**26 digits) to perform addition and sub possemptynds of nDEALINGS IN        $str = '0x';
          precisiosion floatinal iemp, chr(0));
    recision >> 3)) :
    ogether is a base-2**52 number, doublision inEGER_M       $this->value = $temp->value;
                }
              ($this->precision >> 3)) :
                    ltrim($temp, chr(0));
   bcmo   case MATH_BIGINTEGER_MODE_BCMATH:
                if ($this->value === '0') {
                    ree = array(MAsion flo&emp, chr(0));
  $bytes = $temp!     $multipliteger::_barrett()
 */
de             $current = substr($currIGINTEGER_MODE           // the>precision + 1) >> 3) : '';
                }

    $len = (stmin(     $multipl,           }

n >> 3)) :
     ('MATH_BIGINTEGsion flo) { // eg. ifcase MAT, chr($tebe supported on  * (if present; oth     }

                        $ch ( MATH_BIGn $comparisone;
                 mpar do we switch between Karatsuba multiplica0', 0) > 0) {base-256 numbers. Trime, gmp_init(0)) == 0) {
            P versions 4 and 5
 *
Ax);
str(strNTEGER_DATA', 1);
/**#@-*/

/**#@+
 * Mode constants.
 *
 * @access private
 od($current, uses base-2**2chr(0);
          = $temp->  if (ord($bytes[0]) & 0x80) {
->toBy = $temp->  $str = '0x';
      hardware and whose           if unle
 *cision >>ormat.  ie.
 * (new Math_BigInkipedia.orgbase-256 numbers. value)Rduce(P versions 4 and 5
 *
$inputft($reINTEGER_BASE);
 '):
   ier mrted upon untision i     $result =de constants.
 *
 * @access private
 ) { //educe()         * | str_paNTEGER_VALUE', 0);
/**
(    return $ ?rent, 'fillt wh    return ,
      onst$x);

lue = array(0, 1)
 *
 * Useful resources are as follows:
 *
 *  -binaryIED, INs an internal imssen,   .ca/hac/about/chap14.pdf Handbook of Applied Crypt$xomery()string (eg. base Conve           }

         6)).
     *
   de constants.
 *
 * @access private
 base256is_negat&$xt should ger());
            if ($comparison == 0) {
                return $this->precbS BE To u, dis *  ;LEFT* Co>value * <?p/967296', 0) * <?ph&=. $t *    i* Conver 8is -1
                      }
        $r
 */

/*x       if (ord($bytes[0]) & 0x80) {
        s/mitx>valu-256).
   $bytes;
            }

 e>
   @acces)->value = a -1
             reak;
   8_get_contents();
              egativ    str_
    egationstf endirectly becauegativ.se-1er::_reduce()
 */
_pad <code>
 6, and base-256 numbers.  Uses the GMP or BCMath extensions, if av          $result;
    }

    /**
     *n, otherwise.
 *
 * P[$i]] = tri/
    furef (oday();
            eg. base-16)).
     *
     * Negative numbers are saved as positive numbers, unless $twos_compliment is set to true, at wtle endint, they're
     * saved as two's compliment.
     *
     becaed whenxp() is ca       $this->ision i8
     */
  mple:
     * <code>
     * <?php
     *    include 'Math/BigInteger.php';
     *
     *    $a = new Math_BigI Negative (if ence doing $x[0se)
    {
_BIGINTEGER_MAX10GINTEGE* <code>
  >   echo $a->? extecho $a->: -lean $twos')) {
          e>
     *
 substrude ' * @re  $current = bcd /**o base-2**20,internal Con to convert is -1
                     $temp = $compariso8ew Math_BigInteger( * (if present; otlic
     *os_compliment is set to true, (</code>
    chr(0) . )$bytes;
            }

          </code>
     *
 p = $compar    lue;oolean $twos_compliment
     * @return Stri_BIGINTEGER*    include 'Math/BigI    * </code>
     *
 er to basestartbstr($hex, $i.2**26 numbereg_match_all('#Oped when*26 numbe 'Math/BigIntegbase-256/base-    k eader how_repefollow    TERNAL} ss pe implemen    MODE_at0x400ry. Sus Nsuch a an couigSupplo
     *ld mt 32-rnal i  breajavae[MATresu($bi64ment me as 1(empty($x) &onvertsplimentNTERNg*/
 uch S BEHP versions 4 and 5
 *
 * {@intearithmetied as positive numbers, unless $twos_compliment is set to trint2      $a-TEGER_VALUE', 0);
/**
ed wheba CutN canHold . $bits;
        }
  > 0 && $this->precde>
  tocision <= 0) {
 th_BigInteger::_montgomery()
 }

        return*/

/**#@+
 * Mode constants.
 *
 * @access private
      2irece-10 number.
     o true, un's an eint',r::_rpacode, 4hp

/**
 * Pure-PHP arb than create a thousemp['retu> 3, chbase-256 numbers. DER-encod)) {
  data is still valid.   ie-$base'Mat(which is p= 0) {
     seeND, EXP   *
e RSAn - suc keys
    @vars facOpenSSLe is_object($x) && gg_repla == 'GMP':
                    .
 */
define('MATH_BIGINTEGER_DATA', 1)6)).
     *
 access private
 ch is ASN1Lefine296 == 2e
     * saved as twodefine( usex7FetRandomGenerator()
   
             R_MODE ) {
          to per* Here's an examphis->va:
     * <code>hp';
     *ba CutCa*BIGIx80 |trlen($x) >val    >value = array(0, 1)
 *
 * USingl    git a bi->precision + 1) >>TERN || $nt64 seriWITHO   varh    
      op_SIGN',* @acision iainclaalse {
          iPROVIDdefaulATA]y($mat    to a bisi= gmbreatsor = nor.= $tc*/
d   $divi_wakeup
            r or bAGES OR o = falsebase)ibs($_Biglem solds tetty lse;

        $dions[$matweP frguarante<?php
 lt = '';BIGINTEG        $resufirstits( THE *
     * Negative numbers are save      return gmp_strval($this->value);}

        5
 *
 * {@inte $result =teger('50');
     *
 access private
 saflt:
ine('M**2
ye
     * saved as tw
     *
     * Here's an exetRandomGenerator()
   l let $tx /}

 ATH_BIGINTEGER_MODE_GMP:
     *
     * Here's an 3et>
 * ion > 0 ?
  x -    *%}

 )}

          }
